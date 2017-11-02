<?php

/**
 * @file Template4.php
 *
 * <p>Based on code released under a BSD-style license. For complete license
 * text see http://sgraastra.net/code/license/.</p>
 *
 * @author Thijs Putman <thijs@sgraastra.net>
 * @author Thijs Putman <thijs@studyportals>
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @author Rob Janssen <rob@studyportals.com>
 * @copyright © 2008-2009 Thijs Putman, all rights reserved.
 * @copyright © 2010-2016 StudyPortals B.V., all rights reserved.
 * @version 4.1.1
 */

namespace StudyPortals\Template;

use StudyPortals\Cache\CacheStore;

/**
 * General Template class.
 *
 * <p>This is a special purpose extension of the {@link TemplateNodeTree}.
 * It is used as the top-level node in a Template tree. It provides an
 * entry point into Factory and implements the template cache.</p>
 *
 * <p>This is the only node that can be created without a Parent and thus has
 * the ability to server as the root node in a template tree.<br>
 * It is possible for a Template to be part of another template tree, so if
 * you encounter this class while traversing a tree, it does
 * <strong>not</strong> mean your at the root of the tree. Always use
 * {@link Node::getRoot()} for this purpose.</p>
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */

class Template extends TemplateNodeTree{

	/**@var CacheStore $_CacheStore**/
	protected static $_CacheStore;
	protected static $_cache_enabled = true;

	protected static $_default_variables = [];

	protected $_file_name;

	protected $_Parent = null;
	/** @noinspection PhpMissingParentConstructorInspection */

	/**
	 * Construct a new Template4.
	 *
	 * <p>This method just creates an empty named Node. In order to build a
	 * fully functional template you nee to manually attach child Nodes or,
	 * preferably, use {@link Template::templateFactory()} to construct a
	 * template from a predefined template file.</p>
	 *
	 * <p>This method throws an exception if the provided {@link $name} argument
	 * is invalid.</p>
	 *
	 * @param string $name
	 *
	 * @throws TemplateException
	 * @see Template::templateFactory()
	 */

	public function __construct($name){

		if(!$this->_isValidName($name)){

			throw new TemplateException("Unable to create template, the
				specified name \"$name\" is invalid");
		}

		$this->_name = $name;
	}

	/**
	 * Prepare the Template for serialisation.
	 *
	 * <p>Ensures the {@link $_file_name} property is included when
	 * serialised.</p>
	 *
	 * @return array
	 */

	public function __sleep(){

		return array_merge(parent::__sleep(), ["\0*\0_file_name"]);
	}

	/**
	 * Construct a Template tree from a predefined template file.
	 *
	 * <p>This method takes the predefined template definition from {@link
	 * $template_file} and parses it into a Template tree returning the {@link
	 * Template} at the top of the tree. The name for this {@link Template}
	 * node will be the filename of the original {@link $template_file}, with
	 * all illegal characters stripped.</p>
	 *
	 * <p>The optional <strong>third</strong> argument {@link $html} is used to
	 * indicate the template to be parsed contains HTML; it is enabled by
	 * default. When switched on, several parsing optimisations geared towards
	 * HTML (but destructive to plain-text) are enabled. If you need to parse a
	 * plaint-text template file, disabled this option.<br>
	 * The optional <strong>second</strong> argument {@link $locale} should
	 * always be an empty string (c.q. its default value). This argument is
	 * there to ensure method consistency with LocalizedFactory which inherits
	 * from this method. The order was chosen in such a way to minise the need
	 * to overwrite the default arguments.</p>
	 *
	 * <p>This method provides an automated template cache. It compares the date
	 * of the original template against the cached template. If the original
	 * template has been updated, or the cache does not exist, the cache is
	 * refreshed. In all other situations, the template is read directly from
	 * the cache.<br>
	 * Using the template cache reduces the template load/parse time dramatically
	 * (in most situations, reading the cache is ~200 times faster than parsing
	 * the actual template).<p>
	 *
	 * <p>Template4 is able to utilise the caching framework provided by the
	 * {@link Cache} class for optimal caching flexibility. If no cache handler
	 * is provided Template4 falls back to a simple file-system based caching
	 * approach:<br>
	 * The cached template is stored at the same location and under the same
	 * name as the original {@link $template_file}, with "-cache" appended to
	 * its name.<p>
	 *
	 * @param string $template_file
	 * @param boolean $html
	 *
	 * @throws CacheException
	 * @throws ComponentException
	 * @throws FactoryException
	 * @throws TemplateException
	 * @throws \StudyPortals\Cache\CacheException
	 * @return Template | \stdClass
	 * @see Template::_parseTemplate()
	 * @see Template::setTemplateCacheHandler()
	 */

