<?php

/**
 * Theme Lab Colors
 *
 * Color validation and normalization utilities.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Normalize a hexadecimal color.
 *
 * Accepts:
 * #ABC
 * ABC
 * #AABBCC
 * AABBCC
 *
 * Returns a normalized uppercase six-digit color or false.
 */
function themelab_normalize_hex_color($color)
{
	$color = trim((string) $color);
	$color = ltrim($color, '#');

	if (preg_match('~^[0-9a-fA-F]{3}$~', $color))
	{
		$color =
			$color[0] . $color[0]
			. $color[1] . $color[1]
			. $color[2] . $color[2];
	}

	if (!preg_match('~^[0-9a-fA-F]{6}$~', $color))
		return false;

	return '#' . strtoupper($color);
}