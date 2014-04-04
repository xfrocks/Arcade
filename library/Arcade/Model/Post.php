<?php

/**
 * Model for posts.
 *
 * @package xShop
 */
class Arcade_Model_Post extends XFCP_Arcade_Model_Post
{

	public function preparePost(array $post, array $thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$post = parent::preparePost($post, $thread, $forum, $nodePermissions, $viewingUser);

		$post_user_id = $post['user_id'];
		$champModel = $this->_getChampionModel();
		$userChampionPost = $champModel->buildGamePlay($post_user_id);

		$xfaImage = XenForo_Application::get('options')->xfarcade_image_path;
		$xfaText = XenForo_Application::get('options')->xfarcade_text;
		$xfaChampion = XenForo_Application::get('options')->xfarcade_champion;

		$xfaImage = array();

		foreach ($userChampionPost AS $posty)
		{
			if ($posty['highscore'])
			{
				if ($xfaChampion && $xfaImage)
				{
					$champion[$xfaImage] = $xfaImage;
				}
				else
				{
					$champion[$xfaImage] = new XenForo_Phrase('arcade_champion_of_x', array('game' => $userChampionPost['title']));
				}
			}
		}

		if (!empty($champion))
			$post['champion'] = $champion;

		return $post;
	}

	protected function _getChampionModel()
	{
		return $this->getModelFromCache('Arcade_Model_Game');
	}

}