	public static function templateFactory($template_file, $html = true){

		$cache_file = "$template_file-cache";

		// Load from cache

		if(self::$_cache_enabled){

			try{

				$Template = self::_loadCachedTemplate($template_file, $cache_file);
			}
			catch(CacheException $e){}
		}

		// Parse from template-file

		if(!isset($Template) || ($Template instanceof Template) === false){

			$name = basename($template_file);
			$name = substr($name, 0, strrpos($name, '.'));
			$name = preg_replace('/[^A-Z0-9]+/i', '', $name);

			$TemplateTokens = Factory::parseTemplate($template_file, $html);

			$Template = new Template($name);
			$Template->_file_name = $template_file;
			Factory::buildTemplate($TemplateTokens, $Template, $html);

			if(self::$_cache_enabled) self::_storeCachedTemplate($Template, $cache_file);

			// Execute the "_load()" method of all components in the template tree

			TemplateNodeTree::_componentsLoad($Template);
		}

		static::attachDefaultVariables($Template);

		return $Template;
	}

	/**
	 * Attach the default variables.
	 *
	 * @param Template $Template
	 *
	 * @throws TemplateException
	 * @return void
	 */
	protected static function attachDefaultVariables(Template $Template){

		foreach(static::$_default_variables as $name => $value){

			$Template->setValue($name, $value);
		}

		// @deprecated this should be added to the default variables.
		/** @noinspection PhpUndefinedClassInspection */
		if(class_exists('\Site') && \Site::Singleton() instanceof \Site){

			/** @noinspection PhpUndefinedClassInspection */
			$Template->base_url = \Site::Singleton()->base_url;
		}
	}

	/**
	 * Set a default variable to be included when a Template is created.
	 *
	 * @param string $name
	 * @param string $value
	 * @param boolean $overwrite
	 * @throws TemplateException
	 * @return boolean true if the value overwrote an existing value
	 */
	public static function setDefaultVariable($name, $value, $overwrite = true){

		$exists = isset(static::$_default_variables[$name]);

		if(!$overwrite && $exists){

			throw new TemplateException("Variable '$name' was already set");
		}

		static::$_default_variables[$name] = $value;

		return $exists;
	}

	/**
	 * Save a serialised copy of the template-tree to the cache.
	 *
	 * <p>Errors writing the cache will only generate a failed assertion. This
	 * ensures normal operation (although with a major performance hit)
	 * continues if caching fails.</p>
	 *
	 * @param Template $Template
	 * @param string $cache_file
	 *
	 * @throws CacheException
	 * @throws \StudyPortals\Cache\CacheException
	 * @return void
	 */

	protected static function _storeCachedTemplate(Template $Template, $cache_file){

		/*
		 * Some sanity-checks on the to-be-cached Template.
		 *
		 * We recently had some issues with invalid templates getting cached,
		 * causing all kinds of crazy problems (see #2973). This checks are
		 * both intended to signal the issue (so I know I'm actually looking
		 * in the right place) and to prevent invalid templates from getting
		 * cached (and thus prevent them from causing further issues).
		 */

		$Root = $Template->getRoot();

		if($Root !== $Template){

			throw new CacheException('Trying to cache a non-root Template');
		}

		if(count($Template->_children) == 0){

			throw new CacheException('Template has no children');
		}

		foreach($Template->_children as $key => $Child){

			if(!is_numeric($key) && !($Child instanceof TemplateNodeTree)){

				throw new CacheException('Template has an invalid named-Child element');
			}

			if(!($Child instanceof Node)){

				throw new CacheException('Template has an invalid Child element');
			}
		}

		// Atempt to utilise an external cache-engine

		if(self::$_CacheStore instanceof CacheStore){

			$template_mtime = @filemtime($Template->getFileName());
			/** @noinspection PhpUnusedLocalVariableInspection */
			$result = self::$_CacheStore->set(md5($template_mtime . $cache_file), $Template);

			assert('$result === true');
		}

		// Fallback to simple file-system caching

		else{

			/** @noinspection PhpUnusedLocalVariableInspection */
			$result = @file_put_contents($cache_file, serialize($Template), LOCK_EX|FILE_TEXT);

			assert('$result > 0');
		}
	}

