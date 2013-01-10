<?php

class Arcade_ViewAdmin_Import_Step2 extends XenForo_ViewAdmin_Base {

	public function renderHtml() {
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