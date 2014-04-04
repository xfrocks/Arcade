<?php

/**
 * The primary purpose of this class is to handle requests
 * from games. They are different and... well, ugly. So I
 * decided to move them here in order to make our primary
 * controller look clean and easy to follow
 */
abstract class Arcade_ControllerPublic_ArcadeUgly extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		if (!empty($_REQUEST['sessdo']))
		{
			/* manually reroute */
			switch ($_REQUEST['sessdo'])
			{
				case 'sessionstart':
					return $this->responseReroute('Arcade_ControllerPublic_Arcade', 'CoreSessionStart');
				case 'permrequest':
					return $this->responseReroute('Arcade_ControllerPublic_Arcade', 'CorePermRequest');
				case 'burn':
					return $this->responseReroute('Arcade_ControllerPublic_Arcade', 'CoreBurn');
				default:
					return $this->responseNoPermission();
			}
		}

		return false;
	}

	public function actionCoreSessionStart()
	{
		// a game has just been started: save an empty session record
		$this->_assertPostOnly();

		$slug = $this->_input->filterSingle('gamename', XenForo_Input::STRING);
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

	public function actionCorePermRequest()
	{
		// submitting score
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'note' => XenForo_Input::UINT,
			'id' => XenForo_Input::UINT,
			'gametime' => XenForo_Input::UINT,
			'score' => XenForo_Input::FLOAT,
			'key' => XenForo_Input::UINT,
			'fakekey' => XenForo_Input::UINT,
		));

		if (empty($input['note']) OR empty($input['id']) OR empty($input['fakekey']) OR empty($input['gametime']))
		{
			// return $this->responseNoPermission();
		}

		$scoreCeil = ceil($input['score']);
		$noteId = $input['note'] / ($input['fakekey'] * $scoreCeil);

		if ($noteId != $input['id'])
		{
			/* invalidate */
			echo '&validate=0';
			// exit;
		}

		// I won't ask
		$input['score'] = max(0, $input['score']);

		$this->_getSessionModel()->updateSession(array(
			'session_id = ?' => $input['id'],
			'time_start = ?' => $input['gametime'],
			'user_id = ?' => XenForo_Visitor::getUserId(),
		), array(
			'score' => $input['score'],
			'time_finish' => XenForo_Application::$time,
		));

		echo '&validate=1&microone=' . $this->_getMicrotime(true) . '&result=OK';
		exit ;
	}

	public function actionCoreBurn()
	{
		// let's validate the score
		// $this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'id' => XenForo_Input::UINT,
			'microone' => XenForo_Input::FLOAT,
		));

		$gameModel = $this->_getGameModel();
		$sessionModel = $this->_getSessionModel();
		$visitor = XenForo_Visitor::getInstance();

		$conditions = array('session_id' => $input['id']);
		$fetchOptions = array();
		$game = $gameModel->getGame($conditions, $fetchOptions);

		$this->_assertGamePermission('play', $game);

		if (empty($game['session_id']) OR ($game['session_user_id'] != $visitor['user_id']) OR ($game['ping'] > 0 AND Arcade_Option::get('doFraudCheck')))
		{
			return $this->responseNoPermission();
		}

		$ping = sprintf('%.1f', ($this->_getMicrotime(true) - $input['microone']) / 2 * 1000);

		if ($ping > 4500 AND Arcade_Option::get('doFraudCheck'))
		{
			$valid = 0;
		}
		else
		{
			$valid = 1;
		}

		$sessionModel->updateSession($game['session_id'], array(
			'ping' => Arcade_Option::get('doFraudCheck') ? $ping : 0,
			'valid' => $valid,
		));

		if ($valid)
		{
			$gameModel->buildPlayCount($game['game_id']);

			if ($visitor['user_id'])
			{
				$gameModel->buildLatestScores();
				$gameModel->buildGamePlay($game, $visitor->toArray(), $game);
			}
		}

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('arcade'));
	}

	public function actionIpbGameData()
	{
		$gameSlug = $this->_input->filterSingle('slug', XenForo_Input::STRING);
		$gameFile = $this->_input->filterSingle('file', XenForo_Input::STRING);
		$ext = $this->_routeMatch->getResponseType();
		$fileName = "$gameFile.$ext";

		$game = $this->_getGameOrError($gameSlug);

		$url = Arcade_System_IPB::getAdditionalUrl($game, $fileName);
		if (!empty($url))
		{
			// simply redirect
			header("Location: " . XenForo_Link::convertUriToAbsoluteUri($url));
		}
		else
		{
			$filePath = Arcade_System_IPB::getAdditionalFilePath($game, $fileName);
			if (!empty($filePath) AND file_exists($filePath))
			{
				// sondh@2013-01-11
				// dirty fix to support all kind of gamedata file types
				// we can't use mime_content_type or Fileinfo because
				// the real file doesn't always have the correct extension...
				header("Content-Type: application/octet-stream");
				echo file_get_contents($filePath);
			}
			else
			{
				// TODO: error?
			}
		}

		exit ;
	}

	public function actionIpbVerifyScore()
	{
		// Get two random numbers to do our score verification
		$randomvar1 = rand(1, 25);
		$randomvar2 = rand(1, 25);
		$ttl = 500 * 60;
		// 5 minutes?

		// Bake a Cookie
		XenForo_Helper_Cookie::setCookie('xfarcade_v32_cookie', $randomvar1 . ',' . $randomvar2, $ttl);

		// Return the values
		echo '&randchar=' . $randomvar1 . '&randchar2=' . $randomvar2 . '&savescore=1&blah=OK';

		exit ;
	}

	public function actionIpbSaveScoreLegacy()
	{
		$input = $this->_input->filter(array(
			'gscore' => XenForo_Input::STRING,
			'gname' => XenForo_Input::STRING,
		));

		$game = $this->_getGameOrError($input['gname']);

		$sessionId = $this->_getSessionModel()->saveSession($game, XenForo_Visitor::getInstance()->toArray(), array(
			'time_start' => XenForo_Application::$time,
			'time_finish' => XenForo_Application::$time,
			'type' => 1,
			'challenge_id' => 0,
			'score' => $input['gscore'],
		));

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('arcade', array(), array(
			'sessdo' => 'burn',
			'id' => $sessionId,
			'microone' => $this->_getMicrotime(true),
		)));
	}

	public function actionIpbSaveScore()
	{
		$input = $this->_input->filter(array(
			'gscore' => XenForo_Input::STRING,
			'arcadegid' => XenForo_Input::INT,
			'enscore' => XenForo_Input::INT,
			'gname' => XenForo_Input::STRING,
		));

		$randomVars = explode(',', XenForo_Helper_Cookie::getCookie('xfarcade_v32_cookie'));

		if (count($randomVars) != 2 OR $input['enscore'] != ($input['gscore'] * $randomVars[0] ^ $randomVars[1]))
		{
			return $this->responseNoPermission();
		}

		$game = $this->_getGameOrError($input['gname']);

		$sessionId = $this->_getSessionModel()->saveSession($game, XenForo_Visitor::getInstance()->toArray(), array(
			'time_start' => XenForo_Application::$time,
			'time_finish' => XenForo_Application::$time,
			'type' => 1,
			'challenge_id' => 0,
			'score' => $input['gscore'],
		));

		XenForo_Helper_Cookie::setCookie('xfarcade_v32_cookie', '', 123);

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('arcade', array(), array(
			'sessdo' => 'burn',
			'id' => $sessionId,
			'microone' => $this->_getMicrotime(true),
		)));
	}

	protected function _checkCsrf($action)
	{
		if (strtolower($action) === 'index')
		{
			/* bypass CSRF check for entry point */
			self::$_executed['csrf'] = true;
			return true;
		}
		else
		{
			return parent::_checkCsrf($action);
		}
	}

	protected function _getMicrotime($getAsFloat = false)
	{
		// TODO: support all system?
		return microtime($getAsFloat);
	}

}