	/**
	 * Attempt to load a previously cached template file.
	 *
	 * <p>This method can throw a {@link CacheException} which indicates a
	 * recoverable error with the template cache. Simply re-create the cache
	 * and continue.<br>
	 * Alternatively, this method can throw a {@link TemplateException} which
	 * indicates a fatal, non-recoverable, problem with the cache. It's probably
	 * best to let this exception cascade on so it shows up on your radar.
	 * Otherwise, more serious issues might go unnoticed.</p>
	 *
	 * @param string $template_file
	 * @param string $cache_file
	 *
	 * @throws CacheException
	 * @throws TemplateException
	 * @return Template
	 */

	protected static function _loadCachedTemplate($template_file, $cache_file){

		$template_base = basename($template_file);
		$template_mtime = @filemtime($template_file);

		// Atempt to utilise an external cache-engine

		if($template_mtime !== false && self::$_CacheStore instanceof CacheStore){

			$cache_handler = get_class(self::$_CacheStore);
			$cache_entry = md5($template_mtime . $cache_file);

			$error = false;
			$Template = self::$_CacheStore->get($cache_entry, $error);

			if($error){

				// Delete the invalid entry

				self::$_CacheStore->delete($cache_entry);

				throw new CacheException("$cache_handler encountered an
					unknown error while retrieving '$template_base'");
			}

			if($Template instanceof Template) return $Template;

			throw new CacheException("$cache_handler failed to locate a cached
				copy of template $template_base");
		}

		// Fallback to simple file-system caching (if a file is available)

		elseif(file_exists($cache_file) && is_readable($cache_file)){

			// Use cache if it is "fresh" or if the original template is missing

			if($template_mtime === false ||
				($template_mtime !== false && $template_mtime <= @filemtime($cache_file))){

				$cached_data = @file_get_contents($cache_file);

				assert('$cached_data !== false');

				$Template = @unserialize($cached_data);

				if(($Template instanceof Template) === false){

					// Remove the corrupted cache

					unlink($cache_file);

					throw new TemplateException("Corrupted cache encountered
						for template $template_base");
				}

				return $Template;
			}

			throw new CacheException("Cache-file expired for template $template_base");
		}

		// No cache available

		else{

			throw new CacheException("Cache-file for template $template_base
				was not found or was inaccessible");
		}
	}

	/**
	 * Set the global state of the template cache.
	 *
	 * <p>Enables or disables the creation and use of cached templates. Enabled
	 * by default, disabling simplifies development, but comes at a significant
	 * performance penalty.</p>
	 *
	 * @param string $state [on|off]
	 * @return void
	 */

	public static function setTemplateCache($state){

		self::$_cache_enabled = (bool) filter_var($state, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Set the global template CacheStore.
	 *
	 * <p>Template4 is able to utilise the caching infrastructure provided
	 * through the {@link Cache} classes. To enable this feature simple pass a
	 * CacheStore to this method. When no store is provided, Template4 falls
	 * back to a simple file-system cache.</p>
	 *
	 * @param CacheStore $CacheStore
	 * @return void
	 */

	public static function setTemplateCacheStore(CacheStore $CacheStore){

		self::$_CacheStore = $CacheStore;
	}

	/**
	 * Get the name of the file this Template instance was created from.
	 *
	 * <p>Returns the full file name (relative to the PHP-file calling the
	 * {@link Template::templateFactory()} method) of the template file used to
	 * build this Template instance.</p>
	 *
	 * @return string
	 */

	public function getFileName(){

		return $this->_file_name;
	}
}