<?php
class Arcade_ViewAdmin_Game_List extends XenForo_ViewAdmin_Base {
	public function renderHtml() {
		foreach ($this->_params['games'] as &$game) {
			if (!isset($this->_params['categories'][$game['category_id']]['games'])) {
				$this->_params['categories'][$game['category_id']]['games'] = array();
			}
			
			$this->_params['categories'][$game['category_id']]['games'][$game['game_id']] =& $game;
		}
	}
}