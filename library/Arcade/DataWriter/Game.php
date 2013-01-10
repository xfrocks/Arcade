<?php
class Arcade_DataWriter_Game extends XenForo_DataWriter {
	const IMAGE_UPLOADED = 'imageUploaded';
	
	protected static $_imageSizes = array(
		'x' => 192,
		'l' => 96,
		'm' => 48,
		's' => 24
	); 
	public static $imageQuality = 85;
	
	protected function _getFields() {
		return array(
			'xf_arcade_game' => array(
				'game_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'slug' => array('type' => self::TYPE_STRING, 'require' => true, 'verification' => array('$this', '_verifySlug'), 'maxLength' => 50),
				'title' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
				'description' => array('type' => self::TYPE_STRING),
				'instruction' => array('type' => self::TYPE_STRING),
				'system_id' => array('type' => self::TYPE_STRING, 'required'=> true, 'verification' => array('$this', '_verifySystemId'), 'maxLength' => 30),
				'system_options' => array('type' => self::TYPE_SERIALIZED),
				'category_id' => array('type' => self::TYPE_UINT, 'required'=> true, 'verification' => array('$this', '_verifyCategoryId')),
				'image_date' => array('type' => self::TYPE_UINT, 'default' => 0),
				'options' => array('type' => self::TYPE_SERIALIZED),
				'reversed_scoring' => array('type' => self::TYPE_UINT, 'default' => 0),
				'active' => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'game_id')) {
			return false;
		}

		return array('xf_arcade_game' => $this->_getGameModel()->getGameById($id));
	}

	protected function _getUpdateCondition($tableName) {
		return 'game_id = ' . $this->_db->quote($this->getExisting('game_id'));
	}
	
