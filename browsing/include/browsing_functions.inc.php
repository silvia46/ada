<?php
/**
 * Browsing functions
 *
 * @package
 * @copyright	Copyright (c) 2009-2010, Lynx s.r.l.
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU Public License v.2
 * @link
 * @version		0.2
 */
require_once 'CourseViewer.inc.php';
require_once ROOT_DIR.'/include/ViewBaseHelper.php';
require_once ROOT_DIR.'/include/media_viewing_classes.inc.php';
require_once ROOT_DIR.'/comunica/include/MessageHandler.inc.php';
require_once ROOT_DIR.'/comunica/include/UserDataHandler.inc.php';
require_once ROOT_DIR.'/comunica/include/ADAEventProposal.inc.php';

/**
 * Browsing helper class
 */
class BrowsingHelper extends ViewBaseHelper
{
  protected static $tutor_uname = null;

  /**
   * Builds array keys for the browsing directory scripts
   *
   * @param array $neededObjAr
   *
   * @return array
   */
  public static function init(array $neededObjAr = array())
  {
    if (count(self::$helperData) === 0) {
      self::$helperData = parent::init($neededObjAr);
      self::setSessionMode();
      self::$helperData = array_merge(
        self::$helperData,
        self::getEnabledArray(self::$helperData['userObj'], isset(self::$helperData['courseObj']) ? self::$helperData['courseObj'] : null)
      );
      self::$helperData = array_merge(
        self::$helperData,
        self::getUserBrowsingData(self::$helperData['userObj'], self::$helperData['log_enabled']),
        [
          'user_messages' => self::getUserMessages(self::$helperData['userObj']),
          'user_agenda' => self::getUserAgenda(self::$helperData['userObj']),
          'user_events' => self::getUserEvents(self::$helperData['userObj']),
        ]
      );
      self::extract();
    }
    return self::getHelperData();
  }

  /**
   * set in which way video LIS (or other) is showed
   * mode = LIS
   * mode = changeLIS change the LIS status
   * mode = ...
   *
   * @param array|null $dataArr if null defaults to $_REQUEST
   * @return void
   */
  private static function setSessionMode($dataArr = null)
  {
    if (!is_array($dataArr) || is_null($dataArr)) $dataArr = $_REQUEST;
    if (isset($dataArr['mode']) && strlen($dataArr['mode']) > 0) {
      if ($dataArr['mode'] == 'changeLIS') {
        if ($_SESSION['mode'] == 'LIS') {
          unset($_SESSION['mode']);
        } else {
          $_SESSION['mode'] = 'LIS';
        }
      } else {
        $_SESSION['mode'] = $dataArr['mode'];
      }

    } else { //when unset the mode session??
        //$status = $_REQUEST['status'];
    }
  }

  /**
   * Builds the reg_enabled, log_enabled, mod_enabled and com_enabled keys
   * for the browsing directory scripts
   *
   * @param ADAGenericUser $userObj
   * @param Course $courseObj
   *
   * @return array
   */
  protected static function getEnabledArray(ADAGenericUser $userObj = null, Course $courseObj = null)
  {
    /**
     * import globals set from module_init
     */
    global $sess_id_course_instance;
    global $sess_id_user;

    $reg_enabled = true; // links to bookmarks enabled
    $log_enabled = true; // links to history enabled
    $mod_enabled = true; // links to modify nodes  enabled
    $com_enabled = true; // links to comunicate among users  enabled

    /**
     * CONTROLLARE DA DOVE VIENE $sess_id_course_instance
     */
    if (method_exists($userObj, 'get_student_status')) {
      $user_status = $userObj->get_student_status($sess_id_user, $sess_id_course_instance);
    } else $user_status = ADA_STATUS_VISITOR;

    if ($userObj->getType() == AMA_TYPE_STUDENT && ($user_status == ADA_STATUS_VISITOR || $user_status == ADA_STATUS_TERMINATED || $user_status == ADA_STATUS_COMPLETED)) {
      $reg_enabled = false; // links to bookmarks disabled
      $log_enabled = ($user_status != ADA_STATUS_VISITOR); // links to history disabled
      $mod_enabled = false; // links to modify nodes  disabled
      $com_enabled = false;  // links to comunicate among users  disabled
    }

    /**
     * Authors can edit public courses assigned to themselves
     */
    if ($userObj->getType() == AMA_TYPE_AUTHOR && $courseObj instanceof Course && $courseObj->getIsPublic()) {
      $mod_enabled = ($userObj->getId() == $courseObj->getAuthorId());
    }

    if ($com_enabled) {
      self::sendWelcomeMessage($userObj, $sess_id_course_instance);
    }

    return ['reg_enabled' => $reg_enabled, 'log_enabled' => $log_enabled, 'mod_enabled' => $mod_enabled, 'com_enabled' => $com_enabled];
  }

