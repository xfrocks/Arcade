<?php
class Arcade_ViewAdmin_Game_Save extends XenForo_ViewAdmin_Base {
	public function renderJson() {
		$game =& $this->_params['game'];
		$oldGame =& $this->_params['oldGame'];
		
		$output = array(
			'gameId' => $game['game_id'],
			'saveMessage' => new XenForo_Phrase('arcade_game_saved_successfully'),
			'updated' => array(),
		);
		
		if ((isset($game['image_date']) AND !isset($oldGame['image_date'])) OR ($game['image_date'] != $oldGame['image_date'])) {
			$output['updated']['imageHtml'] = new XenForo_Phrase('arcade_image_explain_with_image', array(
				'image' => '<img src="' . Arcade_Model_Game::getImageUrl($game, 'x') . '" />',
			), false);
		}
		
		if (!empty($game['system_options']) AND (empty($oldGame['system_options']) OR $game['system_options'] != $oldGame['system_options'])) {
			$system = XenForo_Model::create('Arcade_Model_System')->initSystem($game['system_id']);
			$system->doInterfaceUpdate($output, $this->_params);
		}
		
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}