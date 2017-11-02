<?php
/**
 * @file Repeater.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

/**
 * Repeater class.
 *
 * <p>This class allows its content to be repeated multiple times. This allows
 * for lists and tables of dynamic length to be build. By utilising the
 * {@link NodeTree::replaceChild()}. method to replace an internal
 * {@link Section} with the {@link Repeater} itself, it's possible to create
 * dynamic, multi-level, nested repeaters.</p>
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
class Repeater extends TemplateNodeTree{

	protected $_output = [];

	/**
	 * Reset the repeater to its initial state.
	 *
	 * <p>Calling this method removes all previously completed repetitions from
	 * the repeater.</p>
	 *
	 * @return void
	 * @see TemplateNodeTree::resetTemplate()
	 */

	public function resetTemplate(){

		$this->_output = [];

		parent::resetTemplate();
	}

	/**
	 * Store the current output of the repeater as one of its repetitions.
	 *
	 * <p>This method stores the result of the repetition and resets the
	 * repeater to its initial state, allowing a new repetition to be
	 * started.</p>
	 *
	 * @return void
	 */

	public function repeat(){

		$this->_output[] = parent::display();

		parent::resetTemplate();
	}

	/**
	 * Display the repeater node.
	 *
	 * <p>This method returns the output of all previously stored repetitions
	 * in a single string.</p>
	 *
	 * @return string
	 */

	public function display(){

		$output = (string) implode('', $this->_output);

		return $output;
	}
}