<?php

class Arcade_System_Mochi extends Arcade_System_Abstract
{
	protected $_customizedOptionsTemplate = 'arcade_system_mochi_options';
	protected $_playerTemplate = 'arcade_player_mochi';

	const DATA_REGISTRY_ITEM_NAME = 'arcade_mochi_feed_cache';

	public function detectGameOptions($dir, array &$gameInfo)
	{
		if (!isset($gameInfo['system_options']))
		{
			$gameInfo['system_options'] = array();
		}

		$metadataFilePath = Arcade_Helper_File::buildPath($dir, '__metadata__.json');
		if (file_exists($metadataFilePath))
		{
			$contents = @file_get_contents($metadataFilePath);
			if (!empty($contents))
			{
				$metadata = @json_decode($contents, true);
				if (!empty($metadata))
				{
					if (!empty($metadata['slug']))
					{
						$gameInfo['slug'] = $metadata['slug'];
					}

					if (!empty($metadata['name']) AND empty($gameInfo['title']))
					{
						$gameInfo['title'] = $metadata['name'];
					}

					if (!empty($metadata['description']) AND empty($gameInfo['description']))
					{
						$gameInfo['description'] = $metadata['description'];
					}

					if (!empty($metadata['width']) AND !empty($metadata['height']))
					{
						if (empty($gameInfo['system_options']['width']))
						{
							$gameInfo['system_options']['width'] = $metadata['width'];
						}

						if (empty($gameInfo['system_options']['height']))
						{
							$gameInfo['system_options']['height'] = $metadata['height'];
						}
					}
				}
			}
		}

		// Mochi packages tend to contain multiple images, not all of them are suitable
		// to be used as thumbnail so we have to re-run the detect routine
		$thumbFilePath = $this->_detectImagePath($dir);
		if (!empty($thumbFilePath))
		{
			$gameInfo['_image_path'] = $thumbFilePath;
		}

		// Mochi packages sometimes contain multiple versions of the swf (different
		// languages?)
		// and most of the time the files are not named properly ({slug}.swf), we have to
		// do
		// some check first and rename as needed
		if (!empty($gameInfo['slug']))
		{
			$swfFilePath = Arcade_Helper_File::buildPath($dir, "{$gameInfo['slug']}.swf");
			if (!file_exists($swfFilePath))
			{
				// start working
				if (!empty($metadata['swf_url']))
				{
					$urlParsed = parse_url($metadata['swf_url']);
					if (!empty($urlParsed['path']))
					{
						$candidateFileName = basename($urlParsed['path']);
						$candidateFilePath = Arcade_Helper_File::buildPath($dir, $candidateFileName);

						if (file_exists($candidateFilePath))
						{
							Arcade_Helper_File::moveFile($candidateFilePath, $swfFilePath);
						}
						else
						{
							$this->_detectAndRenameSwf($dir, $swfFilePath);
						}
					}
				}
			}
		}

		parent::detectGameOptions($dir, $gameInfo);
	}

	protected function _detectImagePath($dir)
	{
		$imagePath = false;
		$imageSize = 0;
		$prefix = '_thumb_';

		$files = Arcade_Helper_File::getContentsInside($dir, Arcade_Helper_File::FLAG_FILE);
		foreach ($files as $file)
		{
			$fileName = basename($file);

			if (substr($fileName, 0, strlen($prefix)) !== $prefix)
			{
				// ignore files do not match the prefix
				continue;
			}

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

	protected function _detectAndRenameSwf($dir, $target)
	{
		$swfPath = false;
		$swfMtime = -1;

		$files = Arcade_Helper_File::getContentsInside($dir, Arcade_Helper_File::FLAG_FILE);
		foreach ($files as $file)
		{
			$fileName = basename($file);

			$ext = XenForo_Helper_File::getFileExtension($fileName);
			if ($ext === 'swf')
			{
				$mtime = filemtime($file);
				if ($swfMtime === -1 || $mtime > $swfMtime)
				{
					$swfPath = $file;
					$swfMtime = $mtime;
				}
			}
		}

		if (!empty($swfPath))
		{
			Arcade_Helper_File::moveFile($swfPath, $target);
		}
	}

	public static function getCategories()
	{
		return array(
			'action' => 'Action',
			'adventure' => 'Adventure',
			'board-game' => 'Board Game',
			'casino' => 'Casino',
			'driving' => 'Driving',
			'dress-up' => 'Dress Up',
			'fighting' => 'Fighting',
			'puzzles' => 'Puzzles',
			'customize' => 'Pimp my / Customize',
			'shooting' => 'Shooting',
			'sports' => 'Sports',
			'other' => 'Other',
			'strategy' => 'Strategy',
			'education' => 'Education',
			'rhythm' => 'Rhythm',
			'jigsaw' => 'Jigsaw / Slider Puzzles',
		);
	}

	public static function getRatings()
	{
		return array(
			'everyone' => 'Everyone',
			'teen' => 'Teen',
			'mature' => 'Mature',
		);
	}

	public static function getFeed(array $queries, $page = 1, $limit = 25)
	{
		$id = Arcade_Option::get('mochimedia_id');
		$q = implode(' and ', $queries);
		$page = max(1, $page);
		$limit = max(10, $limit);
		$dataRegistryModel = self::_getModelFromCache('XenForo_Model_DataRegistry');

		$feedUrl = sprintf('%s?partner_id=%s&q=%s&limit=%d&offset=%d', 'http://feedmonger.mochimedia.com/feeds/query/', $id, urlencode($q), $limit, ($page - 1) * $limit);

		$cached = $dataRegistryModel->get(self::DATA_REGISTRY_ITEM_NAME);
		if (!empty($cached[$feedUrl]))
		{
			$feedContents = $cached[$feedUrl];
		}
		else
		{
			$feedContents = @file_get_contents($feedUrl);
			$cached[$feedUrl] = $feedContents;
			$dataRegistryModel->set(self::DATA_REGISTRY_ITEM_NAME, $cached);
		}

		$json = @json_decode($feedContents, true);

		if (!empty($json))
		{
			if (isset($json['total']) AND isset($json['games']))
			{
				return array(
					'games' => $json['games'],
					'total' => $json['total'],

					'feedUrl' => $feedUrl,
					'limit' => $limit,
					'page' => $page,
				);
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}

}
