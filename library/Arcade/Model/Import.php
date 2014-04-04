<?php

class Arcade_Model_Import extends XenForo_Model
{

	public function extract(XenForo_Upload $package)
	{
		$packageName = $package->getFileName();
		$packagePath = $package->getTempFile();

		if (empty($packagePath))
		{
			throw new XenForo_Exception('Error processing uploaded file. Make sure upload_max_filesize is set properly in php.ini');
		}

		$tempDir = XenForo_Helper_File::getTempDir();
		$tempOutputDir = $tempDir . '/' . $packageName;
		do
		{
			$tempOutputDir = $tempDir . '/' . md5($tempOutputDir);
		}
		while (file_exists($tempOutputDir) OR is_dir($tempOutputDir));
		XenForo_Helper_File::createDirectory($tempOutputDir, false);
		XenForo_Helper_File::makeWritableByFtpUser($tempOutputDir);
		if (!is_dir($tempOutputDir))
		{
			throw new XenForo_Exception('Unable to create temporary output directory');
		}

		$ext = XenForo_Helper_File::getFileExtension($packageName);
		$filter = false;

		// processed extension should match list of extensions
		// in self::findPackages()
		switch ($ext)
		{
			case 'zip':
				$filter = new Zend_Filter_Decompress( array(
					'adapter' => 'Zip',
					'options' => array('target' => $tempOutputDir, )
				));
				break;
			case 'tar':
				$filter = new Zend_Filter_Decompress( array(
					'adapter' => 'Tar',
					'options' => array('target' => $tempOutputDir, )
				));
				break;
		}

		if (empty($filter))
		{
			return false;
		}

		if (!$filter->filter($packagePath))
		{
			return false;
		}

		$contents = Arcade_Helper_File::getContentsInside($tempOutputDir);
		if (count($contents) == 1 AND is_dir($contents[0]))
		{
			// special case: the package contains one directory
			// consider that one as the output directory instead
			$tempOutputDir = $contents[0];
		}

		return $tempOutputDir;
	}

	public function extractFromUrl($url)
	{
		$tempDir = XenForo_Helper_File::getTempDir();
		$tempFile = tempnam($tempDir, 'xfa');

		// TODO: use something more robust like curl?
		// file_put_contents/file_get_contents are known to fail if the
		// file is too big
		$contents = @file_get_contents($url);
		if (empty($contents))
			return false;
		@file_put_contents($tempFile, $contents);

		$upload = new XenForo_Upload(basename($url), $tempFile);
		$result = $this->extract($upload);

		// delete the temp file after extracting it
		// we do not care about successful extract or not at this point
		Arcade_Helper_File::cleanUp($tempFile);

		return $result;
	}

	public function findPackages($dir)
	{
		$packages = array();

		$files = Arcade_Helper_File::getContentsInside($dir, Arcade_Helper_File::FLAG_FILE);
		foreach ($files as $file)
		{
			$ext = XenForo_Helper_File::getFileExtension($file);
			if (in_array($ext, array(
				// list of extensions should match those in self::extract()
				'zip',
				'tar',
			)))
			{
				$packages[] = new XenForo_Upload(basename($file), $file);
			}
		}

		return $packages;
	}

	public function collectGameInfo($dir, array $gameInfo = array())
	{
		if (empty($gameInfo['slug']))
		{
			$gameInfo['slug'] = $this->_detectSlug($dir, $gameInfo);
		}

		if (empty($gameInfo['_image_path']))
		{
			$gameInfo['_image_path'] = $this->_detectImagePath($dir, $gameInfo);
		}

		if (empty($gameInfo['system_id']))
		{
			$gameInfo['system_id'] = $this->_detectSystemId($dir, $gameInfo);
		}

		if (!empty($gameInfo['system_id']))
		{
			$system = $this->getModelFromCache('Arcade_Model_System')->initSystem($gameInfo['system_id']);
			if (!empty($system))
			{
				$system->detectGameOptions($dir, $gameInfo);
			}
		}

		return $gameInfo;
	}

	protected function _detectSlug($dir, array &$gameInfo)
	{
		$slug = false;

		$files = Arcade_Helper_File::getContentsInside($dir, Arcade_Helper_File::FLAG_FILE);
		foreach ($files as $file)
		{
			$fileName = basename($file);

			$ext = XenForo_Helper_File::getFileExtension($fileName);
			if ($ext === 'swf')
			{
				$slug = substr($fileName, 0, -4);
				break;
			}
		}

		return $slug;
	}

	protected function _detectImagePath($dir, array &$gameInfo)
	{
		$imagePath = false;
		$imageSize = 0;

		$files = Arcade_Helper_File::getContentsInside($dir, Arcade_Helper_File::FLAG_FILE);
		foreach ($files as $file)
		{
			$fileName = basename($file);

			$ext = XenForo_Helper_File::getFileExtension($fileName);
			if (in_array($ext, array(
				'gif',
				'jpg',
				'jpeg',
				'png',
			)))
			{
				$fileSize = filesize($file);
				if ($fileSize > $imageSize)
				{
					$imagePath = $file;
					$imageSize = $fileSize;
				}
			}
		}

		return $imagePath;
	}

	protected function _detectSystemId($dir, array &$gameInfo)
	{
		$mochiMetadataFilePath = Arcade_Helper_File::buildPath($dir, '__metadata__.json');
		if (file_exists($mochiMetadataFilePath))
		{
			return 'mochi';
		}

		$childDirs = Arcade_Helper_File::getContentsInside($dir, Arcade_Helper_File::FLAG_DIRECTORY);
		foreach ($childDirs as $childDir)
		{
			$childDirName = basename($childDir);

			if ($childDirName === 'gamedata')
			{
				// game package contains gamedata directory?
				// this must be IPB game
				return 'ipb';
			}
		}

		if (!empty($gameInfo['slug']))
		{
			// it looks like all core game package contains an install file in the format
			// '{$slug}.game.php', feel free to remove this detecting routine if it gives
			// true negative results
			$installFilePath = Arcade_Helper_File::buildPath($dir, $gameInfo['slug'] . '.game.php');
			if (file_exists($installFilePath))
			{
				return 'core';
			}
		}

		// add-on that extends this method may want to catch return value === false
		// to check for other game systems
		return false;
	}

}
