<?php

/**
 * Theme Lab Palette
 *
 * Palette Studio project functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Return the palette currently saved in a project.
 */
function themelab_get_project_palette($project)
{
	if (
		empty($project['project_data_array'])
		|| !is_array($project['project_data_array'])
		|| empty($project['project_data_array']['palette'])
		|| !is_array($project['project_data_array']['palette'])
	)
		return array();

	return $project['project_data_array']['palette'];
}

/**
 * Save project data without replacing unrelated project sections.
 */
function themelab_save_project_data($id_project, $project_data)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}themelab_projects
		SET
			project_data = {string:project_data},
			date_modified = {int:date_modified}
		WHERE id_project = {int:id_project}',
		array(
			'id_project' => (int) $id_project,
			'project_data' => json_encode($project_data),
			'date_modified' => time(),
		)
	);
}

/**
 * Apply a built-in palette preset to a project.
 */

/**
 * Return whether a Studio section is still effectively untouched.
 *
 * New Workspaces are populated with Studio defaults immediately, so "empty"
 * is not a valid test for first-palette seeding. Compare the stored section
 * against its defaults instead. Extra runtime/migration keys are ignored.
 */
function themelab_palette_section_is_default($section, $defaults)
{
	if (empty($section) || !is_array($section))
		return true;

	if (empty($defaults) || !is_array($defaults))
		return false;

	foreach ($defaults as $key => $default_value)
	{
		if (!array_key_exists($key, $section))
			continue;

		$current_value = $section[$key];

		if (is_bool($default_value))
		{
			if ((bool) $current_value !== $default_value)
				return false;
		}
		elseif (is_int($default_value))
		{
			if ((int) $current_value !== $default_value)
				return false;
		}
		elseif ((string) $current_value !== (string) $default_value)
			return false;
	}

	return true;
}


function themelab_apply_palette_preset($id_project, $preset_key)
{
	$project = themelab_get_project_by_id($id_project);
	$preset = themelab_get_palette_preset($preset_key);

	if (empty($project) || empty($preset) || empty($preset['colors']))
		return false;

	$project_data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array'] : array();
	$project_data['schema'] = !empty($project_data['schema']) ? (int) $project_data['schema'] : 1;

	$owned_tokens = themelab_get_palette_tokens();
	$current_palette = !empty($project_data['palette']) && is_array($project_data['palette'])
		? $project_data['palette'] : array();
	$current_colors = !empty($current_palette['colors']) && is_array($current_palette['colors'])
		? $current_palette['colors'] : array();

	// Replace only colors Palette Studio owns. Retired compatibility values are
	// preserved but never edited here.
	foreach ($owned_tokens as $token_key => $token_label)
	{
		if (!empty($preset['colors'][$token_key]))
			$current_colors[$token_key] = strtoupper($preset['colors'][$token_key]);
	}

	$current_colors['link_hover'] = !empty($preset['colors']['link_hover'])
		? strtoupper($preset['colors']['link_hover'])
		: strtoupper(!empty($preset['colors']['primary']) ? $preset['colors']['primary'] : $preset['colors']['link']);
	$current_colors['link_visited'] = !empty($preset['colors']['link_visited'])
		? strtoupper($preset['colors']['link_visited'])
		: strtoupper(!empty($preset['colors']['accent']) ? $preset['colors']['accent'] : $preset['colors']['link']);

	$project_data['palette'] = array_merge($current_palette, array(
		'preset' => $preset_key,
		'colors' => $current_colors,
		'customized' => array(),
	));

	/*
	 * First-palette seeding.
	 *
	 * A new Workspace already contains Header/Footer default arrays, so checking
	 * only for "empty" meant the first chosen palette could never seed those
	 * Studios. That is why a Desert Workspace could retain Theme Lab's default
	 * blue header.
	 *
	 * Seed each specialist only while it still matches its untouched defaults.
	 * Once the user changes that Studio, later palette changes preserve it.
	 */
	$theme_preset = themelab_get_theme_preset($preset_key);
	if (!empty($theme_preset))
	{
		$default_header = function_exists('themelab_get_default_header')
			? themelab_get_default_header()
			: array();
		$default_footer = function_exists('themelab_get_default_footer')
			? themelab_get_default_footer()
			: array();

		$header_is_virgin = themelab_palette_section_is_default(
			!empty($project_data['header']) && is_array($project_data['header']) ? $project_data['header'] : array(),
			$default_header
		);
		$footer_is_virgin = themelab_palette_section_is_default(
			!empty($project_data['footer']) && is_array($project_data['footer']) ? $project_data['footer'] : array(),
			$default_footer
		);

		if ($header_is_virgin && !empty($theme_preset['header']))
			$project_data['header'] = array_merge($default_header, $theme_preset['header']);

		if ($footer_is_virgin && !empty($theme_preset['footer']))
			$project_data['footer'] = array_merge($default_footer, $theme_preset['footer']);
	}

	themelab_save_project_data($id_project, $project_data);
	return true;
}


