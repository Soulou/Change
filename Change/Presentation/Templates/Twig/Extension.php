<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Presentation\Templates\Twig;

/**
 * @name \Change\Presentation\Templates\Twig\Extension
 */
class Extension implements \Twig_ExtensionInterface
{
	/**
	 * @var \Change\I18n\I18nManager
	 */
	protected $i18nManager;

	/**
	 * @param \Change\I18n\I18nManager $i18nManager
	 */
	function __construct(\Change\I18n\I18nManager $i18nManager)
	{
		$this->i18nManager = $i18nManager;
	}

	/**
	 * Returns the name of the extension.
	 * @return string The extension name
	 */
	public function getName()
	{
		return 'Change';
	}

	/**
	 * Initializes the runtime environment.
	 * This is where you can load some file that contains filter functions for instance.
	 * @param \Twig_Environment $environment The current Twig_Environment instance
	 */
	public function initRuntime(\Twig_Environment $environment)
	{
	}

	/**
	 * Returns the token parser instances to add to the existing list.
	 * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
	 */
	public function getTokenParsers()
	{
		return array();
	}

	/**
	 * Returns the node visitor instances to add to the existing list.
	 * @return array An array of Twig_NodeVisitorInterface instances
	 */
	public function getNodeVisitors()
	{
		return array();
	}

	/**
	 * Returns a list of filters to add to the existing list.
	 * @return array An array of filters
	 */
	public function getFilters()
	{
		return array();
	}

	/**
	 * Returns a list of tests to add to the existing list.
	 * @return array An array of tests
	 */
	public function getTests()
	{
		return array();
	}

	/**
	 * Returns a list of functions to add to the existing list.
	 * @return array An array of functions
	 */
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('i18n', array($this, 'i18n')),
			new \Twig_SimpleFunction('i18nAttr', array($this, 'i18nAttr'), array('is_safe' => array('html', 'html_attr'))),
		);
	}

	/**
	 * Returns a list of operators to add to the existing list.
	 * @return array An array of operators
	 */
	public function getOperators()
	{
		return array();
	}

	/**
	 * Returns a list of global variables to add to the existing list.
	 * @return array An array of global variables
	 */
	public function getGlobals()
	{
		return array();
	}

	/**
	 * @return \Change\I18n\I18nManager
	 */
	protected function getI18nManager()
	{
		return $this->i18nManager;
	}

	/**
	 * @param string $i18nKey
	 * @param string[] $formatters
	 * @param array $replacementArray
	 * @return string
	 */
	public function i18n($i18nKey, $formatters = array(), $replacementArray = null)
	{
		if (!is_array($replacementArray))
		{
			$replacementArray = array();
		}
		return $this->getI18nManager()->trans($i18nKey, $formatters, $replacementArray);
	}

	/**
	 * @param string $i18nKey
	 * @param string[] $formatters
	 * @param array $replacementArray
	 * @return string
	 */
	public function i18nAttr($i18nKey, $formatters = array(), $replacementArray = null)
	{
		if (!is_array($replacementArray))
		{
			$replacementArray = array();
		}
		$formatters[] = 'attr';
		return $this->getI18nManager()->trans($i18nKey, $formatters, $replacementArray);
	}
}