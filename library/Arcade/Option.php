<?php
class Arcade_Option {
	public static function get($key) {
		switch ($key) {
			case 'doFraudCheck': return false;
			case 'gamesPerPage': return 25;
		}
		
		return XenForo_Application::get('options')->get('xfarcade_' . $key);
	}
}