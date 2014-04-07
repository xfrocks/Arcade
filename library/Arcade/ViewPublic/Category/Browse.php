<?php

class Arcade_ViewPublic_Category_Browse extends XenForo_ViewPublic_Base
{
	public function prepareParams()
	{
		$this->_params['breadcrumbs'] = Arcade_Helper_Link::getRootBreadcrumbs();

		$this->_params['breadcrumbs'][] = array(
			'href' => XenForo_Link::buildPublicLink('canonical:browse', $this->_params['category']),
			'value' => $this->_params['category']['title'],
		);

		return parent::prepareParams();
	}

}
