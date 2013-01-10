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
			$dw->setExtraData(self::DATA_WRITER_FILES_EXTRA_DATA_KEY, array($fileName => $file));
		}
		
		return $options;
	}
	
	public function detectGameOptions($dir, array &$gameInfo) {
		if (!isset($gameInfo['system_options'])) {
			$gameInfo['system_options'] = array();
		}
		
		if (!empty($gameInfo['slug'])) {
			$installFilePath = Arcade_Helper_File::buildPath($dir, $gameInfo['slug'] . '.php');
			$gname = false;
			$gtitle = false;
			$gwidth = false;
			$gheight = false;
			$gwords = false;
			
			$lines = @file($installFilePath);
			if (!empty($lines)) {
				// trying to parse the install script ourselve
				// of course we can just include() or eval() it
				// but I think doing that is quite dangerous
				// plus, parsing it not very hard... Why not eh?
				foreach ($lines as $line) {
					$line = utf8_trim($line);
					if (!empty($line) AND utf8_substr($line, -1) === ',' AND utf8_strpos($line, '=>') !== false) {
						$parts = explode('=>', utf8_substr($line, 0, -1));
						if (count($parts) == 2) {
							// the line has the format of `key => value,`
							$parts[0] = utf8_trim($parts[0]);
							
							switch ($parts[0]) {
								case 'gname':
									$tmp = Arcade_Helper_PhpParser::parseString($parts[1]);
									if (!empty($tmp)) {
										$gname = $tmp;
									}
									break;
								case 'gtitle':
									$tmp = Arcade_Helper_PhpParser::parseString($parts[1]);
									if (!empty($tmp)) {
										$gtitle = $tmp;
									}
									break;
								case 'gwidth':
									$tmp = Arcade_Helper_PhpParser::parseNumber($parts[1]);
									if ($tmp > 0) {
										$gwidth = $tmp;
									}
									break;
								case 'gheight':
									$tmp = Arcade_Helper_PhpParser::parseNumber($parts[1]);
									if ($tmp > 0) {
										$gheight = $tmp;
									}
									break;
								case 'gwords':
									$tmp = Arcade_Helper_PhpParser::parseString($parts[1]);
									if (!empty($tmp)) {
										$gwords = $tmp;
									}
									break;
								case 'highscore_type':
									// TODO
									break;
							}
						}
					}
				}
				
				if (!empty($gname) AND $gname === $gameInfo['slug']) {
					// compare $gname to check whether we have parsed the script correctly
					if (!empty($gtitle) AND empty($gameInfo['title'])) {
						$gameInfo['title'] = $gtitle;
					}
					
					if (!empty($gwords) AND empty($gameInfo['description'])) {
						$gameInfo['description'] = $gwords;
					}
					
					if (!empty($gwidth) AND !empty($gheight)) {
						if (empty($gameInfo['system_options']['width'])) {
							$gameInfo['system_options']['width'] = $gwidth;
						}
						
						if (empty($gameInfo['system_options']['height'])) {
							$gameInfo['system_options']['height'] = $gheight;
						}
					}
					
					if (empty($gameInfo['_gamedata'])) {
						// now process gamedata files, they are usually stored in
						// package/gamedata/{slug}/...
						$this->_detectGameDataFiles($dir, $gameInfo, 'gamedata/' . $gameInfo['slug']);
					}
				}
			}
		}
		
		parent::detectGameOptions($dir, $gameInfo);
	}
	
	protected function _detectGameDataFiles($dir, array &$gameInfo, $tempPathRelative, $storePathRelative = '') {
		$tempPath = Arcade_Helper_File::buildPath($dir, $tempPathRelative);
		$contents = Arcade_Helper_File::getContentsInside($tempPath);
		
		foreach ($contents as $content) {
			if (is_dir($content)) {
				$dirName = basename($content);
				
				$dirTempPathRelative = Arcade_Helper_File::buildPath($tempPathRelative, $dirName);
				$dirStorePathRelative = Arcade_Helper_File::buildPath($storePathRelative, $dirName);
				
				$this->_detectGameDataFiles($dir, $gameInfo, $dirTempPathRelative, $dirStorePathRelative);
			} else {
				$fileName = basename($content);
				
				if (in_array(utf8_strtolower($fileName), array(
					'index.html',
					'index.htm',
				))) {
					// ignore non-essential files
					continue;
				}
				
				$fileStorePathRelative = Arcade_Helper_File::buildPath($storePathRelative, $fileName);
				
				$gameInfo['_gamedata'][$content] = $fileStorePathRelative;
			}
		}
	}
	
	public function processImport($dir, array &$gameInfo, Arcade_DataWriter_Game $dw) {
		if (!empty($gameInfo['_gamedata'])) {
			$dwExtraData = array();
			
			foreach ($gameInfo['_gamedata'] as $tempPath => $storePath) {
				$fileName = basename($storePath);
				
				$file = new XenForo_Upload($fileName, $tempPath);
				
				if (empty($gameInfo['system_options']['files'])) {
					$gameInfo['system_options']['files'] = array();
				}
				$gameInfo['system_options']['files'][$storePath] = XenForo_Application::$time;
				
				$dwExtraData[$storePath] = $file;
			}
			
			if (!empty($dwExtraData)) {
				$dw->setExtraData(self::DATA_WRITER_FILES_EXTRA_DATA_KEY, $dwExtraData);
			}
		}
		
		return parent::processImport($dir, $gameInfo, $dw);
	}
	
	public function doPostSave(XenForo_DataWriter $dw) {
		parent::doPostSave($dw);
		
		$data = $dw->getMergedData();
		$existingData = $dw->getMergedExistingData();
		
		$files = $dw->getExtraData(self::DATA_WRITER_FILES_EXTRA_DATA_KEY);
		if (!empty($files)) {
			foreach ($files as $fileName => $file) {
				$filePath = $this->getAdditionalFilePath($data, $fileName);
				
				Arcade_Helper_File::moveFile(
					$file->getTempFile(),
					$filePath
				);
			}
		} elseif ($dw->isChanged('slug') AND $dw->isUpdate()) {
			$existingOptions = $existingData['system_options'];
			if (!is_array($existingOptions)) $existingOptions = @unserialize($existingOptions);
			
			if (!empty($existingOptions['files'])) {
				foreach ($existingOptions['files'] as $fileName => $fileDate) {
					$oldFilePath = $this->getAdditionalFilePath($existingData, $fileName);
					$newFilePath = $this->getAdditionalFilePath($data, $fileName);
					
					if ($oldFilePath != $newFilePath) {
						Arcade_Helper_File::moveFile($oldFilePath, $newFilePath);
					}
				}
			}
			
			$oldContainerPath = $this->_getAdditionalFileContainerPath($existingData);
			$newContainerPath = $this->_getAdditionalFileContainerPath($data);
			
			if ($oldContainerPath != $newContainerPath) {
				Arcade_Helper_File::cleanUp($oldContainerPath);
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
				$filePath = $this->getAdditionalFilePath($existingData, $fileName);
				Arcade_Helper_File::cleanUp($filePath);
			}
		}
		
		$containerPath = $this->_getAdditionalFileContainerPath($existingData);
		Arcade_Helper_File::cleanUp($containerPath);
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
	
	public static function getAdditionalUrl(array $game, $fileName) {
		$internal = self::_getAdditionalInternal($game, $fileName);
		
		if (!empty($internal)) {
			$fileExt = XenForo_Helper_File::getFileExtension($fileName);
			$internalExt = XenForo_Helper_File::getFileExtension($internal);
			
			if ($fileExt === $internalExt) {
				// check for matching file extension before generate url
				// if a file is consider dangerous, the extension will be modified
				// and direct URL is not available
				return XenForo_Application::$externalDataUrl . $internal;
			} else {
				return '';
			}
		} else {
			return '';
		}
	}
	
	public static function getAdditionalFilePath(array $game, $fileName) {
		$internal = self::_getAdditionalInternal($game, $fileName);
		
		if (!empty($internal)) {
			return XenForo_Helper_File::getExternalDataPath() . $internal;
		} else {
			return '';
		}
	}
	
	protected static function _getAdditionalInternal(array $game, $fileName) {
		if (empty($game['game_id'])) return '';
		
		$gameId = $game['game_id'];
		$group = floor($gameId / 100);
		$slug = $game['slug'];
		$options = $game['system_options'];
		if (!is_array($options)) $options = @unserialize($options);
		
		if (!empty($options['files'][$fileName])) {
			$fileDate = $options['files'][$fileName];
		} else {
			$fileDate = 0;
		}
		
		if ($fileDate > 0) {
			// .data is appended as an additional security measure
			// we shouldn't store php file directory you know
			$ext = XenForo_Helper_File::getFileExtension($fileName);
			if (!in_array($ext, array(
				'html', 'htm', 'txt', 'xml', // text files
				'gif', 'jpg', 'jpeg', 'png', // images
			))) {
				$extraExt = '.data';
			} else {
				// I would want to add .data to all files but that would break
				// existing game, this solution is quite good I think
				$extraExt = '';
			}
			
			if (utf8_strpos($fileName, '/') !== false) {
				// xfrocks@2013-01-09
				// added support for games with multiple gamedata files
				// in some directory/file structure...
				return "/games/{$group}/{$slug}_files/{$fileName}{$extraExt}";
			} else {
				return "/games/{$group}/{$slug}_{$fileName}{$extraExt}";
			}
		} else {
			return '';
		}
	}
	
	protected static function _getAdditionalFileContainerPath(array $game) {
		$fakeFileName = 'dir/file.ext';
		$fakePath = self::getAdditionalFilePath($game, $fakeFileName);
		$path = utf8_substr($fakePath, 0, -1 * utf8_strlen($fakeFileName));
		return utf8_rtrim($path, '/');
	}
}