<?php

/**
 * Theme Lab Header
 *
 * Header Studio project functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Return default Header Studio settings.
 */
function themelab_get_default_header()
{
	return array(
		'height' => 130,
		'logo_text' => 'Theme Lab Forum',
		'logo_image' => '',
		'logo_filename' => '',
		'logo_mime' => '',
		'logo_width' => 0,
		'logo_height' => 0,
		'logo_source_width' => 0,
		'logo_source_height' => 0,
		'logo_was_normalized' => 0,
		'logo_size' => 100,
		'logo_file_size' => 0,
		'logo_alignment' => 'left',
		'smf_logo_visibility' => 'show',
		'smf_logo_position' => 'right',
		'background_image' => '',
		'background_image_filename' => '',
		'background_image_mime' => '',
		'background_image_width' => 0,
		'background_image_height' => 0,
		'background_image_file_size' => 0,
		'background_image_position' => 'center',
		'background_image_position_x' => 50,
		'background_image_position_y' => 50,
		'background_image_size' => 'cover',
		'background_image_opacity' => 100,
		'background_overlay_opacity' => 35,
		'background_mode' => 'solid',
		'background_color' => '#3465A4',
		'text_color' => '#FFFFFF',
		'navigation_alignment' => 'left',
		'navigation_style' => 'pills',
		'search_position' => 'right',
		'utility_mode' => 'separate',
		'utility_position' => 'auto',
	);
}

/**
 * Return header settings saved in a project.
 */
function themelab_get_project_header($project)
{
	$defaults = themelab_get_default_header();

	if (
		empty($project['project_data_array'])
		|| !is_array($project['project_data_array'])
		|| empty($project['project_data_array']['header'])
		|| !is_array($project['project_data_array']['header'])
	)
		return $defaults;

	$header = array_merge(
		$defaults,
		$project['project_data_array']['header']
	);

	/*
	 * RC5.5.1 repurposes logo_size as the administrator-facing percentage.
	 * RC5.5.0 stored the uploaded file byte count in that field, so values
	 * outside the valid 25-200 range are treated as the 100% default.
	 */
	if (!isset($header['logo_size']) || (int) $header['logo_size'] < 25)
		$header['logo_size'] = 100;
	elseif ((int) $header['logo_size'] > 100)
		$header['logo_size'] = 100;

	// Keep the branding settings canonical even when opening older projects.
	$header['smf_logo_visibility'] = !empty($header['smf_logo_visibility'])
		&& in_array($header['smf_logo_visibility'], array('show', 'hide'), true)
			? $header['smf_logo_visibility']
			: $defaults['smf_logo_visibility'];
	$header['smf_logo_position'] = !empty($header['smf_logo_position'])
		&& in_array($header['smf_logo_position'], array('left', 'center', 'right'), true)
			? $header['smf_logo_position']
			: $defaults['smf_logo_position'];
	$header['utility_mode'] = !empty($header['utility_mode']) && in_array($header['utility_mode'], array('separate', 'integrated'), true)
		? $header['utility_mode'] : $defaults['utility_mode'];
	$header['utility_position'] = !empty($header['utility_position']) && in_array($header['utility_position'], array('auto', 'left', 'right'), true)
		? $header['utility_position'] : $defaults['utility_position'];

	return $header;
}

/**
 * Validate and normalize submitted Header Studio settings.
 */
