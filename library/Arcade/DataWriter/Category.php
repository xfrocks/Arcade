<?php

class Arcade_DataWriter_Category extends XenForo_DataWriter
{
	protected function _getFields()
	{
		return array('xf_arcade_category' => array(
				'category_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'title' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
					'maxLength' => 50
				),
				'description' => array(
					'type' => self::TYPE_STRING,
					'required' => false
				),
				'display_order' => array(
					'type' => self::TYPE_UINT,
					'default' => 1
				),
				'active' => array(
					'type' => self::TYPE_BOOLEAN,
					'default' => 1
				),
			));
	}

	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'category_id'))
		{
			return false;
		}

		return array('xf_arcade_category' => $this->_getCategoryModel()->getCategoryById($id));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'category_id = ' . $this->_db->quote($this->getExisting('category_id'));
	}

	protected function _preSave()
	{
		if ($this->get('title') && ($this->isChanged('title')))
		{
			$conflict = $this->_getCategoryModel()->getCategoryByTitle($this->get('title'));
			if ($conflict && $conflict['category_id'] != $this->get('category_id'))
			{
				$this->error(new XenForo_Phrase('arcade_category_title_must_be_unique'), 'title');
			}
		}
	}

	protected function _postSave()
	{
		Arcade_Helper_Category::rebuildCache();
	}

	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('Arcade_Model_Category');
	}

}
