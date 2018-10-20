<?php
/**
 * HttpHeader Plugin
 *
 * @copyright  Copyright (C) 2017 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Utilities\ArrayHelper;

FormHelper::addFormPath(JPATH_PLUGINS . '/system/httpheader/forms');

/**
 * Plugin class for Http Header
 *
 * @since   1.0
 */
class PlgSystemHttpHeader extends CMSPlugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 *
	 * @since   1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Extension Id.
	 *
	 * @var    int
	 *
	 * @since   1.0
	 */
	protected $id;

	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 *
	 * @since   1.0
	 */
	protected $app;

	/**
	 * The list of the suported HTTP headers
	 *
	 * @var    array
	 *
	 * @since   1.0
	 */
	protected $supportedHttpHeaders = array(
		// Upcoming Header
		'Expect-CT',
	);

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @return   void
	 *
	 * @since   1.5
	 */
	public function __construct(&$subject, $config = array())
	{
		if (empty($this->id))
		{
			$this->id = (int) $config['id'];
		}

		parent::__construct($subject, $config);
	}

	/**
	 * Listener for the `onContentPrepareForm` event
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   array  $data  The associated data for the form
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	public function onContentPrepareForm($form, $data)
	{
		if ($form->getName() == 'com_menus.item')
		{
			$this->loadLanguage('plg_system_httpheader');
			$form->setField(new SimpleXMLElement('<fieldset name="httpheader"></fieldset>'), 'params');
			$form->setField(
				new SimpleXMLElement(
					'<field name="xframeoptions_subform" type="subform" label="" formsource="/plugins/system/httpheader/forms/xframeoptions.xml"/>'
				),
				null, false, 'httpheader');
			$form->setField(
				new SimpleXMLElement(
					'<field name="contentsecuritypolicy_subform" type="subform" label="" formsource="/plugins/system/httpheader/forms/contentsecuritypolicy.xml"/>'
				),
				null, false, 'httpheader');
			$form->setField(
				new SimpleXMLElement(
					'<field name="additional_httpheader_subform" type="subform" label="" formsource="/plugins/system/httpheader/forms/additionalhttpheader.xml"/>'
				),
				null, false, 'httpheader');
		}
	}

	/**
	 * Listener for the `onContentPrepareData` event
	 *
	 * @param   string  $form  The context for the data
	 * @param   object  $data  An object containing the data for the form.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function onContentPrepareData($form, $data)
	{
		if ($form == 'com_menus.item')
		{
			$globals = $this->app->getUserState('plugins.system.httpheaders');
			$new = false;
			$newData = array();

			if (!isset($data['params']['xframeoptions_subform']) && !empty($globals))
			{
				$newData['params']['xframeoptions_subform'] = json_encode(
					$this->app->getUserState('plugins.system.httpheaders.xframeoptions_subform')
				);

				$new = true;
			}

			if (!isset($data['params']['contentsecuritypolicy_subform']) && !empty($globals))
			{
				$newData['params']['contentsecuritypolicy_subform'] = json_encode(
					$this->app->getUserState('plugins.system.httpheaders.contentsecuritypolicy_subform')
				);
				$new = true;
			}

			if (!isset($data['params']['additional_httpheader_subform']) && !empty($globals))
			{
				$newData['params']['additional_httpheader_subform'] = json_encode(
					$this->app->getUserState('plugins.system.httpheaders.additional_httpheader_subform')
				);
				$new = true;
			}

			if ($new)
			{
				$_form = Form::getInstance($form);
				$_form->bind($newData);
			}
		}

		if ($form == 'com_plugins.plugin')
		{
			if ((string) $data->name == 'plg_system_httpheader')
			{
				if (isset($data->params['hsts_subform']) && (int) $data->params['save_note_handler'] === 1)
				{
					$data->params['save_note_handler'] = 0;
					$this->setDefaultValues($data->params);
				}
			}
		}
	}

	/**
	 * Set Plugin params global
	 *
	 * @param   array  $data
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	private function setDefaultValues($data = array())
	{
		if (empty($data))
		{
			$data = $this->params->toArray();
		}

		$this->app->setUserState('plugins.system.httpheaders', null);

		foreach ($data as $key => $values)
		{
			$this->app->setUserState('plugins.system.httpheaders.' . $key, $values);
		}
	}

	/**
	 * Listener for the `onBeforeCompileHead` event
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	public function onBeforeCompileHead()
	{
		$this->setDefaultValues();

		$globals = (array) $this->app->getUserState('plugins.system.httpheaders');

		if ($this->app->isClient('site'))
		{
			$menuParams = $this->app->getMenu()->getActive()->getParams()->toArray();

			if (isset($menuParams['xframeoptions_subform']))
			{
				$globals['xframeoptions_subform'] = $menuParams['xframeoptions_subform'];
			}

			if (isset($menuParams['contentsecuritypolicy_subform']))
			{
				$globals['contentsecuritypolicy_subform'] = $menuParams['contentsecuritypolicy_subform'];
			}

			if (isset($menuParams['additionalhttpheader_subform']))
			{
				$globals['additionalhttpheader_subform'] = $menuParams['additionalhttpheader_subform'];
			}
		}

		foreach ($globals as $globalKey => $globalValue)
		{
			if ($globalKey == 'save_note_handler')
			{
				continue;
			}

			$class = 'set' . ucfirst(stristr($globalKey, '_', true)) . 'Header';

			$this->{$class}($globalValue);
		}
	}

	/**
	 * Listener for the `onAfterDispatch` event
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	public function onAfterDispatch()
	{
		$this->onBeforeCompileHead();
	}

	/**
	 * Listener for the `onAfterInitialise` event
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	public function onAfterInitialise()
	{
		if ($this->app->isClient('administrator'))
		{
			// Clear globals after save plugin
			if ($this->app->input->get('option', null) == 'com_plugins'
				&& $this->app->input->getInt('extension_id') === $this->id)
			{
				if (in_array($this->app->input->get('task', null), array('plugin.save', 'plugin.apply')))
				{
					$this->app->setUserState('plugins.system.httpheaders', null);
				}
			}
		}
	}

	/**
	 * Set the HSTS header when enabled
	 *
	 * @param   array  $options
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	private function setHstsHeader($options)
	{
		extract($options);

		/**
		 * Variables
		 * -----------------
		 * @var   string  $hsts
		 * @var   string  $hsts_maxage
		 * @var   string  $hsts_subdomains
		 * @var   string  $hsts_preload
		 */

		if ($hsts != '0')
		{
			$hstsOptions   = array();
			$hstsOptions[] = (int) $hsts_maxage <= 300 ? 'max-age=300' : 'max-age=' . $hsts_maxage;

			if ($hsts_subdomains != '0')
			{
				$hstsOptions[] = 'includeSubDomains';
			}

			if ($hsts_preload != '0')
			{
				$hstsOptions[] = 'preload';
			}

			$this->app->setHeader('Strict-Transport-Security', implode('; ', $hstsOptions), true);
		}
	}

	/**
	 * Set the X-Content-Type-Options headers when enabled
	 *
	 * @param   array  $options
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	private function setXcontenttypeoptionsHeader($options)
	{
		extract($options);

		/**
		 * Variables
		 * -----------------
		 * @var   string  $xcontenttypeoptions
		 */

		if ($xcontenttypeoptions == '1')
		{
			$this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
		}
	}

	/**
	 * Set the X-Frame-Options headers when enabled
	 *
	 * @param   array  $options
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	private function setXframeoptionsHeader($options)
	{
		extract($options);

		/**
		 * Variables
		 * -----------------
		 * @var   string  $xframeoptions
		 * @var   array   $xframeoptions_allowfrom
		 */

		$xframeoptions = Text::_($xframeoptions);

		if ($xframeoptions != '0')
		{
			$value = $xframeoptions;

			if ($xframeoptions == 'ALLOW-FROM')
			{
				$value = 'ALLOW-FROM ' . implode('; ', ArrayHelper::getColumn($xframeoptions_allowfrom, 'url'));
			}

			$this->app->setHeader('X-Frame-Options', $value, true);
		}
	}

	/**
	 * Set the Referrer-Policy headers when enabled
	 *
	 * @param   array  $options
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	private function setReferrerpolicyHeader($options)
	{
		extract($options);

		/**
		 * Variables
		 * -----------------
		 * @var   string  $referrerpolicy
		 */

		$referrerpolicy = Text::_($referrerpolicy);

		if ($referrerpolicy != '0')
		{
			$this->app->setHeader('Referrer-Policy', $referrerpolicy, true);
		}
	}

	/**
	 * Set the X-XSS-Protection headers when enabled
	 *
	 * @param   array  $options
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	private function setXxssprotectionHeader($options)
	{
		extract($options);

		/**
		 * Variables
		 * -----------------
		 * @var   string  $xxssprotection
		 * @var   string  $xxssprotection_block
		 */

		if ($xxssprotection == '1')
		{
			$blockMode = $xxssprotection_block == '1' ? '; mode=block' : '';
			$this->app->setHeader('X-XSS-Protection', '1' . $blockMode, true);
		}
	}

	/**
	 * Set the additional headers when enabled
	 *
	 * @param   array  $options
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	private function setAdditionalHeader($options)
	{
		extract($options);

		/**
		 * Variables
		 * -----------------
		 * @var   string  $additional_httpheader
		 * @var   array   $additional_httpheader_values
		 */

		if ($additional_httpheader != '0')
		{
			foreach ($additional_httpheader_values as $httpHeader)
			{
				// Handle the client settings foreach header
				if (!$this->app->isClient($httpHeader['client']) && $httpHeader['client'] != 'both'
					|| empty($httpHeader['key'])
					|| empty($httpHeader['value']))
				{
					continue;
				}

				// @todo Set a group of allowed HTTP-Headers an validate
				/*
				 * if (in_array($httpHeader['key'], $this->supportedHttpHeaders))
				 * {
				 *      $this->app->setHeader($httpHeader['key'], $httpHeader['value']);
				 * }
				*/
				$this->app->setHeader($httpHeader['key'], $httpHeader['value'], true);
			}
		}
	}

	/**
	 * Set the Content-Security-Policy header when enabled
	 *
	 * @param   array  $options
	 *
	 * @return   void
	 *
	 * @since   1.0
	 */
	private function setContentsecuritypolicyHeader($options)
	{
		extract($options);

		/**
		 * Variables
		 * -----------------
		 * @var   string  $contentsecuritypolicy
		 * @var   string  $contentsecuritypolicy_report_only
		 * @var   array   $contentsecuritypolicy_values
		 */

		if ($contentsecuritypolicy != '0')
		{
			$csp          = $contentsecuritypolicy_report_only == '0' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
			$newCspValues = array();

			foreach ($contentsecuritypolicy_values as $cspValue)
			{
				// Handle the client settings foreach header
				if (!$this->app->isClient($cspValue['client']) && $cspValue['client'] != 'both'
					|| empty(trim($cspValue['key']))
					|| empty(trim($cspValue['value'])))
				{
					continue;
				}

				$newCspValues[] = trim($cspValue['key']) . ': ' . trim($cspValue['value']);
			}

			if (!empty($newCspValues))
			{
				$this->app->setHeader($csp, implode('; ', $newCspValues), true);
			}
		}
	}
}