function themelab_validate_header($submitted, $has_logo_image = false)
{
	$defaults = themelab_get_default_header();
	$errors = array();

	$header = array();

	$header['height'] = isset($submitted['height'])
		? (int) $submitted['height']
		: $defaults['height'];

	if ($header['height'] < 70 || $header['height'] > 350)
		$errors[] = 'themelab_header_error_height';

	$header['logo_text'] = isset($submitted['logo_text'])
		? themelab_clean_text($submitted['logo_text'])
		: $defaults['logo_text'];

	if ($header['logo_text'] === '' && !$has_logo_image)
		$errors[] = 'themelab_header_error_logo_text';

	$header['logo_size'] = isset($submitted['logo_size'])
		? (int) $submitted['logo_size']
		: $defaults['logo_size'];

	if ($header['logo_size'] < 25 || $header['logo_size'] > 100)
	{
		$errors[] = 'themelab_header_error_logo_scale';
		$header['logo_size'] = $defaults['logo_size'];
	}

	$header['logo_alignment'] =
		!empty($submitted['logo_alignment'])
		&& in_array(
			$submitted['logo_alignment'],
			array('left', 'center', 'right'),
			true
		)
			? $submitted['logo_alignment']
			: $defaults['logo_alignment'];

	$header['smf_logo_visibility'] =
		!empty($submitted['smf_logo_visibility'])
		&& in_array($submitted['smf_logo_visibility'], array('show', 'hide'), true)
			? $submitted['smf_logo_visibility']
			: $defaults['smf_logo_visibility'];

	$header['smf_logo_position'] =
		!empty($submitted['smf_logo_position'])
		&& in_array($submitted['smf_logo_position'], array('left', 'center', 'right'), true)
			? $submitted['smf_logo_position']
			: $defaults['smf_logo_position'];

	$header['background_mode'] =
		!empty($submitted['background_mode'])
		&& in_array(
			$submitted['background_mode'],
			array('solid', 'image'),
			true
		)
			? $submitted['background_mode']
			: $defaults['background_mode'];

	$header['background_image_position'] =
		!empty($submitted['background_image_position'])
		&& in_array($submitted['background_image_position'], array('center', 'top', 'bottom', 'left', 'right', 'custom'), true)
			? $submitted['background_image_position']
			: $defaults['background_image_position'];

	$position_defaults = array(
		'center' => array(50, 50),
		'top' => array(50, 0),
		'bottom' => array(50, 100),
		'left' => array(0, 50),
		'right' => array(100, 50),
	);
	$fallback_position = $position_defaults[$header['background_image_position']] ?? array(50, 50);
	$header['background_image_position_x'] = isset($submitted['background_image_position_x'])
		? max(0, min(100, (int) $submitted['background_image_position_x']))
		: $fallback_position[0];
	$header['background_image_position_y'] = isset($submitted['background_image_position_y'])
		? max(0, min(100, (int) $submitted['background_image_position_y']))
		: $fallback_position[1];

	$header['background_image_size'] =
		!empty($submitted['background_image_size'])
		&& in_array($submitted['background_image_size'], array('cover', 'contain', 'auto'), true)
			? $submitted['background_image_size']
			: $defaults['background_image_size'];

	$header['background_image_opacity'] = isset($submitted['background_image_opacity'])
		? (int) $submitted['background_image_opacity']
		: $defaults['background_image_opacity'];

	if ($header['background_image_opacity'] < 0 || $header['background_image_opacity'] > 100)
	{
		$errors[] = 'themelab_header_error_image_opacity';
		$header['background_image_opacity'] = $defaults['background_image_opacity'];
	}

	$header['background_overlay_opacity'] = isset($submitted['background_overlay_opacity'])
		? (int) $submitted['background_overlay_opacity']
		: $defaults['background_overlay_opacity'];

	if ($header['background_overlay_opacity'] < 0 || $header['background_overlay_opacity'] > 100)
	{
		$errors[] = 'themelab_header_error_background_opacity';
		$header['background_overlay_opacity'] = $defaults['background_overlay_opacity'];
	}

	$color_fields = array(
		'background_color',
		'text_color',
	);

	foreach ($color_fields as $field)
	{
		$normalized = isset($submitted[$field])
			? themelab_normalize_hex_color($submitted[$field])
			: false;

		if ($normalized === false)
		{
			$errors[] = 'themelab_header_error_color';
			$header[$field] = $defaults[$field];
		}
		else
		{
			$header[$field] = $normalized;
		}
	}

	$header['navigation_alignment'] =
		!empty($submitted['navigation_alignment'])
		&& in_array(
			$submitted['navigation_alignment'],
			array('left', 'center', 'right'),
			true
		)
			? $submitted['navigation_alignment']
			: $defaults['navigation_alignment'];

	$header['navigation_style'] =
		!empty($submitted['navigation_style'])
		&& in_array(
			$submitted['navigation_style'],
			array('classic', 'pills', 'minimal'),
			true
		)
			? $submitted['navigation_style']
			: $defaults['navigation_style'];

	$header['search_position'] =
		!empty($submitted['search_position'])
		&& in_array(
			$submitted['search_position'],
			array('hidden', 'left', 'right'),
			true
		)
			? $submitted['search_position']
			: $defaults['search_position'];

	$header['utility_mode'] = !empty($submitted['utility_mode']) && in_array($submitted['utility_mode'], array('separate', 'integrated'), true)
		? $submitted['utility_mode'] : $defaults['utility_mode'];
	$header['utility_position'] = !empty($submitted['utility_position']) && in_array($submitted['utility_position'], array('auto', 'left', 'right'), true)
		? $submitted['utility_position'] : $defaults['utility_position'];

	// Manual placement may never occupy the same side as the Theme Lab logo.
	if ($header['utility_mode'] === 'integrated' && $header['utility_position'] !== 'auto')
	{
		if ($header['logo_alignment'] === 'left' && $header['utility_position'] === 'left')
			$header['utility_position'] = 'right';
		elseif ($header['logo_alignment'] === 'right' && $header['utility_position'] === 'right')
			$header['utility_position'] = 'left';
	}

	return array(
		'header' => $header,
		'errors' => array_values(array_unique($errors)),
	);
}


