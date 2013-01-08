<?php
class Arcade_Model_Vote extends XenForo_Model {
	public function up($gameId, $userId) {
		$this->_getDb()->query("
			REPLACE INTO `xf_arcade_vote`
			SET game_id = ?, user_id = ?, vote_date = ?, vote_up = 1
		", array($gameId, $userId, XenForo_Application::$time));
		
		$this->_updateGame($gameId);
	}
	
	public function down($gameId, $userId) {
		$this->_getDb()->query("
			REPLACE INTO `xf_arcade_vote`
			SET game_id = ?, user_id = ?, vote_date = ?, vote_up = 0
		", array($gameId, $userId, XenForo_Application::$time));
		
		$this->_updateGame($gameId);
	}
	
	protected function _updateGame($gameId) {
		$db = $this->_getDb();
		
		$votes = $db->fetchAll("
			SELECT vote_up, COUNT(*) AS count
			FROM `xf_arcade_vote`
			WHERE game_id = ?
			GROUP BY vote_up
		", array($gameId));
		
		$voteUp = 0;
		$voteDown = 0;
		foreach ($votes as $vote) {
			switch ($vote['vote_up']) {
				case '1': $voteUp = $vote['count']; break;
				case '0': $voteDown = $vote['count']; break;
			}
		}
		
		$db->query("
			UPDATE `xf_arcade_game`
			SET vote_up = ?, vote_down = ?
			WHERE game_id = ?
		", array($voteUp, $voteDown, $gameId));
	}
}