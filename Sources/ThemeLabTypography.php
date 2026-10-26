<?php

/**
 * Theme Lab Typography
 *
 * Typography Studio project functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');


/**
 * Return the canonical Theme Lab font-weight scale.
 *
 * All Studios, the Preview Engine, Theme Health, and the future Compiler
 * must consume this definition rather than maintaining local weight lists.
 */
function themelab_get_font_weights()
{
	return array(
		'400' => 'regular',
		'500' => 'medium',
		'700' => 'bold',
	);
}

/**
 * Normalize a font weight against the shared Theme Lab scale.
 *
 * Legacy projects used 600 for Semi Bold. Web-safe font stacks often
 * synthesize 600 and 700 almost identically, so 600 is migrated to the
 * semantic Medium step at 500. Preview and compiled styling add a subtle
 * Core-managed synthetic stroke so two-face web-safe fonts still render
 * Regular, Medium, and Bold as visibly distinct choices.
 */
function themelab_normalize_font_weight($weight, $default = '400')
{
	$weight = (string) $weight;
	$default = (string) $default;

	if ($weight === '600')
		$weight = '500';

	$weights = themelab_get_font_weights();

	if (!isset($weights[$default]))
		$default = '400';

	return isset($weights[$weight]) ? $weight : $default;
}


/**
 * Return the synthetic emphasis used for Theme Lab's semantic Medium weight.
 *
 * Most bundled web-safe fonts expose only regular and bold faces. Browsers
 * therefore render numeric 500 exactly like 400. Theme Lab preserves 500 in
 * project data while applying this small stroke at render time.
 */
function themelab_get_medium_font_stroke()
{
	return '0.22px';
}

/**
 * Return whether a normalized weight uses Theme Lab's synthetic Medium style.
 */
function themelab_is_medium_font_weight($weight)
{
	return themelab_normalize_font_weight($weight) === '500';
}

/**
 * Return whether a submitted font weight is part of the canonical scale.
 */
function themelab_is_font_weight($weight)
{
	return isset(themelab_get_font_weights()[(string) $weight]);
}

/**
 * Return the fonts supported by Typography Studio.
 */
function themelab_get_typography_fonts()
{
	return array(
		'arial' => array(
			'name' => 'Arial',
			'family' => 'Arial, Helvetica, sans-serif',
			'type' => 'Sans Serif',
		),
		'verdana' => array(
			'name' => 'Verdana',
			'family' => 'Verdana, Geneva, sans-serif',
			'type' => 'Sans Serif',
		),
		'tahoma' => array(
			'name' => 'Tahoma',
			'family' => 'Tahoma, Geneva, sans-serif',
			'type' => 'Sans Serif',
		),
		'trebuchet' => array(
			'name' => 'Trebuchet MS',
			'family' => '"Trebuchet MS", Helvetica, sans-serif',
			'type' => 'Sans Serif',
		),
		'segoe' => array(
			'name' => 'Segoe UI',
			'family' => '"Segoe UI", Arial, sans-serif',
			'type' => 'Sans Serif',
		),
		'georgia' => array(
			'name' => 'Georgia',
			'family' => 'Georgia, "Times New Roman", serif',
			'type' => 'Serif',
		),
		'times' => array(
			'name' => 'Times New Roman',
			'family' => '"Times New Roman", Times, serif',
			'type' => 'Serif',
		),
	);
}

/**
 * Return the supported monospace fonts.
 */
function themelab_get_monospace_fonts()
{
	return array(
		'consolas' => array(
			'name' => 'Consolas',
			'family' => 'Consolas, Monaco, monospace',
		),
		'courier' => array(
			'name' => 'Courier New',
			'family' => '"Courier New", Courier, monospace',
		),
		'monospace' => array(
			'name' => 'Lucida Console',
			'family' => '"Lucida Console", "Lucida Sans Typewriter", monospace',
		),
	);
}

/**
 * Return the default typography settings.
 */
function themelab_get_default_typography()
{
	return array(
		'font_family' => 'verdana',
		'font_size' => 14,
		'line_spacing' => 'comfortable',
		'heading_weight' => '700',
		'monospace_font' => 'consolas',
	);
}

