<?php

/**
 * Theme Lab Review
 *
 * Stage 1 of the publishing workflow. Collects project metadata and a
 * read-only design summary before the certification and publisher engines
 * are enabled.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Build the read-only Theme Review model for an active project.
 */
function themelab_build_review($project)
{
	global $txt;

	$data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array']
		: array();

	$metadata = array(
		array(
			'key' => 'project_name',
			'label' => $txt['themelab_review_project_name'],
			'value' => isset($project['project_name']) ? trim((string) $project['project_name']) : '',
			'required' => true,
		),
		array(
			'key' => 'author',
			'label' => $txt['themelab_review_author'],
			'value' => isset($project['author']) ? trim((string) $project['author']) : '',
			'required' => true,
		),
		array(
			'key' => 'theme_version',
			'label' => $txt['themelab_review_version'],
			'value' => isset($project['theme_version']) ? trim((string) $project['theme_version']) : '',
			'required' => true,
		),
		array(
			'key' => 'base_theme',
			'label' => $txt['themelab_review_base_theme'],
			'value' => !empty($project['base_theme']) ? (string) $project['base_theme'] : 'default',
			'required' => true,
		),
		array(
			'key' => 'description',
			'label' => $txt['themelab_review_description'],
			'value' => isset($project['description']) ? trim((string) $project['description']) : '',
			'required' => false,
		),
	);

	$missing_required = 0;
	foreach ($metadata as $index => $item)
	{
		$metadata[$index]['complete'] = $item['value'] !== '';
		if ($item['required'] && !$metadata[$index]['complete'])
			$missing_required++;
	}

	$studio_map = array(
		'palette' => array('label' => $txt['themelab_palette'], 'sa' => 'palette'),
		'gradients' => array('label' => $txt['themelab_gradients'], 'sa' => 'gradients'),
		'layout' => array('label' => $txt['themelab_layout'], 'sa' => 'layout'),
		'typography' => array('label' => $txt['themelab_typography'], 'sa' => 'typography'),
		'buttons' => array('label' => $txt['themelab_buttons'], 'sa' => 'buttons'),
		'forms' => array('label' => $txt['themelab_forms'], 'sa' => 'forms'),
		'navigation' => array('label' => $txt['themelab_navigation'], 'sa' => 'navigation'),
		'board_index' => array('label' => $txt['themelab_board_index'], 'sa' => 'board_index'),
		'header' => array('label' => $txt['themelab_header'], 'sa' => 'header'),
		'footer' => array('label' => $txt['themelab_footer'], 'sa' => 'footer'),
	);

	$studios = array();
	$configured_count = 0;
	foreach ($studio_map as $key => $studio)
	{
		$configured = !empty($data[$key]) && is_array($data[$key]);
		if ($configured)
			$configured_count++;

		$studios[] = array(
			'key' => $key,
			'label' => $studio['label'],
			'sa' => $studio['sa'],
			'configured' => $configured,
			'summary' => themelab_get_review_studio_summary($key, $project),
		);
	}

	return array(
		'metadata' => $metadata,
		'studios' => $studios,
		'missing_required' => $missing_required,
		'configured_count' => $configured_count,
		'total_studios' => count($studios),
		'ready_for_certification' => $missing_required === 0,
		'last_modified' => !empty($project['date_modified']) ? (int) $project['date_modified'] : 0,
	);
}

/**
 * Return a concise, human-readable summary for one Studio.
 */
function themelab_get_review_studio_summary($key, $project)
{
	global $txt;

	switch ($key)
	{
		case 'palette':
			$palette = themelab_get_project_palette($project);
			$preset = !empty($palette['preset']) ? $palette['preset'] : $txt['themelab_review_custom'];
			$count = !empty($palette['colors']) && is_array($palette['colors']) ? count($palette['colors']) : 0;
			return sprintf($txt['themelab_review_palette_summary'], ucfirst(str_replace('_', ' ', $preset)), $count);

		case 'gradients':
			$gradients = themelab_get_project_gradients($project);
			$enabled = 0;
			foreach ($gradients as $gradient)
				if (!empty($gradient['enabled'])) $enabled++;
			return sprintf($txt['themelab_review_gradients_summary'], $enabled, count($gradients));

		case 'layout':
			$layout = themelab_get_project_layout($project);
			return sprintf($txt['themelab_review_layout_summary'], ucfirst($layout['width_mode']), (int) $layout['max_width']);

		case 'typography':
			$typography = themelab_get_project_typography($project);
			$fonts = themelab_get_typography_fonts();
			$font = isset($fonts[$typography['font_family']]['name']) ? $fonts[$typography['font_family']]['name'] : ucfirst($typography['font_family']);
			return sprintf($txt['themelab_review_typography_summary'], $font, (int) $typography['font_size']);

		case 'buttons':
			$buttons = themelab_get_project_buttons($project);
			return sprintf($txt['themelab_review_buttons_summary'], (int) $buttons['border_radius'], ucfirst($buttons['hover_effect']));

		case 'forms':
			$forms = themelab_get_project_forms($project);
			return sprintf($txt['themelab_review_forms_summary'], ucfirst($forms['field_size']), (int) $forms['border_radius']);

		case 'navigation':
			$navigation = themelab_get_project_navigation($project);
			return sprintf($txt['themelab_review_navigation_summary'], ucfirst($navigation['footer_style']), (int) $navigation['menu_radius']);

		case 'board_index':
			$board = themelab_get_project_board_index($project);
			return sprintf($txt['themelab_review_board_summary'], ucfirst($board['row_spacing']), ucfirst($board['icon_style']));

		case 'header':
			$header = themelab_get_project_header($project);
			return sprintf($txt['themelab_review_header_summary'], ucfirst($header['background_mode']), (int) $header['height']);

		case 'footer':
			$footer = themelab_get_project_footer($project);
			return sprintf($txt['themelab_review_footer_summary'], ucfirst($footer['background_mode']), (int) $footer['height']);
	}

	return $txt['themelab_review_defaults'];
}
