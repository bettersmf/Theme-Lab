<?php

/**
 * Theme Lab Navigation
 *
 * Menu and Navigation Studio project functions. This Studio owns menu layout
 * and behavior; button-state colors are owned by Buttons Studio.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

function themelab_get_default_navigation()
{
	return array(
		'menu_radius' => 5,
		'menu_gap' => 6,
		'menu_padding_vertical' => 6,
		'menu_padding_horizontal' => 12,
		'font_weight' => '500',
		'text_transform' => 'none',
		'breadcrumb_style' => 'plain',
		'breadcrumb_separator' => 'chevron',
		'pagination_style' => 'boxed',
		'pagination_active_emphasis' => 'filled',
		'pagination_size' => 'standard',
		'pagination_gap' => 5,
		'pagination_radius' => 5,
		'pagination_active_weight' => 'balanced',
		'active_emphasis' => 'filled',
		'footer_style' => 'text',
	);
}

function themelab_get_project_navigation($project)
{
	$defaults = themelab_get_default_navigation();
	$saved = !empty($project['project_data_array']['navigation']) && is_array($project['project_data_array']['navigation'])
		? $project['project_data_array']['navigation'] : array();
	$navigation = array_intersect_key(array_merge($defaults, $saved), $defaults);
	$navigation['font_weight'] = themelab_normalize_font_weight($navigation['font_weight'], $defaults['font_weight']);
	return $navigation;
}

function themelab_validate_navigation($submitted)
{
	$defaults = themelab_get_default_navigation();
	$errors = array();
	$navigation = array();

	$navigation['menu_radius'] = isset($submitted['menu_radius']) ? (int) $submitted['menu_radius'] : $defaults['menu_radius'];
	if ($navigation['menu_radius'] < 0 || $navigation['menu_radius'] > 30)
		$errors[] = 'themelab_navigation_error_radius';
	$navigation['menu_gap'] = isset($submitted['menu_gap']) ? (int) $submitted['menu_gap'] : $defaults['menu_gap'];
	if ($navigation['menu_gap'] < 0 || $navigation['menu_gap'] > 24)
		$errors[] = 'themelab_navigation_error_gap';
	$navigation['menu_padding_vertical'] = isset($submitted['menu_padding_vertical']) ? (int) $submitted['menu_padding_vertical'] : $defaults['menu_padding_vertical'];
	if ($navigation['menu_padding_vertical'] < 2 || $navigation['menu_padding_vertical'] > 20)
		$errors[] = 'themelab_navigation_error_vertical_padding';
	$navigation['menu_padding_horizontal'] = isset($submitted['menu_padding_horizontal']) ? (int) $submitted['menu_padding_horizontal'] : $defaults['menu_padding_horizontal'];
	if ($navigation['menu_padding_horizontal'] < 4 || $navigation['menu_padding_horizontal'] > 36)
		$errors[] = 'themelab_navigation_error_horizontal_padding';

	$navigation['pagination_gap'] = isset($submitted['pagination_gap']) ? (int) $submitted['pagination_gap'] : $defaults['pagination_gap'];
	if ($navigation['pagination_gap'] < 0 || $navigation['pagination_gap'] > 16)
		$errors[] = 'themelab_navigation_error_pagination_gap';
	$navigation['pagination_radius'] = isset($submitted['pagination_radius']) ? (int) $submitted['pagination_radius'] : $defaults['pagination_radius'];
	if ($navigation['pagination_radius'] < 0 || $navigation['pagination_radius'] > 24)
		$errors[] = 'themelab_navigation_error_pagination_radius';

	$allowed = array(
		'font_weight' => array_keys(themelab_get_font_weights()),
		'text_transform' => array('none', 'uppercase'),
		'breadcrumb_style' => array('plain', 'bar', 'boxed'),
		'breadcrumb_separator' => array('chevron', 'slash', 'dot'),
		'pagination_style' => array('plain', 'boxed', 'pills'),
		'pagination_active_emphasis' => array('filled', 'underline', 'outline'),
		'pagination_size' => array('compact', 'standard', 'large'),
		'pagination_active_weight' => array('balanced', '400', '500', '600', '700'),
		'active_emphasis' => array('filled', 'underline', 'outline'),
		'footer_style' => array('text', 'buttons'),
	);
	foreach ($allowed as $key => $values)
		$navigation[$key] = isset($submitted[$key]) && in_array((string) $submitted[$key], $values, true)
			? (string) $submitted[$key] : $defaults[$key];
	$navigation['font_weight'] = themelab_normalize_font_weight($navigation['font_weight'], $defaults['font_weight']);

	return array('navigation' => $navigation, 'errors' => array_values(array_unique($errors)));
}

function themelab_save_navigation($id_project, $navigation)
{
	$project = themelab_get_project_by_id($id_project);
	if (empty($project)) return false;
	$project_data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array'] : array();
	if (empty($project_data['schema'])) $project_data['schema'] = 1;

	// Preserve legacy color keys until Buttons Studio has saved its migrated copy.
	$legacy = !empty($project_data['navigation']) && is_array($project_data['navigation'])
		? $project_data['navigation'] : array();
	$project_data['navigation'] = array_merge($legacy, $navigation);
	themelab_save_project_data($id_project, $project_data);
	return true;
}

function themelab_handle_navigation_actions()
{
	global $context;
	if (empty($_POST['themelab_save_navigation'])) return;
	checkSession();
	$id_project = themelab_get_active_project_id();
	if (empty($id_project)) redirectexit('action=admin;area=themelab;sa=project');
	$submitted = array();
	foreach (array('menu_radius','menu_gap','menu_padding_vertical','menu_padding_horizontal','font_weight','text_transform','breadcrumb_style','breadcrumb_separator','pagination_style','pagination_active_emphasis','pagination_size','pagination_gap','pagination_radius','pagination_active_weight','active_emphasis','footer_style') as $key)
		$submitted[$key] = $_POST['themelab_navigation_' . $key] ?? '';
	$result = themelab_validate_navigation($submitted);
	$context['themelab_navigation'] = $result['navigation'];
	$context['themelab_navigation_errors'] = $result['errors'];
	if (!empty($result['errors'])) return;
	if (!themelab_save_navigation($id_project, $result['navigation']))
	{
		$context['themelab_navigation_errors'][] = 'themelab_navigation_error_save';
		return;
	}
	redirectexit('action=admin;area=themelab;sa=navigation;navigation_saved');
}
