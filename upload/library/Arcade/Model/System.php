<?php
class Arcade_Model_System extends XenForo_Model {
	protected static $_systems = array(
		array(
			'system_id' => 'core',
			'class' => 'Arcade_System_Core',
			'name' => 'Core',
		),
		array(
			'system_id' => 'ipb',
			'class' => 'Arcade_System_IPB',
			'name' => 'IPB',
		)
	);
	
	public function getSystems() {
		$systems = array();
		
		foreach (self::$_systems as $system) {
			$systems[$system['system_id']] = $system;
		}
		
		return $systems;
	}
	
	public function getSystemById($systemId) {
		foreach (self::$_systems as $system) {
			if ($system['system_id'] == $systemId) {
				return $system;
			}
		}
		
		return false;
	}
	
	public function initSystem($systemId) {
		$system = $this->getSystemById($systemId);
		
		if ($system) {
			return Arcade_System::create($system['class']);
		}
		
		return false;
	}
}