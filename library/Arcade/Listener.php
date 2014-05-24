<?php

class Arcade_Listener
{
	public static function navigation_tabs(array &$extraTabs, $seletectedTabId)
	{
		$visitor = XenForo_Visitor::getInstance();
		$autoNavbar = XenForo_Application::get('options')->xfarcade_auto_navbar;
		if ($autoNavbar)
		{
			if (XenForo_Model::create('Arcade_Model_Game')->hasPermission('navbar'))
			{
				$options = XenForo_Application::get('options');
				$tabId = 'arcade';

				$extraTabs[$tabId] = array(
					'href' => XenForo_Link::buildPublicLink("full:arcade"),
					'title' => new XenForo_Phrase('arcade'),
					'linksTemplate' => 'arcade_links',
					'selected' => ($seletectedTabId == $tabId),
					'categories' => Arcade_Helper_Category::getCategories(),
				);
			}
		}
	}

	public static function load_class($class, array &$extend)
	{
		static $classes = array(
			'XenForo_ControllerPublic_Index',
			'XenForo_DataWriter_User',
		);

		if (in_array($class, $classes))
		{
			$extend[] = 'Arcade_' . $class;
		}
	}

	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		if (defined('IS_ARCADE_PHP'))
		{
			$routesPublic = Arcade_Helper_Link::getHandlerInfoForGroup('public');
			$routesPublic['index'] = $data['routesPublic']['arcade'];
			XenForo_Link::setHandlerInfoForGroup('public', $routesPublic);
		}

		XenForo_Template_Helper_Core::$helperCallbacks['arcade_getoption'] = array(
			'Arcade_Option',
			'get'
		);
		XenForo_Template_Helper_Core::$helperCallbacks['arcade_base64_encode'] = 'base64_encode';

		XenForo_Template_Helper_Core::$helperCallbacks['arcade_renderscore'] = array(
			'Arcade_Template_Helper',
			'renderScore'
		);
	}

	public static function template_create(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		static $preLoadedCommonTemplates = false;

		if (!$preLoadedCommonTemplates)
		{
			$template->preloadTemplate('arcade_hook_message_user_info_text');
		}
	}

	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		switch ($hookName)
		{
			case 'message_user_info_text':
				if (XenForo_Template_Helper_Core::styleProperty('xfarcade_messageShowChampion'))
				{
					$params = $template->getParams();
					$params['user'] = $hookParams['user'];

					if (!empty($params['user']['arcade_champion']))
					{
						$params['userChampion'] = unserialize($params['user']['arcade_champion']);
					}

					if (!empty($params['userChampion']))
					{
						$ourTemplate = $template->create('arcade_hook_message_user_info_text', $params);
						$ourHtml = $ourTemplate->render();

						$search = '<!-- slot: message_user_info_text -->';
						$contents = str_replace($search, $ourHtml . $search, $contents);
					}
				}
				break;
		}
	}

	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += Arcade_FileSums::getHashes();
	}

}