	public function addImage(XenForo_Upload $upload) {
		if (!$upload->isValid()) {
			throw new XenForo_Exception($upload->getErrors(), true);
		}

		if (!$upload->isImage()) {
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		};

		$imageType = $upload->getImageInfoField('type');
		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG))) {
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$this->setExtraData(self::IMAGE_UPLOADED, $this->_prepareImage($upload));
		$this->set('image_date', XenForo_Application::$time);
	}
	
	protected function _prepareImage(XenForo_Upload $upload) {
		$outputFiles = array();
		$fileName = $upload->getTempFile();
		$imageType = $upload->getImageInfoField('type');
		$outputType = $imageType;
		$width = $upload->getImageInfoField('width');
		$height = $upload->getImageInfoField('height');

		reset(self::$_imageSizes);
		list($sizeCode, $maxDimensions) = each(self::$_imageSizes);

		$shortSide = ($width > $height ? $height : $width);

		if ($shortSide > $maxDimensions) {
			$newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfa');
			$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
			$image->thumbnailFixedShorterSide($maxDimensions);
			
			/*
			if ($image->getOrientation() != XenForo_Image_Abstract::ORIENTATION_SQUARE) {
				$x = floor(($image->getWidth() - $maxDimensions) / 2);
				$y = floor(($image->getHeight() - $maxDimensions) / 2);
				$image->crop($x, $y, $maxDimensions, $maxDimensions);
			}
			*/
			
			$image->output($outputType, $newTempFile, self::$imageQuality);

			$width = $image->getWidth();
			$height = $image->getHeight();

			$outputFiles[$sizeCode] = $newTempFile;
		}
		else
		{
			$outputFiles[$sizeCode] = $fileName;
		}

		while (list($sizeCode, $maxDimensions) = each(self::$_imageSizes)) {
			$newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfa');
			$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
			if (!$image) {
				continue;
			}

			$image->thumbnailFixedShorterSide($maxDimensions);

			if ($image->getOrientation() != XenForo_Image_Abstract::ORIENTATION_SQUARE) {
				$x = floor(($image->getWidth() - $maxDimensions) / 2);
				$y = floor(($image->getHeight() - $maxDimensions) / 2);
				$image->crop($x, $y, $maxDimensions, $maxDimensions);
			}

			$image->output($outputType, $newTempFile, self::$imageQuality);
			unset($image);

			$outputFiles[$sizeCode] = $newTempFile;
		}

		if (count($outputFiles) != count(self::$_imageSizes)) {
			foreach ($outputFiles AS $tempFile) {
				if ($tempFile != $fileName) {
					@unlink($tempFile);
				}
			}
			throw new XenForo_Exception('Non-image passed in to _prepareImage');
		}
		
		return $outputFiles;
	}
	
	protected function _moveImages($uploaded) {
		if (is_array($uploaded)) {
			$data = $this->getMergedData();
			$gameModel = $this->_getGameModel();
			foreach ($uploaded as $sizeCode => $tempFile) {
				$filePath = $gameModel->getImageFilePath($data, $sizeCode);
				$directory = dirname($filePath);
 
				if (XenForo_Helper_File::createDirectory($directory, true) && is_writable($directory)) {
					if (file_exists($filePath)) {
						unlink($filePath);
					}
					
					$success = @rename($tempFile, $filePath);
					if ($success) {
						XenForo_Helper_File::makeWritableByFtpUser($filePath);
					}
				}
			}
		}
	}
	
	protected function _preSave() {
		if ($this->get('slug') && ($this->isChanged('slug'))) {
			$conflict = $this->_getGameModel()->getGameBySlug($this->get('slug'));
			if ($conflict && $conflict['game_id'] != $this->get('game_id')) {
				$this->error(new XenForo_Phrase('arcade_slug_must_be_unique'), 'slug');
			}
		}
	} 
	
	protected function _postSave() {
		$uploaded = $this->getExtraData(self::IMAGE_UPLOADED);
		if ($uploaded) {
			$this->_moveImages($uploaded);

			if ($this->isUpdate()) {
				/* remove old image */
				$existingData = $this->getMergedExistingData();
				foreach (array_keys(self::$_imageSizes) as $sizeCode) {
					$filePath = Arcade_Model_Game::getImageFilePath($existingData, $sizeCode);
					if (!empty($filePath)) @unlink($filePath);
				}
			}
		} elseif ($this->isChanged('slug') AND $this->isUpdate()) {
			/* update image */
			$existingData = $this->getMergedExistingData();
			$data = $this->getMergedData();
			$gameModel = $this->_getGameModel();
			foreach (array_keys(self::$_imageSizes) as $sizeCode) {
				$oldFilePath = Arcade_Model_Game::getImageFilePath($existingData, $sizeCode);
				$newFilePath = Arcade_Model_Game::getImageFilePath($data, $sizeCode);
				if (!empty($oldFilePath) AND !empty($newFilePath)) @rename($oldFilePath, $newFilePath);
			}
		}
	}
	
	protected function _postSaveAfterTransaction() {
		$system = $this->_getSystemModel()->initSystem($this->get('system_id'));
		$system->doPostSave($this);
	}
	
	protected function _postDelete() {
		$existingData = $this->getMergedExistingData();
		foreach (array_keys(self::$_imageSizes) as $sizeCode) {
			$filePath = Arcade_Model_Game::getImageFilePath($existingData, $sizeCode);
			@unlink($filePath);
		}
		
		$system = $this->_getSystemModel()->initSystem($this->get('system_id'));
		$system->doPostDelete($this);
	}

	protected function _verifySlug(&$data) {
		if (empty($data)) {
			$data = null;
			return true;
		}

		if (!preg_match('/^[a-z0-9_\-]+$/i', $data)) {
			$this->error(new XenForo_Phrase('please_enter_slug_using_alphanumeric'), 'slug');
			return false;
		}

		if ($data === strval(intval($data)) || $data == '-') {
			$this->error(new XenForo_Phrase('arcade_slugs_contain_more_numbers_hyphen'), 'slug');
			return false;
		}

		return true;
	}
	
	protected function _verifySystemId(&$data) {
		if (empty($data)) {
			$this->error(new XenForo_Phrase('arcade_each_game_must_be_in_a_system'), 'system_id');
			return false;
		}
		
		$systemModel = $this->_getSystemModel();
		$system = $systemModel->getSystemById($data);
		
		if (empty($system)) {
			$this->error(new XenForo_Phrase('arcade_specified_system_not_found_x', array('system_id' => $data)), 'system_id');
			return false;
		}
		
		return true;
	}
	
	protected function _verifyTarget(&$data) {
		// TODO: validate here?
		return true;
	}
	
	protected function _verifyCategoryId(&$data) {
		if (empty($data)) {
			$this->error(new XenForo_Phrase('arcade_each_game_must_be_in_a_category'), 'category_id');
			return false;
		}
		
		$categoryModel = $this->_getCategoryModel();
		$category = $categoryModel->getCategoryById($data);
		
		if (empty($category)) {
			$this->error(new XenForo_Phrase('arcade_invalid_category'), 'category_id');
			return false;
		}
		
		return true;
	}
	
	protected function _getGameModel() {
		return $this->getModelFromCache('Arcade_Model_Game');
	}
	
	protected function _getSystemModel() {
		return $this->getModelFromCache('Arcade_Model_System');
	}
	
	protected function _getCategoryModel() {
		return $this->getModelFromCache('Arcade_Model_Category');
	}
}