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

}
