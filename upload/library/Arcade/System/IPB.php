<?php
class Arcade_System_IPB extends Arcade_System_Abstract {
	const DATA_WRITER_FILES_EXTRA_DATA_KEY = 'uploadedFiles';
	
	protected $_customizedOptionsTemplate = 'arcade_system_ipb_options';
	protected $_playerTemplate = 'arcade_player_ipb';
	
	public function renderCustomizedOptions(XenForo_View $view, array $game) {
		$template = parent::renderCustomizedOptions($view, $game);
		
		if ($template instanceof XenForo_Template_Abstract) {
			if (!empty($game['system_options'])) {
				$options = $game['system_options'];
				if (!is_array($options)) $options = unserialize($options);				
			} else {
				$options = array();
			}
			
			if (!empty($options['files'])) {
				$template->setParam('additionalFiles', implode(', ', array_keys($options['files'])));
			}
		}
		
		return $template;
	}
	
	public function processOptionsInput(XenForo_Input $inputObject, array $game, XenForo_DataWriter $dw) {
		$options = parent::processOptionsInput($inputObject, $game, $dw);
		
		$file = XenForo_Upload::getUploadedFile('file');
		if (!empty($file)) {
			$fileName = $file->getFileName();
			
			if (empty($options['files'])) {
				$options['files'] = array();
			}
			
			$options['files'][$fileName] = XenForo_Application::$time;
			$dw->setExtraData(self::DATA_WRITER_FILES_EXTRA_DATA_KEY, array($file));
		}
		
		return $options;
	}
	
	public function doPostSave(XenForo_DataWriter $dw) {
		parent::doPostSave($dw);
		
		$data = $dw->getMergedData();
		$existingData = $dw->getMergedExistingData();
		
		$files = $dw->getExtraData(self::DATA_WRITER_FILES_EXTRA_DATA_KEY);
		if (!empty($files)) {
			foreach ($files as $file) {
				$fileName = $file->getFileName();
				$filePath = $this->getAdditionalFilePath($data, $fileName);
				$directory = dirname($filePath);
	
				if (XenForo_Helper_File::createDirectory($directory, true) && is_writable($directory)) {
					if (file_exists($filePath)) {
						unlink($filePath);
					}
					
					$success = @rename($file->getTempFile(), $filePath);
					if ($success) {
						XenForo_Helper_File::makeWritableByFtpUser($filePath);
					}
				}
			}
		} elseif ($dw->isChanged('slug') AND $dw->isUpdate()) {
			$existingOptions = $existingData['system_options'];
			if (!is_array($existingOptions)) $existingOptions = @unserialize($existingOptions);
			
			if (!empty($existingOptions['files'])) {
				foreach ($existingOptions['files'] as $fileName => $fileDate) {
					$oldFilePath = $this->getAdditionalFilePath($existingData, $fileName);
					$newFilePath = $this->getAdditionalFilePath($data, $fileName);
					if (!empty($oldFilePath) AND !empty($newFilePath)) @rename($oldFilePath, $newFilePath);
				}
			}
		}
	}
	
	public function doPostDelete(XenForo_DataWriter $dw) {
		parent::doPostDelete($dw);
		
		$existingData = $dw->getMergedExistingData();
		$existingOptions = $existingData['system_options'];
		if (!is_array($existingOptions)) $existingOptions = @unserialize($existingOptions);
		
		if (!empty($existingOptions['files'])) {
			foreach ($existingOptions['files'] as $fileName => $fileDate) {
				$oldFilePath = $this->getAdditionalFilePath($existingData, $fileName);
				@unlink($oldFilePath);
			}
		}
	}
	
	public function doInterfaceUpdate(array &$output, array $params) {
		$options = $params['game']['system_options'];
		if (!is_array($options)) $options = unserialize($options);
		
		if (!empty($options['files'])) {
			$output['updated']['fileHtml'] = implode(', ', array_keys($options['files']));
		} else {
			$output['updated']['fileHtml'] = '';
		}
		
		return parent::doInterfaceUpdate($output, $params);
	}
	
	public static function getAdditionalFilePath(array $game, $fileName) {
		if (empty($game['game_id'])) return '';
		
		$gameId = $game['game_id'];
		$group = floor($gameId / 100);
		$slug = $game['slug'];
		$options = $game['system_options'];
		if (!is_array($options)) $options = unserialize($options);
		
		if (!empty($options['files'][$fileName])) {
			$fileDate = $options['files'][$fileName];
		} else {
			$fileDate = 0;
		}
		
		if ($fileDate > 0) {
			return XenForo_Helper_File::getExternalDataPath() . "/games/$group/{$slug}_$fileName";
		} else {
			return '';
		}
	}
}