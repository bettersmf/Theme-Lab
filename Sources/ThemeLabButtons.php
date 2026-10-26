<?php

/**
 * Theme Lab Buttons
 *
 * Button Studio project functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

function themelab_get_default_buttons()
{
	return array(
		'border_radius' => 5,
		'border_width' => 0,
		'padding_vertical' => 3,
		'padding_horizontal' => 16,
		'font_weight' => '500',
		'text_transform' => 'none',
		'depth' => 'soft',
		'hover_effect' => 'darken',

		// Semantic preview button foregrounds. Backgrounds remain palette-driven.
		'primary_text' => '#FFFFFF',
		'secondary_text' => '#222222',
		'danger_background' => '#B33A3A',
		'danger_text' => '#FFFFFF',
		'danger_border' => '#8F2E2E',
		'disabled_background' => '#D6DCE2',
		'disabled_text' => '#6B7280',
		'disabled_border' => '#C3CAD2',

		// Header navigation button states.
		'header_background' => '#FFFFFF',
		'header_text' => '#243447',
		'header_border' => '#C8D0D8',
		'header_hover_background' => '#E7EEF5',
		'header_hover_text' => '#17324D',
		'header_current_background' => '#3465A4',
		'header_current_text' => '#FFFFFF',

		// Footer navigation button/link states.
		'footer_background' => '#FFFFFF',
		'footer_text' => '#3465A4',
		'footer_border' => '#C8D0D8',
		'footer_hover_background' => '#E7EEF5',
		'footer_hover_text' => '#17324D',
		'footer_current_background' => '#3465A4',
		'footer_current_text' => '#FFFFFF',
	);
}

/**
 * Copy navigation colors saved by RC5.6.4 through RC5.7.0c into the new
 * Buttons ownership model. New button values always take precedence.
 */
function themelab_migrate_navigation_colors_to_buttons($buttons, $project_data)
{
	$legacy_navigation = !empty($project_data['navigation']) && is_array($project_data['navigation'])
		? $project_data['navigation']
		: array();

	$color_keys = array(
		'primary_text', 'secondary_text',
		'danger_background', 'danger_text', 'danger_border',
		'disabled_background', 'disabled_text', 'disabled_border',
		'header_background', 'header_text', 'header_border',
		'header_hover_background', 'header_hover_text',
		'header_current_background', 'header_current_text',
		'footer_background', 'footer_text', 'footer_border',
		'footer_hover_background', 'footer_hover_text',
		'footer_current_background', 'footer_current_text',
	);

	$saved_buttons = !empty($project_data['buttons']) && is_array($project_data['buttons'])
		? $project_data['buttons']
		: array();

	foreach ($color_keys as $key)
		if (!array_key_exists($key, $saved_buttons) && !empty($legacy_navigation[$key]))
			$buttons[$key] = $legacy_navigation[$key];

	return $buttons;
}

function themelab_get_project_buttons($project)
{
	$defaults = themelab_get_default_buttons();
	$project_data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array']
		: array();
	$saved = !empty($project_data['buttons']) && is_array($project_data['buttons'])
		? $project_data['buttons']
		: array();

	$buttons = array_merge($defaults, $saved);
	$buttons = themelab_migrate_navigation_colors_to_buttons($buttons, $project_data);
	$buttons['font_weight'] = themelab_normalize_font_weight($buttons['font_weight'], $defaults['font_weight']);

	return $buttons;
}

