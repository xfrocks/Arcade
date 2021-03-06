<?php

class Arcade_Route_Prefix_Arcade implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$section = 'arcade';
		if (!Arcade_Option::get('auto_navbar'))
		{
			$section = 'forums';
		}

		$gamedata = 'gamedata';
		if (strpos($routePath, $gamedata) === 0)
		{
			// serve game data (IPB games)
			$requested = substr($routePath, strlen($gamedata) + 1);
			$parts = explode('/', $requested);
			if (count($parts) >= 2)
			{
				$first = array_shift($parts);
				$firstParts = explode('_', $first);
				$countFirstParts = count($firstParts);
				if ($countFirstParts > 2 AND is_numeric($firstParts[$countFirstParts - 2]) AND is_numeric($firstParts[$countFirstParts - 1]))
				{
					array_pop($firstParts);
					array_pop($firstParts);
				}
				$slug = implode('_', $firstParts);

				$file = implode('/', $parts);

				$request->setParam('slug', $slug);
				$request->setParam('file', $file);
				return $router->getRouteMatch('Arcade_ControllerPublic_Arcade', 'IpbGameData', $section);
			}
		}

		if (strpos($routePath, '/') === false)
		{
			$action = $routePath;
		}
		else
		{
			$action = $router->resolveActionWithIntegerOrStringParam($routePath, $request, 'id', 'slug');
		}

		return $router->getRouteMatch('Arcade_ControllerPublic_Arcade', $action, $section);
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		$array = array();
		if (is_array($data))
		{
			foreach ($data as $key => $value)
			{
				if (strpos($key, '_id') !== false)
				{
					$array['id'] = $value;
					break;
				}
			}

			if (isset($data['slug']))
			{
				$array['slug'] = $data['slug'];
			}
			elseif (isset($data['title']))
			{
				$array['slug'] = $data['title'];
			}
		}

		if (isset($array['slug']) && isset($array['id']))
		{
			return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $array, 'id', 'slug');
		}
		elseif (!empty($array['slug']))
		{
			return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $array, 'slug');
		}
		elseif (!empty($array['id']))
		{
			return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $array, 'id');
		}
		else
		{
			return XenForo_Link::buildBasicLink($outputPrefix, $action, $extension);
		}
	}

}
