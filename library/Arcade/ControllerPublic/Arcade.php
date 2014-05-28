<?php

class Arcade_ControllerPublic_Arcade extends Arcade_ControllerPublic_ArcadeUgly
{

	public static function getSessionActivityDetailsForList(array $activities)
	{

		$output = array();
		foreach ($activities AS $key => $activity)
		{

			// Default the link to the main Arcade page
			$viewLink = XenForo_Link::buildPublicLink('arcade');

			// Determine where the user is within the Arcade
			$action = $activity['controller_action'];
			switch ($action)
			{
				case 'Index':
					// Viewing the main Arcade page
					$output[$key] = array(
						new XenForo_Phrase('arcade_status_viewing'),
						new XenForo_Phrase('arcade'),
						$viewLink,
						''
					);
					break;

				case 'Play':
					// Playing a game
					$gameDetails = array();
					$gameDetails['title'] = new XenForo_Phrase('arcade_play_game');
					if ($activity['params'])
					{
						if ($activity['params']['id'])
						{
							$gameModel = Arcade_Model_Game::create('Arcade_Model_Game');
							$game = $gameModel->getGameById($activity['params']['id'], array('image' => '0', ));
							$gameDetails['id'] = $activity['params']['id'];
							$gameDetails['title'] = $game['title'];
							$viewLink = XenForo_Link::buildPublicLink('arcade/play', $gameDetails);
						}
					}
					$output[$key] = array(
						new XenForo_Phrase('arcade_status_playing'),
						$gameDetails['title'],
						$viewLink,
						''
					);
					break;

				case 'Browse':
					// Browsing a game category
					$categoryDetails = array();
					$categoryDetails['title'] = new XenForo_Phrase('browse') . ' ' . new XenForo_Phrase('arcade_category');
					if ($activity['params'])
					{
						if ($activity['params']['id'])
						{
							$categoryDetails['category_id'] = $activity['params']['id'];
							$viewLink = XenForo_Link::buildPublicLink('arcade/browse', $categoryDetails);
						}
					}
					$output[$key] = array(
						new XenForo_Phrase('arcade_status_viewing_category'),
						$categoryDetails['title'],
						$viewLink,
						''
					);
					break;

				default:
					$output[$key] = new XenForo_Phrase('arcade_status_viewing');
			}
		}

		return $output;
	}

	public function actionIndex()
	{
		$response = parent::actionIndex();
		$options = XenForo_Application::get('options');

		$this->_checkView();
		// Do they have permission to view/browse games?

		$canVote = $this->_checkVote();

		if ($response === false)
		{
			//			$this->_assertGamePermission('browse');

			// Is a game ID being specified?
			$gameId = $this->_input->filterSingle('id', XenForo_Input::UINT);
			if ($gameId)
			{
				return $this->responseReroute(__CLASS__, 'play');
			}

			$gameModel = $this->_getGameModel();
			$categoryModel = $this->_getCategoryModel();

			$categories = $categoryModel->getCategories(0);

			$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
			$allGamesPage = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
			$gamesPerPage = Arcade_Option::get('gamesPerPage');
			$allGamesCondition = array();
			$allGamesFetchOptions = $this->_getGameFetchOptions(array(
				'order' => $order,
				'direction' => 'desc',
				'page' => $allGamesPage,
				'limit' => $gamesPerPage,
			));
			$allGames = $gameModel->getGames($allGamesCondition, $allGamesFetchOptions);
			$allGamesTotal = $gameModel->countGames($allGamesCondition, $allGamesFetchOptions);

			//			$newGames = $gameModel->getGames(array(), $this->_getGameFetchOptions(array(
			//				'order' => 'game_id',
			//				'direction' => 'desc',
			//			)));
			//			$randomGames = $gameModel->getGames(array(),
			// $this->_getGameFetchOptions(array(
			//				'order' => 'random',
			//			)));

			$viewParams = array(
				'order' => $order,
				'selectedCategoryId' => '',
				'categories' => $categories,
				'allGames' => $allGames,
				//				'newGames' => $newGames,
				//				'randomGames' => $randomGames,
				'canVote' => $canVote,

				'gamesPerPage' => $gamesPerPage,
				'allGamesPage' => $allGamesPage,
				'allGamesStartOffset' => ($allGamesPage - 1) * $gamesPerPage + 1,
				'allGamesEndOffset' => ($allGamesPage - 1) * $gamesPerPage + count($allGames),
				'allGamesTotal' => $allGamesTotal,
			);

			$response = $this->responseView('Arcade_ViewPublic_Index', 'arcade_index', $viewParams);
		}

		return $response;
	}

	public function actionBrowse()
	{
		$this->_checkView();

		$categoryId = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$category = $this->_getCategoryOrError($categoryId);

		$gameModel = $this->_getGameModel();

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$gamesPerPage = Arcade_Option::get('gamesPerPage');

		$games = $gameModel->getGamesFromCategory($category['category_id'], $this->_getGameFetchOptions(array(
			'order' => $order,
			'page' => $page,
			'perPage' => $gamesPerPage,
		)));

		$totalGames = $gameModel->countGamesFromCategory($category['category_id']);

		// Get Categories for sidebar
		$categoryModel = $this->_getCategoryModel();
		$categories = $categoryModel->getCategories(0);

		$viewParams = array(
			'order' => $order,
			'selectedCategoryId' => $categoryId,
			'categories' => $categories,
			'category' => $category,
			'games' => $games,

			'inTab' => $this->_noRedirect(),

			'page' => $page,
			'gameStartOffset' => ($page - 1) * $gamesPerPage + 1,
			'gameEndOffset' => ($page - 1) * $gamesPerPage + count($games),
			'gamesPerPage' => $gamesPerPage,
			'totalGames' => $totalGames,
		);

		return $this->responseView('Arcade_ViewPublic_Category_Browse', 'arcade_category_browse', $viewParams);
	}

