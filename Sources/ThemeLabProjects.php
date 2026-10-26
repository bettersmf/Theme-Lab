<?php

/**
 * Theme Lab Projects
 *
 * Theme Workspace persistence and import functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Get all Theme Lab projects.
 */
function themelab_get_projects()
{
	global $smcFunc;

	$projects = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_project, project_name, author, theme_version, base_theme, date_created, date_modified
		FROM {db_prefix}themelab_projects
		ORDER BY date_modified DESC',
		array()
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$projects[] = $row;
	}

	$smcFunc['db_free_result']($request);

	return $projects;
}

/**
 * Get one Theme Lab project.
 */
function themelab_get_project_by_id($id_project)
{
	global $smcFunc;

	$id_project = (int) $id_project;

	$request = $smcFunc['db_query']('', '
		SELECT *
		FROM {db_prefix}themelab_projects
		WHERE id_project = {int:id_project}
		LIMIT 1',
		array(
			'id_project' => $id_project,
		)
	);

	$project = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (empty($project))
		return false;

	$project['project_data_array'] = !empty($project['project_data']) ? json_decode($project['project_data'], true) : array();

	if (!is_array($project['project_data_array']))
		$project['project_data_array'] = array();

	return $project;
}

/**
 * Create a new Theme Lab project.
 */
function themelab_create_project($data)
{
	global $smcFunc;

	$now = time();

	$project_data = array(
		'palette' => array(),
		'typography' => array(),
		'buttons' => array(),
		'navigation' => array(),
		'layout' => array(),
		'header' => array(),
		'footer' => array(),
	);

	$smcFunc['db_insert']('insert',
		'{db_prefix}themelab_projects',
		array(
			'project_name' => 'string-120',
			'author' => 'string-120',
			'theme_version' => 'string-30',
			'description' => 'string',
			'base_theme' => 'string-60',
			'project_data' => 'string',
			'date_created' => 'int',
			'date_modified' => 'int',
		),
		array(
			isset($data['project_name']) ? $data['project_name'] : '',
			isset($data['author']) ? $data['author'] : '',
			isset($data['theme_version']) ? $data['theme_version'] : '1.0',
			isset($data['description']) ? $data['description'] : '',
			isset($data['base_theme']) ? $data['base_theme'] : 'default',
			json_encode($project_data),
			$now,
			$now,
		),
		array('id_project')
	);

		$id_project = $smcFunc['db_insert_id'](
		'{db_prefix}themelab_projects',
		'id_project'
	);

	themelab_set_active_project($id_project);

	return $id_project;
}

/**
 * Update an existing Theme Lab project.
 */
function themelab_update_project($id_project, $data)
{
	global $smcFunc;

	$id_project = (int) $id_project;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}themelab_projects
		SET
			project_name = {string:project_name},
			author = {string:author},
			theme_version = {string:theme_version},
			description = {string:description},
			base_theme = {string:base_theme},
			date_modified = {int:date_modified}
		WHERE id_project = {int:id_project}',
		array(
			'id_project' => $id_project,
			'project_name' => isset($data['project_name']) ? $data['project_name'] : '',
			'author' => isset($data['author']) ? $data['author'] : '',
			'theme_version' => isset($data['theme_version']) ? $data['theme_version'] : '1.0',
			'description' => isset($data['description']) ? $data['description'] : '',
			'base_theme' => isset($data['base_theme']) ? $data['base_theme'] : 'default',
			'date_modified' => time(),
		)
	);
}

/**
 * Delete a Theme Lab project.
 */
function themelab_delete_project($id_project)
{
	global $smcFunc;

	$id_project = (int) $id_project;

	if ($id_project <= 0)
		return false;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themelab_projects
		WHERE id_project = {int:id_project}',
		array(
			'id_project' => $id_project,
		)
	);

	if (themelab_get_active_project_id() === $id_project)
		themelab_clear_active_project();

	return true;
}

function themelab_get_project_count()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}themelab_projects',
		array()
	);

	list ($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $count;
}

/**
 * Return the currently active Theme Lab project ID.
 */
function themelab_get_active_project_id()
{
	global $modSettings;

	// RC6.1.16 isolates the working project in the administrator's session.
	// The legacy setting remains synchronized as a compatibility fallback.
	if (isset($_SESSION['themelab_active_project']))
		return max(0, (int) $_SESSION['themelab_active_project']);

	$id_project = !empty($modSettings['themelab_active_project'])
		? (int) $modSettings['themelab_active_project']
		: 0;

	$_SESSION['themelab_active_project'] = $id_project;

	return $id_project;
}

