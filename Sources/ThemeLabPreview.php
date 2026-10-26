<?php

/**
 * Theme Lab Preview
 *
 * Builds the shared preview settings used by every Theme Lab Studio.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Convert a saved gradient direction into a CSS angle.
 *
 * Horizontal: left to right.
 * Vertical: top to bottom.
 *
 * @param string $direction Gradient direction key.
 *
 * @return string CSS gradient angle.
 */
function themelab_get_gradient_angle($direction)
{
	return $direction === 'vertical'
		? '180deg'
		: '90deg';
}

/**
 * Build the complete mini-forum preview settings for a project.
 *
 * This combines all saved Studio data into one normalized array:
 *
 * - Palette
 * - Layout
 * - Typography
 * - Header
 * - Footer
 *
 * Every design Studio should use this function so the preview always
 * reflects the complete saved project.
 *
 * @param array $project Active Theme Lab project.
 *
 * @return array Complete preview settings.
 */
function themelab_build_preview_settings($project)
{
	// Always rehydrate the selected project from storage. This prevents a
	// Studio or prior project request from supplying stale Header/Footer data.
	$id_project = !empty($project['id_project']) ? (int) $project['id_project'] : 0;
	if ($id_project > 0 && function_exists('themelab_get_project_by_id'))
	{
		$fresh_project = themelab_get_project_by_id($id_project);
		if (!empty($fresh_project))
			$project = $fresh_project;
	}

	if (empty($project) || !is_array($project))
		$project = array('id_project' => 0, 'project_name' => '', 'project_data_array' => array());

	$project_data =
		!empty($project['project_data_array'])
		&& is_array($project['project_data_array'])
			? $project['project_data_array']
			: array();

	/*
	 * Project sections.
	 */
	$palette =
		!empty($project_data['palette']['colors'])
		&& is_array($project_data['palette']['colors'])
			? $project_data['palette']['colors']
			: array();

	$layout =
		!empty($project_data['layout'])
		&& is_array($project_data['layout'])
			? $project_data['layout']
			: array();

	$typography =
		!empty($project_data['typography'])
		&& is_array($project_data['typography'])
			? $project_data['typography']
			: array();

	$buttons =
		!empty($project_data['buttons'])
		&& is_array($project_data['buttons'])
			? $project_data['buttons']
			: array();

	$forms = !empty($project_data['forms']) && is_array($project_data['forms']) ? $project_data['forms'] : array();

	$board_index = !empty($project_data['board_index']) && is_array($project_data['board_index']) ? $project_data['board_index'] : array();

	$navigation =
		!empty($project_data['navigation']) && is_array($project_data['navigation'])
			? $project_data['navigation'] : array();

	$header = function_exists('themelab_get_project_header')
		? themelab_get_project_header($project)
		: (!empty($project_data['header']) && is_array($project_data['header']) ? $project_data['header'] : array());

	$gradients = function_exists('themelab_get_project_gradients')
		? themelab_get_project_gradients($project)
		: array();

	$footer = function_exists('themelab_get_project_footer')
		? themelab_get_project_footer($project)
		: (!empty($project_data['footer']) && is_array($project_data['footer']) ? $project_data['footer'] : array());

	/*
	 * Font definitions.
	 */
	$fonts = function_exists('themelab_get_typography_fonts')
		? themelab_get_typography_fonts()
		: array();

	$monospace_fonts = function_exists('themelab_get_monospace_fonts')
		? themelab_get_monospace_fonts()
		: array();

	$font_key = !empty($typography['font_family'])
		? $typography['font_family']
		: 'verdana';

	$monospace_key = !empty($typography['monospace_font'])
		? $typography['monospace_font']
		: 'consolas';

	$line_heights = array(
		'compact' => '1.25',
		'comfortable' => '1.5',
		'relaxed' => '1.75',
	);

	$line_spacing = !empty($typography['line_spacing'])
		? $typography['line_spacing']
		: 'comfortable';

	/*
	 * Palette fallbacks.
	 */
	$palette_primary = !empty($palette['primary'])
		? $palette['primary']
		: '#3465A4';

	$palette_secondary = !empty($palette['secondary'])
		? $palette['secondary']
		: '#5B87B2';

	$palette_accent = !empty($palette['accent'])
		? $palette['accent']
		: '#204A87';

	$palette_background = !empty($palette['background'])
		? $palette['background']
		: '#E9EEF2';

	$palette_surface = !empty($palette['surface'])
		? $palette['surface']
		: '#FFFFFF';

	$palette_surface_alt = !empty($palette['surface_alt'])
		? $palette['surface_alt']
		: '#F5F7F8';

	$palette_text = !empty($palette['text'])
		? $palette['text']
		: '#222222';

	$palette_muted_text = !empty($palette['muted_text'])
		? $palette['muted_text']
		: '#666666';

	$palette_link = !empty($palette['link'])
		? $palette['link']
		: '#3465A4';

	$palette_link_hover = !empty($palette['link_hover'])
		? $palette['link_hover']
		: $palette_primary;

	$palette_link_visited = !empty($palette['link_visited'])
		? $palette['link_visited']
		: $palette_accent;

	$palette_button = !empty($palette['button'])
		? $palette['button']
		: '#3465A4';

	$palette_border = !empty($palette['border'])
		? $palette['border']
		: '#C7CDD1';

	$palette_header = !empty($palette['header'])
		? $palette['header']
		: '#3465A4';

	$palette_footer = !empty($palette['footer'])
		? $palette['footer']
		: '#D5DEE5';

	/*
	 * Start with the shared palette and layout values.
	 */
	$settings = array_merge(
		array(
			'primary' => $palette_primary,
			'secondary' => $palette_secondary,
			'accent' => $palette_accent,
			'background' => $palette_background,
			'surface' => $palette_surface,
			'surface_alt' => $palette_surface_alt,
			'text' => $palette_text,
			'muted_text' => $palette_muted_text,
			'link' => $palette_link,
			'link_hover' => $palette_link_hover,
			'link_visited' => $palette_link_visited,
			'button' => $palette_button,
			'border' => $palette_border,
			'header' => $palette_header,
			'footer' => $palette_footer,
		),
		$layout
	);

	$settings['project_id'] = !empty($project['id_project']) ? (int) $project['id_project'] : 0;
	$settings['project_name'] = !empty($project['project_name']) ? (string) $project['project_name'] : '';

	/*
	 * Typography settings.
	 */
	$settings['font_family'] =
		isset($fonts[$font_key]['family'])
			? $fonts[$font_key]['family']
			: 'Verdana, Geneva, sans-serif';

	$settings['font_size'] =
		isset($typography['font_size'])
			? (int) $typography['font_size']
			: 14;

	$settings['line_height'] =
		isset($line_heights[$line_spacing])
			? $line_heights[$line_spacing]
			: '1.5';

	$settings['heading_weight'] =
		!empty($typography['heading_weight'])
			? (string) $typography['heading_weight']
			: '700';

	$settings['monospace_font'] =
		isset($monospace_fonts[$monospace_key]['family'])
			? $monospace_fonts[$monospace_key]['family']
			: 'Consolas, Monaco, monospace';

	/*
	 * Button settings.
	 */
	$button_defaults = function_exists('themelab_get_default_buttons')
		? themelab_get_default_buttons()
		: array(
			'border_radius' => 5,
			'border_width' => 1,
			'padding_vertical' => 8,
			'padding_horizontal' => 16,
			'font_weight' => '500',
			'text_transform' => 'none',
			'depth' => 'soft',
			'hover_effect' => 'darken',
		);
	$button_settings = array_merge($button_defaults, $buttons);
	// RC5.7.0e moves header/footer navigation state colors into Buttons Studio.
	// Read legacy Navigation values until the project is next saved in Buttons.
	if (function_exists('themelab_migrate_navigation_colors_to_buttons'))
		$button_settings = themelab_migrate_navigation_colors_to_buttons($button_settings, $project_data);
	$button_settings['font_weight'] = themelab_normalize_font_weight(
		$button_settings['font_weight'],
		$button_defaults['font_weight']
	);
	foreach ($button_settings as $button_key => $button_value)
		$settings['button_' . $button_key] = $button_value;


	/* Form control settings. */
	$form_defaults = function_exists('themelab_get_default_forms') ? themelab_get_default_forms() : array(
		'field_size'=>'standard','border_radius'=>4,'border_width'=>1,'padding_horizontal'=>9,'focus_style'=>'glow',
		'textarea_height'=>82,'textarea_resize'=>'vertical','choice_size'=>16,'choice_style'=>'classic',
		'label_weight'=>'500','required_marker'=>'asterisk','validation_style'=>'border',
	);
	$form_settings = array_merge($form_defaults, $forms);
	$form_settings['label_weight'] = themelab_normalize_font_weight($form_settings['label_weight'], $form_defaults['label_weight']);
	foreach ($form_settings as $form_key => $form_value) $settings['forms_' . $form_key] = $form_value;

	/* Navigation settings. */
	$navigation_defaults = function_exists('themelab_get_default_navigation')
		? themelab_get_default_navigation() : array(
			'menu_radius'=>5,'menu_gap'=>6,'menu_padding_vertical'=>6,'menu_padding_horizontal'=>12,
			'font_weight'=>'500','text_transform'=>'none','breadcrumb_style'=>'plain',
			'breadcrumb_separator'=>'chevron','pagination_style'=>'boxed','pagination_active_emphasis'=>'filled',
			'pagination_size'=>'standard','pagination_gap'=>5,'pagination_radius'=>5,'pagination_active_weight'=>'balanced','active_emphasis'=>'filled',
		);
	$navigation_settings = array_intersect_key(array_merge($navigation_defaults, $navigation), $navigation_defaults);
	$navigation_settings['font_weight'] = themelab_normalize_font_weight($navigation_settings['font_weight'], $navigation_defaults['font_weight']);
	foreach ($navigation_settings as $navigation_key => $navigation_value)
		$settings['navigation_' . $navigation_key] = $navigation_value;


	/* Board Index settings. */
	$board_defaults = function_exists('themelab_get_default_board_index') ? themelab_get_default_board_index() : array();
	$board_settings = array_merge($board_defaults, $board_index);
	if (isset($board_settings['header_weight']))
		$board_settings['header_weight'] = themelab_normalize_font_weight($board_settings['header_weight'], $board_defaults['header_weight']);
	foreach ($board_settings as $board_key => $board_value)
		$settings['board_index_' . $board_key] = $board_value;

	/*
	 * Header settings.
	 */
	$settings['header_height'] =
		isset($header['height'])
			? (int) $header['height']
			: 130;

	$settings['logo_text'] =
		isset($header['logo_text'])
			? $header['logo_text']
			: 'Theme Lab Forum';

	$settings['logo_image'] =
		!empty($header['logo_image'])
			? $header['logo_image']
			: '';

	$settings['logo_image_url'] =
		!empty($settings['logo_image']) && function_exists('themelab_get_logo_url')
			? themelab_get_logo_url($settings['logo_image'])
			: '';

	$settings['logo_filename'] = !empty($header['logo_filename']) ? $header['logo_filename'] : '';
	$settings['logo_mime'] = !empty($header['logo_mime']) ? $header['logo_mime'] : '';
	$settings['logo_width'] = isset($header['logo_width']) ? (int) $header['logo_width'] : 0;
	$settings['logo_height'] = isset($header['logo_height']) ? (int) $header['logo_height'] : 0;
	$settings['logo_size'] = isset($header['logo_size']) && (int) $header['logo_size'] >= 25 && (int) $header['logo_size'] <= 200
		? (int) $header['logo_size']
		: 100;
	$settings['logo_file_size'] = isset($header['logo_file_size']) ? (int) $header['logo_file_size'] : 0;

	$settings['logo_alignment'] =
		!empty($header['logo_alignment'])
			? $header['logo_alignment']
			: 'left';

	$settings['header_background_image'] = !empty($header['background_image']) ? $header['background_image'] : '';
	$settings['header_background_image_url'] = !empty($settings['header_background_image']) && function_exists('themelab_get_header_background_url')
		? themelab_get_header_background_url($settings['header_background_image']) : '';
	$settings['header_background_position'] = !empty($header['background_image_position']) ? $header['background_image_position'] : 'center';
	$legacy_positions = array('center' => array(50, 50), 'top' => array(50, 0), 'bottom' => array(50, 100), 'left' => array(0, 50), 'right' => array(100, 50));
	$legacy_position = $legacy_positions[$settings['header_background_position']] ?? array(50, 50);
	$settings['header_background_position_x'] = isset($header['background_image_position_x']) ? max(0, min(100, (int) $header['background_image_position_x'])) : $legacy_position[0];
	$settings['header_background_position_y'] = isset($header['background_image_position_y']) ? max(0, min(100, (int) $header['background_image_position_y'])) : $legacy_position[1];
	$settings['header_background_size'] = !empty($header['background_image_size']) ? $header['background_image_size'] : 'cover';
	$settings['header_image_opacity'] = isset($header['background_image_opacity']) ? max(0, min(100, (int) $header['background_image_opacity'])) : 100;
	$settings['header_background_opacity'] = isset($header['background_overlay_opacity']) ? max(0, min(100, (int) $header['background_overlay_opacity'])) : 35;

	/* Header base and image are independent layers. */
	$settings['header_background_mode'] = !empty($header['background_mode'])
		? $header['background_mode']
		: 'solid';

	$settings['header_gradient_direction'] =
		!empty($header['gradient_direction'])
			&& in_array(
				$header['gradient_direction'],
				array('horizontal', 'vertical'),
				true
			)
				? $header['gradient_direction']
				: 'horizontal';

	$settings['header_background_color'] =
		!empty($header['background_color'])
			? $header['background_color']
			: $palette_header;

	$settings['header_gradient_start'] =
		!empty($header['gradient_start'])
			? $header['gradient_start']
			: $palette_header;

	$settings['header_gradient_end'] =
		!empty($header['gradient_end'])
			? $header['gradient_end']
			: $palette_accent;

	$settings['smf_logo_visibility'] = !empty($header['smf_logo_visibility']) ? $header['smf_logo_visibility'] : 'show';
	$settings['smf_logo_position'] = !empty($header['smf_logo_position']) ? $header['smf_logo_position'] : 'right';

	$settings['header_text_color'] =
		!empty($header['text_color'])
			? $header['text_color']
			: '#FFFFFF';

	$settings['navigation_alignment'] =
		!empty($header['navigation_alignment'])
			? $header['navigation_alignment']
			: 'left';

	$settings['navigation_style'] =
		!empty($header['navigation_style'])
			? $header['navigation_style']
			: 'pills';

	$settings['search_position'] =
		!empty($header['search_position'])
			? $header['search_position']
			: 'right';

	$settings['utility_mode'] = !empty($header['utility_mode']) && $header['utility_mode'] === 'integrated' ? 'integrated' : 'separate';
	$settings['utility_position'] = !empty($header['utility_position']) && in_array($header['utility_position'], array('auto', 'left', 'right'), true)
		? $header['utility_position'] : 'auto';

	/*
	 * Footer settings.
	 */
	$settings['footer_height'] =
		isset($footer['height'])
			? (int) $footer['height']
			: 70;

	$settings['footer_text'] =
		isset($footer['footer_text'])
			? $footer['footer_text']
			: 'Powered by SMF and Theme Lab';

	$settings['footer_text_alignment'] =
		!empty($footer['text_alignment'])
			? $footer['text_alignment']
			: 'center';

	$settings['footer_background_mode'] =
		!empty($footer['background_mode'])
			? $footer['background_mode']
			: 'solid';

	$settings['footer_gradient_direction'] =
		!empty($footer['gradient_direction'])
		&& in_array(
			$footer['gradient_direction'],
			array('horizontal', 'vertical'),
			true
		)
			? $footer['gradient_direction']
			: 'horizontal';

	$settings['footer_background_color'] =
		!empty($footer['background_color'])
			? $footer['background_color']
			: $palette_footer;

	$settings['footer_gradient_start'] =
		!empty($footer['gradient_start'])
			? $footer['gradient_start']
			: $palette_footer;

	$settings['footer_gradient_end'] =
		!empty($footer['gradient_end'])
			? $footer['gradient_end']
			: '#B9C8D3';

	$settings['footer_text_color'] =
		!empty($footer['text_color'])
			? $footer['text_color']
			: $palette_text;

	$settings['footer_link_color'] =
		!empty($footer['link_color'])
			? $footer['link_color']
			: $palette_link;

	$settings['footer_show_credit'] =
		isset($footer['show_credit'])
			? (int) $footer['show_credit']
			: 1;

	/* Unified project-driven gradient state. */
	foreach (array('header', 'footer', 'category_header', 'card_header') as $surface)
	{
		$gradient = !empty($gradients[$surface]) && is_array($gradients[$surface])
			? $gradients[$surface]
			: (function_exists('themelab_get_default_gradient_surface') ? themelab_get_default_gradient_surface($surface) : array());
		$settings[$surface . '_gradient_enabled'] = !empty($gradient['enabled']) ? 1 : 0;
		$settings[$surface . '_gradient_css'] = !empty($gradient['enabled']) && function_exists('themelab_build_gradient_css')
			? themelab_build_gradient_css($gradient)
			: '';
	}

	/* Gradient Studio owns the base layer; an uploaded image is an optional layer above it. */
	$settings['header_background_mode'] = !empty($settings['header_gradient_enabled']) ? 'gradient' : 'solid';
	$settings['header_has_background_image'] = !empty($settings['header_background_image_url']) ? 1 : 0;
	$settings['footer_background_mode'] = !empty($settings['footer_gradient_enabled']) ? 'gradient' : 'solid';

	return $settings;
}