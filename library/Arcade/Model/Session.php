<?php

class Arcade_Model_Session extends XenForo_Model
{

	public function saveSession(array $game, array $user, array $extraData)
	{
		$bind = $extraData;
		$bind['game_id'] = $game['game_id'];
		$bind['user_id'] = $user['user_id'];
		$bind['username'] = $user['username'];

		$this->_getDb()->insert('xf_arcade_session', $bind);

		return $this->_getDb()->lastInsertId();
	}

	public function updateSession($session, array $updateData)
	{
		if (!is_array($session))
			$session = array('session_id = ?' => intval($session));

		$this->_getDb()->update('xf_arcade_session', $updateData, $session);
	}

	public function getTopSessions($gameId, $reversedScoring = false, $limit = 1)
	{
		$db = $this->_getDb();
		$scoring = $this->_getScoreComparison($reversedScoring);
		extract($scoring);

		$whereConditions = array();
		$scores = $db->fetchAll("
			SELECT user_id, $scoreFunction(score) AS score
			FROM `xf_arcade_session`
			WHERE game_id = ?
				AND valid = 1
				AND user_id > 0
			GROUP BY user_id
			ORDER BY score $scoreDirection, time_finish DESC
			LIMIT $limit
		", array($gameId));
		foreach ($scores as $score)
		{
			$whereConditions[] = "(session.score = $score[score] AND session.user_id = $score[user_id])";
		}

		$topScores = $db->fetchAll("
			SELECT session.*
			FROM xf_arcade_session AS session
			WHERE session.game_id = ?
				AND session.valid = 1
				AND (" . implode(' OR ', $whereConditions) . ")
			ORDER BY session.score $scoreDirection, session.time_finish DESC
		", array($gameId));

		$oldCount = count($topScores);
		$userIds = array();
		foreach (array_keys($topScores) as $key)
		{
			if (in_array($topScores[$key]['user_id'], $userIds))
			{
				// one score each user only
				unset($topScores[$key]);
			}
			$userIds[] = $topScores[$key]['user_id'];
		}
		if (count($topScores) != $oldCount)
		{
			// reset array keys
			$topScores = array_values($topScores);
		}

		return $topScores;
	}

	public function getBestSession($gameId, $reversedScoring = false)
	{
		$array = $this->getTopSessions($gameId, $reversedScoring);

		return reset($array);
	}

	public function countBetterUsers($gameId, $userId, $score, $timeFinish, $reversedScoring = false)
	{
		$scoring = $this->_getScoreComparison($reversedScoring);
		extract($scoring);

		$better = $this->_getDb()->fetchAll("
			SELECT user_id
			FROM xf_arcade_session
			WHERE game_id = ?
				AND user_id > 0
				AND (score $scoreOperator ? OR (score = ? AND time_finish < ?))
			GROUP BY user_id
		", array(
			$gameId,
			$score,
			$score,
			$timeFinish
		));

		return count($better);
	}

	public function _getScoreComparison($reversedScoring)
	{
		return Arcade_Model_Game::getScoreComparison($reversedScoring);
	}

	public function deleteSessionByGameId($gameId)
	{
		$this->_getDb()->query('DELETE FROM `xf_arcade_session` WHERE game_id = ?', array($gameId));
	}

}
