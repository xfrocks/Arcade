<?php

class Arcade_ViewPublic_Index extends XenForo_ViewPublic_Base
{
	public function prepareParams()
	{
		$this->_params['breadcrumbs'] = Arcade_Helper_Link::getRootBreadcrumbs();
		
		return parent::prepareParams();
	}
}
