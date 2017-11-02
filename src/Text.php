<?php
/**
 * @file Text.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

/**
 * Text node.
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
class Text extends Node{

	protected $_data;

	/**
	 * Construct a new Text Node.
	 *
	 * @param string $data
	 * @param NodeTree $Parent
	 *
	 * @throws TemplateException
	 */

	public function __construct($data, NodeTree $Parent){

		parent::__construct($Parent);

		$this->_data = $data;
	}

	/**
	 * Display the Text Node (c.q. return its contents).
	 *
	 * @return string
	 * @see Node::display()
	 */

	public function display(){

		return $this->_data;
	}
}