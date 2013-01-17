<?php
class Arcade_Model_Game extends XenForo_Model {
	const FETCH_CATEGORY = 0x01;
	const FETCH_HIGHSCORE_USER = 0x02;
	const FETCH_SESSION = 0x04;

	public function hasPermission($permission, array $data = array()) {

          switch ($permission) {
               case 'navbar':
                    // This is only used for deciding if the user should see the
                    // Arcade link in the navbar.
                    $visitor = XenForo_Visitor::getInstance();
                    return $visitor->hasPermission('xfarcade','xfarcade_can_view');


               case 'play':
                    // Check if the game is playable
			if ($data['active'] == 0) {
                        // TO DO: Add permission for playing incative games
					return false;
				} else {
                         if (!Arcade_Permissions::canPlay()) {
                              throw $this->getNoPermissionResponseException();
                         } else {
                              return true;
                         }
				};
				break;

               case 'view':
                    // Check if the game is viewable.  If no $data is provided
                    // then it means a check is being done if the user has
                    // permission to view games in general.
                    if ($data) {
                         // Do they have permissions to view an explicit game?
                         // TO DO: Game explicit permissions
                         $this->_checkView();
                    } else {
                         // Do they have permissions in general to view this game?
                         $this->_checkView();
                    }

               case 'vote':
                    return false;

               default:
                    // We don't know what they're trying to do?!
                    return false;
		}

	}

