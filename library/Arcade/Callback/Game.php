<?php

/**
 * Handles game postback events
 *
 */
class Arcade_Callback_Game
{

	protected $_request;
	protected $_input;
	protected $_filtered = null;

	public function initCallbackHandling(Zend_Controller_Request_Http $request)
	{

		$this->_request = $request;
		$this->_input = new XenForo_Input($request);

		$this->_filtered = $this->_input->filter(array(
			'sessdo' => XenForo_Input::STRING,
			'gamename' => XenForo_Input::STRING
		));
	}

	public function actionCoreSessionStart()
	{
		// a game has just been started: save an empty session record
		// $this->_assertPostOnly();

		$slug = $this->_filtered['gamename'];
		$game = $this->_getGameOrError($slug);

		$sessionId = $this->_getSessionModel()->saveSession($game, XenForo_Visitor::getInstance()->toArray(), array(
			'time_start' => XenForo_Application::$time,
			'type' => 1,
			'challenge_id' => 0,
		));

		echo '&connStatus=1&initbar=' . rand(1, 10) . '&gametime=' . XenForo_Application::$time . '&lastid=' . $sessionId . '&result=OK';
		echo '<META HTTP-EQUIV=Refresh CONTENT="0; URL=' . XenForo_Link::buildPublicLink('full:arcade') . '>';
		exit ;
	}

	public function processTransaction()
	{

		if (!empty($this->_filtered['sessdo']))
		{
			/* manually reroute */
			switch ($this->_filtered['sessdo'])
			{
				case 'sessionstart':
					return actionCoreSessionStart();
				case 'permrequest':
					return $this->responseReroute('Arcade_ControllerPublic_Arcade', 'CorePermRequest');
				case 'burn':
					return $this->responseReroute('Arcade_ControllerPublic_Arcade', 'CoreBurn');
				default:
					return $this->responseNoPermission();
			}
		}

		return array(
			'info',
			'OK, no action'
		);

	}

}
