<?php

/**
 * Theme Lab Palettes
 *
 * Built-in palette definitions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Return the official Theme Lab palette token definitions.
 */
function themelab_get_palette_tokens()
{
	// Palette Studio owns only the global color foundation. Dedicated Studios
	// own Header, Footer, Gradients, Buttons, and specialist component colors.
	// Legacy preset keys remain in preset data for import compatibility.
	return array(
		'primary' => 'Primary',
		'secondary' => 'Secondary',
		'accent' => 'Accent',
		'background' => 'Background',
		'surface' => 'Standard Row / Surface',
		'surface_alt' => 'Alternate Row / Surface',
		'text' => 'Text',
		'muted_text' => 'Muted Text',
		'link' => 'Link Color',
		'link_hover' => 'Link Hover Color',
		'link_visited' => 'Visited Link Color',
		'border' => 'Borders',
	);
}

/**
 * Return all built-in Theme Lab palettes.
 */
function themelab_get_palette_presets()
{
	return array(
		'classic' => array(
			'name' => 'Classic Blue',
			'description' => 'A clean palette inspired by the traditional Curve2 appearance.',
			'colors' => array(
				'primary' => '#3465A4',
				'secondary' => '#5B87B2',
				'accent' => '#204A87',
				'background' => '#E9EEF2',
				'surface' => '#FFFFFF',
				'surface_alt' => '#F5F7F8',
				'text' => '#222222',
				'muted_text' => '#666666',
				'link' => '#3465A4',
				'button' => '#3465A4',
				'border' => '#C7CDD1',
				'success' => '#2E7D32',
				'warning' => '#B26A00',
				'danger' => '#B00020',
				'header' => '#3465A4',
				'footer' => '#D5DEE5',
			),
		),

		'ocean' => array(
			'name' => 'Ocean',
			'description' => 'Deep ocean blues with cool, airy surfaces.',
			'colors' => array(
				'primary' => '#005B96',
				'secondary' => '#6497B1',
				'accent' => '#03396C',
				'background' => '#EAF6FA',
				'surface' => '#FFFFFF',
				'surface_alt' => '#D9EDF5',
				'text' => '#173042',
				'muted_text' => '#587484',
				'link' => '#005B96',
				'button' => '#0077B6',
				'border' => '#A8C7D4',
				'success' => '#2A9D8F',
				'warning' => '#E9A23B',
				'danger' => '#D1495B',
				'header' => '#03396C',
				'footer' => '#B3D7E5',
			),
		),

		'midnight' => array(
			'name' => 'Midnight',
			'description' => 'A dark palette with cool blue highlights.',
			'colors' => array(
				'primary' => '#4EA1FF',
				'secondary' => '#7089AC',
				'accent' => '#7C5CFC',
				'background' => '#0F1720',
				'surface' => '#18222E',
				'surface_alt' => '#202D3A',
				'text' => '#F2F6FA',
				'muted_text' => '#A6B3C0',
				'link' => '#66B3FF',
				'button' => '#397AC6',
				'border' => '#34495E',
				'success' => '#4BC38D',
				'warning' => '#F0B44D',
				'danger' => '#F06A78',
				'header' => '#111B27',
				'footer' => '#0B1118',
			),
		),

		'forest' => array(
			'name' => 'Forest',
			'description' => 'Natural greens with warm neutral surfaces.',
			'colors' => array(
				'primary' => '#2F6B45',
				'secondary' => '#6B8F71',
				'accent' => '#8B6F47',
				'background' => '#EEF3EC',
				'surface' => '#FFFFFF',
				'surface_alt' => '#DDE8D9',
				'text' => '#243027',
				'muted_text' => '#657067',
				'link' => '#2F6B45',
				'button' => '#3F7D54',
				'border' => '#B6C7B2',
				'success' => '#2E7D32',
				'warning' => '#A86F16',
				'danger' => '#A13D3D',
				'header' => '#315D42',
				'footer' => '#C9D8C5',
			),
		),

		'arctic' => array(
			'name' => 'Arctic',
			'description' => 'Bright icy surfaces with crisp blue accents.',
			'colors' => array(
				'primary' => '#2A7F9E',
				'secondary' => '#70B7C9',
				'accent' => '#5B6FC8',
				'background' => '#F2FAFC',
				'surface' => '#FFFFFF',
				'surface_alt' => '#E3F3F7',
				'text' => '#263941',
				'muted_text' => '#607A84',
				'link' => '#287A99',
				'button' => '#3A8EA8',
				'border' => '#B8D7DF',
				'success' => '#3A9D82',
				'warning' => '#D99435',
				'danger' => '#CC5966',
				'header' => '#D6EDF3',
				'footer' => '#C8E2E9',
			),
		),

		'desert' => array(
			'name' => 'Desert',
			'description' => 'Warm sand, clay and sunset tones.',
			'colors' => array(
				'primary' => '#B5653A',
				'secondary' => '#D19A66',
				'accent' => '#8A4F2D',
				'background' => '#F5E9D6',
				'surface' => '#FFFDF8',
				'surface_alt' => '#ECD7B8',
				'text' => '#3B2C22',
				'muted_text' => '#7D6858',
				'link' => '#A44E2C',
				'button' => '#B5653A',
				'border' => '#D8BE98',
				'success' => '#6B8E4E',
				'warning' => '#C58A23',
				'danger' => '#A94442',
				'header' => '#C87A4A',
				'footer' => '#DFC49E',
			),
		),

		'retro' => array(
			'name' => 'Retro',
			'description' => 'Muted vintage colors with bold contrasting accents.',
			'colors' => array(
				'primary' => '#2A6F7B',
				'secondary' => '#E3A857',
				'accent' => '#C44B4F',
				'background' => '#F3E9D2',
				'surface' => '#FFF9E8',
				'surface_alt' => '#E5D7B8',
				'text' => '#35302A',
				'muted_text' => '#746A5D',
				'link' => '#A23B48',
				'button' => '#2A6F7B',
				'border' => '#C8B993',
				'success' => '#638B4E',
				'warning' => '#C9892B',
				'danger' => '#B33A3A',
				'header' => '#2A6F7B',
				'footer' => '#D8C79F',
			),
		),

		'high_contrast' => array(
			'name' => 'High Contrast',
			'description' => 'Strong contrast for maximum readability.',
			'colors' => array(
				'primary' => '#005FCC',
				'secondary' => '#444444',
				'accent' => '#FFB000',
				'background' => '#FFFFFF',
				'surface' => '#FFFFFF',
				'surface_alt' => '#EFEFEF',
				'text' => '#000000',
				'muted_text' => '#333333',
				'link' => '#0047AB',
				'button' => '#005FCC',
				'border' => '#000000',
				'success' => '#007A32',
				'warning' => '#8A5700',
				'danger' => '#B00020',
				'header' => '#000000',
				'footer' => '#222222',
			),
		),
	);
}