	public function buildPlayCount($gameId = 0) {
		$db = $this->_getDb();
		$gameId = intval($gameId);

		$results = $db->fetchAll("
			SELECT game_id, COUNT(*) AS play_count
			FROM `xf_arcade_session`
			WHERE valid = 1
			" . ($gameId > 0?(" AND game_id = $gameId"):'') . " 
			GROUP BY game_id
		");

		foreach ($results as $result) {
			$db->query("
				UPDATE xf_arcade_game
				SET play_count = ?
				WHERE game_id = ?
			", array($result['play_count'], $result['game_id']));
		}

		return count($results);
	}

	public function buildLatestScores() {
		// TODO: implement this
	}

	public function buildGamePlay(array $game, array $user, array $session) {
		$db = $this->_getDb();

		// we have to query to find our rank
		$higher = $this->_getSessionModel()->countBetterUsers($game['game_id'], $user['user_id'], $session['score'], $session['time_finish'], $game['reversed_scoring']);
		$rank = $higher + 1;

		if ($rank == 1) {
			// we got the new champion here!

			$db->update('xf_arcade_game', array(
				'highscore' => $session['score'],
				'highscore_user_id' => $user['user_id'],
				'highscore_username' => $user['username'],
				'highscore_date' => $session['time_finish'],
			), array('game_id = ?' => $game['game_id']));

			$oldChampion = $this->_getSessionModel()->getBestSession($game['game_id'], $game['reversed_scoring']);

			if ($oldChampion['username'] != $user['username'])
			{
				if (!$this->_getConversationModel()->canStartConversations($errorPhraseKey))
				{
					throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
				}

				$xfaPcTitle = XenForo_Application::get('options')->xfarcade_pc_title;
				$xfaPcMessage = XenForo_Application::get('options')->xfarcade_pc_message;

				$visitor = XenForo_Visitor::getInstance();

				$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
				$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $visitor->toArray());
				$conversationDw->set('user_id', $user['user_id']);
				$conversationDw->set('username', $user['username']);
				$conversationDw->set('title', $xfaPcTitle);
				$conversationDw->set('open_invite', '0');
				$conversationDw->set('conversation_open', '1');
				$conversationDw->addRecipientUserNames(explode(',', $oldChampion['username'])); // checks permissions

				$messageDw = $conversationDw->getFirstMessageDw();
				$messageDw->set('message', $xfaPcMessage);

				$conversationDw->preSave();

				if (!$conversationDw->hasErrors())
				{
					$this->assertNotFlooding('conversation');
				}

				$conversationDw->save();
				$conversation = $conversationDw->getMergedData();

				$this->_getConversationModel()->markConversationAsRead(
					$conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time
				);
			}

			//TODO: news feed item
			//TODO: champion cache
			//TODO: award cache
		}

		$existed = $db->fetchRow("
			SELECT *
			FROM `xf_arcade_game_play`
			WHERE user_id = ? AND game_id = ?
		", array($user['user_id'], $game['game_id']));

		// we may use REPLACE INTO, hmm
		if (!empty($existed)) {
			if ($rank != $existed['best_rank']) {			
				$db->query("
					UPDATE `xf_arcade_game_play`
					SET best_rank = best_rank + 1
					WHERE game_id = ?
						AND user_id <> ?
						AND best_rank >= ?
						AND best_rank < ?
				", array($game['game_id'], $user['user_id'], $rank, $existed['best_rank']));
			}
			
			$bestScore = $game['reversed_scoring']?min($session['score'], $existed['best_score']):max($session['score'], $existed['best_score']);
			$bestRank = min($rank, $existed['best_rank']);
			
			$db->query("
				UPDATE `xf_arcade_game_play`
				SET last_date = ?, best_score = ?, best_rank = ?
				WHERE user_id = ? AND game_id = ?
			", array($session['time_finish'], $bestScore, $bestRank, $user['user_id'], $game['game_id']));
		} else {
			$db->query("
				INSERT INTO `xf_arcade_game_play`
				SET user_id = ?, game_id = ?, last_date = ?, best_score = ?, best_rank = ?
			", array($user['user_id'], $game['game_id'], $session['time_finish'], $session['score'], $rank));
			
			$db->query("
				UPDATE `xf_arcade_game_play`
				SET best_rank = best_rank + 1
				WHERE game_id = ?
					AND user_id <> ?
					AND best_rank >= ?
			", array($game['game_id'], $user['user_id'], $rank));
		}
	}
	
	public static function getScoreComparison($reversedScoring) {
		if ($reversedScoring) {
			$scoreFunction = 'MIN';
			$scoreDirection = 'ASC';
			$scoreOperator = '<';
			$scoreOperatorAlt = '>';
		} else {
			$scoreFunction = 'MAX';
			$scoreDirection = 'DESC';
			$scoreOperator = '>';
			$scoreOperatorAlt = '<';
		}

		return compact('scoreFunction', 'scoreDirection', 'scoreOperator', 'scoreOperatorAlt');
	}
	
	public function deleteGamePlayByGameId($gameId) {
		$this->_getDb()->query('DELETE FROM `xf_arcade_game_play` WHERE game_id = ?', array($gameId));
	}
	
	public static function getImageFilePath(array $game, $size = 'l') {
		$gameId = $game['game_id'];
		$group = floor($gameId / 100);
		$slug = $game['slug'];
		$imageDate = $game['image_date'];
		
		if ($imageDate > 0) {
			return XenForo_Helper_File::getExternalDataPath() . "/games/$group/{$slug}_{$gameId}_{$imageDate}{$size}.jpg";
		} else {
			return '';
		}
	}

	public static function getImageUrl(array $game, $size = 'l') {
		$gameId = $game['game_id'];
		$group = floor($gameId / 100);
		$slug = $game['slug'];
		$imageDate = $game['image_date'];

		if ($imageDate > 0) {
			return XenForo_Application::$externalDataPath  . "/games/$group/{$slug}_{$gameId}_{$imageDate}{$size}.jpg";
		} else {
			return '';
		}
	}
	
	public function getGames(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareGameConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareGameFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$db = XenForo_Application::getDb();

		$games = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT game.*
					$sqlClauses[selectFields]
					, (select max(last_date) from xf_arcade_game_play where game_id = game.game_id) as last_play_date
				FROM xf_arcade_game AS game
				$sqlClauses[joinTables]
				WHERE $whereConditions
				$sqlClauses[orderClause]
		", $limitOptions['limit'], $limitOptions['offset']), 'game_id');

		foreach ($games as &$game) {
			$game['system_options'] = @unserialize($game['system_options']);
			$game['options'] = @unserialize($game['options']);
			
			/// @todo: fold out into subquery
			$game['highscores'] = $db->fetchAll("
				select best_score as score, play.*, user.*
				from xf_arcade_game_play play
				LEFT JOIN xf_user AS user ON (user.user_id = play.user_id)
				where game_id = ? and best_score > 0 and play.user_id <> ?
				order by best_score desc, last_date asc 
				limit 5
				", array($game['game_id'], $game['highscore_user_id']));
				
			if (!empty($fetchOptions['image'])) {
				if (!is_array($fetchOptions['image'])) {
					$fetchOptions['image'] = array(trim($fetchOptions['image']));
				}

				$game['images'] = array();
				foreach ($fetchOptions['image'] as $sizeCode) {
					$game['images'][$sizeCode] = self::getImageUrl($game, $sizeCode);
				}
			}
		}

		return $games;
	}

	public function getGame(array $conditions = array(), array $fetchOptions = array()) {
		$fetchOptions['limit'] = 1;

		$games = $this->getGames($conditions, $fetchOptions);

		if (!empty($games)) {
			return reset($games);
		} else {
			return false;
		}
	}

	public function getGameById($gameId, array $fetchOptions = array()) {
		$games = $this->getGames(array('game_id' => $gameId), $fetchOptions);

		if (!empty($games)) {
			return reset($games);
		} else {
			return false;
		}
	}

	public function getGameBySlug($slug, array $fetchOptions = array()) {
		$games = $this->getGames(array('slug' => $slug), $fetchOptions);
		
		if (!empty($games)) {
			return reset($games);
		} else {
			return false;
		}
	}
	
	public function getGamesFromCategory($categoryId, array $fetchOptions = array()) {
		return $this->getGames(array('category_id' => $categoryId), $fetchOptions);
	}
	
	public function countGames(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareGameConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareGameFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM xf_arcade_game AS game
			$sqlClauses[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function countGamesFromCategory($categoryId) {
		return $this->countGames(array('category_id' => $categoryId));
	}
	
	public function prepareGameConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['game_id'])) {
			$sqlConditions[] = 'game.game_id = ' . $db->quote($conditions['game_id']);
		}

		if (!empty($conditions['slug'])) {
			$sqlConditions[] = 'game.slug = ' . $db->quote($conditions['slug']);
		}

		if (!empty($conditions['session_id']) OR !empty($conditions['session_user_id'])) {
			if (!empty($conditions['session_id'])) {
				$sqlConditions[] = 'session.session_id = ' . $db->quote($conditions['session_id']);
			}
			if (!empty($conditions['session_user_id'])) {
				$sqlConditions[] = 'session.user_id = ' . $db->quote($conditions['session_user_id']);
			}

			if (isset($fetchOptions['join'])) {
				$fetchOptions['join'] += self::FETCH_SESSION;
			} else {
				$fetchOptions['join'] = self::FETCH_SESSION;
			}
		}

		if (!empty($conditions['category_id'])) {
			$sqlConditions[] = 'game.category_id = ' . $db->quote($conditions['category_id']);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareGameFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		$choices = array(
			'game_id' => 'game.game_id',
			'title' => 'game.title',
			'play_count' => 'game.play_count',
			'votes' => 'game.votes',
			'session_id' => 'session.session_id',
			'random' => 'RAND()',
		);
		$orderBy = $this->getOrderByClause($choices, $fetchOptions, 'game.title ASC');

		if (!empty($fetchOptions['join'])) {
			if ($fetchOptions['join'] & self::FETCH_CATEGORY) {
				$selectFields .= ",category.title AS category_title";
				$joinTables .= "\nLEFT JOIN xf_arcade_category AS category ON (category.category_id = game.category_id)";
			}

			if ($fetchOptions['join'] & self::FETCH_HIGHSCORE_USER)
			{
				$selectFields .= ",user.*, IF(user.username IS NULL, game.highscore_username, user.username) AS username";
				$joinTables .= "\nLEFT JOIN xf_user AS user ON (user.user_id = game.highscore_user_id)";
			}

			if ($fetchOptions['join'] & self::FETCH_SESSION)
			{
				$selectFields .= ",session.*, session.user_id AS session_user_id, session.username AS session_username";
				$joinTables .= "\nLEFT JOIN xf_arcade_session AS session ON (session.game_id = game.game_id)";
			}
		}

		if (isset($fetchOptions['playUserId'])) {
			if (!empty($fetchOptions['playUserId'])) {
				$joinTables .= "\nLEFT JOIN xf_arcade_game_play AS game_play ON (game_play.game_id = game.game_id
					AND game_play.user_id = " . $this->_getDb()->quote($fetchOptions['playUserId']) . ")";
				$selectFields .= ",	game_play.last_date AS played_date, game_play.best_score AS played_score, game_play.best_rank AS played_rank";
			} else {
				$selectFields .= ", 0 AS played_date, 0 AS played_score, 0 AS played_rank";
			}
		}

		if (isset($fetchOptions['voteUserId'])) {
			if (!empty($fetchOptions['voteUserId'])) {
				$joinTables .= "\nLEFT JOIN xf_arcade_vote AS vote ON (vote.game_id = game.game_id
					AND vote.user_id = " . $this->_getDb()->quote($fetchOptions['voteUserId']) . ")";
				$selectFields .= ",	vote.vote_date AS voted_date, vote.vote_up AS voted_up";
			} else {
				$selectFields .= ", 0 AS voted_date, 0 AS voted_up";
			}
		}
		
		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause'  => $orderBy,
		);
	}

	protected function _getSessionModel() {
		return $this->getModelFromCache('Arcade_Model_Session');
	}


}
