<?php
abstract class Arcade_System {
	abstract protected function _getAvailableOptions();
	
	abstract protected function _getPhrase($phraseId);
	
	public function renderOptions(XenForo_View $view, array $game) {
		$options = $this->_getAvailableOptions();
		$this->standardizeOptions($options, $game);
		
		$optionModel = XenForo_Model::create('XenForo_Model_Option');
		$preparedOptions = $optionModel->prepareOptions($options);
		
		foreach ($preparedOptions AS $key => $preparedOption) {
			$preparedOption['title'] = $this->_getPhrase('option_' . $preparedOption['option_id'] . '_title');
			$preparedOption['explain'] = $this->_getPhrase('option_' . $preparedOption['option_id'] . '_explain');
			
			$renderedOptions[$key] = XenForo_ViewAdmin_Helper_Option::renderPreparedOptionHtml($view, $preparedOption, false, self::_getFieldPrefix());
		}
		
		return $renderedOptions;
	}
	
	public function renderCustomizedOptions(XenForo_View $view, array $game) {
		return '';
	}
	
	public function standardizeOptions(array &$options, array $game) {
		$fields = array(
			'option_value' => '',
			'default_value' => '',
			'edit_format_params' => '',
			'sub_options' => '',
			'can_backup' => 0,
			'validation_class' => '',
			'validation_method' => '',
			'addon_id' => 'oops',
		);
		foreach ($options as &$option) {
			$option = array_merge($fields, $option);
			if (!empty($game['system_options'][$option['option_id']])) {
				$option['option_value'] = $game['system_options'][$option['option_id']];
			} else {
				$option['option_value'] = $option['default_value'];
			}
		}
	}
	
	abstract protected function _validateOption($optionId, &$optionValue);
	
	public function processOptionsInput(XenForo_Input $inputObject, array $game, XenForo_DataWriter $dw) {
		$input = $inputObject->filter(array(
			self::_getFieldPrefix() => XenForo_Input::ARRAY_SIMPLE,
		));		
		$inputOptions = array_shift($input);
		$availableOptions = $this->_getAvailableOptions();
		$options = isset($game['system_options'])?$game['system_options']:array();
		
		foreach ($availableOptions as $option) {
			if (!isset($inputOptions[$option['option_id']])) $inputOptions[$option['option_id']] = '';
			
			if ($this->_validateOption($option['option_id'], $inputOptions[$option['option_id']])) {
				$options[$option['option_id']] = $inputOptions[$option['option_id']];
			}
		}

		return $options;
	}
	
	public function doPostSave(XenForo_DataWriter $dw) {
		return false;
	}
	
	public function doPostDelete(XenForo_DataWriter $dw) {
		return false;
	}
	
	public function doInterfaceUpdate(array &$output, array $params) {
		return true;
	}
	
	abstract protected function _getPlayerTemplate();
	
	abstract protected function _renderPlayer(XenForo_Template_Abstract $template, array $game);
	
	public function renderPlayer(XenForo_View $view, array $game) {
		$templateName = $this->_getPlayerTemplate();
		$template = $view->createTemplateObject($templateName, array('game' => $game));
		return $this->_renderPlayer($template, $game);
	}
	
	public static function create($class) {
		$createClass = XenForo_Application::resolveDynamicClass($class, 'arcade');
		if (!$createClass) {
			throw new XenForo_Exception("Invalid arcade system '$class' specified");
		}

		return new $createClass;
	}
	
	protected static function _getFieldPrefix() {
		return 'options';
	}
}