	public function actionPlay()
	{
		$this->_checkPlay();
		$gameId = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$game = $this->_getGameOrError($gameId, array('image' => 'l', ));

		$this->_assertGamePermission('play', $game);
		$category = $this->_getCategoryModel()->getCategoryById($game['category_id']);
		if ($category['active'] == 0)
		{
			throw $this->getNoPermissionResponseException();
		}

		$system = $this->_getSystemModel()->initSystem($game['system_id']);
		if (empty($system))
		{
			throw new XenForo_Exception(new XenForo_Phrase('arcade_specified_system_not_found_x', array('system_id' => $game['system_id'])), false);
		}

		$viewParams = array(
			'game' => $game,
			'system' => $system,
			'category' => $category,
		);

		return $this->responseView('Arcade_ViewPublic_Game_Play', 'arcade_play', $viewParams);
	}

	public function actionView()
	{
		$this->_checkView();
		$gameId = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$game = $this->_getGameOrError($gameId, array('image' => 'l', ));

		$this->_assertGamePermission('view', $game);

		var_dump($game);
		exit ;

		// TODO: implement this
	}

	public function actionVote()
	{
		$canVote = $this->_checkVote();
		$gameId = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$game = $this->_getGameOrError($gameId, array(
			'voteUserId' => XenForo_Visitor::getUserId(),
			'image' => 'x',
			'join' => Arcade_Model_Game::FETCH_CATEGORY,
			'canVote' => $canVote,
		));
		$d = $this->_input->filterSingle('d', XenForo_Input::STRING);

		$this->_assertGamePermission('vote', $game);

		if ($this->_request->isPost() AND ($this->_noRedirect() OR $this->isConfirmedPost()))
		{
			// process the request

			switch ($d)
			{
				case 'up':
					$this->_getVoteModel()->up($game['game_id'], XenForo_Visitor::getUserId());
					break;
				case 'down':
					$this->_getVoteModel()->down($game['game_id'], XenForo_Visitor::getUserId());
					break;
				default:
					return $this->responseNoPermission();
			}

			if ($this->_noRedirect())
			{
				$viewParams = array('game' => $this->_getGameModel()->getGameById($game['game_id'], array('voteUserId' => XenForo_Visitor::getUserId())), );
				return $this->responseView('Arcade_ViewPublic_Game_VoteResult', 'arcade_game_vote', $viewParams);
			}
			else
			{
				return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('arcade/view', $game));
			}
		}
		else
		{
			// display the form
			if (!empty($game['voted_date']))
			{
				$d = $game['voted_up'] ? 'down' : 'up';
				// revert user old vote
			}

			return $this->responseView('Arcade_ViewPublic_Game_Vote', 'arcade_vote', array(
				'game' => $game,
				'd' => $d,
			));
		}
	}

	protected function _getGameOrError($gameId, array $fetchOptions = array())
	{
		if (is_numeric($gameId))
		{
			$info = $this->_getGameModel()->getGameById($gameId, $fetchOptions);
		}
		else
		{
			$info = $this->_getGameModel()->getGameBySlug($gameId, $fetchOptions);
		}

		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('arcade_game_not_found'), 404));
		}

		return $info;
	}

	protected function _getCategoryOrError($categoryId)
	{
		$info = $this->_getCategoryModel()->getCategoryById($categoryId);

		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('arcade_category_not_found'), 404));
		}

		return $info;
	}

	protected function _getGameFetchOptions(array $fetchOptions = array())
	{
		$default = array(
			'join' => Arcade_Model_Game::FETCH_HIGHSCORE_USER + Arcade_Model_Game::FETCH_CATEGORY,
			'limit' => Arcade_Option::get('gamesPerPage'),
			'image' => array(
				'l',
				'm',
				's'
			),
			'playUserId' => XenForo_Visitor::getUserId(),
			'voteUserId' => XenForo_Visitor::getUserId(),
		);

		return array_merge($default, $fetchOptions);
	}

	protected function _assertGamePermission($permission, array $data = array())
	{
		if (!$this->_getGameModel()->hasPermission($permission, $data))
		{
			throw $this->getNoPermissionResponseException();
		}
	}

	protected function _getGameModel()
	{
		return $this->getModelFromCache('Arcade_Model_Game');
	}

	protected function _getSystemModel()
	{
		return $this->getModelFromCache('Arcade_Model_System');
	}

	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('Arcade_Model_Category');
	}

	protected function _getSessionModel()
	{
		return $this->getModelFromCache('Arcade_Model_Session');
	}

	protected function _getVoteModel()
	{
		return $this->getModelFromCache('Arcade_Model_Vote');
	}

	protected function _checkView()
	{
		if (!Arcade_Permissions::canView())
		{
			throw $this->getNoPermissionResponseException();
		}
	}

	protected function _checkPlay()
	{
		if (!Arcade_Permissions::canPlay())
		{
			throw $this->getNoPermissionResponseException();
		}
	}

	protected function _checkComment()
	{
		if (!Arcade_Permissions::canComment())
		{
			throw $this->getNoPermissionResponseException();
		}
	}

	protected function _checkVote()
	{
		if (!Arcade_Permissions::canVote())
		{
			return false;
		}
	}

	protected function _preDispatch($action)
	{
		if (!Arcade_Option::get('enable'))
		{
			throw new XenForo_Exception(Arcade_Option::get('disabled_message'), true);
		}

		return parent::_preDispatch($action);
	}

}