/**
 * Return the currently active Theme Lab project.
 */
function themelab_get_active_project()
{
	$id_project = themelab_get_active_project_id();

	if (empty($id_project))
		return false;

	return themelab_get_project_by_id($id_project);
}

/**
 * Set the active Theme Lab project.
 */
function themelab_set_active_project($id_project)
{
	global $modSettings;

	$id_project = (int) $id_project;

	if ($id_project > 0 && !themelab_get_project_by_id($id_project))
		return false;

	$_SESSION['themelab_active_project'] = $id_project;
	$modSettings['themelab_active_project'] = $id_project;
	updateSettings(array(
		'themelab_active_project' => $id_project,
	));

	return true;
}

/**
 * Clear the active project.
 */
function themelab_clear_active_project()
{
	global $modSettings;

	$_SESSION['themelab_active_project'] = 0;
	$modSettings['themelab_active_project'] = 0;
	updateSettings(array(
		'themelab_active_project' => 0,
	));
}

/**
 * Normalize a native Theme Lab workspace payload.
 *
 * RC6.1.21 deliberately keeps the database's historic "project" field names
 * for backward compatibility while presenting "Workspace" throughout the UI.
 */
function themelab_normalize_workspace_import($payload, &$error = '')
{
	$error = '';

	if (!is_array($payload))
	{
		$error = 'themelab_import_error_invalid';
		return false;
	}

	$generator = isset($payload['generator']) ? strtolower(trim((string) $payload['generator'])) : '';
	/* Import is intentionally closed to Theme Lab workspaces.  A missing signature
	 * is treated the same as a foreign generator so arbitrary SMF themes cannot
	 * be partially normalized into a misleading workspace. */
	if ($generator !== 'theme lab' && $generator !== 'themelab')
	{
		$error = 'themelab_import_error_not_themelab';
		return false;
	}

	$workspace = !empty($payload['workspace']) && is_array($payload['workspace'])
		? $payload['workspace']
		: $payload;

	$project_data = array();
	if (!empty($workspace['project_data']) && is_array($workspace['project_data']))
		$project_data = $workspace['project_data'];
	elseif (!empty($workspace['settings']) && is_array($workspace['settings']))
		$project_data = $workspace['settings'];
	elseif (!empty($payload['project_data']) && is_array($payload['project_data']))
		$project_data = $payload['project_data'];

	if (empty($project_data['schema']))
		$project_data['schema'] = 1;

	$name = isset($workspace['project_name']) ? trim((string) $workspace['project_name']) : '';
	if ($name === '' && isset($workspace['name']))
		$name = trim((string) $workspace['name']);

	if ($name === '')
	{
		$error = 'themelab_import_error_name';
		return false;
	}

	$clean = function ($value) {
		$value = trim((string) $value);
		return function_exists('themelab_clean_text')
			? themelab_clean_text($value)
			: htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	};

	return array(
		'project_name' => $clean($name),
		'author' => $clean(isset($workspace['author']) ? $workspace['author'] : ''),
		'theme_version' => $clean(!empty($workspace['theme_version']) ? $workspace['theme_version'] : '1.0'),
		'description' => $clean(isset($workspace['description']) ? $workspace['description'] : ''),
		'base_theme' => 'default',
		'project_data' => $project_data,
	);
}


/**
 * Convert Theme Lab's generated CSS manifest back into editable Studio data.
 *
 * This is the compatibility bridge for themes published before RC6.1.21a,
 * before themelab-workspace.json existed. Asset source files cannot always be
 * recovered from CSS alone, but the design settings are reconstructed from
 * Theme Lab's own --tl-* manifest rather than guessed from arbitrary CSS.
 */
