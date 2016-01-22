<?php
/**
 * SLIDEIMPORT MODULE.
 *
 * @package        slideimport module
 * @author         Giorgio Consorti <g.consorti@lynxlab.com>
 * @copyright      Copyright (c) 2016, Lynx s.r.l.
 * @license        http://www.gnu.org/licenses/gpl-2.0.html GNU Public License v.2
 * @link           slideimport
 * @version		   0.1
 */

ini_set('display_errors', '0'); error_reporting(E_ALL);
/**
 * Base config file
*/
require_once (realpath(dirname(__FILE__)) . '/../../config_path.inc.php');

/**
 * Clear node and layout variable in $_SESSION
*/
$variableToClearAR = array('node', 'layout', 'course', 'user');
/**
 * Users (types) allowed to access this module.
*/
$allowedUsersAr = array(AMA_TYPE_AUTHOR);

/**
 * Get needed objects
*/
$neededObjAr = array(
		AMA_TYPE_AUTHOR => array('layout')
);

/**
 * Performs basic controls before entering this module
*/
require_once(ROOT_DIR.'/include/module_init.inc.php');
include_once ROOT_DIR . '/services/include/author_functions.inc.php';

// MODULE's OWN IMPORTS
require_once MODULES_SLIDEIMPORT_PATH .'/config/config.inc.php';

$self = 'slideimport';

$layout_dataAr['CSS_filename'] = array(
		JQUERY_UI_CSS
);

$content_dataAr = array(
		'user_name'    => $user_name,
		'user_type'    => $user_type,
		'edit_profile' => $userObj->getEditProfilePage(),
		'status' => $status,
		'title' => translateFN('Importa Presentazione'),
);

$layout_dataAr['JS_filename'] = array(
		JQUERY,
		JQUERY_UI,
		MODULES_SLIDEIMPORT_PATH . '/js/jquery.lazyload.js',
		MODULES_SLIDEIMPORT_PATH . '/js/tree.jquery.js',
		JQUERY_NO_CONFLICT,
		MODULES_SLIDEIMPORT_PATH . '/js/dropzone.js'
);

$optionsAr['onload_func'] = 'initDoc('.$userObj->getId().', \''.MODULES_SLIDEIMPORT_UPLOAD_SESSION_VAR.'\');';

// clear session var
if(isset($_SESSION[MODULES_SLIDEIMPORT_UPLOAD_SESSION_VAR]['filename'])) {
	unset($_SESSION[MODULES_SLIDEIMPORT_UPLOAD_SESSION_VAR]['filename']);
}

if (isset($data)) $content_dataAr['data'] = $data->getHtml();

/**
 * load course list from the DB and output the generated select in a template field
 */
$providerCourses = $dh->get_courses_list (array ('nome','titolo'));

$courses = array();
foreach($providerCourses as $course) {
	$courses[$course[0]] = '('.$course[0].') '.$course[1].' - '.$course[2];
}

if(count($courses)>0) {
	reset($courses);
	$content_dataAr['course_select'] = BaseHtmlLib::selectElement2('id:courseSelect,class:ui search selection dropdown', $courses, key($courses))->getHtml();
} else {
	$content_dataAr['course_select'] = translateFN('Nessun corso trovato');
}

$avatar = CDOMElement::create('img','class:img_user_avatar,src:'.$userObj->getAvatar());
$content_dataAr['user_avatar'] = $avatar->getHtml();
$content_dataAr['user_modprofilelink'] = $userObj->getEditProfilePage();

ARE::render($layout_dataAr, $content_dataAr, NULL, $optionsAr);
?>
