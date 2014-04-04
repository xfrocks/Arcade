<?php

class Arcade_Helper_PhpParser
{
	public static function parseNumber($str)
	{
		$str = preg_replace('/[^0-9\+\-\.]/', '', $str);

		return doubleval($str);
	}

	public static function parseString($str)
	{
		$str = utf8_trim($str);
		$literal = false;
		$result = '';

		if (!empty($str))
		{
			if ($str[0] === "'" AND substr($str, -1) === "'")
			{
				$literal = "'";
			}
			elseif ($str[0] === '"' AND substr($str, -1) === '"')
			{
				$literal = '"';
			}

			if ($literal !== false)
			{
				$result = substr($str, 1, -1);
				$result = str_replace('\\' . $literal, $literal, $result);
				$result = str_replace('\\\\', '\\', $result);
			}
		}

		return $result;
	}

}
