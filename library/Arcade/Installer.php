<?php
class Arcade_Installer {
	public static function install($existingAddOn) {
		$db = XenForo_Application::get('db');
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_arcade_category` (
				`category_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				`title` varchar(50) NOT NULL,
				`display_order` INT(10) UNSIGNED DEFAULT 0,
				`active` TINYINT(3) UNSIGNED DEFAULT 1,
				PRIMARY KEY (`category_id`),
				UNIQUE KEY `title` (`title`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_arcade_game` (
				`game_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				`slug` varchar(50) NOT NULL,
				`title` varchar(50) NOT NULL,
				`description` TEXT,
				`instruction` TEXT,
				`system_id` VARCHAR(30) NOT NULL,
				`system_options` MEDIUMBLOB,
				`category_id` INT(10) UNSIGNED NOT NULL,
				`image_date` INT(10) UNSIGNED DEFAULT 0,
				`highscore` FLOAT(15, 3) UNSIGNED DEFAULT '0.000',
				`highscore_user_id` INT(10) UNSIGNED DEFAULT 0,
				`highscore_username` VARCHAR(50) DEFAULT '',
				`highscore_date` INT(10) UNSIGNED DEFAULT 0,
				`play_count` INT(10) UNSIGNED DEFAULT 0,
				`vote_up` INT(10) UNSIGNED DEFAULT 0,
				`vote_down` INT(10) UNSIGNED DEFAULT 0,
				`options` MEDIUMBLOB,
				`reversed_scoring` TINYINT(3) UNSIGNED DEFAULT 0,
				`active` TINYINT(3) UNSIGNED DEFAULT 1,
				PRIMARY KEY (`game_id`),
				UNIQUE KEY `slug` (`slug`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_arcade_game_play` (
				`game_play_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				`game_id` INT(10) UNSIGNED NOT NULL,
				`user_id` INT(10) UNSIGNED NOT NULL,
				`last_date` INT(10) UNSIGNED NOT NULL,
				`best_score` FLOAT(15, 3) UNSIGNED DEFAULT '0.000',
				`best_rank` INT(10) UNSIGNED DEFAULT 0,
				PRIMARY KEY (`game_play_id`),
				UNIQUE KEY `game_id_user_id` (`game_id`, `user_id`),
				KEY `best_rank` (`best_rank`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_arcade_session` (
				`session_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				`game_id` INT(10) UNSIGNED NOT NULL,
				`user_id` INT(10) UNSIGNED NOT NULL,
				`username` VARCHAR(50) NOT NULL,
				`time_start` INT(10) UNSIGNED NOT NULL,
				`time_finish` INT(10) UNSIGNED DEFAULT 0,
				`ping` float(7, 2) UNSIGNED DEFAULT '0.00',
				`valid` TINYINT(3) UNSIGNED DEFAULT 0,
				`score` FLOAT(15, 3) UNSIGNED DEFAULT '0.000',
				`type` TINYINT(3) UNSIGNED NOT NULL,
				`challenge_id` INT(10) UNSIGNED DEFAULT 0,
				`tour_id` INT(10) UNSIGNED DEFAULT 0, 
				PRIMARY KEY (`session_id`),
				KEY `game_id` (`game_id`),
				KEY `user_id` (`user_id`),
				KEY `time_finish` (`time_finish`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_arcade_vote` (
				`vote_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				`game_id` INT(10) UNSIGNED NOT NULL,
				`user_id` INT(10) UNSIGNED NOT NULL,
				`vote_date` INT(10) UNSIGNED NOT NULL,
				`vote_up` TINYINT(3) UNSIGNED NOT NULL,
				PRIMARY KEY (`vote_id`),
				UNIQUE KEY `game_id_user_id` (`game_id`, `user_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		self::_installDemoData($db);
	}
	
	public static function uninstall($addonInfo) {
		$db = XenForo_Application::get('db');
		
		$db->query('DROP TABLE `xf_arcade_category`');
		$db->query('DROP TABLE `xf_arcade_game`');
		$db->query('DROP TABLE `xf_arcade_game_play`');
		$db->query('DROP TABLE `xf_arcade_session`');
		$db->query('DROP TABLE `xf_arcade_vote`');
	}
	
	protected static function _installDemoData($db) {
		$category = $db->fetchOne("SELECT COUNT(*) FROM `xf_arcade_category`");
		$game = $db->fetchOne("SELECT COUNT(*) FROM `xf_arcade_game`");
		
		if (empty($category) AND empty($game)) {
			$categories = array(
				'Puzzle',
				'Action',
				'Retro',
				'Sport',
				'Shooters',
				'Other'
			);
			
			foreach ($categories as $category) {
				$dw = XenForo_DataWriter::create('Arcade_DataWriter_Category');
				$dw->set('title', $category);
				$dw->save();
				$lastCategory = $dw->getMergedData();
			}
			
			$demoPath = dirname(__FILE__) . '/_demo';
			self::_installDemoCoreData($demoPath . '/core', $lastCategory);
			self::_installDemoIpbData( $demoPath . '/ipb', $lastCategory);
		}
	}
	
	protected static function _installDemoCoreData($path, $category) {
		if (is_dir($path)) {
			$dh = opendir($path);
			while ($file = readdir($dh)) {
				if ($file != '.' AND $file != '..') {
					$ext = XenForo_Helper_File::getFileExtension($file);
					if ($ext == 'swf') {
						$slug = substr($file, 0, -1 * strlen($ext) - 1);
						$imageFile = $slug . '.gif';
						$imagePath = $path . '/' . $imageFile;
						if (file_exists($imagePath)) {
							// we have both swf and gif 
							// let install the game
							$dw = XenForo_DataWriter::create('Arcade_DataWriter_Game');
							$dw->bulkSet(array(
								'slug' => $slug,
								'title' => ucwords($slug),
								'category_id' => $category['category_id'],
								'system_id' => 'core',
							));
							
							$tmpImagePath = XenForo_Helper_File::getInternalDataPath() . '/' . $imageFile;
							copy($imagePath, $tmpImagePath); // we have to make a copy because XenForo_Upload will auto-delete the file
							$image = new XenForo_Upload($imageFile, $tmpImagePath);
							$dw->addImage($image);
							
							$dw->set('system_options', array(
								'width' => 550,
								'height' => 400,
								'target_date' => XenForo_Application::$time,
							));
							
							$tmpPath = XenForo_Helper_File::getInternalDataPath() . '/' . $file;
							copy($path . '/' . $file, $tmpPath); // we have to make a copy because XenForo_Upload will auto-delete the file
							$target = new XenForo_Upload($file, $tmpPath);
							$dw->setExtraData(Arcade_System_Abstract::DATA_WRITER_TARGET_EXTRA_DATA_KEY, $target);
							
							$dw->save();
						}
					}
				}
			}
			closedir($dh);
		}
	}
	
	protected static function _installDemoIpbData($path, $category) {
		if (is_dir($path)) {
			$dh = opendir($path);
			while ($file = readdir($dh)) {
				if ($file != '.' AND $file != '..') {
					$filePath = $path . '/' . $file;
					$ext = XenForo_Helper_File::getFileExtension($file);
					if ($ext == 'swf') {
						$slug = substr($file, 0, -1 * strlen($ext) - 1);
						$imageFile = $slug . '.gif';
						$imagePath = $path . '/' . $imageFile;
						if (file_exists($imagePath)) {
							// we have both swf and gif 
							// let install the game
							$dw = XenForo_DataWriter::create('Arcade_DataWriter_Game');
							$dw->bulkSet(array(
								'slug' => $slug,
								'title' => ucwords($slug),
								'category_id' => $category['category_id'],
								'system_id' => 'ipb',
							));
							
							$tmpImagePath = XenForo_Helper_File::getInternalDataPath() . '/' . $imageFile;
							copy($imagePath, $tmpImagePath); // we have to make a copy because XenForo_Upload will auto-delete the file
							$image = new XenForo_Upload($imageFile, $tmpImagePath);
							$dw->addImage($image);
							
							$systemOptions = array(
								'width' => 550,
								'height' => 400,
								'target_date' => XenForo_Application::$time,
								'files' => array(),
							);
							
							$tmpPath = XenForo_Helper_File::getInternalDataPath() . '/' . $file;
							copy($filePath, $tmpPath); // we have to make a copy because XenForo_Upload will auto-delete the file
							$target = new XenForo_Upload($file, $tmpPath);
							$dw->setExtraData(Arcade_System_Abstract::DATA_WRITER_TARGET_EXTRA_DATA_KEY, $target);
							
							$gamedataPath = $path . '/' . $slug;
							if (is_dir($gamedataPath)) {
								$files = array();
								
								$dh2 = opendir($gamedataPath);
								while ($file2 = readdir($dh2)) {
									if ($file2 != '.' AND $file2 != '..') {
										$tmp2Path = XenForo_Helper_File::getInternalDataPath() . '/' . $file2;
										copy($gamedataPath . '/' . $file2, $tmp2Path); // we have to make a copy because XenForo_Upload will auto-delete the file
										$files[] = new XenForo_Upload($file2, $tmp2Path);
										$systemOptions['files'][$file2] = XenForo_Application::$time;
									}
								}
								closedir($dh2);

								$dw->setExtraData(Arcade_System_IPB::DATA_WRITER_FILES_EXTRA_DATA_KEY, $files);
							}
							
							$dw->set('system_options', $systemOptions);
							
							$dw->save();
						}
					}
				}
			}
			closedir($dh);
		}
	}
	
	protected static function _doRmRf($path) {
		if (is_dir($path)) {
			$children = array();
			
			$dh = opendir($path);
			while ($file = readdir($dh)) {
				if ($file != '.' AND $file != '..') {
					$children[] = $path . '/' . $file;
				}
			}

			var_dump($children);
			foreach ($children as $child) self::_doRmRf($child);	
		}
		
		@unlink($path);
		
		// TODO: find out why sometimes this doesn't work????
	}
}