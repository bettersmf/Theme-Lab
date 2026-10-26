<?php

/**
 * Theme Lab Footer
 *
 * Footer Studio project functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Normalize administrator-authored footer text as plain text while allowing
 * HTML character entities such as &copy;, &reg;, &#169;, etc.
 *
 * Markup is never rendered: encoded or literal tags are stripped after entity
 * decoding, leaving only their text content. This keeps Footer Studio a plain
 * text field while making common entities useful.
 */
function themelab_footer_plain_text($value)
{
	$value = (string) $value;

	// Older projects may contain one or more layers of htmlspecialchars()
	// encoding, so decode a few bounded passes until the value stabilizes.
	for ($i = 0; $i < 4; $i++)
	{
		$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		if ($decoded === $value)
			break;

		$value = $decoded;
	}

	$value = strip_tags($value);
	$value = str_replace(array("\r\n", "\r"), "\n", $value);

	return trim($value);
}

/**
 * Return default Footer Studio settings.
 */
function themelab_get_default_footer()
{
	return array(
		'height' => 70,
		'footer_text' => '',
		'text_alignment' => 'center',
		'background_mode' => 'solid',
		'background_color' => '#D5DEE5',
		'gradient_start' => '#D5DEE5',
		'gradient_end' => '#B9C8D3',
        'gradient_direction' => 'horizontal',
		'text_color' => '#222222',
		'link_color' => '#3465A4',
		'show_credit' => 1,
	);
}

/**
 * Return footer settings saved in a project.
 */
function themelab_get_project_footer($project)
{
	$defaults = themelab_get_default_footer();

	if (
		empty($project['project_data_array'])
		|| !is_array($project['project_data_array'])
		|| empty($project['project_data_array']['footer'])
		|| !is_array($project['project_data_array']['footer'])
	)
		return $defaults;

	$footer = array_merge(
		$defaults,
		$project['project_data_array']['footer']
	);

	// RC5.6.0 retires the old placeholder copy without erasing
	// administrator-authored footer text.
	if (isset($footer['footer_text'])
		&& $footer['footer_text'] === 'Powered by SMF and Theme Lab')
		$footer['footer_text'] = '';

	// RC6.1.21ao: normalize legacy escaped values and resolve HTML entities
	// to their plain-text characters for Studio preview and publishing.
	$footer['footer_text'] = themelab_footer_plain_text($footer['footer_text']);

	return $footer;
}

/**
 * Build the concise SMF credit used by Footer Studio and the shared preview.
 */
function themelab_get_footer_credit_text()
{
	$version = defined('SMF_VERSION')
		? SMF_VERSION
		: (!empty($GLOBALS['forum_version'])
			? $GLOBALS['forum_version']
			: '2.1');

	return 'SMF ' . $version . ' © ' . date('Y') . ', Simple Machines';
}

/**
 * Validate and normalize submitted Footer Studio settings.
 */
function themelab_validate_footer($submitted)
{
	$defaults = themelab_get_default_footer();
	$errors = array();
	$footer = array();

	$footer['height'] = isset($submitted['height'])
		? (int) $submitted['height']
		: $defaults['height'];

	if ($footer['height'] < 40 || $footer['height'] > 300)
		$errors[] = 'themelab_footer_error_height';

	$footer['footer_text'] = isset($submitted['footer_text'])
		? themelab_footer_plain_text($submitted['footer_text'])
		: $defaults['footer_text'];

	$footer['text_alignment'] =
		!empty($submitted['text_alignment'])
		&& in_array(
			$submitted['text_alignment'],
			array('left', 'center', 'right'),
			true
		)
			? $submitted['text_alignment']
			: $defaults['text_alignment'];

	foreach (
		array(
			'background_color',
			'text_color',
			'link_color',
		) as $field
	)
	{
		$normalized = isset($submitted[$field])
			? themelab_normalize_hex_color($submitted[$field])
			: false;

		if ($normalized === false)
		{
			$errors[] = 'themelab_footer_error_color';
			$footer[$field] = $defaults[$field];
		}
		else
		{
			$footer[$field] = $normalized;
		}
	}

	$footer['show_credit'] = !empty($submitted['show_credit'])
		? 1
		: 0;

	return array(
		'footer' => $footer,
		'errors' => array_values(array_unique($errors)),
	);
}

/**
 * Save Footer Studio settings.
 */
function themelab_save_footer($id_project, $footer)
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

	$existing_footer = !empty($project_data['footer'])
		&& is_array($project_data['footer'])
			? $project_data['footer']
			: array();

	// Footer Studio owns structure and solid colors only. Preserve any
	// centralized Gradient Studio metadata already stored for this surface.
	$project_data['footer'] = array_merge($existing_footer, $footer);

	themelab_save_project_data($id_project, $project_data);

	return true;
}

/**
 * Handle Footer Studio form actions.
 */
function themelab_handle_footer_actions()
{
	global $context;

	if (empty($_POST['themelab_save_footer']))
		return;

	checkSession();

	$id_project = themelab_get_active_project_id();

	if (empty($id_project))
		redirectexit('action=admin;area=themelab;sa=project');

	$submitted = array(
		'height' => isset($_POST['themelab_footer_height'])
			? $_POST['themelab_footer_height']
			: '',

		'footer_text' => isset($_POST['themelab_footer_text'])
			? $_POST['themelab_footer_text']
			: '',

		'text_alignment' => isset($_POST['themelab_footer_text_alignment'])
			? $_POST['themelab_footer_text_alignment']
			: '',

		'background_color' => isset($_POST['themelab_footer_background_color'])
			? $_POST['themelab_footer_background_color']
			: '',

		'text_color' => isset($_POST['themelab_footer_text_color'])
			? $_POST['themelab_footer_text_color']
			: '',
		'link_color' => isset($_POST['themelab_footer_link_color'])
			? $_POST['themelab_footer_link_color']
			: '',

		'show_credit' => !empty($_POST['themelab_footer_show_credit'])
			? 1
			: 0,
	);

	$result = themelab_validate_footer($submitted);

	$context['themelab_footer'] = $result['footer'];
	$context['themelab_footer_errors'] = $result['errors'];

	if (!empty($result['errors']))
		return;

	if (!themelab_save_footer($id_project, $result['footer']))
	{
		$context['themelab_footer_errors'][] =
			'themelab_footer_error_save';

		return;
	}

	redirectexit(
		'action=admin;area=themelab;sa=footer;footer_saved'
	);
}