  /**
   * Uses the SERVICECOMPLETE module to check if the passed user has completed the passed instance
   * using the condition set linked to the passed course.
   * Possibly sets the user status to ADA_SERVICE_SUBSCRIPTION_STATUS_COMPLETED in the passed instance
   *
   * @param ADAGenericUser $userObj
   * @param Course $courseObj
   * @param Course_Instance $courseInstanceObj
   *
   * @return void
   */
  public static function checkServiceComplete(ADAGenericUser $userObj, Course $courseObj = null, Course_Instance $courseInstanceObj = null)
  {
    if ($userObj->getType() == AMA_TYPE_STUDENT && defined('MODULES_SERVICECOMPLETE') && MODULES_SERVICECOMPLETE) {
      if (isset($courseInstanceObj) && isset($courseObj) && isset($userObj) &&
        is_object($courseInstanceObj) && is_object($courseObj) && is_object($userObj)) {
        $user_status = self::getUserBrowsingData($userObj)['user_status'];
        if ($user_status != ADA_SERVICE_SUBSCRIPTION_STATUS_COMPLETED) {

          // need the service-complete module data handler
          require_once MODULES_SERVICECOMPLETE_PATH . '/include/init.inc.php';
          $mydh = AMACompleteDataHandler::instance(MultiPort::getDSN($_SESSION['sess_selected_tester']));
          // load the conditionset for this course
          $conditionSet = $mydh->get_linked_conditionset_for_course($courseObj->getId());
          $mydh->disconnect();

          if ($conditionSet instanceof CompleteConditionSet) {
            // evaluate the conditionset for this instance ID and course ID
            $is_course_instance_complete = $conditionSet->evaluateSet(array($courseInstanceObj->getId(), $userObj->getId()));
          } else {
            $is_course_instance_complete = false;
          }

          // if course is complete, save this information to the db
          if ($is_course_instance_complete) {
            require_once ROOT_DIR . '/switcher/include/Subscription.inc.php';
            $s = new Subscription($userObj->getId(), $courseInstanceObj->getId());
            $s->setSubscriptionStatus(ADA_SERVICE_SUBSCRIPTION_STATUS_COMPLETED);
            if (isset($userObj->livello) && intval($userObj->livello) > 0) $s->setStartStudentLevel($userObj->livello);
            $subscribedCount = Subscription::updateSubscription($s);
          }
        }
      }
    }
  }

  /**
   * Sends a welcome message to the passed user in the passed instance
   *
   * @param ADAGenericUser $userObj
   * @param int $courseInstanceId
   *
   * @return void
   */
  private static function sendWelcomeMessage(ADAGenericUser $userObj, $courseInstanceId)
  {
    global $sess_selected_tester, $sess_id_course;

    $last_access_date = $userObj->get_last_accessFN($courseInstanceId, 'T');
    if ($last_access_date == translateFN("Nessun'informazione")) {
      $user_name = $userObj->username;
      $destAr = array($user_name);
    // FIXME: multiportare, ora e' bloccato sul tester selezionato
      $mh = MessageHandler::instance(MultiPort::getDSN($sess_selected_tester));
      $message_ha['destinatari'] = $destAr;
      $message_ha['priorita'] = 1;
      $message_ha['data_ora'] = "now";
      $message_ha['titolo'] = translateFN("Messaggio di benvenuto");
      $welcome_file = "service_" . $sess_id_course . "_" . $courseInstanceId . "_welcome_$language.txt";
    // es. course_2_12_welcome_italiano.txt
      if (file_exists($welcome_file)) {
        $fp = fopen($welcome_file, 'r');
        $message_ha['testo'] = fread($fp, filesize($welcome_file));
        fclose($fd);
      } else {
        $message_ha['testo'] = translateFN("Benvenuto in ADA!");
        $message_ha['testo'] .= translateFN("Se hai problemi, dubbi o domande, puoi inviare un messaggio al tuo") . "<a href=\"" . HTTP_ROOT_DIR . "/comunica/send_message.php?destinatari=" . self::$tutor_uname . "\">" . translateFN("E-practitioner") . "</a>.";
      }
      $message_ha['data_ora'] = "now";
      $message_ha['mittente'] = self::$tutor_uname;
    // e-mail
      $message_ha['tipo'] = ADA_MSG_MAIL;
      $res = $mh->send_message($message_ha);
    // messaggio interno
      $message_ha['tipo'] = ADA_MSG_SIMPLE;
    //$res = $mh->send_message($message_ha);
    // reload messages to show this one !
      // $user_messages = $userObj->get_messagesFN($sess_id_user);
    }
  }
}
