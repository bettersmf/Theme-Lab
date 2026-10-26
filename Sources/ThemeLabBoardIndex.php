<?php

/**
 * Theme Lab Board Index
 *
 * Board Index Studio project functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

function themelab_get_default_board_index()
{
	return array(
		'container_radius' => 6,
		'border_width' => 1,
		'panel_padding' => 12,
		'shadow_style' => 'soft',
		'header_height' => 38,
		'header_alignment' => 'left',
		'header_weight' => '700',
		'row_spacing' => 'standard',
		'row_background' => '#FFFFFF',
		'row_background_alt' => '#F5F7F8',
		'stats_position' => 'right',
		'last_post_layout' => 'standard',
		'alternating_rows' => 'on',
		'icon_style' => 'filled',
		'header_text' => '#FFFFFF',
		'header_link' => '#FFFFFF',
		'stats_text' => '#666666',
		'description_text' => '#666666',
		'unread_indicator' => '#3465A4',
	);
}

function themelab_get_project_board_index($project)
{
	$defaults = themelab_get_default_board_index();
	if (empty($project['project_data_array']) || !is_array($project['project_data_array'])
		|| empty($project['project_data_array']['board_index']) || !is_array($project['project_data_array']['board_index']))
		return $defaults;
	$board_index = array_merge($defaults, $project['project_data_array']['board_index']);
	$board_index['header_weight'] = themelab_normalize_font_weight($board_index['header_weight'], $defaults['header_weight']);
	return $board_index;
}

function themelab_validate_board_index($submitted)
{
	$defaults = themelab_get_default_board_index();
	$errors = array();
	$data = array();
	$ranges = array(
		'container_radius' => array(0, 30, 'themelab_board_index_error_radius'),
		'border_width' => array(0, 5, 'themelab_board_index_error_border'),
		'panel_padding' => array(4, 30, 'themelab_board_index_error_padding'),
		'header_height' => array(28, 80, 'themelab_board_index_error_header_height'),
	);
	foreach ($ranges as $key => $range)
	{
		$data[$key] = isset($submitted[$key]) ? (int) $submitted[$key] : $defaults[$key];
		if ($data[$key] < $range[0] || $data[$key] > $range[1]) $errors[] = $range[2];
	}
	$allowed = array(
		'shadow_style' => array('none','soft','medium','strong'),
		'header_alignment' => array('left','center','right'),
		'header_weight' => array_keys(themelab_get_font_weights()),
		'row_spacing' => array('tight','standard','spacious'),
		'stats_position' => array('right','below'),
		'last_post_layout' => array('compact','standard','expanded'),
		'alternating_rows' => array('off','on'),
		'icon_style' => array('classic','filled','outline','modern'),
	);
	foreach ($allowed as $key => $values)
		$data[$key] = isset($submitted[$key]) && in_array((string) $submitted[$key], $values, true)
			? (string) $submitted[$key] : $defaults[$key];
	$data['header_weight'] = themelab_normalize_font_weight(
		isset($submitted['header_weight']) ? $submitted['header_weight'] : '',
		$defaults['header_weight']
	);
	foreach (array('header_text','header_link','stats_text','description_text','unread_indicator','row_background','row_background_alt') as $color_key)
	{
		$normalized = themelab_normalize_hex_color(isset($submitted[$color_key]) ? $submitted[$color_key] : '');
		$data[$color_key] = $normalized !== false ? $normalized : $defaults[$color_key];
		if ($normalized === false) $errors[] = 'themelab_board_index_error_color';
	}
	return array('board_index' => $data, 'errors' => array_values(array_unique($errors)));
}

function themelab_save_board_index($id_project, $data)
{
	$project = themelab_get_project_by_id($id_project);
	if (empty($project)) return false;
	$project_data = !empty($project['project_data_array']) && is_array($project['project_data_array']) ? $project['project_data_array'] : array();
	if (empty($project_data['schema'])) $project_data['schema'] = 1;
	$project_data['board_index'] = $data;
	themelab_save_project_data($id_project, $project_data);
	return true;
}

function themelab_handle_board_index_actions()
{
	global $context;
	if (empty($_POST['themelab_save_board_index'])) return;
	checkSession();
	$id_project = themelab_get_active_project_id();
	if (empty($id_project)) redirectexit('action=admin;area=themelab;sa=project');
	$keys = array_keys(themelab_get_default_board_index());
	$submitted = array();
	foreach ($keys as $key) $submitted[$key] = $_POST['themelab_board_index_' . $key] ?? '';
	$result = themelab_validate_board_index($submitted);
	$context['themelab_board_index'] = $result['board_index'];
	$context['themelab_board_index_errors'] = $result['errors'];
	if (!empty($result['errors'])) return;
	if (!themelab_save_board_index($id_project, $result['board_index'])) {
		$context['themelab_board_index_errors'][] = 'themelab_board_index_error_save';
		return;
	}
	redirectexit('action=admin;area=themelab;sa=board_index;board_index_saved');
}
