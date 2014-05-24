<?php

class Arcade_Template_Helper
{
	public static function renderScore($score)
	{
		$floor = floor($score);
		$ceil = ceil($score);

		if ($ceil > $floor)
		{
			return XenForo_Template_Helper_Core::numberFormat($score, 2);
		}
		else
		{
			return XenForo_Template_Helper_Core::numberFormat($score, 0);
		}
	}

}
