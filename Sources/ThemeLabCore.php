<?php

/**
 * Theme Lab Core
 *
 * Shared Studio registry, project context, dispatch, and navigation helpers.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Return the registered Theme Lab screens in workflow order.
 */
function themelab_get_studio_registry()
{
	global $txt;

	return array(
		'environment' => array(
			'label' => $txt['themelab_environment'],
			'description' => $txt['themelab_environment_desc'],
			'template' => 'themelab_environment',
			'prepare' => 'themelab_prepare_environment_screen',
			'requires_project' => false,
		),
		'project' => array(
			'label' => $txt['themelab_project'],
			'description' => $txt['themelab_project_desc'],
			'template' => 'themelab_project',
			'prepare' => 'themelab_prepare_project_screen',
			'requires_project' => false,
		),
		'palette' => array(
			'label' => $txt['themelab_palette'],
			'description' => $txt['themelab_palette_desc'],
			'template' => 'themelab_palette',
			'prepare' => 'themelab_prepare_palette_studio',
			'requires_project' => true,
		),
		'gradients' => array(
			'label' => $txt['themelab_gradients'],
			'description' => $txt['themelab_gradients_desc'],
			'template' => 'themelab_gradients',
			'prepare' => 'themelab_prepare_gradient_studio',
			'requires_project' => true,
		),
		'layout' => array(
			'label' => $txt['themelab_layout'],
			'description' => $txt['themelab_layout_desc'],
			'template' => 'themelab_layout',
			'prepare' => 'themelab_prepare_layout_studio',
			'requires_project' => true,
		),
		'typography' => array(
			'label' => $txt['themelab_typography'],
			'description' => $txt['themelab_typography_desc'],
			'template' => 'themelab_typography',
			'prepare' => 'themelab_prepare_typography_studio',
			'requires_project' => true,
		),
		'buttons' => array(
			'label' => $txt['themelab_buttons'],
			'description' => $txt['themelab_buttons_desc'],
			'template' => 'themelab_buttons',
			'prepare' => 'themelab_prepare_button_studio',
			'requires_project' => true,
		),
		'forms' => array(
			'label' => $txt['themelab_forms'],
			'description' => $txt['themelab_forms_desc'],
			'template' => 'themelab_forms',
			'prepare' => 'themelab_prepare_forms_studio',
			'requires_project' => true,
		),
		'navigation' => array(
			'label' => $txt['themelab_navigation'],
			'description' => $txt['themelab_navigation_desc'],
			'template' => 'themelab_navigation',
			'prepare' => 'themelab_prepare_navigation_studio',
			'requires_project' => true,
		),

		'board_index' => array(
			'label' => $txt['themelab_board_index'],
			'description' => $txt['themelab_board_index_desc'],
			'template' => 'themelab_board_index',
			'prepare' => 'themelab_prepare_board_index_studio',
			'requires_project' => true,
		),
		'header' => array(
			'label' => $txt['themelab_header'],
			'description' => $txt['themelab_header_desc'],
			'template' => 'themelab_header',
			'prepare' => 'themelab_prepare_header_studio',
			'requires_project' => true,
		),
		'footer' => array(
			'label' => $txt['themelab_footer'],
			'description' => $txt['themelab_footer_desc'],
			'template' => 'themelab_footer',
			'prepare' => 'themelab_prepare_footer_studio',
			'requires_project' => true,
		),
		'review' => array(
			'label' => $txt['themelab_review'],
			'description' => $txt['themelab_review_desc'],
			'template' => 'themelab_review',
			'prepare' => 'themelab_prepare_review_screen',
			'requires_project' => true,
		),
		'publish' => array(
			'label' => $txt['themelab_publish'],
			'description' => $txt['themelab_publish_desc'],
			'template' => 'themelab_publish',
			'prepare' => 'themelab_prepare_publish_studio',
			'requires_project' => true,
		),
	);
}

/**
 * Build SMF admin subsection metadata from the registry.
 */
function themelab_get_admin_subsections()
{
	$subsections = array();

	foreach (themelab_get_studio_registry() as $key => $studio)
		$subsections[$key] = array($studio['label']);

	return $subsections;
}

/**
 * Clear project-scoped request context before hydrating another Studio.
 */
function themelab_reset_project_context()
{
	global $context;

	foreach (array(
		'themelab_active_project', 'themelab_preview_settings',
		'themelab_project_palette', 'themelab_gradients', 'themelab_layout',
		'themelab_typography', 'themelab_buttons', 'themelab_forms',
		'themelab_navigation', 'themelab_board_index', 'themelab_header',
		'themelab_footer'
	) as $key)
		unset($context[$key]);
}

/**
 * Dispatch the selected Theme Lab screen through the registry.
 */