/**
 * Reset every color-owning Studio to values derived from the selected preset.
 *
 * Geometry, typography, uploads, visibility, layout, gradient enable/flow,
 * and all other non-color choices remain untouched.
 */
function themelab_reset_project_colors_to_preset($id_project, $preset_key)
{
	$project = themelab_get_project_by_id($id_project);
	$preset = themelab_get_palette_preset($preset_key);

	if (empty($project) || empty($preset) || empty($preset['colors']))
		return false;

	$data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array']
		: array();
	$data['schema'] = !empty($data['schema']) ? (int) $data['schema'] : 1;

	$colors = $preset['colors'];
	$primary = strtoupper($colors['primary']);
	$secondary = strtoupper($colors['secondary']);
	$accent = strtoupper($colors['accent']);
	$surface = strtoupper($colors['surface']);
	$surface_alt = strtoupper($colors['surface_alt']);
	$text = strtoupper($colors['text']);
	$muted = strtoupper($colors['muted_text']);
	$link = strtoupper($colors['link']);
	$border = strtoupper($colors['border']);
	$danger = strtoupper(!empty($colors['danger']) ? $colors['danger'] : '#B33A3A');
	$header_bg = strtoupper(!empty($colors['header']) ? $colors['header'] : $primary);
	$footer_bg = strtoupper(!empty($colors['footer']) ? $colors['footer'] : $surface_alt);

	// Palette foundation.
	$current_palette = !empty($data['palette']) && is_array($data['palette']) ? $data['palette'] : array();
	$current_palette_colors = !empty($current_palette['colors']) && is_array($current_palette['colors'])
		? $current_palette['colors']
		: array();

	foreach (themelab_get_palette_tokens() as $key => $label)
	{
		if (!empty($colors[$key]))
			$current_palette_colors[$key] = strtoupper($colors[$key]);
	}

	$current_palette_colors['link_hover'] = !empty($colors['link_hover'])
		? strtoupper($colors['link_hover'])
		: $primary;
	$current_palette_colors['link_visited'] = !empty($colors['link_visited'])
		? strtoupper($colors['link_visited'])
		: $accent;

	$data['palette'] = array_merge($current_palette, array(
		'preset' => $preset_key,
		'colors' => $current_palette_colors,
		'customized' => array(),
	));

	// Header Studio: colors only. Uploaded logo/background, geometry and layout stay intact.
	$header = !empty($data['header']) && is_array($data['header']) ? $data['header'] : array();
	$header['background_color'] = $header_bg;
	$header['text_color'] = themelab_get_contrast_text_color($header_bg);
	$data['header'] = $header;

	// Footer Studio: colors only.
	$footer = !empty($data['footer']) && is_array($data['footer']) ? $data['footer'] : array();
	$footer['background_color'] = $footer_bg;
	$footer['text_color'] = themelab_get_contrast_text_color($footer_bg);
	$footer['link_color'] = $link;
	$data['footer'] = $footer;

	// Gradient Studio: reset color stops only; retain enable/type/flow/softness.
	$gradients = !empty($data['gradients']) && is_array($data['gradients']) ? $data['gradients'] : array();
	$gradient_colors = array(
		'header' => array($header_bg, $accent),
		'footer' => array($footer_bg, $secondary),
		'category_header' => array($primary, $accent),
		'card_header' => array($secondary, $primary),
	);
	foreach ($gradient_colors as $surface_name => $stops)
	{
		$current = !empty($gradients[$surface_name]) && is_array($gradients[$surface_name])
			? $gradients[$surface_name]
			: themelab_get_default_gradient_surface($surface_name);
		$current['start'] = $stops[0];
		$current['end'] = $stops[1];
		$gradients[$surface_name] = $current;
	}
	$data['gradients'] = $gradients;

	// Buttons Studio: color-owned fields only. Shape, spacing, depth and hover behavior stay intact.
	$buttons = !empty($data['buttons']) && is_array($data['buttons'])
		? $data['buttons']
		: themelab_get_default_buttons();

	$button_colors = array(
		'primary_text' => themelab_get_contrast_text_color($primary),
		'secondary_text' => themelab_get_contrast_text_color($secondary),
		'danger_background' => $danger,
		'danger_text' => themelab_get_contrast_text_color($danger),
		'danger_border' => $danger,
		'disabled_background' => $surface_alt,
		'disabled_text' => $muted,
		'disabled_border' => $border,
		'header_background' => $surface,
		'header_text' => $text,
		'header_border' => $border,
		'header_hover_background' => $surface_alt,
		'header_hover_text' => $text,
		'header_current_background' => $primary,
		'header_current_text' => themelab_get_contrast_text_color($primary),
		'footer_background' => $surface,
		'footer_text' => $link,
		'footer_border' => $border,
		'footer_hover_background' => $surface_alt,
		'footer_hover_text' => $text,
		'footer_current_background' => $primary,
		'footer_current_text' => themelab_get_contrast_text_color($primary),
	);
	$data['buttons'] = array_merge($buttons, $button_colors);

	// Board Index Studio: specialist colors only. Geometry/layout remain untouched.
	$board = !empty($data['board_index']) && is_array($data['board_index'])
		? $data['board_index']
		: themelab_get_default_board_index();
	$data['board_index'] = array_merge($board, array(
		'row_background' => $surface,
		'row_background_alt' => $surface_alt,
		'header_text' => themelab_get_contrast_text_color($primary),
		'header_link' => themelab_get_contrast_text_color($primary),
		'stats_text' => $muted,
		'description_text' => $muted,
		'unread_indicator' => $primary,
	));

	themelab_save_project_data($id_project, $data);
	return true;
}

