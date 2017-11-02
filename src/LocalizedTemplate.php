<?php
/**
 * @file LocalizedTemplate.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

use StudyPortals\Utils\File;

/**
 * LocalizedTemplate class.
 *
 * <p>This class extends the base {@link Template} class with the ability to
 * localise the template into any number of different languages.</p>
 *
 * <p>Apart from allowing localized templates to be constructed from template
 * files, this class also serves as the root node for instantiated localised
 * templates.<br>
 * In this function it is similar to {@link Template}, with the addition of a
 * {@link LocalizedTemplate::getLocale()} method which allows the easy retrieval
 * of the locale into which the current instance is translated.</p>
 *
 * @package Sgraastra.Framework
 * @subpackage Template4
 * @see Template
 * @see LocalizedFactory
 */
class LocalizedTemplate extends Template{

	protected $_locale;

	/**
	 * Construct a new Localized Template4.
	 *
	 * <p>The {@link $locale} argument can be either in RFC-4646 format or in
	 * the more traditional "underscore" format.</p>
	 *
	 * @param string $name
	 * @param string $locale RFC-4646 compliant
	 *
	 * @throws TemplateException
	 * @see Template::__construct()
	 */

	public function __construct($name, $locale){

		parent::__construct($name);

		$locale = str_replace('_', '-', $locale);
		assert('preg_match(\'/^[a-z]{2}-[A-Z]{2}$/\', $locale)');

		$this->_locale = $locale;
	}

	/**
	 * Prepare the LocalizedTemplate for serialisation.
	 *
	 * <p>Ensures the {@link $_locale} property is included when serialised</p>
	 *
	 * @return array
	 */

	public function __sleep(){

		return array_merge(parent::__sleep(), ["\0*\0_locale"]);
	}

	/**
	 * Construct a localized Template tree from a predefined template file.
	 *
	 * <p>This method attempts to read the translations for the template from an
	 * XML-file.The filename of this XML-file should be that of the original
	 * template file, with ".langs" appended.</p>
	 * <p>The {@link $locale} argument specified which language to use. If the
	 * specified language is not found in the translation file, an attempt is
	 * made to use the default language (as specified in the translation file).
	 * If this fails, a TemplateException is thrown.</p>
	 *
	 * @param string $template_file
	 * @param string $locale RFC-4646 compliant locale
	 * @param boolean $html
	 *
	 * @throws \StudyPortals\Cache\CacheException
	 * @return LocalizedTemplate
	 * @throws TemplateException
	 * @see Factory::templateFactory()
	 */

	public static function templateFactory($template_file, $locale = '',
		$html = true){

		/*
		 * Locale is required, but cannot be defined as such because the
		 * function signature needs to match that of Template.
		 */

		if(empty($locale)){

			throw new TemplateException(
				'LocalizedTemplate::templateFactory()
				requires a $locale to be provided'
			);
		}

		$name = basename($template_file);
		$directory = dirname($template_file);

		$extension = File::getExtension($template_file);
		$name = substr($name, 0, strrpos($name, '.'));

		$cache_file = "$directory/$name-$locale.$extension-cache";
		$name = preg_replace('/[^A-Z0-9]+/i', '', $name);

		// Load from cache

		if(Template::$_cache_enabled){

			try{

				$Template =
					Template::_loadCachedTemplate($template_file, $cache_file);
			}
			catch(CacheException $e){}
		}

		// Parse from template-file

		if(!isset($Template) || ($Template instanceof Template) === false){

			$TemplateTokens =
				LocalizedFactory::parseTemplate($template_file, $html, $locale);

			$Template = new LocalizedTemplate($name, $locale);
			$Template->_file_name = $template_file;
			LocalizedFactory::buildTemplate($TemplateTokens, $Template, $html);

			if(Template::$_cache_enabled){
				Template::_storeCachedTemplate($Template, $cache_file);
			}

			// Execute the "_load()" method of all components in the template tree

			TemplateNodeTree::_componentsLoad($Template);
		}

		static::attachDefaultVariables($Template);

		return $Template;
	}

	/**
	 * Return this Template's locale.
	 *
	 * @return string
	 */

	public function getLocale(){

		return $this->_locale;
	}
}