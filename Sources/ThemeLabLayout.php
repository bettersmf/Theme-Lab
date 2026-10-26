<?php

/**
 * Theme Lab Layout
 *
 * Layout Studio project functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Return the default Theme Lab layout settings.
 */
function themelab_get_default_layout()
{
	return array(
		'width_mode' => 'fixed',
		'max_width' => 1200,
		'border_radius' => 6,
		'content_spacing' => 'medium',
		'shadow_style' => 'soft',
	);
}

/**
 * Return the layout currently saved in a project.
 */
function themelab_get_project_layout($project)
{
	$defaults = themelab_get_default_layout();

	if (
		empty($project['project_data_array'])
		|| !is_array($project['project_data_array'])
		|| empty($project['project_data_array']['layout'])
		|| !is_array($project['project_data_array']['layout'])
	)
		return $defaults;

	$layout = array_merge(
		$defaults,
		$project['project_data_array']['layout']
	);

	// RC5.4.4: Dark Companion is a Publish Wizard decision, not a Layout setting.
	unset($layout['dark_mode']);

	return $layout;
}

/**
 * Validate and normalize submitted layout settings.
 */
function themelab_validate_layout($submitted)
{
	$defaults = themelab_get_default_layout();
	$errors = array();

	$layout = array();

	$layout['width_mode'] = !empty($submitted['width_mode'])
		&& in_array($submitted['width_mode'], array('fixed', 'fluid'), true)
			? $submitted['width_mode']
			: $defaults['width_mode'];

	$layout['max_width'] = isset($submitted['max_width'])
		? (int) $submitted['max_width']
		: $defaults['max_width'];

	if ($layout['max_width'] < 800 || $layout['max_width'] > 2400)
		$errors[] = 'themelab_layout_error_max_width';

	$layout['border_radius'] = isset($submitted['border_radius'])
		? (int) $submitted['border_radius']
		: $defaults['border_radius'];

	if ($layout['border_radius'] < 0 || $layout['border_radius'] > 40)
		$errors[] = 'themelab_layout_error_radius';

	$layout['content_spacing'] = !empty($submitted['content_spacing'])
		&& in_array(
			$submitted['content_spacing'],
			array('compact', 'medium', 'comfortable'),
			true
		)
			? $submitted['content_spacing']
			: $defaults['content_spacing'];

	$layout['shadow_style'] = !empty($submitted['shadow_style'])
		&& in_array(
			$submitted['shadow_style'],
			array('none', 'soft', 'medium', 'strong'),
			true
		)
			? $submitted['shadow_style']
			: $defaults['shadow_style'];


	return array(
		'layout' => $layout,
		'errors' => $errors,
	);
}

/**
 * Save layout settings to a Theme Lab project.
 */
function themelab_save_layout($id_project, $layout)
{
	$project = themelab_get_project_by_id($id_project);

	if (empty($project))
		return false;

	$project_data = !empty($project['project_data_array'])
		&& is_array($project['project_data_array'])
			? $project['project_data_array']
			: array();

	if (empty($project_data['schema']))
		$project_data['schema'] = 1;

	$project_data['layout'] = $layout;

	themelab_save_project_data($id_project, $project_data);

	return true;
}

/**
 * Handle Layout Studio form actions.
 */
function themelab_handle_layout_actions()
{
	global $context;

	if (empty($_POST['themelab_save_layout']))
		return;

	checkSession();

	$id_project = themelab_get_active_project_id();

	if (empty($id_project))
		redirectexit('action=admin;area=themelab;sa=project');

	$submitted = array(
		'width_mode' => isset($_POST['themelab_width_mode'])
			? (string) $_POST['themelab_width_mode']
			: '',
		'max_width' => isset($_POST['themelab_max_width'])
			? $_POST['themelab_max_width']
			: '',
		'border_radius' => isset($_POST['themelab_border_radius'])
			? $_POST['themelab_border_radius']
			: '',
		'content_spacing' => isset($_POST['themelab_content_spacing'])
			? (string) $_POST['themelab_content_spacing']
			: '',
		'shadow_style' => isset($_POST['themelab_shadow_style'])
			? (string) $_POST['themelab_shadow_style']
			: '',
	);

	$result = themelab_validate_layout($submitted);

	$context['themelab_layout'] = $result['layout'];
	$context['themelab_layout_errors'] = $result['errors'];

	if (!empty($result['errors']))
		return;

	if (!themelab_save_layout($id_project, $result['layout']))
	{
		$context['themelab_layout_errors'][] =
			'themelab_layout_error_save';

		return;
	}

	redirectexit(
		'action=admin;area=themelab;sa=layout;layout_saved'
	);
}