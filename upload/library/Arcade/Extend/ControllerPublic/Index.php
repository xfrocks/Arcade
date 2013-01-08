<?php
class Arcade_Extend_ControllerPublic_Index extends XFCP_Arcade_Extend_ControllerPublic_Index {
	public function actionIndex() {
		if ($this->_request->getParam('autocom') == 'arcade') {
			// IPB v32
			switch ($_REQUEST['do']) {
				case 'verifyscore':
					return $this->responseReroute('Arcade_ControllerPublic_Arcade', 'IpbVerifyScore');
				case 'savescore':
					return $this->responseReroute('Arcade_ControllerPublic_Arcade', 'IpbSaveScore');
				default:
					return $this->responseNoPermission();
			}
          } elseif ($this->_request->getParam('act') == 'Arcade') {
			// IPB Legacy
			switch ($_REQUEST['do']) {
				case 'newscore':
					return $this->responseReroute('Arcade_ControllerPublic_Arcade', 'IpbSaveScoreLegacy');
				default:
					return $this->responseNoPermission();
			}
		} else {
			return parent::actionIndex();
		}
	}


	protected function _checkCsrf($action) {
		if ($this->_request->getParam('autocom') == 'arcade') {
			self::$_executed['csrf'] = true;
			return true;
          } elseif ($this->_request->getParam('act') == 'Arcade') {
			self::$_executed['csrf'] = true;
			return true;
		} else {
			return parent::_checkCsrf($action);
		}
	}
}