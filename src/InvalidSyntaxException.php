<?php
/**
 * @file InvalidSyntaxException.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

/**
 * InvalidSyntaxException.
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
class InvalidSyntaxException extends FactoryException{

	/**
	 * Construct a new Syntax Exception.
	 *
	 * @param string $message
	 * @param integer $line
	 */

	public function __construct($message, $line = 0){

		if($line > 0){
			$message .= " on line $line";
		}

		parent::__construct($message);
	}
}