/**
 * Return typography settings saved in a project.
 */
function themelab_get_project_typography($project)
{
	$defaults = themelab_get_default_typography();

	if (
		empty($project['project_data_array'])
		|| !is_array($project['project_data_array'])
		|| empty($project['project_data_array']['typography'])
		|| !is_array($project['project_data_array']['typography'])
	)
		return $defaults;

		$typography = array_merge(
		$defaults,
		$project['project_data_array']['typography']
	);

	$typography['heading_weight'] = themelab_normalize_font_weight(
		$typography['heading_weight'],
		$defaults['heading_weight']
	);

	return $typography;
}

/**
 * Validate and normalize submitted typography settings.
 */
function themelab_validate_typography($submitted)
{
	$defaults = themelab_get_default_typography();
	$fonts = themelab_get_typography_fonts();
	$monospace_fonts = themelab_get_monospace_fonts();
	$errors = array();

	$typography = array();

	$typography['font_family'] =
		!empty($submitted['font_family'])
		&& isset($fonts[$submitted['font_family']])
			? $submitted['font_family']
			: $defaults['font_family'];

	$typography['font_size'] = isset($submitted['font_size'])
		? (int) $submitted['font_size']
		: $defaults['font_size'];

	if (
		$typography['font_size'] < 10
		|| $typography['font_size'] > 22
	)
		$errors[] = 'themelab_typography_error_font_size';

	$typography['line_spacing'] =
		!empty($submitted['line_spacing'])
		&& in_array(
			$submitted['line_spacing'],
			array('compact', 'comfortable', 'relaxed'),
			true
		)
			? $submitted['line_spacing']
			: $defaults['line_spacing'];

	$typography['heading_weight'] = themelab_normalize_font_weight(
		isset($submitted['heading_weight']) ? $submitted['heading_weight'] : '',
		$defaults['heading_weight']
	);

	$typography['monospace_font'] =
		!empty($submitted['monospace_font'])
		&& isset($monospace_fonts[$submitted['monospace_font']])
			? $submitted['monospace_font']
			: $defaults['monospace_font'];

	return array(
		'typography' => $typography,
		'errors' => $errors,
	);
}

/**
 * Save typography settings to a project.
 */
function themelab_save_typography($id_project, $typography)
{
	$project = themelab_get_project_by_id($id_project);

	if (empty($project))
		return false;

	$project_data =
		!empty($project['project_data_array'])
		&& is_array($project['project_data_array'])
			? $project['project_data_array']
			: array();

	if (empty($project_data['schema']))
		$project_data['schema'] = 1;

	$project_data['typography'] = $typography;

	themelab_save_project_data($id_project, $project_data);

	return true;
}

/**
 * Handle Typography Studio form actions.
 */
function themelab_handle_typography_actions()
{
	global $context;

	if (empty($_POST['themelab_save_typography']))
		return;

	checkSession();

	$id_project = themelab_get_active_project_id();

	if (empty($id_project))
		redirectexit('action=admin;area=themelab;sa=project');

	$submitted = array(
		'font_family' => isset($_POST['themelab_font_family'])
			? (string) $_POST['themelab_font_family']
			: '',
		'font_size' => isset($_POST['themelab_font_size'])
			? $_POST['themelab_font_size']
			: '',
		'line_spacing' => isset($_POST['themelab_line_spacing'])
			? (string) $_POST['themelab_line_spacing']
			: '',
		'heading_weight' => isset($_POST['themelab_heading_weight'])
			? (string) $_POST['themelab_heading_weight']
			: '',
		'monospace_font' => isset($_POST['themelab_monospace_font'])
			? (string) $_POST['themelab_monospace_font']
			: '',
	);

	$result = themelab_validate_typography($submitted);

	$context['themelab_typography'] = $result['typography'];
	$context['themelab_typography_errors'] = $result['errors'];

	if (!empty($result['errors']))
		return;

	if (
		!themelab_save_typography(
			$id_project,
			$result['typography']
		)
	)
	{
		$context['themelab_typography_errors'][] =
			'themelab_typography_error_save';

		return;
	}

	redirectexit(
		'action=admin;area=themelab;sa=typography;typography_saved'
	);
}