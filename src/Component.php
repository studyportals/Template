<?php
/**
 * @file Component.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

/**
 * Component base class.
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
abstract class Component extends TemplateNodeTree{

	private $_defaults = [];
	private $_options = [];

	/**
	 * Prepare the component for serialisation.
	 *
	 * @return array
	 */

	public final function __sleep(){

		return array_merge(
			parent::__sleep(),
			[
				"\0Component\0_options",
				"\0Component\0_defaults"
			]
		);
	}

	/**
	 * Wakeup the component on unserialisation.
	 *
	 * <p>If the Component::_load() method fails with an Component type of
	 * exception, the component is removed from the template tree.<p>
	 *
	 * This removal is done silently, only generating a FireLogger message.
	 * Being more strict here has a high risk of tripping the PHP engine and
	 * resulting in even less useful error messages.</p>
	 *
	 * @throws NodeNotFoundException
	 * @return void
	 * @see Component::_load()
	 */

	public final function __wakeup(){

		try{

			$this->_load();
		}
		catch(ComponentException $e){

			$this->_Parent->removeChild($this);
		}
	}

	/**
	 * Set a default option.
	 *
	 * <p>Set a default option, which is used if no other value is defined
	 * through Component::setOption() for the {@link $name} specified.</p>
	 *
	 * <p>Once a default option is set, it cannot be changed or unset. This
	 * method is specifically designed to be used in the context of
	 * Component::_load(), where it allows a component to specify its default
	 * (c.q. fallback) options.<br>
	 * This method is also called during template construction if "in-template"
	 * configuration option is encountered. As a result, in-template options are
	 * defined before Component::_load() is called. The options defined in the
	 * template file thus serve as immutable defaults.</p>
	 *
	 * <p>Default options are <strong>not</strong> reset when the component is
	 * reset through Component::resetTemplate(). This as opposed to regular
	 * options.</p>
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @return void
	 * @see Component::_load()
	 * @see Component::resetTemplate()
	 * @see Component::setOption()
	 */

	public final function setDefault($name, $value){

		if(!isset($this->_defaults[$name])){

			$this->_defaults[$name] = $value;
		}
	}

	/**
	 * Set an option.
	 *
	 * <p>Set the {@link $value} parameter to <em>null</em> to unset a
	 * previously set option.</p>
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @return void
	 */

	public final function setOption($name, $value){

		if($value === null){

			unset($this->_options[$name]);
		}

		else{

			$this->_options[$name] = $value;
		}
	}

	/**
	 * Get the value for a previously defined option.
	 *
	 * <p>If an option with the provided {@link $name} is defined, its value is
	 * returned. If no such option exists, the default with the provided
	 * {@link $name} is returned, or <em> null</em> if nothing is found.</p>
	 *
	 * @param string $name
	 *
	 * @return mixed
	 * @see Component::setDefault()
	 * @see Component::setOption()
	 */

	public final function getOption($name){

		if(isset($this->_options[$name])){

			return $this->_options[$name];
		}
		elseif(isset($this->_defaults[$name])){

			return $this->_defaults[$name];
		}
		else{

			return null;
		}
	}

	/**
	 * Reset the component to its initial state.
	 *
	 * @return void
	 */

	public final function resetTemplate(){

		$this->_options = [];

		parent::resetTemplate();
	}

	/**
	 * Load the component settings.
	 *
	 * <p>This method is called directly after the component is either
	 * constructed or unserialised. It can be used to set default options,
	 * verify the internal template structure and other "setup" tasks.
	 * Implementing this method is optional.</p>
	 *
	 * @return void
	 */

	public function _load(){

		return;
	}

	/**
	 * Prepare the component template for output.
	 *
	 * <p>This method is called upon display and should be used by the component
	 * to execute the necessary actions on its template in order for it to be
	 * displayed properly.<br>
	 * In case of failure, this method should throw an ComponentException.</p>
	 *
	 * @return void
	 * @throws ComponentException
	 */

	protected abstract function _prepareTemplate();

	/**
	 * Display the component.
	 *
	 * <p>If the Component::_prepareTemplate() method which is called by this
	 * method, generates a Component exception, this method returns an empty
	 * string.</p>
	 *
	 * <p>Just as with {@link Component::__wakeup()} exceptions are dropped
	 * silently, only generating a FireLogger message. This way we don't run
	 * the risk of tripping the PHP engine. Something which can easily happen
	 * when  the "display()" method gets called from a "__toString()" method.
	 * Such a call is not allowed to generate any exceptions...</p>
	 *
	 * @return string
	 * @see Component::_prepareTemplate()
	 * @see Component::__wakupe()
	 */

	public final function display(){

		try{

			$this->_prepareTemplate();
		}
		catch(ComponentException $e){

			return '';
		}

		return parent::display();
	}
}