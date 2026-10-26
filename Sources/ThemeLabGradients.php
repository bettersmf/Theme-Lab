<?php

/**
 * Theme Lab Gradients
 *
 * Central Gradient Studio and shared gradient rendering helpers.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

function themelab_get_gradient_surfaces()
{
	return array(
		'header' => array('label' => 'themelab_gradient_surface_header'),
		'footer' => array('label' => 'themelab_gradient_surface_footer'),
		'category_header' => array('label' => 'themelab_gradient_surface_category'),
		'card_header' => array('label' => 'themelab_gradient_surface_card'),
	);
}

function themelab_get_default_gradient_surface($surface)
{
	$defaults = array(
		'header' => array('#3465A4', '#204A87'),
		'footer' => array('#D5DEE5', '#B9C8D3'),
		'category_header' => array('#3465A4', '#204A87'),
		'card_header' => array('#5B87B2', '#3465A4'),
	);
	$colors = isset($defaults[$surface]) ? $defaults[$surface] : $defaults['header'];

	return array(
		'enabled' => in_array($surface, array('header', 'footer'), true) ? 1 : 0,
		'start' => $colors[0],
		'end' => $colors[1],
		'type' => 'linear',
		'flow' => 'left_right',
		'softness' => 50,
	);
}

function themelab_get_project_gradients($project)
{
	$saved = !empty($project['project_data_array']['gradients']) && is_array($project['project_data_array']['gradients'])
		? $project['project_data_array']['gradients'] : array();
	$result = array();

	foreach (themelab_get_gradient_surfaces() as $surface => $definition)
	{
		$result[$surface] = array_merge(
			themelab_get_default_gradient_surface($surface),
			!empty($saved[$surface]) && is_array($saved[$surface]) ? $saved[$surface] : array()
		);

		// Migrate RC5.6.2's combined direction field into the v2 type + flow model.
		if (!empty($saved[$surface]['direction']) && empty($saved[$surface]['flow']))
		{
			$result[$surface]['type'] = $saved[$surface]['direction'] === 'center' ? 'center' : 'linear';
			$result[$surface]['flow'] = $saved[$surface]['direction'] === 'center' ? 'left_right' : $saved[$surface]['direction'];
		}

		if ($result[$surface]['type'] === 'center')
			$result[$surface]['flow'] = in_array($result[$surface]['flow'], array('top_bottom', 'bottom_top'), true)
				? 'top_bottom'
				: 'left_right';
	}

	// Adopt existing Header/Footer Studio values on first visit.
	foreach (array('header', 'footer') as $surface)
	{
		if (!empty($saved[$surface]))
			continue;

		$legacy = !empty($project['project_data_array'][$surface]) && is_array($project['project_data_array'][$surface])
			? $project['project_data_array'][$surface] : array();
		if (!empty($legacy['gradient_start'])) $result[$surface]['start'] = $legacy['gradient_start'];
		if (!empty($legacy['gradient_end'])) $result[$surface]['end'] = $legacy['gradient_end'];
		if (!empty($legacy['background_mode'])) $result[$surface]['enabled'] = $legacy['background_mode'] === 'gradient' ? 1 : 0;
		if (!empty($legacy['gradient_direction']))
			$result[$surface]['flow'] = $legacy['gradient_direction'] === 'vertical' ? 'top_bottom' : 'left_right';
	}

	return $result;
}

function themelab_normalize_gradient_surface($submitted, $surface, &$errors)
{
	$defaults = themelab_get_default_gradient_surface($surface);
	$types = array('linear', 'center');
	$flows = array('left_right', 'right_left', 'top_bottom', 'bottom_top');
	$start = themelab_normalize_hex_color(isset($submitted['start']) ? $submitted['start'] : '');
	$end = themelab_normalize_hex_color(isset($submitted['end']) ? $submitted['end'] : '');

	if ($start === false || $end === false)
		$errors[] = 'themelab_gradient_error_color';

	$softness = isset($submitted['softness']) ? (int) $submitted['softness'] : $defaults['softness'];
	if ($softness < 10 || $softness > 90)
		$errors[] = 'themelab_gradient_error_softness';

	$type = !empty($submitted['type']) && in_array($submitted['type'], $types, true)
		? $submitted['type'] : $defaults['type'];
	$flow = !empty($submitted['flow']) && in_array($submitted['flow'], $flows, true)
		? $submitted['flow'] : $defaults['flow'];

	// Center Highlight is symmetric. Reverse directions are visually identical,
	// so store only horizontal (left_right) or vertical (top_bottom) orientation.
	if ($type === 'center')
		$flow = in_array($flow, array('top_bottom', 'bottom_top'), true) ? 'top_bottom' : 'left_right';

	return array(
		'enabled' => !empty($submitted['enabled']) ? 1 : 0,
		'start' => $start !== false ? $start : $defaults['start'],
		'end' => $end !== false ? $end : $defaults['end'],
		'type' => $type,
		'flow' => $flow,
		'softness' => max(10, min(90, $softness)),
	);
}

function themelab_handle_gradient_actions()
{
	global $context;

	if (empty($_POST['themelab_save_gradients']))
		return;

	checkSession();
	$id_project = themelab_get_active_project_id();
	if (empty($id_project))
		redirectexit('action=admin;area=themelab;sa=project');

	$errors = array();
	$gradients = array();
	foreach (themelab_get_gradient_surfaces() as $surface => $definition)
	{
		$gradients[$surface] = themelab_normalize_gradient_surface(array(
			'enabled' => !empty($_POST['themelab_gradient_' . $surface . '_enabled']),
			'start' => isset($_POST['themelab_gradient_' . $surface . '_start']) ? $_POST['themelab_gradient_' . $surface . '_start'] : '',
			'end' => isset($_POST['themelab_gradient_' . $surface . '_end']) ? $_POST['themelab_gradient_' . $surface . '_end'] : '',
			'type' => isset($_POST['themelab_gradient_' . $surface . '_type']) ? $_POST['themelab_gradient_' . $surface . '_type'] : '',
			'flow' => isset($_POST['themelab_gradient_' . $surface . '_flow']) ? $_POST['themelab_gradient_' . $surface . '_flow'] : '',
			'softness' => isset($_POST['themelab_gradient_' . $surface . '_softness']) ? $_POST['themelab_gradient_' . $surface . '_softness'] : 50,
		), $surface, $errors);
	}

	$context['themelab_gradients'] = $gradients;
	$context['themelab_gradient_errors'] = array_values(array_unique($errors));
	if (!empty($errors))
		return;

	$project = themelab_get_project_by_id($id_project);
	$project_data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array'] : array();
	if (empty($project_data['schema'])) $project_data['schema'] = 1;
	$project_data['gradients'] = $gradients;

	// Gradient Studio is the sole authority for gradient state.
	// Header/Footer retain their own solid colors, artwork and geometry.
	themelab_save_project_data($id_project, $project_data);
	redirectexit('action=admin;area=themelab;sa=gradients;gradients_saved');
}

function themelab_build_gradient_css($gradient)
{
	$start = !empty($gradient['start']) ? $gradient['start'] : '#3465A4';
	$end = !empty($gradient['end']) ? $gradient['end'] : '#204A87';
	$type = !empty($gradient['type']) && $gradient['type'] === 'center' ? 'center' : 'linear';
	$flow = !empty($gradient['flow']) ? $gradient['flow'] : 'left_right';
	$softness = isset($gradient['softness']) ? max(10, min(90, (int) $gradient['softness'])) : 50;
	$low = 50 - ($softness / 2);
	$high = 50 + ($softness / 2);
	$angles = array('left_right' => '90deg', 'right_left' => '270deg', 'top_bottom' => '180deg', 'bottom_top' => '0deg');
	$angle = isset($angles[$flow]) ? $angles[$flow] : '90deg';

	if ($type === 'center')
		return 'linear-gradient(' . $angle . ', ' . $end . ' 0%, ' . $end . ' ' . $low . '%, ' . $start . ' 50%, ' . $end . ' ' . $high . '%, ' . $end . ' 100%)';

	return 'linear-gradient(' . $angle . ', ' . $start . ' 0%, ' . $start . ' ' . $low . '%, ' . $end . ' ' . $high . '%, ' . $end . ' 100%)';
}