function themelab_legacy_workspace_from_css($css, $metadata = array(), &$error = '')
{
	$error = '';
	$vars = array();

	if (!preg_match_all('~--tl-([a-zA-Z0-9_-]+)\s*:\s*([^;}]+)~', (string) $css, $matches, PREG_SET_ORDER))
	{
		$error = 'themelab_import_error_legacy_css';
		return false;
	}

	foreach ($matches as $match)
	{
		$value = trim($match[2]);
		if ($value !== '' && strtolower($value) !== 'initial')
			$vars[$match[1]] = $value;
	}

	$get = function ($key, $default = null) use ($vars) {
		return array_key_exists($key, $vars) ? $vars[$key] : $default;
	};
	$typed = function ($value, $default) {
		if (is_int($default)) return (int) $value;
		if (is_float($default)) return (float) $value;
		return (string) $value;
	};
	$section = function ($defaults, $prefix = '') use ($vars, $typed) {
		$result = array();
		foreach ($defaults as $key => $default)
		{
			$manifest_key = $prefix . $key;
			if (array_key_exists($manifest_key, $vars))
				$result[$key] = $typed($vars[$manifest_key], $default);
		}
		return array_merge($defaults, $result);
	};

	$project_data = array('schema' => 1);

	$palette_keys = array('primary','secondary','accent','background','surface','surface_alt','text','muted_text','link','link_hover','link_visited','button','border','header','footer');
	$colors = array();
	foreach ($palette_keys as $key)
		if (isset($vars[$key])) $colors[$key] = $vars[$key];
	$palette_preset = '';
	if (function_exists('themelab_get_palette_presets'))
	{
		$available_presets = themelab_get_palette_presets();
		if (!empty($available_presets))
		{
			$keys = array_keys($available_presets);
			$palette_preset = (string) reset($keys);
		}
	}
	$project_data['palette'] = array(
		'preset' => $palette_preset,
		'colors' => $colors,
		'customized' => array_fill_keys(array_keys($colors), true),
		'color_mode' => 'single',
	);

	if (function_exists('themelab_get_default_layout'))
		$project_data['layout'] = $section(themelab_get_default_layout());

	/* Typography uses resolved font stacks in generated CSS, so map them back to Studio keys. */
	$typography = function_exists('themelab_get_default_typography') ? themelab_get_default_typography() : array();
	if (!empty($vars['font_family']) && function_exists('themelab_get_typography_fonts'))
		foreach (themelab_get_typography_fonts() as $key => $font)
			if (isset($font['family']) && trim((string) $font['family']) === trim((string) $vars['font_family'])) $typography['font_family'] = $key;
	if (isset($vars['font_size'])) $typography['font_size'] = (int) $vars['font_size'];
	if (isset($vars['heading_weight'])) $typography['heading_weight'] = (string) $vars['heading_weight'];
	if (isset($vars['line_height']))
	{
		$line_map = array('1.25' => 'compact', '1.5' => 'comfortable', '1.50' => 'comfortable', '1.75' => 'relaxed');
		if (isset($line_map[(string) $vars['line_height']])) $typography['line_spacing'] = $line_map[(string) $vars['line_height']];
	}
	if (!empty($vars['monospace_font']) && function_exists('themelab_get_monospace_fonts'))
		foreach (themelab_get_monospace_fonts() as $key => $font)
			if (isset($font['family']) && trim((string) $font['family']) === trim((string) $vars['monospace_font'])) $typography['monospace_font'] = $key;
	$project_data['typography'] = $typography;

	if (function_exists('themelab_get_default_buttons'))
		$project_data['buttons'] = $section(themelab_get_default_buttons(), 'button_');
	if (function_exists('themelab_get_default_forms'))
		$project_data['forms'] = $section(themelab_get_default_forms(), 'forms_');
	if (function_exists('themelab_get_default_navigation'))
		$project_data['navigation'] = $section(themelab_get_default_navigation(), 'navigation_');
	if (function_exists('themelab_get_default_board_index'))
		$project_data['board_index'] = $section(themelab_get_default_board_index(), 'board_index_');

	if (function_exists('themelab_get_default_header'))
	{
		$header = themelab_get_default_header();
		$header_map = array(
			'height' => 'header_height', 'logo_text' => 'logo_text', 'logo_size' => 'logo_size',
			'logo_alignment' => 'logo_alignment', 'smf_logo_visibility' => 'smf_logo_visibility',
			'smf_logo_position' => 'smf_logo_position', 'background_image_position_x' => 'header_background_position_x',
			'background_image_position_y' => 'header_background_position_y', 'background_image_size' => 'header_background_size',
			'background_image_opacity' => 'header_image_opacity', 'background_overlay_opacity' => 'header_background_opacity',
			'background_mode' => 'header_background_mode', 'background_color' => 'header_background_color',
			'gradient_start' => 'header_gradient_start', 'gradient_end' => 'header_gradient_end',
			'gradient_direction' => 'header_gradient_direction', 'text_color' => 'header_text_color',
			'navigation_alignment' => 'navigation_alignment', 'navigation_style' => 'navigation_style',
			'search_position' => 'search_position',
		);
		foreach ($header_map as $target => $source)
			if (isset($vars[$source])) $header[$target] = $typed($vars[$source], isset($header[$target]) ? $header[$target] : '');
		/* Source upload paths are intentionally not invented during a legacy import. */
		$header['logo_image'] = '';
		$header['background_image'] = '';
		$project_data['header'] = $header;
	}

	if (function_exists('themelab_get_default_footer'))
	{
		$footer = themelab_get_default_footer();
		$footer_map = array(
			'height' => 'footer_height', 'footer_text' => 'footer_text', 'text_alignment' => 'footer_text_alignment',
			'background_mode' => 'footer_background_mode', 'background_color' => 'footer_background_color',
			'gradient_start' => 'footer_gradient_start', 'gradient_end' => 'footer_gradient_end',
			'gradient_direction' => 'footer_gradient_direction', 'text_color' => 'footer_text_color',
			'link_color' => 'footer_link_color', 'show_credit' => 'footer_show_credit',
		);
		foreach ($footer_map as $target => $source)
			if (isset($vars[$source])) $footer[$target] = $typed($vars[$source], $footer[$target]);
		$project_data['footer'] = $footer;
	}

	/* Reconstruct the gradient surfaces that can be represented by the generated manifest. */
	if (function_exists('themelab_get_gradient_surfaces') && function_exists('themelab_get_default_gradient_surface'))
	{
		$gradients = array();
		foreach (themelab_get_gradient_surfaces() as $surface => $definition)
		{
			$gradient = themelab_get_default_gradient_surface($surface);
			if (isset($vars[$surface . '_gradient_enabled'])) $gradient['enabled'] = (int) $vars[$surface . '_gradient_enabled'];
			if (isset($vars[$surface . '_gradient_start'])) $gradient['start'] = $vars[$surface . '_gradient_start'];
			if (isset($vars[$surface . '_gradient_end'])) $gradient['end'] = $vars[$surface . '_gradient_end'];
			if (isset($vars[$surface . '_gradient_direction']))
				$gradient['flow'] = $vars[$surface . '_gradient_direction'] === 'vertical' ? 'top_bottom' : 'left_right';
			if (!empty($vars[$surface . '_gradient_css']))
			{
				if (preg_match_all('~#[0-9a-fA-F]{6}~', $vars[$surface . '_gradient_css'], $color_matches) && !empty($color_matches[0]))
				{
					$gradient['start'] = $color_matches[0][0];
					$gradient['end'] = end($color_matches[0]);
				}
				if (preg_match('~linear-gradient\((0deg|90deg|180deg|270deg)~', $vars[$surface . '_gradient_css'], $angle))
				{
					$flows = array('0deg'=>'bottom_top','90deg'=>'left_right','180deg'=>'top_bottom','270deg'=>'right_left');
					$gradient['flow'] = isset($flows[$angle[1]]) ? $flows[$angle[1]] : $gradient['flow'];
				}
			}
			$gradients[$surface] = $gradient;
		}
		$project_data['gradients'] = $gradients;
	}

	$name = !empty($metadata['project_name']) ? $metadata['project_name'] : (!empty($metadata['name']) ? $metadata['name'] : 'Imported Theme Lab Theme');
	$author = !empty($metadata['author']) ? $metadata['author'] : '';
	$version = !empty($metadata['theme_version']) ? $metadata['theme_version'] : (!empty($metadata['version']) ? $metadata['version'] : '1.0');

	return array(
		'generator' => 'Theme Lab',
		'schema' => 1,
		'source' => 'legacy-generated-css',
		'workspace' => array(
			'project_name' => $name,
			'author' => $author,
			'theme_version' => $version,
			'description' => 'Imported from a Theme Lab generated theme created before native Workspace manifests were available.',
			'base_theme' => 'default',
			'project_data' => $project_data,
		),
	);
}

