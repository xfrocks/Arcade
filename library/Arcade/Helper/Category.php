<?php

class Arcade_Helper_Category
{
	const SIMPLE_CACHE_KEY = 'Arcade_categories';

	public static function getCategories()
	{
		$categories = XenForo_Application::getSimpleCacheData(self::SIMPLE_CACHE_KEY);

		if ($categories === false)
		{
			$categories = self::rebuildCache();
		}

		return $categories;
	}

	public static function rebuildCache()
	{
		$categories = XenForo_Model::create('Arcade_Model_Category')->getCategories(true);

		XenForo_Application::setSimpleCacheData(self::SIMPLE_CACHE_KEY, $categories);

		return $categories;
	}

}
