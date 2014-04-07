<?php

class Arcade_Helper_Link extends XenForo_Link
{
	public static function getHandlerInfoForGroup($group)
	{
		if (XenForo_Application::$versionId >= 1020000)
		{
			return XenForo_Link::getHandlerInfoForGroup($group);
		}

		if (!isset(XenForo_Link::$_handlerCache[$group]))
		{
			return array();
		}

		return XenForo_Link::$_handlerCache[$group];
	}

	public static function getRootBreadcrumbs(array $breadcrumbs = array())
	{
		if (!Arcade_Option::get('auto_navbar'))
		{
			$breadcrumbs[] = array(
				'href' => XenForo_Link::buildPublicLink('canonical:arcade'),
				'value' => new XenForo_Phrase('arcade'),
			);
		}

		return $breadcrumbs;
	}

	public static function getCategoryBreadcrumbs(array $category, array $breadcrumbs = array())
	{
		$breadcrumbs[] = array(
			'href' => XenForo_Link::buildPublicLink('canonical:arcade/browse', $category),
			'value' => $category['title'],
		);

		return $breadcrumbs;
	}

}