function themelab_validate_buttons($submitted)
{
	$defaults = themelab_get_default_buttons();
	$errors = array();
	$buttons = array();

	$buttons['border_radius'] = isset($submitted['border_radius']) ? (int) $submitted['border_radius'] : $defaults['border_radius'];
	if ($buttons['border_radius'] < 0 || $buttons['border_radius'] > 40)
		$errors[] = 'themelab_buttons_error_radius';

	$buttons['border_width'] = isset($submitted['border_width']) ? (int) $submitted['border_width'] : $defaults['border_width'];
	if ($buttons['border_width'] < 0 || $buttons['border_width'] > 5)
		$errors[] = 'themelab_buttons_error_border';

	$buttons['padding_vertical'] = isset($submitted['padding_vertical']) ? (int) $submitted['padding_vertical'] : $defaults['padding_vertical'];
	if ($buttons['padding_vertical'] < 3 || $buttons['padding_vertical'] > 24)
		$errors[] = 'themelab_buttons_error_vertical_padding';

	$buttons['padding_horizontal'] = isset($submitted['padding_horizontal']) ? (int) $submitted['padding_horizontal'] : $defaults['padding_horizontal'];
	if ($buttons['padding_horizontal'] < 6 || $buttons['padding_horizontal'] > 48)
		$errors[] = 'themelab_buttons_error_horizontal_padding';

	$buttons['font_weight'] = themelab_normalize_font_weight(
		isset($submitted['font_weight']) ? $submitted['font_weight'] : '',
		$defaults['font_weight']
	);
	$buttons['text_transform'] = !empty($submitted['text_transform']) && in_array($submitted['text_transform'], array('none', 'uppercase'), true)
		? $submitted['text_transform'] : $defaults['text_transform'];
	$buttons['depth'] = !empty($submitted['depth']) && in_array($submitted['depth'], array('flat', 'soft', 'raised'), true)
		? $submitted['depth'] : $defaults['depth'];
	$buttons['hover_effect'] = !empty($submitted['hover_effect']) && in_array($submitted['hover_effect'], array('none', 'darken', 'lift', 'glow'), true)
		? $submitted['hover_effect'] : $defaults['hover_effect'];

	$color_keys = array(
		'primary_text', 'secondary_text',
		'danger_background', 'danger_text', 'danger_border',
		'disabled_background', 'disabled_text', 'disabled_border',
		'header_background', 'header_text', 'header_border',
		'header_hover_background', 'header_hover_text',
		'header_current_background', 'header_current_text',
		'footer_background', 'footer_text', 'footer_border',
		'footer_hover_background', 'footer_hover_text',
		'footer_current_background', 'footer_current_text',
	);
	foreach ($color_keys as $key)
	{
		$normalized = themelab_normalize_hex_color(isset($submitted[$key]) ? $submitted[$key] : '');
		$buttons[$key] = $normalized !== false ? $normalized : $defaults[$key];
		if ($normalized === false)
			$errors[] = 'themelab_buttons_error_color';
	}

	return array('buttons' => $buttons, 'errors' => array_values(array_unique($errors)));
}

function themelab_save_buttons($id_project, $buttons)
{
	$project = themelab_get_project_by_id($id_project);
	if (empty($project))
		return false;

	$project_data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array'] : array();
	if (empty($project_data['schema']))
		$project_data['schema'] = 1;

	$project_data['buttons'] = $buttons;
	themelab_save_project_data($id_project, $project_data);
	return true;
}

function themelab_handle_button_actions()
{
	global $context;
	if (empty($_POST['themelab_save_buttons']))
		return;

	checkSession();
	$id_project = themelab_get_active_project_id();
	if (empty($id_project))
		redirectexit('action=admin;area=themelab;sa=project');

	$submitted = array(
		'border_radius' => $_POST['themelab_button_border_radius'] ?? '',
		'border_width' => $_POST['themelab_button_border_width'] ?? '',
		'padding_vertical' => $_POST['themelab_button_padding_vertical'] ?? '',
		'padding_horizontal' => $_POST['themelab_button_padding_horizontal'] ?? '',
		'font_weight' => $_POST['themelab_button_font_weight'] ?? '',
		'text_transform' => $_POST['themelab_button_text_transform'] ?? '',
		'depth' => $_POST['themelab_button_depth'] ?? '',
		'hover_effect' => $_POST['themelab_button_hover_effect'] ?? '',
	);

	foreach (array(
		'primary_text', 'secondary_text',
		'danger_background', 'danger_text', 'danger_border',
		'disabled_background', 'disabled_text', 'disabled_border',
		'header_background', 'header_text', 'header_border',
		'header_hover_background', 'header_hover_text',
		'header_current_background', 'header_current_text',
		'footer_background', 'footer_text', 'footer_border',
		'footer_hover_background', 'footer_hover_text',
		'footer_current_background', 'footer_current_text',
	) as $key)
		$submitted[$key] = $_POST['themelab_button_' . $key] ?? '';

	$result = themelab_validate_buttons($submitted);
	$context['themelab_buttons'] = $result['buttons'];
	$context['themelab_button_errors'] = $result['errors'];
	if (!empty($result['errors']))
		return;

	if (!themelab_save_buttons($id_project, $result['buttons']))
	{
		$context['themelab_button_errors'][] = 'themelab_buttons_error_save';
		return;
	}

	redirectexit('action=admin;area=themelab;sa=buttons;buttons_saved');
}
