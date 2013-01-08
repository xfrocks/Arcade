<?php
class Arcade_ControllerAdmin_Arcade extends XenForo_ControllerAdmin_Abstract {
	public function actionIndex() {
		return $this->responseReroute(__CLASS__, 'Games');
	}
	
	public function actionGames() {
		if ($this->_request->isPost()) {
			// probably a toggle request
			$gameExists = $this->_input->filterSingle('gameExists', array(XenForo_Input::UINT, 'array' => true));
			$games = $this->_input->filterSingle('game', array(XenForo_Input::UINT, 'array' => true));

			if (!empty($gameExists)) {
				$gameModel = $this->_getGameModel();
		
				foreach ($gameModel->getAllGames() AS $gameId => $game) {
					if (isset($gameExists[$gameId])) {
						$gameActive = (isset($games[$gameId]) && $games[$gameId] ? 1 : 0);
		
						if ($game['active'] != $gameActive) {
							$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Game');
							$dw->setExistingData($gameId);
							$dw->set('active', $gameActive);
							$dw->save();
						}
					}
				}
		
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('arcade/games')
				);
			}
		} 
		
		// a simple listing request
		$gameModel = $this->_getGameModel();
		$categoryModel = $this->_getCategoryModel();
		$games = $gameModel->getGames(array(), array('image' => 's'));
		$categories = $categoryModel->getCategories(1);

		$viewParams = array(
			'games' => $games,
			'categories' => $categories,
		);

