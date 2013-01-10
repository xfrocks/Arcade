<?php
class Arcade_ViewAdmin_Game_Edit extends XenForo_ViewAdmin_Base {
	public function renderHtml() {
		$this->_params['renderedOptions'] = array();
		$this->_params['system_options_loaded'] = 0;
		$game =& $this->_params['game'];
		if (!empty($game['system_id'])) {
			$system = XenForo_Model::create('Arcade_Model_System')->initSystem($game['system_id']);
			$this->_params['renderedOptions'] = $system->renderOptions($this, $game);
			$this->_params['customizedOptions'] = $system->renderCustomizedOptions($this, $game);
			$this->_params['system_options_loaded'] = $game['system_id'];
		}
		
		$this->_params['systemsSource'] = array();
		if (!empty($this->_params['systems'])) {
			foreach ($this->_params['systems'] as &$system) {
				$this->_params['systemsSource'][] = array(
					'value' => $system['system_id'],
					'label' => $system['name'],
				);
			}
		}
		
		$this->_params['categoriesSource'] = array();
		if (!empty($this->_params['categories'])) {
			foreach ($this->_params['categories'] as &$category) {
				$this->_params['categoriesSource'][] = array(
					'value' => $category['category_id'],
					'label' => $category['title'],
				);
			}
		}
	}
}