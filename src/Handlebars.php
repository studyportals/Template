<?php
/**
 * @file Handlebars.php
 *
 * @author Rob van den Hout <vdhout@studyportals.eu>
 * @author Rob Janssen <rob@studyportals.com>
 * @version 1.0.1
 * @copyright © 2014-2016 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

use StudyPortals\Utils\File;

/**
 * Class Handlebars.
 *
 * @package StudyPortals\Framework\Template4
 */

class Handlebars extends LocalizedTemplate{

	/**
	 * Construct a handlebars Template tree from a predefined template file.
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
	 * @return Handlebars
	 * @throws TemplateException
	 * @see Factory::templateFactory()
	 */

	public static function templateFactory($template_file, $locale = 'en-GB', $html = true){

		/*
		 * Locale is required, but cannot be defined as such because the
		 * function signature needs to match that of Template.
		 */

		if(empty($locale)){

			throw new TemplateException('Handlebars::templateFactory()
				requires a $locale to be provided');
		}

		$name = basename($template_file);
		$directory = dirname($template_file);

		$extension = File::getExtension($template_file);
		$name = substr($name, 0, strrpos($name, '.'));

		$cache_file = "$directory/$name-$locale-handlebars.$extension-cache";
		$name = preg_replace('/[^A-Z0-9]+/i', '', $name);

		// Load from cache

		if(Template::$_cache_enabled){

			try{

				$Template = Template::_loadCachedTemplate($template_file, $cache_file);
			}
			catch(CacheException $e){}
		}

		// Parse from template-file

		if(!isset($Template) || ($Template instanceof Template) === false){

			$TemplateTokens = HandlebarsFactory::parseTemplate($template_file, $html, $locale);

			$Template = new Handlebars($name, $locale);
			$Template->_file_name = $template_file;
			HandlebarsFactory::buildTemplate($TemplateTokens, $Template, $html);

			if(Template::$_cache_enabled) Template::_storeCachedTemplate($Template, $cache_file);

			// Execute the "_load()" method of all components in the template tree

			TemplateNodeTree::_componentsLoad($Template);

		}

		// Add the base URL.
		/** @noinspection PhpUndefinedClassInspection */
		if(\Site::Singleton() instanceof \Site){

			/** @noinspection PhpUndefinedClassInspection */
			$Template->base_url = \Site::Singleton()->base_url;
		}

		return $Template;
	}
}