/**
 * Return black or white text for a background color.
 */
function themelab_get_contrast_text_color($hex_color)
{
	$hex_color = ltrim((string) $hex_color, '#');

	if (strlen($hex_color) !== 6 || !ctype_xdigit($hex_color))
		return '#FFFFFF';

	$red = hexdec(substr($hex_color, 0, 2));
	$green = hexdec(substr($hex_color, 2, 2));
	$blue = hexdec(substr($hex_color, 4, 2));
	$luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

	return $luminance >= 150 ? '#222222' : '#FFFFFF';
}

/**
 * Return a complete Theme Preset definition.
 *
 * Palette remains the source of the visual identity. Header and Footer
 * sections translate that identity into their dedicated Studio settings.
 */
function themelab_get_theme_preset($preset_key)
{
	$preset = themelab_get_palette_preset($preset_key);

	if (empty($preset) || empty($preset['colors']))
		return false;

	$colors = $preset['colors'];
	$header_background = !empty($colors['header'])
		? $colors['header']
		: $colors['primary'];
	$footer_background = !empty($colors['footer'])
		? $colors['footer']
		: $colors['surface_alt'];

	$preset['palette'] = array(
		'preset' => $preset_key,
		'colors' => $colors,
		'customized' => array(),
	);

	$preset['header'] = array(
		'background_mode' => 'solid',
		'background_color' => $header_background,
		'gradient_start' => $header_background,
		'gradient_end' => !empty($colors['accent'])
			? $colors['accent']
			: $header_background,
		'text_color' => themelab_get_contrast_text_color($header_background),
		'gradient_direction' => 'horizontal',
	);

	$preset['footer'] = array(
		'background_mode' => 'solid',
		'background_color' => $footer_background,
		'gradient_start' => $footer_background,
		'gradient_end' => !empty($colors['secondary'])
			? $colors['secondary']
			: $footer_background,
		'text_color' => themelab_get_contrast_text_color($footer_background),
		'link_color' => !empty($colors['link'])
			? $colors['link']
			: $colors['primary'],
		'gradient_direction' => 'horizontal',
	);

	return $preset;
}
/**
 * Return one built-in palette.
 */
function themelab_get_palette_preset($preset_key)
{
	$presets = themelab_get_palette_presets();

	return isset($presets[$preset_key])
		? $presets[$preset_key]
		: false;
}