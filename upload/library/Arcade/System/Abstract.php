<?php
abstract class Arcade_System_Abstract extends Arcade_System {
	const DATA_WRITER_TARGET_EXTRA_DATA_KEY = 'uploadedTarget';
	
	protected $_customizedOptionsTemplate = false;
	protected $_playerTemplate = false;
	
	protected function _getAvailableOptions() {
		return array( 
			array(
				'option_id' => 'width',
				'edit_format' => 'textbox',
				'data_type' => 'positive_integer',
				'default_value' => 550,
			),
			array(
				'option_id' => 'height',
				'edit_format' => 'textbox',
				'data_type' => 'positive_integer',
				'default_value' => 400,
			),
		);
	}
	
	public function renderCustomizedOptions(XenForo_View $view, array $game) {
		if ($this->_customizedOptionsTemplate) {
			$targetFound = false;
			$targetPath = $this->getTargetFilePath($game);
			if (!empty($targetPath)) {
				$targetFound = file_exists($targetPath);
			}
	
			$params = array(
				'game' => $game,
				'targetFound' => $targetFound,
				'targetPath' => $targetPath,
			);
			
			return $view->createTemplateObject($this->_customizedOptionsTemplate, $params);
		}
	}
	
	protected function _validateOption($optionId, &$optionValue) {
		return true;
	}
	
	public function processOptionsInput(XenForo_Input $inputObject, array $game, XenForo_DataWriter $dw) {
		$options = parent::processOptionsInput($inputObject, $game, $dw);
		
		$target = XenForo_Upload::getUploadedFile('target');
		if (!empty($target)) {
			$fileName = $target->getFileName();
			$ext = strtolower(substr($fileName, strrpos($fileName, '.') + 1));
			if ($ext !== 'swf') {
				throw new XenForo_Exception(new XenForo_Phrase('arcade_target_must_be_swf'), true);
			}
			
			$options['target_date'] = XenForo_Application::$time;
			$dw->setExtraData(self::DATA_WRITER_TARGET_EXTRA_DATA_KEY, $target);
		}
		
		return $options;
	}
	
	public function doPostSave(XenForo_DataWriter $dw) {
		parent::doPostSave($dw);
		
		$data = $dw->getMergedData();
		$existingData = $dw->getMergedExistingData();
		
		$target = $dw->getExtraData(self::DATA_WRITER_TARGET_EXTRA_DATA_KEY);
		if (!empty($target)) {
			$filePath = $this->getTargetFilePath($data);
			$directory = dirname($filePath);

			if (XenForo_Helper_File::createDirectory($directory, true) && is_writable($directory)) {
				if (file_exists($filePath)) {
					unlink($filePath);
				}
				
				$success = @rename($target->getTempFile(), $filePath);
				if ($success) {
					XenForo_Helper_File::makeWritableByFtpUser($filePath);
				}
			}
			
			if ($dw->isUpdate()) {
				$oldFilePath = $this->getTargetFilePath($existingData);
				if (!empty($oldFilePath) AND $oldFilePath != $filePath) @unlink($oldFilePath);
			}
		} elseif ($dw->isChanged('slug') AND $dw->isUpdate()) {
			$oldFilePath = $this->getTargetFilePath($existingData);
			$newFilePath = $this->getTargetFilePath($data);
			if (!empty($oldFilePath) AND !empty($newFilePath)) @rename($oldFilePath, $newFilePath);
		}
	}
	
	public function doPostDelete(XenForo_DataWriter $dw) {
		parent::doPostDelete($dw);
		
		$existingData = $dw->getMergedExistingData();
		$oldFilePath = $this->getTargetFilePath($existingData);
		@unlink($oldFilePath);
	}
	
	public function doInterfaceUpdate(array &$output, array $params) {
		$targetFound = false;
		$targetPath = $this->getTargetFilePath($params['game']);
		if (!empty($targetPath)) {
			$targetFound = file_exists($targetPath);
		}
		if ($targetFound) {
			$output['updated']['targetHtml'] = new XenForo_Phrase('arcade_target_found', array('target' => $targetPath));
		} else {
			$output['updated']['targetHtml'] = '';
		}
		
		
		return true;
	}
	
	protected function _getPlayerTemplate() {
		return $this->_playerTemplate;
	}
	
	protected function _renderPlayer(XenForo_Template_Abstract $template, array $game) {
		$template->setParam('targetUrl', $this->getTargetUrl($game));
		
		return $template->render();
	}
	
	protected function _getPhrase($phraseId) {
		return new XenForo_Phrase('arcade_system_' . $phraseId);
	}
	
	public static function getTargetFilePath(array $game) {
		$internal = self::_getTargetInternal($game);
		
		if (!empty($internal)) {
			return XenForo_Helper_File::getExternalDataPath() . $internal;
		} else {
			return '';
		}
	}
	
	public static function getTargetUrl(array $game) {
		$internal = self::_getTargetInternal($game);
		
		if (!empty($internal)) {
			return XenForo_Application::$externalDataPath . $internal;
		} else {
			return '';
		}
	}
	
	protected static function _getTargetInternal(array $game) {
		if (empty($game['game_id'])) return '';
		
		$gameId = $game['game_id'];
		$group = floor($gameId / 100);
		$slug = $game['slug'];
		$options = $game['system_options'];
		if (!is_array($options)) $options = unserialize($options);
		$targetDate = isset($options['target_date']) ? $options['target_date'] : 0;
		
		if ($targetDate > 0) {
			return "/games/$group/{$slug}.swf";
		} else {
			return '';
		}
	}
}