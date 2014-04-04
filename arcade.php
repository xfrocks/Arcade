<?php

define('IS_ARCADE_PHP', true);
$startTime = microtime(true);

// we have to figure out XenForo path
// dirname(__FILE__) should work most of the time
// as it was the way XenForo's index.php does
// however, sometimes it may not work...
// so we have to be creative
$dirOfFile = dirname(__FILE__);
$scriptFilename = (isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
$pathToCheck = '/library/XenForo/Autoloader.php';
$fileDir = false;
if (file_exists($dirOfFile . $pathToCheck))
{
	$fileDir = $dirOfFile;
}
if ($fileDir === false AND !empty($scriptFilename))
{
	$dirOfScriptFilename = dirname($scriptFilename);
	if (file_exists($dirOfScriptFilename . $pathToCheck))
	{
		$fileDir = $dirOfScriptFilename;
	}
}
if ($fileDir === false)
{
	die('XenForo path could not be figured out...');
}

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

$fc = new XenForo_FrontController(new XenForo_Dependencies_Public());
$fc->run();