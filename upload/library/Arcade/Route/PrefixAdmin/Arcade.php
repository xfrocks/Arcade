<?php
class Arcade_Route_PrefixAdmin_Arcade implements XenForo_Route_Interface {
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router) {
		if (strpos($routePath, '/') === false) {
			$action = $routePath;
		} else {
			$action = $router->resolveActionWithIntegerOrStringParam($routePath, $request, 'id', 'slug');
		}
		
		return $router->getRouteMatch('Arcade_ControllerAdmin_Arcade', $action, 'arcade');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams) {
		$array = array();
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if (strpos($key, '_id') !== false) {
					$array['id'] = $value;
					break;
				}
			}
			if (isset($data['slug'])) $array['slug'] = $data['slug'];
		}
		
		if (!empty($$array['slug'])) {
			return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $array, 'slug');
		} elseif (!empty($array['id'])) {
			return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $array, 'id');
		} else {
			return XenForo_Link::buildBasicLink($outputPrefix, $action, $extension);
		}
	}
}