/**
 * Return the public URL for a saved Theme Lab logo path.
 */
function themelab_get_logo_url($relative_path)
{
	global $boardurl;

	if (empty($relative_path))
		return '';

	return rtrim($boardurl, '/') . '/Themes/' . ltrim(str_replace('\\', '/', $relative_path), '/');
}

/**
 * Remove a saved logo asset from disk.
 */
function themelab_delete_logo_asset($relative_path)
{
	global $boarddir;

	if (empty($relative_path))
		return;

	$themes_root = realpath($boarddir . '/Themes');
	$asset = $boarddir . '/Themes/' . ltrim($relative_path, '/\\');
	$asset_dir = realpath(dirname($asset));

	if ($themes_root === false || $asset_dir === false || strpos($asset_dir, $themes_root) !== 0)
		return;

	if (is_file($asset))
		@unlink($asset);
}

/**
 * Validate and store one uploaded logo image for a Theme Lab project.
 *
 * RC5.5.0 preserves the original image. Display sizing is handled by the
 * shared preview with object-fit/maximum bounds; derivative generation is
 * reserved for the image-processing pass.
 */
function themelab_process_logo_upload($id_project, $file, $existing_logo = '')
{
	global $boarddir;

	$result = array(
		'logo' => array(),
		'errors' => array(),
	);

	if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
		return $result;

	if ((int) $file['error'] !== UPLOAD_ERR_OK)
	{
		$result['errors'][] = 'themelab_header_error_logo_upload';
		return $result;
	}

	if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
	{
		$result['errors'][] = 'themelab_header_error_logo_upload';
		return $result;
	}

	$max_bytes = 5 * 1024 * 1024;
	if (empty($file['size']) || (int) $file['size'] > $max_bytes)
	{
		$result['errors'][] = 'themelab_header_error_logo_size';
		return $result;
	}

	$image_info = @getimagesize($file['tmp_name']);
	if ($image_info === false || empty($image_info[0]) || empty($image_info[1]) || empty($image_info['mime']))
	{
		$result['errors'][] = 'themelab_header_error_logo_image';
		return $result;
	}

	$allowed = array(
		'image/png' => 'png',
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
	);

	$mime = strtolower($image_info['mime']);
	if (!isset($allowed[$mime]))
	{
		$result['errors'][] = 'themelab_header_error_logo_type';
		return $result;
	}

	$source_width = (int) $image_info[0];
	$source_height = (int) $image_info[1];

	if ($source_width > 10000 || $source_height > 10000)
	{
		$result['errors'][] = 'themelab_header_error_logo_dimensions';
		return $result;
	}

	/*
	 * Header logos are presentation artwork, not full-size media.
	 * Keep a generous working envelope while preserving aspect ratio.
	 * Display safety is enforced separately by the shared preview/publish math.
	 */
	$working_max_width = 1200;
	$working_max_height = 300;
	$scale = min(
		1,
		$working_max_width / max(1, $source_width),
		$working_max_height / max(1, $source_height)
	);
	$target_width = max(1, (int) floor($source_width * $scale));
	$target_height = max(1, (int) floor($source_height * $scale));
	$needs_normalize = $target_width !== $source_width || $target_height !== $source_height;

	$relative_dir = 'themelab_assets/project_' . (int) $id_project . '/logo';
	$absolute_dir = $boarddir . '/Themes/' . $relative_dir;

	if (!is_dir($absolute_dir) && !@mkdir($absolute_dir, 0755, true))
	{
		$result['errors'][] = 'themelab_header_error_logo_directory';
		return $result;
	}

	if (!is_writable($absolute_dir))
	{
		$result['errors'][] = 'themelab_header_error_logo_directory';
		return $result;
	}

	$filename = 'header_logo_' . time() . '_' . mt_rand(1000, 9999) . '.' . $allowed[$mime];
	$relative_path = $relative_dir . '/' . $filename;
	$absolute_path = $boarddir . '/Themes/' . $relative_path;
	$normalized = false;

	if ($needs_normalize && function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor'))
	{
		$bytes = @file_get_contents($file['tmp_name']);
		$source = $bytes !== false ? @imagecreatefromstring($bytes) : false;

		if ($source !== false)
		{
			$target = @imagecreatetruecolor($target_width, $target_height);

			if ($target !== false)
			{
				if ($mime === 'image/png' || $mime === 'image/webp')
				{
					@imagealphablending($target, false);
					@imagesavealpha($target, true);
					$transparent = @imagecolorallocatealpha($target, 0, 0, 0, 127);
					if ($transparent !== false)
						@imagefilledrectangle($target, 0, 0, $target_width, $target_height, $transparent);
				}

				if (@imagecopyresampled(
					$target,
					$source,
					0,
					0,
					0,
					0,
					$target_width,
					$target_height,
					$source_width,
					$source_height
				))
				{
					if ($mime === 'image/png' && function_exists('imagepng'))
						$normalized = @imagepng($target, $absolute_path, 6);
					elseif ($mime === 'image/jpeg' && function_exists('imagejpeg'))
						$normalized = @imagejpeg($target, $absolute_path, 90);
					elseif ($mime === 'image/webp' && function_exists('imagewebp'))
						$normalized = @imagewebp($target, $absolute_path, 90);
				}

				@imagedestroy($target);
			}

			@imagedestroy($source);
		}
	}

	// If normalization is unnecessary or unavailable, safely preserve the
	// validated upload. Display caps still prevent it from breaking the header.
	if (!$normalized)
	{
		$target_width = $source_width;
		$target_height = $source_height;

		if (!@move_uploaded_file($file['tmp_name'], $absolute_path))
		{
			$result['errors'][] = 'themelab_header_error_logo_upload';
			return $result;
		}
	}

	@chmod($absolute_path, 0644);

	$result['logo'] = array(
		'logo_image' => $relative_path,
		'logo_filename' => isset($file['name']) ? basename($file['name']) : $filename,
		'logo_mime' => $mime,
		'logo_width' => $target_width,
		'logo_height' => $target_height,
		'logo_source_width' => $source_width,
		'logo_source_height' => $source_height,
		'logo_was_normalized' => $normalized ? 1 : 0,
		'logo_file_size' => is_file($absolute_path) ? (int) @filesize($absolute_path) : (int) $file['size'],
	);

	return $result;
}

/**
 * Return the public URL for a saved Theme Lab header background path.
 */
function themelab_get_header_background_url($relative_path)
{
	return themelab_get_logo_url($relative_path);
}

/**
 * Validate and store one uploaded header background image.
 */
function themelab_process_header_background_upload($id_project, $file)
{
	global $boarddir;

	$result = array('image' => array(), 'errors' => array());

	if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
		return $result;

	if ((int) $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
	{
		$result['errors'][] = 'themelab_header_error_background_upload';
		return $result;
	}

	$max_bytes = 10 * 1024 * 1024;
	if (empty($file['size']) || (int) $file['size'] > $max_bytes)
	{
		$result['errors'][] = 'themelab_header_error_background_size';
		return $result;
	}

	$image_info = @getimagesize($file['tmp_name']);
	$allowed = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp');
	$mime = !empty($image_info['mime']) ? strtolower($image_info['mime']) : '';

	if ($image_info === false || empty($image_info[0]) || empty($image_info[1]) || !isset($allowed[$mime]))
	{
		$result['errors'][] = 'themelab_header_error_background_type';
		return $result;
	}

	if ((int) $image_info[0] > 16000 || (int) $image_info[1] > 16000)
	{
		$result['errors'][] = 'themelab_header_error_background_dimensions';
		return $result;
	}

	$relative_dir = 'themelab_assets/project_' . (int) $id_project . '/header';
	$absolute_dir = $boarddir . '/Themes/' . $relative_dir;

	if ((!is_dir($absolute_dir) && !@mkdir($absolute_dir, 0755, true)) || !is_writable($absolute_dir))
	{
		$result['errors'][] = 'themelab_header_error_background_directory';
		return $result;
	}

	$filename = 'header_background_' . time() . '_' . mt_rand(1000, 9999) . '.' . $allowed[$mime];
	$relative_path = $relative_dir . '/' . $filename;
	$absolute_path = $boarddir . '/Themes/' . $relative_path;

	if (!@move_uploaded_file($file['tmp_name'], $absolute_path))
	{
		$result['errors'][] = 'themelab_header_error_background_upload';
		return $result;
	}

	@chmod($absolute_path, 0644);
	$result['image'] = array(
		'background_image' => $relative_path,
		'background_image_filename' => isset($file['name']) ? basename($file['name']) : $filename,
		'background_image_mime' => $mime,
		'background_image_width' => (int) $image_info[0],
		'background_image_height' => (int) $image_info[1],
		'background_image_file_size' => (int) $file['size'],
	);

	return $result;
}

/**
 * Save Header Studio settings.
 */
function themelab_save_header($id_project, $header)
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

	$project_data['header'] = $header;

	themelab_save_project_data($id_project, $project_data);

	return true;
}

/**
 * Handle Header Studio form actions.
 */
function themelab_handle_header_actions()
{
	global $context;

	if (empty($_POST['themelab_save_header']))
		return;

	checkSession();

	$id_project = themelab_get_active_project_id();

	if (empty($id_project))
		redirectexit('action=admin;area=themelab;sa=project');

	$project = themelab_get_project_by_id($id_project);
	$existing_header = themelab_get_project_header($project);
	$remove_logo = !empty($_POST['themelab_remove_logo']);
	$remove_background = !empty($_POST['themelab_remove_header_background']);
	$has_upload = !empty($_FILES['themelab_logo_image'])
		&& isset($_FILES['themelab_logo_image']['error'])
		&& (int) $_FILES['themelab_logo_image']['error'] !== UPLOAD_ERR_NO_FILE;
	$has_background_upload = !empty($_FILES['themelab_header_background_image'])
		&& isset($_FILES['themelab_header_background_image']['error'])
		&& (int) $_FILES['themelab_header_background_image']['error'] !== UPLOAD_ERR_NO_FILE;

	$submitted = array(
		'height' => $_POST['themelab_header_height'] ?? '',
		'logo_text' => $_POST['themelab_logo_text'] ?? '',
		'logo_size' => $_POST['themelab_logo_size'] ?? '',
		'logo_alignment' => $_POST['themelab_logo_alignment'] ?? '',
		'smf_logo_visibility' => $_POST['themelab_smf_logo_visibility'] ?? '',
		'smf_logo_position' => $_POST['themelab_smf_logo_position'] ?? '',
		'background_mode' => !empty($existing_header['background_image']) ? 'image' : 'solid',
		'background_image_position' => $_POST['themelab_header_background_position'] ?? '',
		'background_image_position_x' => $_POST['themelab_header_background_position_x'] ?? '',
		'background_image_position_y' => $_POST['themelab_header_background_position_y'] ?? '',
		'background_image_size' => $_POST['themelab_header_background_size'] ?? '',
		'background_image_opacity' => $_POST['themelab_header_image_opacity'] ?? '',
		'background_overlay_opacity' => $_POST['themelab_header_background_opacity'] ?? '',
		'background_color' => $_POST['themelab_header_background_color'] ?? '',
		'text_color' => $_POST['themelab_header_text_color'] ?? '',
		'navigation_alignment' => $_POST['themelab_navigation_alignment'] ?? '',
		'navigation_style' => $_POST['themelab_navigation_style'] ?? '',
		'search_position' => $_POST['themelab_search_position'] ?? '',
		'utility_mode' => $_POST['themelab_utility_mode'] ?? '',
		'utility_position' => $_POST['themelab_utility_position'] ?? '',
	);

	$has_existing_logo = !$remove_logo && !empty($existing_header['logo_image']);
	$result = themelab_validate_header($submitted, $has_existing_logo || $has_upload);
	$header = array_merge($existing_header, $result['header']);

	if ($remove_logo)
	{
		foreach (array('logo_image', 'logo_filename', 'logo_mime') as $field)
			$header[$field] = '';
		foreach (array('logo_width', 'logo_height', 'logo_source_width', 'logo_source_height', 'logo_was_normalized', 'logo_file_size') as $field)
			$header[$field] = 0;
	}

	if (empty($result['errors']) && $has_upload)
	{
		$upload = themelab_process_logo_upload(
			$id_project,
			$_FILES['themelab_logo_image'],
			$remove_logo ? '' : ($existing_header['logo_image'] ?? '')
		);

		if (!empty($upload['errors']))
			$result['errors'] = array_merge($result['errors'], $upload['errors']);
		else
			$header = array_merge($header, $upload['logo']);
	}

	if ($remove_background)
	{
		foreach (array('background_image', 'background_image_filename', 'background_image_mime') as $field)
			$header[$field] = '';
		foreach (array('background_image_width', 'background_image_height', 'background_image_file_size') as $field)
			$header[$field] = 0;
		if ($header['background_mode'] === 'image')
			$header['background_mode'] = 'solid';
	}

	if (empty($result['errors']) && $has_background_upload)
	{
		$background_upload = themelab_process_header_background_upload($id_project, $_FILES['themelab_header_background_image']);
		if (!empty($background_upload['errors']))
			$result['errors'] = array_merge($result['errors'], $background_upload['errors']);
		else
		{
			$header = array_merge($header, $background_upload['image']);
			$header['background_mode'] = 'image';
		}
	}

	// Header Studio no longer owns gradient settings. Remove legacy copies so
	// Gradient Studio remains the sole source of truth for header gradients.
	foreach (array('gradient_start', 'gradient_end', 'gradient_direction') as $legacy_gradient_key)
		unset($header[$legacy_gradient_key]);

	$old_background = $existing_header['background_image'] ?? '';
	$new_background = $header['background_image'] ?? '';
	$old_logo = $existing_header['logo_image'] ?? '';
	$new_logo = $header['logo_image'] ?? '';

	$context['themelab_header'] = $header;
	$context['themelab_header_errors'] = array_values(array_unique($result['errors']));

	if (!empty($context['themelab_header_errors']))
		return;

	if (!themelab_save_header($id_project, $header))
	{
		if ($new_logo !== '' && $new_logo !== $old_logo)
			themelab_delete_logo_asset($new_logo);
		if ($new_background !== '' && $new_background !== $old_background)
			themelab_delete_logo_asset($new_background);

		$context['themelab_header_errors'][] = 'themelab_header_error_save';
		return;
	}

	if ($old_logo !== '' && $old_logo !== $new_logo)
		themelab_delete_logo_asset($old_logo);
	if ($old_background !== '' && $old_background !== $new_background)
		themelab_delete_logo_asset($old_background);

	redirectexit('action=admin;area=themelab;sa=header;header_saved');
}
