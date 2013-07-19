<?php
/**
 * EXPORT MODULE
 *
 * @package		export/import course
 * @author			giorgio <g.consorti@lynxlab.com>
 * @copyright		Copyright (c) 2009, Lynx s.r.l.
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU Public License v.2
 * @link			impexport
 * @version		0.1
 */

require_once(ROOT_DIR.'/include/Forms/lib/classes/FForm.inc.php');

/**
 * class for handling export phase 1, selecting a course
 *
 * @author giorgio
*/
class FormSelectExportCourse extends FForm {

	public function __construct( $formName, $courseList ) {
		parent::__construct();
		$this->setName($formName);
		
		$courseList[0] = translateFN('Scegli un corso da esportare');
		
		$this->addSelect('course', translateFN('Seleziona un corso da cui esportatre'), $courseList, 0)
			->setRequired()
			->setValidator(FormValidator::POSITIVE_NUMBER_VALIDATOR);
		
		$this->setSubmitValue(translateFN('Avanti')."&nbsp;&gt;&gt;");
		$this->setOnSubmit('return goToExportStepTwo();');
		
	}
}
?>