/**
 * Handle Palette Studio form actions.
 */
function themelab_handle_palette_actions()
{
	$id_project = themelab_get_active_project_id();

	if (empty($id_project))
		redirectexit('action=admin;area=themelab;sa=project');

	/*
	 * Load a built-in preset.
	 */
	if (!empty($_POST['themelab_load_palette']))
	{
		checkSession();

		$preset_key = '';

		if (is_string($_POST['themelab_load_palette']))
			$preset_key = $_POST['themelab_load_palette'];
		elseif (!empty($_POST['themelab_palette_preset']))
			$preset_key = $_POST['themelab_palette_preset'];

		$preset_key = preg_replace(
			'~[^a-z0-9_]~',
			'',
			strtolower($preset_key)
		);

		if (!themelab_apply_palette_preset($id_project, $preset_key))
			redirectexit('action=admin;area=themelab;sa=palette;palette_error');

		redirectexit('action=admin;area=themelab;sa=palette;palette_saved');
	}

	/*
	 * Save customized token colors.
	 */
	if (!empty($_POST['themelab_save_colors']))
	{
		checkSession();

		$project = themelab_get_project_by_id($id_project);

		if (
			empty($project)
			|| empty($project['project_data_array']['palette'])
		)
		{
			redirectexit('action=admin;area=themelab;sa=palette;palette_error');
		}

		$project_data = $project['project_data_array'];
		$palette = $project_data['palette'];

		$preset_key = !empty($palette['preset'])
			? $palette['preset']
			: '';

		$preset = themelab_get_palette_preset($preset_key);
		$tokens = themelab_get_palette_tokens();

		if (empty($preset))
			redirectexit('action=admin;area=themelab;sa=palette;palette_error');

		// Preserve retired/Studio-owned compatibility values without editing them.
		$colors = !empty($palette['colors']) && is_array($palette['colors'])
			? $palette['colors']
			: array();
		$customized = !empty($palette['customized']) && is_array($palette['customized'])
			? $palette['customized']
			: array();
		$has_errors = false;

		foreach ($tokens as $token_key => $token_label)
		{
			$submitted_color = isset($_POST['themelab_color'][$token_key])
				? $_POST['themelab_color'][$token_key]
				: '';

			$normalized_color = themelab_normalize_hex_color(
				$submitted_color
			);

			if ($normalized_color === false)
			{
				$has_errors = true;
				continue;
			}

			$colors[$token_key] = $normalized_color;

			$preset_color = !empty($preset['colors'][$token_key])
				? strtoupper($preset['colors'][$token_key])
				: ($token_key === 'link_hover'
					? strtoupper(!empty($preset['colors']['primary']) ? $preset['colors']['primary'] : $preset['colors']['link'])
					: ($token_key === 'link_visited'
						? strtoupper(!empty($preset['colors']['accent']) ? $preset['colors']['accent'] : $preset['colors']['link'])
						: ''));

			if ($normalized_color !== $preset_color)
				$customized[$token_key] = true;
			else
				unset($customized[$token_key]);
		}

		if ($has_errors || count(array_intersect_key($colors, $tokens)) !== count($tokens))
		{
			redirectexit(
				'action=admin;area=themelab;sa=palette;color_error'
			);
		}

		$project_data['palette']['colors'] = $colors;
		$project_data['palette']['customized'] = $customized;

		$project_data['palette']['color_mode'] = (!empty($_POST['themelab_color_mode']) && $_POST['themelab_color_mode'] === 'light_dark') ? 'light_dark' : 'single';

		themelab_save_project_data($id_project, $project_data);

		redirectexit(
			'action=admin;area=themelab;sa=palette;colors_saved'
		);
	}

	/*
	 * Deliberately reset every color-owning Studio to the selected preset.
	 * This is the explicit global exception to normal Palette ownership.
	 */
	if (!empty($_POST['themelab_reset_colors']))
	{
		checkSession();

		$project = themelab_get_project_by_id($id_project);

		$preset_key = !empty($project['project_data_array']['palette']['preset'])
			? $project['project_data_array']['palette']['preset']
			: '';

		if (!themelab_reset_project_colors_to_preset($id_project, $preset_key))
			redirectexit('action=admin;area=themelab;sa=palette;palette_error');

		redirectexit('action=admin;area=themelab;sa=palette;colors_reset');
	}
}