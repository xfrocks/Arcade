<?php
class Arcade_ViewPublic_Game_Play extends XenForo_ViewPublic_Base {
	public function renderHtml() {
		$this->_params['player'] = $this->_params['system']->renderPlayer($this, $this->_params['game']);
	}
}