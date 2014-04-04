<?php

class Arcade_Helper_File
{

	const FLAG_FILE = 1;
	const FLAG_DIRECTORY = 2;
	const FLAG_FILE_OR_DIRECTORY = 3;

	public static function prepareDirectoryForFile($filePath)
	{
		if (empty($filePath))
		{
			return false;
		}

		$dir = dirname($filePath);

		if (is_dir($dir))
		{
			if (is_writable($dir))
			{
				return true;
			}
			else
			{
				throw new XenForo_Exception(sprintf('Directory exists and not writable for files: %s', $dir));
			}
		}

		if (XenForo_Helper_File::createDirectory($dir, true))
		{
			XenForo_Helper_File::makeWritableByFtpUser($dir);

			return true;
		}
		else
		{
			throw new XenForo_Exception('Unable to create directory for files');
		}
	}

	public static function moveFile($from, $to)
	{
		if (empty($from) OR empty($to))
		{
			return false;
		}

		if (!is_readable($from))
		{
			throw new XenForo_Exception(sprintf('Unable to read file: %s', $from));
		}

		if (!self::prepareDirectoryForFile($to))
		{
			throw new XenForo_Exception(sprintf('Unable to prepare directory for file: %s', $to));
		}

		@unlink($to);

		if (file_exists($to))
		{
			throw new XenForo_Exception(sprintf('Unable to delete file for new content: %s', $to));
		}

		@rename($from, $to);

		if (!file_exists($to))
		{
			throw new XenForo_Exception(sprintf('Unable to move file from %s to %s', $from, $to));
		}

		XenForo_Helper_File::makeWritableByFtpUser($to);
		return true;
	}

	public static function cleanUp($path)
	{
		if (empty($path))
		{
			return true;
		}

		if (is_dir($path))
		{
			// delete this directory and contents
			$contents = self::getContentsInside($path);

			foreach ($contents as $content)
			{
				// call this method recursively
				// to delete the directory's contents
				self::cleanUp($content);
			}

			// remove the directory, ignoring errors
			@rmdir($path);
		}
		elseif (file_exists($path))
		{
			// delete the file, ignoring errors
			@unlink($path);
		}
		else
		{
			return false;
		}
	}

	public static function getContentsInside($dir, $flag = self::FLAG_FILE_OR_DIRECTORY)
	{
		$contents = array();

		if (!empty($dir) AND is_dir($dir) AND is_readable($dir))
		{
			$contentsAll = glob(self::buildPath($dir, '*'), GLOB_MARK);

			if ($flag == self::FLAG_FILE_OR_DIRECTORY)
			{
				$contents = $contentsAll;
			}
			else
			{
				foreach ($contentsAll as $path)
				{
					if (($flag & self::FLAG_FILE) AND is_file($path))
					{
						$contents[] = $path;
					}

					if (($flag & self::FLAG_DIRECTORY) AND is_dir($path))
					{
						$contents[] = $path;
					}
				}
			}
		}

		return $contents;
	}

	public static function buildPath($dir, $fileName)
	{
		$dir = utf8_rtrim($dir, '/');
		if (!empty($dir))
		{
			$dir .= '/';
		}

		return $dir . $fileName;
	}

}
