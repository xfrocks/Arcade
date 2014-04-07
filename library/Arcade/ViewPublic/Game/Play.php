<?php

class Arcade_ViewPublic_Game_Play extends XenForo_ViewPublic_Base
{
	public function prepareParams()
	{
		$this->_params['breadcrumbs'] = Arcade_Helper_Link::getRootBreadcrumbs();

		if (!empty($this->_params['category']))
		{
			$this->_params['breadcrumbs'] = Arcade_Helper_Link::getCategoryBreadcrumbs($this->_params['category'], $this->_params['breadcrumbs']);
		}

		$this->_params['breadcrumbs'][] = array(
			'href' => XenForo_Link::buildPublicLink('canonical:arcade', $this->_params['game']),
			'value' => $this->_params['game']['title'],
		);

		return parent::prepareParams();
	}

	public function renderHtml()
	{
		$this->_params['player'] = $this->_params['system']->renderPlayer($this, $this->_params['game']);
	}

}
