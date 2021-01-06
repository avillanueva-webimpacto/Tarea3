<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA

*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class WeatherApiBox extends Module
{
	const HOOK_TARGET        = 'displayNav1';
	const CONFIG_KEY         = 'a2fc5cd2c929f810a3a20d7664bbee0e'; //For test Use Weatherapi Api Key: 577c084f57b4dcba8301631201709e
	const FORM_INPUT_API_KEY = 'api_key_input';
	const FORM_ACTION_SUBMIT = 'api_key_submit';
	const API_ENDPOINT       = 'http://api.weatherapi.com/v1/current.json';

	public $form  = [];
	private $_configValue = '';

	public function __construct()
	{
		$this->author                 = 'Armando';
		$this->bootstrap              = true;
		$this->controllers            = ['default'];
		$this->displayName            = $this->l('API Open Weather');
		$this->description            = $this->l('Tarea número 3 de Prestashop para webImpacto');
		$this->name                   = 'weatherapibox';
		$this->need_instance          = 1;
		$this->version                = '1.0.0';
        $this->confirmUninstall       = $this->l('¿Quieres desinstalar este modulo?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->_configValue           = Configuration::get(static::CONFIG_KEY);
		parent::__construct();
	}

	public function install()
	{
		return ((!parent::install() || !$this->registerHook( static::HOOK_TARGET )) ? false : true);
	}

	public function unistall()
	{
		return ((!parent::install() || !$this->unregisterHook( static::HOOK_TARGET )) ? false : true);
	}

	public function getContent()
	{
		return $this->postProcess() . $this->getForm();
	}

	public function getForm()
	{
		$form = new HelperForm();
		$form->module                   = $this;
		$form->name_controller          = $this->name;
		$form->identifier               = $this->identifier;
		$form->token                    = Tools::getAdminTokenLite('AdminModules');
		$form->languages                = $this->context->controller->getLanguages();
 		$form->currentIndex             = AdminController::$currentIndex . '&configure=' . $this->name;
 		$form->default_form_language    = $this->context->controller->default_form_language;
		$form->allow_employee_form_lang = $this->context->controller->allow_employee_form_lang;
		$form->title                    = $this->displayName;
		$form->submit_action            = static::FORM_ACTION_SUBMIT;

		$form->fields_value[static::FORM_INPUT_API_KEY] = $this->_configValue;

		array_push($this->form, [
            'form' => [
				'legend' => ['title' => $this->displayName ],
				'submit' => ['title' => $this->l('Save')   ],
				'input'  => $this->formInputs()
            ]
		]);

		return $form->generateForm($this->form);
	}

	public function postProcess()
	{
		if(Tools::isSubmit(static::FORM_ACTION_SUBMIT))
		{
			$value = Tools::getValue(static::FORM_INPUT_API_KEY);
			Configuration::updateValue(static::CONFIG_KEY, $value);
			return $this->displayConfirmation( $this->l('Updated Successfully') );
		}
	}

	protected function formInputs()
	{
		$inputs = [];

		// uno a uno!
		array_push($inputs, [
			'type'     => 'text',
			'label'    => $this->l('Api Key'),
			'desc'     => $this->l('Weatherapi Api Key'),
			'required' => true,
			'name'     => static::FORM_INPUT_API_KEY,
			'lang'     => false,
			#'empty_message' => $this->l('To be displayed when the field is empty.'),
		]);

		return $inputs;
	}

	public static function getUserIp()
	{
		$userIp = $_SERVER['REMOTE_ADDR'] ?? '';

		#dev localhost ip
		if(in_array($userIp, ['localhost', '127.0.0.1', '']))
		{
			try{
				$userIp = file_get_contents("http://ipecho.net/plain");
			}
			catch(\Exception $e){
				return '';
			}
		}

		return $userIp;
	}

	protected function apiRequest()
	{
		$key    = trim($this->_configValue);
		$userIp = trim(static::getUserIp());

		if(empty($userIp) || empty($key))
			return;

		$url = sprintf('%s?%s', static::API_ENDPOINT, http_build_query([
			'key' =>  $key,
			'q'   => $userIp
		]));

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$response = curl_exec($ch);
		$info     = curl_getinfo($ch);

		// check request
		if($info['http_code']!= 200)
		{
			//@todo add log..
			curl_close($ch);
			return;
		}
		curl_close($ch);

		//check response json:
		try{

			$data = json_decode($response, true);
			if(json_last_error()!= JSON_ERROR_NONE)
			{
				//@todo add log error json..
				return;
			}

		}
		catch(\Exception $e){
			//@todo add log error json..
			return;
		}

		if(isset($data['error']))
		{
			/*
				@todo log list error codes..
				HTTP Status Code	Error code	Description
				401	1002	API key not provided.
				400	1003	Parameter 'q' not provided.
				400	1005	API request url is invalid
				400	1006	No location found matching parameter 'q'
				401	2006	API key provided is invalid
				403	2007	API key has exceeded calls per month quota.
				403	2008	API key has been disabled.
				400	9999	Internal application error.
			*/
			return $data['error'];
		}

		return $data;
	}

	public function hookDisplayNav1()
	{
		$data = $this->apiRequest();

		if(empty($data))
			return '';


		$this->context->smarty->assign([
			'icon'     => $data['current']['condition']['icon'],
			'temp'     => $data['current']['temp_c'],
			'humidity' => $data['current']['humidity'],
		]);
		return $this->context->smarty->fetch($this->local_path.'views/templates/hook/displaynav1.tpl');
	}
}
