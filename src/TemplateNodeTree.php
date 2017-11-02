<?php
/**
 * @file TemplateNodeTree.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

use StudyPortals\Exception\ExceptionHandler;

/**
 * TemplateNodeTree class.
 *
 * <p>Extends {@link NodeTree} with the ability to assign names to Nodes in
 * the tree. Furthermore, this class allows values to be assigned to Nodes in
 * the tree. These values are used by {@link Replace} and {@link Condition} and
 * as such are the basis of the actual <em>replace-marker-with</em> behaviour
 * offered by Template4.</p>
 *
 * <p>All classes that inherit from TemplateNodeTree can be used as root
 * elements inside a template tree.</p>
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
abstract class TemplateNodeTree extends NodeTree{

	protected $_name;
	protected $_values = [];

	protected $_virtual_children = [];

	/**
	 * Construct a new template node tree.
	 *
	 * <p>This method throws an exception if the provided {@link $name} argument
	 * is invalid.</p>
	 *
	 * @param string $name
	 * @param NodeTree $Parent
	 *
	 * @throws TemplateException
	 */

	public function __construct($name, NodeTree $Parent){

		// Set name before calling parent constructor

		if(!$this->_isValidName($name)){

			throw new TemplateException(
				"Unable to create Template node,
				the specified name \"$name\" is invalid"
			);
		}

		$this->_name = $name;

		parent::__construct($Parent);
	}

	/**
	 * Execute the "_load()" method of all components in a NodeTree.
	 *
	 * <p>This method traverses the provided NodeTree and calls the _load()
	 * method for every component it encounters.</p>
	 *
	 * <p>This method is required by {@link Template::templateFactory()} to
	 * properly initialise all components. It is located here to allow
	 * Component::_load() to keep its protected signature.<br>
	 * When a template tree is unserialised, Component::_load() is also called
	 * on all components, but through the use of the __wakeup() magic method
	 * defined in the Component class.</p>
	 *
	 * @param NodeTree $NodeTree
	 *
	 * @throws ComponentException
	 * @return void
	 * @see Template::templateFactory()
	 */

	protected static function _componentsLoad(NodeTree $NodeTree){

		foreach($NodeTree->_children as $Child){

			if($Child instanceof Component){

				$Child->_load();
			}

			elseif($Child instanceof NodeTree){

				self::_componentsLoad($Child);
			}
		}
	}

	/**
	 * Clear list of virtual children before serialisation.
	 *
	 * <p>If left untouched, the list of virtual children will contain
	 * non-circular references which are not serialised correctly. This leads
	 * to duplicate objects after unserialisation, which <em>really</em> bad.</p>
	 *
	 * @return array
	 */

	public function __sleep(){

		$this->_virtual_children = [];

		return [
			"\0*\0_Parent",
			"\0*\0_children",
			"\0*\0_name",
			"\0*\0_values",
			"\0*\0_virtual_children"
		];
	}

	/**
	 * Create a "deep" clone of the TemplateNodeTree.
	 *
	 * @see NodeTree::__clone()
	 */

	public function __clone(){

		$this->_virtual_children = [];

		parent::__clone();
	}

	/**
	 * Get an element from the "virtual" Template tree.
	 *
	 * <p>This method first attempts to call {@link TemplateNodeTree::getChildByName()}
	 * with the provided {@link $name} argument. If this fails it calls
	 * {@link TemplateNodeTree::getValue()}.<br>
	 * When assertions are enabled, this method will assert the element requested
	 * to be not <em>null</em>.</p>
	 *
	 * <p>This method thus allows access to a <em>virtual</em> Template tree
	 * consisting of named Nodes and values available that are
	 * <strong>named</strong> descendants of this Node. See
	 * {@link NodeTree::getChildByName()} for a description on when a named
	 * Node is considered to be a <strong>named</strong> descendant.</p>
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */

	public function __get($name){

		try{

			$value = $this->getChildByName($name);
		}

		catch(NodeNotFoundException $e){

			$value = $this->getValue($name, true);

			assert('!is_null($value)');
		}

		return $value;
	}

	/**
	 * Add an element to the "virtual" Template tree.
	 *
	 * <p>This method first attempts to replace the Node named {@link $name}
	 * with the node provided in the {@link $value} argument. If this fails,
	 * either because there exists no Node named {@link $name} or because
	 * {@link $value} is not a valid Node, a value named {@link $name} is set
	 * to value {@link $value} instead.</p>
	 *
	 * <p><strong>Note:</strong> This method will <em>never</em> attempt to add
	 * a new Node to the Template tree. This can only be done by explicitly
	 * callling {@link NodeTree::appendChild()}.</p>
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @return void
	 * @see TemplateNodeTree::__get()
	 * @see TemplateNodeTree::setValue()
	 * @see NodeTree::replaceChild()
	 */

	public function __set($name, $value){

		// Try to prevent the use of existing property names

		assert('!isset($this->$name)');

		try{

			if($value instanceof TemplateNodeTree){

				try{

					$Node = $this->getChildByName($name);

					// Node is a virtual child

					if($Node->_Parent !== $this){

						$Node->_Parent->replaceChild($Node, $value);

						// Clear (the now invalidated) virtual child cache

						$this->_virtual_children = [];
					}

					else{

						$this->replaceChild($Node, $value);
					}
				}

				catch(NodeNotFoundException $e){

					$this->setValue($name, $value);
				}
			}

			else{

				$this->setValue($name, $value);
			}
		}

		catch(TemplateException $e){

			ExceptionHandler::notice($e->getMessage());
		}
	}

	/**
	 * Return this Node's name.
	 *
	 * @return string
	 */

	public function getName(){

		return $this->_name;
	}

	/**
	 * Return this node's named parent.
	 *
	 * <p>In case the node doesn't have a named parent, c.q. it is the root
	 * element in the tree, <em>null</em> is returned.</p>
	 *
	 * <p>Since this method always returns an instance of TemplateNodeTree (c.q.
	 * a named node), getParent() should <strong>not</strong> be used in an
	 * attempt to retrieve the root node of a template tree. Use
	 * {@link Node::getRoot()} instead.</p>
	 *
	 * @see Node::getRoot()
	 */

	public function getParent(){

		if($this->_Parent instanceof NodeTree){

			if($this->_Parent instanceof TemplateNodeTree){

				return $this->_Parent;
			}

			else{

				$AnonymousParent = $this->_Parent;

				while(true){

					assert('++$i < 100');

					$Parent = $AnonymousParent->_Parent;

					if(is_null($Parent) || $Parent instanceof TemplateNodeTree){

						return $Parent;
					}

					$AnonymousParent = $Parent;
				}
			}
		}

		return null;
	}

	/**
	 * Return this Node's <strong>named</strong> descendant with a matching name.
	 *
	 * @param string $name
	 *
	 * @return TemplateNodeTree
	 * @throws NodeNotFoundException
	 * @see NodeTree::getChildByName()
	 */

	public function getChildByName($name){

		// Direct child

		if(isset($this->_children[$name]) &&
			$this->_children[$name] instanceof TemplateNodeTree){

			return $this->_children[$name];
		}

		// Virtual child

		elseif(isset($this->_virtual_children[$name]) &&
			$this->_virtual_children[$name] instanceof TemplateNodeTree){

			return $this->_virtual_children[$name];
		}

		else{

			$VirtualChild = parent::getChildByName($name);

			$this->_virtual_children[$name] = $VirtualChild;

			return $VirtualChild;
		}
	}

	/**
	 * Retrieve a value from this Node.
	 *
	 * <p>The optional {@link $local} argument specifies whether to search only
	 * the local scope (c.q. this Node) or to go through all descendants of
	 * this Node in search of a value named {@link $name}.</p>
	 *
	 * @param string $name
	 * @param boolean $local
	 *
	 * @return mixed
	 * @see NodeTree::getValue()
	 */

	public function getValue($name, $local = false){

		$value = null;
		if(isset($this->_values[$name])){
			$value = $this->_values[$name];
		}

		if(is_null($value) && !is_null($this->_Parent) && !$local){

			return $this->_Parent->getValue($name, $local);
		}

		else{

			return $value;
		}
	}

	/**
	 * Set a value in this Node.
	 *
	 * <p>To unset a value, pass <em>null</em> as its {@link $value}.</p>
	 *
	 * <p>In case {@link $value} is an object, it is checked if the object
	 * implements the {@link Templated} interface. If this is not the case, the
	 * object is searched for the existance of a <em>__toString()</em> method.
	 * In case this method is found, the resulting string is added under
	 * {@link $name}.<br>
	 * If neither the interface nor the method is not a TemplateException gets
	 * thrown.</p>
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @return void
	 * @throws TemplateException
	 */

	public function setValue($name, $value){

		if(!$this->_isValidName($name)){

			throw new TemplateException("Name \"$name\" is invalid");
		}

		// Scalar types

		elseif(is_string($value) || is_bool($value)
			|| is_int($value) || is_float($value)){

			$this->_values[$name] = $value;
		}

		elseif($value === null){

			unset($this->_values[$name]);
		}

		// Objects

		elseif(is_object($value)){

			if(method_exists($value, '__toString')){

				$this->_values[$name] = (string) $value;
			}

			else{

				throw new TemplateException(
					"Unable to set value
					\"$name\", could not convert object to string"
				);
			}
		}

		// Invalid types

		else{

			throw new TemplateException(
				"Unable to set value
				\"$name\", type \"" . gettype($value) . "\" is not allowed"
			);
		}
	}

	/**
	 * Reset the template to its initial state.
	 *
	 * @return void
	 * @see TemplateNodeTree::setValue()
	 * @see NodeTree::resetTemplate()
	 */

	public function resetTemplate(){

		$this->_values = [];

		parent::resetTemplate();
	}
}