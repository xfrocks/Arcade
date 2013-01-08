<?php
class Arcade_Model_Category extends XenForo_Model {
	public function getCategories($bGetInactive) {
          if ($bGetInactive == 1) {
     		return $this->fetchAllKeyed("
     	    		SELECT *
     	    		FROM `xf_arcade_category`
     			ORDER BY display_order
          		", 'category_id');
          } else {
     		return $this->fetchAllKeyed("
     			SELECT *
     			FROM `xf_arcade_category`
     			WHERE active = 1
     			ORDER BY display_order
     		", 'category_id');

          }
	}
	
	public function getCategoryById($categoryId) {
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM `xf_arcade_category`
			WHERE category_id = ?
		", array($categoryId));
	}
	
	public function getCategoryByTitle($title) {
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM `xf_arcade_category`
			WHERE title = ?
		", array($title)); 
	}
}