<?php

final class Arcade_Permissions
{
	const PERMISSIONS_GROUP = 'xfarcade';
	private static $permissions = array(
          'canView' => 'xfarcade_can_view',
		'canPlay' => 'xfarcade_can_use',
		'canComment' => 'xfarcade_can_comment',
		'canVote' => 'xfarcade_can_vote'
	);

	public static function get($key)
	{
		return self::$permissions[$key];
	}

	public static function canView()
	{
		$visitor = XenForo_Visitor::getInstance();
		return $visitor->hasPermission(
			self::PERMISSIONS_GROUP,
			self::get('canView')
		);
	}

	public static function canPlay()
	{
		$visitor = XenForo_Visitor::getInstance();

          if (self::canView()) {
               return $visitor->hasPermission(
                    self::PERMISSIONS_GROUP,
                    self::get('canPlay')
               );
          } else {
               return false;
          }
	}

	public static function canComment()
	{
		$visitor = XenForo_Visitor::getInstance();
		return $visitor->hasPermission(
		   self::PERMISSIONS_GROUP,
			self::get('canComment')
		);
	}

	public static function canVote()
	{
		$visitor = XenForo_Visitor::getInstance();
		return $visitor->hasPermission(
			self::PERMISSIONS_GROUP,
			self::get('canVote')
		);
	}

}
