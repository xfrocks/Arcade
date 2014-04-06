<?php

if (XenForo_Application::$versionId >= 1030000)
{
	XenForo_DataWriter_User::$changeLogIgnoreFields[] = 'arcade_champion';
}

class Arcade_XenForo_DataWriter_User extends XFCP_Arcade_XenForo_DataWriter_User
{
	public function Arcade_demoteChampionForGame(array $game)
	{
		$champion = $this->Arcade_getChampion();

		if (isset($champion[$game['game_id']]))
		{
			unset($champion[$game['game_id']]);
			$this->set('arcade_champion', $champion);
		}
	}

	public function Arcade_promoteChampionForGame(array $game, array $session)
	{
		$champion = $this->Arcade_getChampion();

		$champion[$game['game_id']] = array(
			'game' => $game,
			'session' => $session
		);
		$this->set('arcade_champion', $champion);
	}

	public function Arcade_getChampion()
	{
		$champion = $this->get('arcade_champion');

		if (!is_array($champion))
		{
			$champion = @unserialize($champion);
		}

		if (!is_array($champion))
		{
			$champion = array();
		}

		return $champion;
	}

	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['xf_user_profile']['arcade_champion'] = array(
			'type' => XenForo_DataWriter::TYPE_SERIALIZED,
			'default' => 'a:0:{}',
		);

		return $fields;
	}

}