		return $this->responseView('Arcade_ViewAdmin_Game_List', 'arcade_game_list', $viewParams);
	}
	
	public function actionAddGame() {
		$categoryModel = $this->_getCategoryModel();
		$systemModel = $this->_getSystemModel();
		
		$categories = $categoryModel->getCategories(1);
		$systems = $systemModel->getSystems();
		
		$viewParams = array(
			'game' => array(
				'active' => 1,
			),
			'categories' => $categories,
			'systems' => $systems,
		);
		
		return $this->responseView('Arcade_ViewAdmin_Game_Edit', 'arcade_game_edit', $viewParams);
	}
	
	public function actionEditGame() {
		$gameId = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$game = $this->_getGameOrError($gameId, array('image' => 'x'));
		
		$categoryModel = $this->_getCategoryModel();
		$systemModel = $this->_getSystemModel();
		
		$categories = $categoryModel->getCategories(1);
		$systems = $systemModel->getSystems();

		$viewParams = array(
			'game' => $game,
			'categories' => $categories,
			'systems' => $systems,
		);

		return $this->responseView('Arcade_ViewAdmin_Game_Edit', 'arcade_game_edit', $viewParams);
	}
	
	public function actionSaveGame() {
		$this->_assertPostOnly();

		$gameId = $this->_input->filterSingle('game_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'slug' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'instruction' => XenForo_Input::STRING,
			'category_id' => XenForo_Input::UINT,
			'system_id' => XenForo_Input::STRING,
			'reversed_scoring' => XenForo_Input::UINT,
			'active' => XenForo_Input::UINT,
		));
		
		$dw = XenForo_DataWriter::create('Arcade_DataWriter_Game');
		if ($gameId) {
			$dw->setExistingData($gameId);
		}
		$dw->bulkSet($dwInput);
		
		if ($this->_input->filterSingle('system_options_loaded', XenForo_Input::STRING) == $dwInput['system_id'] AND !empty($dwInput['system_id'])) {
			// process options now
			$system = $this->_getSystemModel()->initSystem($dwInput['system_id']);
			$systemOptions = $system->processOptionsInput($this->_input, $dw->getMergedData(), $dw);
			$dw->set('system_options', $systemOptions);
		} else {
			// $dw->set('system_options', array()); - not needed
			// mark to redirect later
			$flagGoBackToEdit = true;
		}
		
		$image = XenForo_Upload::getUploadedFile('image');
		if (!empty($image)) {
			$dw->addImage($image);
		}
		
		$dw->save();

		if ($this->_noRedirect()) {
			return $this->responseView('Arcade_ViewAdmin_Game_Save', '', array(
				'game' => $dw->getMergedData(),
				'oldGame' => $dw->getMergedExistingData(),
			));
		} elseif (!empty($flagGoBackToEdit)) {
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildAdminLink('arcade/edit-game', $dw->getMergedData())
			);
		} else {
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('arcade/games')
			);
		}
	}
	
	public function actionDeleteGame() {
		$gameId = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$game = $this->_getGameOrError($gameId);
		
		if ($this->isConfirmedPost()) {
			$dw = XenForo_DataWriter::create('Arcade_DataWriter_Game');
			$dw->setExistingData($gameId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('arcade/games')
			);
		} else {
			$viewParams = array(
				'game' => $game
			);

			return $this->responseView('Arcade_ViewAdmin_Game_Delete', 'arcade_game_delete', $viewParams);
		}
	}
	
	public function actionSystemOptions() {
		$this->_assertPostOnly();
		
		$gameId = $this->_input->filterSingle('game_id', XenForo_Input::UINT);
		if ($gameId) {
			$game = $this->_getGameOrError($gameId);
		} else {
			$game = array();
		}
		$game['system_id'] = $this->_input->filterSingle('system_id', XenForo_Input::UINT);
		
		$viewParams = array(
			'game' => $game,
		);
		return $this->responseView('Arcade_ViewAdmin_Game_Edit', 'arcade_game_system_options', $viewParams);
	}
	
	public function actionCategories() {
		$categoryModel = $this->_getCategoryModel();
		$categories = $categoryModel->getCategories(1);
		
		$viewParams = array(
			'categories' => $categories,
		);

		return $this->responseView('Arcade_ViewAdmin_Category_List', 'arcade_category_list', $viewParams);
	}
	
	public function actionAddCategory() {
		$viewParams = array(
			'category' => array(
				'display_order' => 1,
				'active' => 1,
			),
		);
		
		return $this->responseView('Arcade_ViewAdmin_Category_Edit', 'arcade_category_edit', $viewParams);
	}
	
	public function actionEditCategory() {
		$category = $this->_getCategoryOrError($this->_input->filterSingle('id', XenForo_Input::UINT));

		$viewParams = array(
			'category' => $category,
		);

		return $this->responseView('Arcade_ViewAdmin_Category_Edit', 'arcade_category_edit', $viewParams);
	}
	
	public function actionSaveCategory() {
		$this->_assertPostOnly();

		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'active' => XenForo_INput::UINT,
		));
		
		$dw = XenForo_DataWriter::create('Arcade_DataWriter_Category');
		if ($categoryId) {
			$dw->setExistingData($categoryId);
		}
		$dw->bulkSet($dwInput);
		
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('arcade/categories')
		);
	}
	
	public function actionDeleteCategory() {
		$category = $this->_getCategoryOrError($this->_input->filterSingle('id', XenForo_Input::UINT));
		
		if ($this->isConfirmedPost()) {
			$dw = XenForo_DataWriter::create('Arcade_DataWriter_Category');
			$dw->setExistingData($category);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('arcade/categories')
			);
		} else {
			$viewParams = array(
				'category' => $category
			);

			return $this->responseView('Arcade_ViewAdmin_Category_Delete', 'arcade_category_delete', $viewParams);
		}
	}
	
	protected function _getGameOrError($gameId, array $fetchOptions = array()) {
		$info = $this->_getGameModel()->getGameById($gameId, $fetchOptions);
		if (!$info) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('arcade_game_not_found'), 404));
		}

		return $info;
	}
	
	protected function _getCategoryOrError($categoryId) {
		$info = $this->_getCategoryModel()->getCategoryById($categoryId);
		if (!$info) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('arcade_category_not_found'), 404));
		}

		return $info;
	}
	
	protected function _getGameModel() {
		return $this->getModelFromCache('Arcade_Model_Game');
	}
	
	protected function _getSystemModel() {
		return $this->getModelFromCache('Arcade_Model_System');
	}
	
	protected function _getCategoryModel() {
		return $this->getModelFromCache('Arcade_Model_Category');
	}
}