function themelab_dispatch_screen($screen_key)
{
	global $context;

	$registry = themelab_get_studio_registry();

	if (!isset($registry[$screen_key]))
		$screen_key = 'environment';

	$screen = $registry[$screen_key];
	$project = false;

	if (!empty($screen['requires_project']))
	{
		themelab_reset_project_context();
		$project = themelab_get_active_project();

		if (empty($project))
			redirectexit('action=admin;area=themelab;sa=project');

		$context['themelab_active_project'] = $project;
	}

	$keys = array_keys($registry);
	$position = array_search($screen_key, $keys, true);

	$context['themelab_registry'] = $registry;
	$context['themelab_current_subaction'] = $screen_key;
	$context['themelab_current_studio'] = $screen;
	$context['themelab_previous_studio'] = $position > 1 ? $keys[$position - 1] : '';
	$context['themelab_next_studio'] = $position !== false && $position < count($keys) - 1
		? $keys[$position + 1]
		: '';
	$context['sub_template'] = $screen['template'];

	if (!empty($screen['prepare']) && is_callable($screen['prepare']))
		call_user_func($screen['prepare'], $project);
}

function themelab_prepare_environment_screen()
{
	global $context;
	$context['themelab_checks'] = themelab_environment_checks();
}

function themelab_prepare_project_screen()
{
	global $context;

	$context['themelab_project_errors'] = array();
	$context['themelab_import_errors'] = array();
	$context['themelab_submitted_project'] = array();
	themelab_handle_project_actions();
	$context['themelab_projects'] = themelab_get_projects();
	$context['themelab_active_project'] = themelab_get_active_project();
}

function themelab_prepare_palette_studio($project)
{
	global $context;

	themelab_handle_palette_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_palette_presets'] = themelab_get_palette_presets();
	$context['themelab_palette_tokens'] = themelab_get_palette_tokens();
	$context['themelab_project_palette'] = themelab_get_project_palette($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}


function themelab_prepare_gradient_studio($project)
{
	global $context;
	$context['themelab_gradient_errors'] = array();
	themelab_handle_gradient_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_gradient_surfaces'] = themelab_get_gradient_surfaces();
	$context['themelab_gradients'] = themelab_get_project_gradients($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}

function themelab_prepare_layout_studio($project)
{
	global $context;

	$context['themelab_layout_errors'] = array();
	themelab_handle_layout_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_layout'] = themelab_get_project_layout($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}

function themelab_prepare_typography_studio($project)
{
	global $context;
	$context['themelab_font_weights'] = themelab_get_font_weights();

	$context['themelab_typography_errors'] = array();
	themelab_handle_typography_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_typography_fonts'] = themelab_get_typography_fonts();
	$context['themelab_monospace_fonts'] = themelab_get_monospace_fonts();
	$context['themelab_typography'] = themelab_get_project_typography($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}


function themelab_prepare_button_studio($project)
{
	global $context;
	$context['themelab_font_weights'] = themelab_get_font_weights();

	$context['themelab_button_errors'] = array();
	themelab_handle_button_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_buttons'] = themelab_get_project_buttons($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}


function themelab_prepare_forms_studio($project)
{
	global $context;
	$context['themelab_font_weights'] = themelab_get_font_weights();
	$context['themelab_forms_errors'] = array();
	themelab_handle_form_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_forms'] = themelab_get_project_forms($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}

function themelab_prepare_navigation_studio($project)
{
	global $context;
	$context['themelab_font_weights'] = themelab_get_font_weights();
	$context['themelab_navigation_errors'] = array();
	themelab_handle_navigation_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_navigation'] = themelab_get_project_navigation($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}

function themelab_prepare_board_index_studio($project)
{
	global $context;
	$context['themelab_font_weights'] = themelab_get_font_weights();
	$context['themelab_board_index_errors'] = array();
	themelab_handle_board_index_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_board_index'] = themelab_get_project_board_index($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}

function themelab_prepare_header_studio($project)
{
	global $context;

	$context['themelab_header_errors'] = array();
	themelab_handle_header_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_header'] = themelab_get_project_header($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}

function themelab_prepare_footer_studio($project)
{
	global $context;

	$context['themelab_footer_errors'] = array();
	themelab_handle_footer_actions();
	$project = themelab_get_active_project();
	$context['themelab_active_project'] = $project;
	$context['themelab_footer'] = themelab_get_project_footer($project);
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}

/**
 * Prepare Stage 1 of the Theme Review & Publishing workflow.
 */
function themelab_prepare_review_screen($project)
{
	global $context;

	$context['themelab_active_project'] = $project;
	$context['themelab_review_data'] = themelab_build_review($project);
}