/**
 * Import a native Theme Lab workspace from JSON or a Theme Lab generated ZIP.
 *
 * Current Theme Lab published themes include themelab-workspace.json for
 * lossless round-trip editing. Recognized older Theme Lab themes can still be
 * reconstructed from the generated CSS manifest when enough metadata exists.
 */
function themelab_import_workspace_upload($upload, &$error = '')
{
	$error = '';

	if (empty($upload) || !is_array($upload) || !isset($upload['error']))
	{
		$error = 'themelab_import_error_no_file';
		return false;
	}

	$upload_error = (int) $upload['error'];
	if ($upload_error !== UPLOAD_ERR_OK)
	{
		switch ($upload_error)
		{
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				$error = 'themelab_import_error_server_size';
				break;

			case UPLOAD_ERR_PARTIAL:
				$error = 'themelab_import_error_partial';
				break;

			case UPLOAD_ERR_NO_FILE:
				$error = 'themelab_import_error_no_file';
				break;

			case UPLOAD_ERR_NO_TMP_DIR:
				$error = 'themelab_import_error_no_tmp';
				break;

			case UPLOAD_ERR_CANT_WRITE:
				$error = 'themelab_import_error_write';
				break;

			case UPLOAD_ERR_EXTENSION:
				$error = 'themelab_import_error_extension';
				break;

			default:
				$error = 'themelab_import_error_upload';
		}
		return false;
	}

	if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name']))
	{
		$error = 'themelab_import_error_upload';
		return false;
	}

	// Theme ZIPs can include user-supplied header/logo artwork, so keep the
	// application-level ceiling comfortably above typical host defaults. PHP's
	// own upload_max_filesize/post_max_size limits still apply first.
	if (!empty($upload['size']) && (int) $upload['size'] > 64 * 1024 * 1024)
	{
		$error = 'themelab_import_error_size';
		return false;
	}

	$filename = isset($upload['name']) ? (string) $upload['name'] : '';
	$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	$json = '';

	if ($extension === 'zip')
	{
		if (!class_exists('ZipArchive'))
		{
			$error = 'themelab_import_error_zip';
			return false;
		}

		$zip = new ZipArchive();
		if ($zip->open($upload['tmp_name']) !== true)
		{
			$error = 'themelab_import_error_zip';
			return false;
		}

		$index = $zip->locateName('themelab-workspace.json', ZipArchive::FL_NODIR);
		if ($index !== false)
		{
			$json = (string) $zip->getFromIndex($index);
			$zip->close();
		}
		else
		{
			$css_index = $zip->locateName('themelab-generated.css', ZipArchive::FL_NODIR);
			if ($css_index === false)
			{
				$zip->close();
				$error = 'themelab_import_error_not_themelab';
				return false;
			}

			$legacy_css = (string) $zip->getFromIndex($css_index);
			/* The filename alone is not proof of origin.  Legacy Theme Lab exports carry
			 * both the generator banner and the --tl-* manifest. Require both before
			 * attempting reconstruction. */
			if (stripos($legacy_css, 'Generated by Theme Lab') === false
				|| !preg_match('~--tl-(?:primary|project_name|font_family)\s*:~i', $legacy_css))
			{
				$zip->close();
				$error = 'themelab_import_error_not_themelab';
				return false;
			}
			$metadata = array();
			$theme_info_index = $zip->locateName('theme_info.xml', ZipArchive::FL_NODIR);
			if ($theme_info_index !== false)
			{
				$theme_info = (string) $zip->getFromIndex($theme_info_index);
				foreach (array('name' => 'project_name', 'author' => 'author', 'version' => 'theme_version') as $tag => $target)
					if (preg_match('~<' . $tag . '>(.*?)</' . $tag . '>~si', $theme_info, $match))
						$metadata[$target] = html_entity_decode(trim(strip_tags($match[1])), ENT_QUOTES, 'UTF-8');
			}
			$zip->close();

			$legacy_payload = themelab_legacy_workspace_from_css($legacy_css, $metadata, $error);
			if ($legacy_payload === false)
				return false;
			$json = json_encode($legacy_payload);
		}
	}
	elseif ($extension === 'json')
	{
		$json = (string) @file_get_contents($upload['tmp_name']);
	}
	else
	{
		$error = 'themelab_import_error_type';
		return false;
	}

	$payload = json_decode($json, true);
	$normalized = themelab_normalize_workspace_import($payload, $error);
	if ($normalized === false)
		return false;

	$id_project = themelab_create_project($normalized);
	if (empty($id_project))
	{
		$error = 'themelab_import_error_save';
		return false;
	}

	if (!empty($normalized['project_data']) && function_exists('themelab_save_project_data'))
		themelab_save_project_data($id_project, $normalized['project_data']);

	themelab_set_active_project($id_project);
	return $id_project;
}

