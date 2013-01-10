<?php
class Arcade_System_Core extends Arcade_System_Abstract {
	protected $_customizedOptionsTemplate = 'arcade_system_core_options';
	protected $_playerTemplate = 'arcade_player_core';
	
	public function detectGameOptions($dir, array &$gameInfo) {
		if (!isset($gameInfo['system_options'])) {
			$gameInfo['system_options'] = array();
		}
		
		if (!empty($gameInfo['slug'])) {
			$installFilePath = Arcade_Helper_File::buildPath($dir, $gameInfo['slug'] . '.game.php');
			$title = false;
			$shortname = false;
			$description = false;
			$gameWidth = false;
			$gameHeight = false;
			
			$lines = @file($installFilePath);
			if (!empty($lines)) {
				// trying to parse the install script ourselve
				// of course we can just include() or eval() it
				// but I think doing that is quite dangerous
				// plus, parsing it not very hard... Why not eh?
				foreach ($lines as $line) {
					$line = utf8_trim($line);
					if (!empty($line) AND $line[0] === '$' AND utf8_substr($line, -1) === ';' AND utf8_strpos($line, '=') !== false) {
						$parts = explode('=', utf8_substr($line, 0, -1));
						if (count($parts) == 2) {
							// the line has the format of `$var = ...;`
							switch ($parts[0]) {
								case '$title':
									$tmp = Arcade_Helper_PhpParser::parseString($parts[1]);
									if (!empty($tmp)) {
										$title = $tmp;
									}
									break;
								case '$shortname':
									$tmp = Arcade_Helper_PhpParser::parseString($parts[1]);
									if (!empty($tmp)) {
										$shortname = $tmp;
									}
									break;
								case '$description':
									$tmp = Arcade_Helper_PhpParser::parseString($parts[1]);
									if (!empty($tmp)) {
										$description = $tmp;
									}
									break;
								case '$game_width':
									$tmp = Arcade_Helper_PhpParser::parseNumber($parts[1]);
									if ($tmp > 0) {
										$gameWidth = $tmp;
									}
									break;
								case '$game_height':
									$tmp = Arcade_Helper_PhpParser::parseNumber($parts[1]);
									if ($tmp > 0) {
										$gameHeight = $tmp;
									}
									break;
								case '$secondary_swf_exist':
								case '$secondary_swf_filename':
									// TODO
									break;
							}
						}
					}
				}
				
				if (!empty($shortname) AND $shortname === $gameInfo['slug']) {
					// compare $shortname to check whether we have parsed the script correctly
					if (!empty($title) AND empty($gameInfo['title'])) {
						$gameInfo['title'] = $title;
					}
					
					if (!empty($description) AND empty($gameInfo['description'])) {
						$gameInfo['description'] = $description;
					}
					
					if (!empty($gameWidth) AND !empty($gameHeight)) {
						if (empty($gameInfo['system_options']['width'])) {
							$gameInfo['system_options']['width'] = $gameWidth;
						}
						
						if (empty($gameInfo['system_options']['height'])) {
							$gameInfo['system_options']['height'] = $gameHeight;
						}
					}
				}
			}
		}
		
		parent::detectGameOptions($dir, $gameInfo);
	}
}