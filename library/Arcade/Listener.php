<?php
class Arcade_Listener {
	public static function navigation_tabs(array &$extraTabs,$seletectedTabId) {
		$visitor = XenForo_Visitor::getInstance();

		if (XenForo_Model::create('Arcade_Model_Game')->hasPermission('navbar')) {
			
			
			$options = XenForo_Application::get('options');
		
			$tabId = 'arcade';
			
			/// @todo: implement arcade_links template, need way of linking directly to a category tab
			
			$extraTabs[$tabId] = array(
				'href' => XenForo_Link::buildPublicLink("full:arcade"),
				'title' => new XenForo_Phrase('arcade'),
				//'linksTemplate' => 'arcade_links',
				'selected' => ($seletectedTabId == $tabId),
			);
			
			if(!$options->xfarcade_enable_categories)
				unset($extraTabs[$tabId]['linksTemplate']);
		}
	}
	
	public static function load_class($class, array &$extend) {
		static $classes = array(
			'XenForo_ControllerPublic_Index',
		);
		
		if (in_array($class, $classes)) {
			$extend[] = str_replace('XenForo_', 'Arcade_Extend_', $class);
		}
	}
	
	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data) {
		XenForo_Template_Helper_Core::$helperCallbacks['arcade_base64_encode'] = 'base64_encode';
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes) {
		$hashes += Arcade_FileSums::getHashes();
	}
}