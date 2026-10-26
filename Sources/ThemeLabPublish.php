<?php

/**
 * Theme Lab Publish Studio.
 *
 * Validates the active project and creates an installable SMF theme archive.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

function themelab_publish_slug($value)
{
	$value = strtolower(trim((string) $value));
	$value = preg_replace('~[^a-z0-9]+~', '_', $value);
	return trim($value, '_') ?: 'themelab_theme';
}


/**
 * Return the logged-in SMF member name used as the automatic release publisher.
 */
function themelab_publish_current_publisher()
{
	global $user_info;

	foreach (array('name', 'real_name', 'username') as $key)
		if (!empty($user_info[$key]))
			return trim(htmlspecialchars_decode((string) $user_info[$key], ENT_QUOTES));

	return 'SMF Administrator';
}

/**
 * Increment the final numeric version component.
 * Examples: 2.0 -> 2.1, 2.0.0 -> 2.0.1, 4.3.7-beta -> 4.3.8.
 */

function themelab_publish_color_luminance($hex)
{
	$hex = strtoupper(trim((string) $hex));
	if (!preg_match('~^#([0-9A-F]{6})$~', $hex, $m))
		return 1.0;

	$rgb = array(
		hexdec(substr($m[1], 0, 2)) / 255,
		hexdec(substr($m[1], 2, 2)) / 255,
		hexdec(substr($m[1], 4, 2)) / 255,
	);

	foreach ($rgb as &$c)
		$c = $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
	unset($c);

	return (0.2126 * $rgb[0]) + (0.7152 * $rgb[1]) + (0.0722 * $rgb[2]);
}

function themelab_publish_theme_is_dark($project)
{
	$settings = themelab_build_preview_settings($project);
	$background = !empty($settings['background']) ? $settings['background'] : '#FFFFFF';
	$surface = !empty($settings['surface']) ? $settings['surface'] : '#FFFFFF';

	$score = (themelab_publish_color_luminance($background) * 0.55)
		+ (themelab_publish_color_luminance($surface) * 0.45);

	return $score < 0.24;
}

function themelab_publish_increment_version($version)
{
	$version = trim((string) $version);
	if ($version === '')
		return '1.0';

	if (preg_match('~^(.*?)(\d+)([^0-9]*)$~', $version, $match))
		return $match[1] . ((int) $match[2] + 1) . $match[3];

	return $version . '.1';
}

/**
 * Save the successfully published version back to the active project.
 */
function themelab_publish_store_version($id_project, $version)
{
	global $smcFunc;

	$id_project = (int) $id_project;
	if ($id_project <= 0)
		return false;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}themelab_projects
		SET theme_version = {string:theme_version}, date_modified = {int:date_modified}
		WHERE id_project = {int:id_project}',
		array(
			'id_project' => $id_project,
			'theme_version' => (string) $version,
			'date_modified' => time(),
		)
	);

	return true;
}

function themelab_publish_validate($project)
{
	global $boarddir;

	$data = !empty($project['project_data_array']) && is_array($project['project_data_array']) ? $project['project_data_array'] : array();
	$checks = array();

	$checks[] = array('key' => 'metadata', 'label' => 'Theme metadata', 'status' => !empty($project['project_name']) && !empty($project['author']) && !empty($project['theme_version']), 'message' => 'Theme name, author and version are required.');
	$checks[] = array('key' => 'base_theme', 'label' => 'Curve2 base theme', 'status' => is_file($boarddir . '/Themes/default/index.template.php'), 'message' => 'The default Curve2 theme could not be found.');
	$checks[] = array('key' => 'palette', 'label' => 'Palette', 'status' => !empty($data['palette']['colors']) && is_array($data['palette']['colors']), 'message' => 'Save Palette Studio before publishing.');
	$checks[] = array('key' => 'typography', 'label' => 'Typography', 'status' => !empty($data['typography']) && is_array($data['typography']), 'message' => 'Save Typography Studio before publishing.');
	$checks[] = array('key' => 'buttons', 'label' => 'Buttons', 'status' => !empty($data['buttons']) && is_array($data['buttons']), 'message' => 'Save Buttons Studio before publishing.');
	$checks[] = array('key' => 'forms', 'label' => 'Form controls', 'status' => !empty($data['forms']) && is_array($data['forms']), 'message' => 'Save Form Controls Studio before publishing.');
	$checks[] = array('key' => 'navigation', 'label' => 'Navigation', 'status' => !empty($data['navigation']) && is_array($data['navigation']), 'message' => 'Save Navigation Studio before publishing.');
	$checks[] = array('key' => 'board_index', 'label' => 'Board index', 'status' => !empty($data['board_index']) && is_array($data['board_index']), 'message' => 'Save Board Index Studio before publishing.');
	$checks[] = array('key' => 'header', 'label' => 'Header', 'status' => !empty($data['header']) && is_array($data['header']), 'message' => 'Save Header Studio before publishing.');
	$checks[] = array('key' => 'footer', 'label' => 'Footer', 'status' => !empty($data['footer']) && is_array($data['footer']), 'message' => 'Save Footer Studio before publishing.');
	$checks[] = array('key' => 'zip', 'label' => 'ZIP support', 'status' => class_exists('ZipArchive'), 'message' => 'PHP ZipArchive is required to build the package.');
	$checks[] = array('key' => 'workspace', 'label' => 'Publish workspace', 'status' => is_writable($boarddir . '/Packages') || @mkdir($boarddir . '/Packages', 0755, true), 'message' => 'The Packages directory must be writable.');

	$passed = 0;
	foreach ($checks as $check)
		if (!empty($check['status'])) $passed++;

	return array('items' => $checks, 'passed' => $passed, 'total' => count($checks), 'ready' => $passed === count($checks));
}

function themelab_publish_copy_tree($source, $destination)
{
	if (!is_dir($destination) && !@mkdir($destination, 0755, true))
		return false;

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $item)
	{
		$relative = substr($item->getPathname(), strlen($source) + 1);
		// Do not ship Theme Lab's own administration assets inside the generated theme.
		if (preg_match('~(^|/)(ThemeLab(?:\.template\.php)?|ThemeLab\.css|ThemeLabPreview\.js)$~i', $relative))
			continue;
		$target = $destination . '/' . $relative;
		if ($item->isDir())
		{
			if (!is_dir($target)) @mkdir($target, 0755, true);
		}
		else
		{
			if (!is_dir(dirname($target))) @mkdir(dirname($target), 0755, true);
			if (!@copy($item->getPathname(), $target)) return false;
		}
	}
	return true;
}


/**
 * Copy Header Studio artwork into the generated theme.
 *
 * Workspace uploads live under Themes/themelab_assets. A published theme must
 * own its own copy so it remains portable after Theme Lab is removed or the
 * theme is installed on another forum.
 */
function themelab_publish_header_assets($project, $theme_dir)
{
	global $boarddir;

	$data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array']
		: array();
	$header = !empty($data['header']) && is_array($data['header']) ? $data['header'] : array();

	$result = array(
		'logo_filename' => '',
		'background_filename' => '',
		'background_css_url' => '',
		'copied' => array(),
		'errors' => array(),
	);

	$themes_root = realpath($boarddir . '/Themes');
	if ($themes_root === false)
	{
		$result['errors'][] = 'Themes directory could not be resolved.';
		return $result;
	}

	$images_dir = rtrim($theme_dir, '/\\') . '/images';
	if (!is_dir($images_dir) && !@mkdir($images_dir, 0755, true))
	{
		$result['errors'][] = 'Generated theme images directory could not be created.';
		return $result;
	}

	$copy_asset = function ($relative, $base_name) use ($boarddir, $themes_root, $images_dir, &$result) {
		$relative = ltrim(str_replace('\\', '/', (string) $relative), '/');

		if ($relative === '' || strpos($relative, '..') !== false)
			return '';

		$source = $boarddir . '/Themes/' . $relative;
		$real_source = realpath($source);

		if (
			$real_source === false
			|| !is_file($real_source)
			|| strpos($real_source, $themes_root . DIRECTORY_SEPARATOR) !== 0
		)
		{
			$result['errors'][] = 'Header asset is missing or outside the Themes directory: ' . $relative;
			return '';
		}

		$extension = strtolower(pathinfo($real_source, PATHINFO_EXTENSION));
		if (!in_array($extension, array('png', 'jpg', 'jpeg', 'webp'), true))
		{
			$result['errors'][] = 'Header asset has an unsupported extension: ' . $relative;
			return '';
		}

		if ($extension === 'jpeg')
			$extension = 'jpg';

		$filename = $base_name . '.' . $extension;
		$target = $images_dir . '/' . $filename;

		if (!@copy($real_source, $target))
		{
			$result['errors'][] = 'Header asset could not be copied: ' . $relative;
			return '';
		}

		@chmod($target, 0644);
		$result['copied'][] = 'images/' . $filename;

		return $filename;
	};

	if (!empty($header['logo_image']))
		$result['logo_filename'] = $copy_asset($header['logo_image'], 'themelab-header-logo');

	if (!empty($header['background_image']))
	{
		$result['background_filename'] = $copy_asset($header['background_image'], 'themelab-header-background');

		if ($result['background_filename'] !== '')
			$result['background_css_url'] = '../images/' . $result['background_filename'];
	}

	return $result;
}

/**
 * Make Curve2's real forum-title slot consume Header Studio identity.
 */
function themelab_publish_patch_header_identity($file, $logo_filename, $logo_text)
{
	$content = @file_get_contents($file);
	if ($content === false)
		return false;

	if (strpos($content, 'themelab-header-identity') !== false)
		return true;

	$pattern = '~empty\(\$context\[\'header_logo_url_html_safe\'\]\)\s*\?\s*\$context\[\'forum_name_html_safe\'\]\s*:\s*\'<img src="\'\s*\.\s*\$context\[\'header_logo_url_html_safe\'\]\s*\.\s*\'" alt="\'\s*\.\s*\$context\[\'forum_name_html_safe\'\]\s*\.\s*\'">\'~';

	$safe_text = htmlspecialchars((string) $logo_text, ENT_QUOTES, 'UTF-8');

	if ($logo_filename !== '')
	{
		$safe_file = preg_replace('~[^A-Za-z0-9._-]+~', '', basename((string) $logo_filename));
		$replacement = '\'<img class="themelab_custom_logo" data-themelab-header-identity="1" src="\' . $settings[\'theme_url\'] . \'/images/' . $safe_file . '" alt="' . addslashes($safe_text) . '">\'';
	}
	else
	{
		$replacement = '\'' . addslashes($safe_text) . '<span class="themelab-header-identity" hidden></span>\'';
	}

	$patched = preg_replace($pattern, $replacement, $content, 1, $count);

	if ($count !== 1 || !is_string($patched))
		return false;

	return @file_put_contents($file, $patched) !== false;
}

function themelab_publish_flatten_tokens($value, $prefix = '')
{
	$tokens = array();
	if (!is_array($value))
	{
		if ($prefix !== '') $tokens[$prefix] = $value;
		return $tokens;
	}
	foreach ($value as $key => $item)
	{
		$path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
		if (is_array($item))
			$tokens += themelab_publish_flatten_tokens($item, $path);
		else
			$tokens[$path] = $item;
	}
	return $tokens;
}

function themelab_publish_studio_inventory($project)
{
	$data = !empty($project['project_data_array']) && is_array($project['project_data_array']) ? $project['project_data_array'] : array();
	$studios = array(
		'palette' => 'Palette', 'layout' => 'Layout', 'typography' => 'Typography',
		'buttons' => 'Buttons', 'forms' => 'Form Controls', 'navigation' => 'Navigation',
		'board_index' => 'Board Index', 'header' => 'Header', 'footer' => 'Footer',
		'gradients' => 'Gradient',
	);
	$inventory = array();
	foreach ($studios as $key => $label)
	{
		$tokens = isset($data[$key]) ? themelab_publish_flatten_tokens($data[$key]) : array();
		$inventory[$key] = array('label' => $label, 'count' => count($tokens), 'tokens' => array_keys($tokens));
	}
	return $inventory;
}


/**
 * Escape plain project text for use in a generated CSS content string.
 */
function themelab_publish_css_string($value)
{
	$value = (string) $value;

	// Resolve entities to real Unicode characters before writing CSS content.
	// Bounded repeated decoding also repairs footer text saved by older builds
	// where htmlspecialchars() could leave values such as &amp;copy;.
	for ($i = 0; $i < 4; $i++)
	{
		$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		if ($decoded === $value)
			break;

		$value = $decoded;
	}

	$value = strip_tags($value);
	$value = str_replace(array('\\', '"', "\r\n", "\r", "\n", "\t"), array('\\\\', '\"', ' ', ' ', ' ', ' '), $value);

	return trim(preg_replace('~\s+~u', ' ', $value));
}


/**
 * Compile Palette Studio values onto Curve2's real page and surface hierarchy.
 *
 * This intentionally avoids painting every wrapper the same color. The page
 * canvas, forum shell, information panels, content cards, alternate rows,
 * tables, and separators each receive the palette role used by the preview.
 */
function themelab_compile_palette_surfaces($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('border_radius', 6), 6);
    $css = "/* Palette and Surface translator: Curve2 hierarchy parity. */\n";

    /* Browser canvas and major transparent structural wrappers. */
    $css .= "html,body{background:var(--tl-background)!important;color:var(--tl-text)!important}\n";
    $css .= "#top_section,#header_section,#content_section,#main_content_section{background:transparent!important}\n";
    $css .= "#wrapper{background:transparent!important;border:0!important;box-shadow:none!important}\n";

    /* Forum shell and primary content surfaces. */
    $css .= ".main_container,.generic_list_wrapper,.roundframe,.roundframe2,.windowbg,.information,.errorbox,.noticebox{box-sizing:border-box;background:var(--tl-surface)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important;border-radius:{$radius}}\n";

    /* Alternate rows and secondary panels. */
    $css .= ".windowbg2,.windowbg:nth-child(even),.alternate,.table_grid tbody tr:nth-child(even) td,.settings{background:var(--tl-surface-alt)!important;color:var(--tl-text)!important}\n";

    /* Tables should preserve row hierarchy rather than inherit one solid block. */
    $css .= ".table_grid,.table_grid table,.boardindex_table{background:transparent!important;border-color:var(--tl-border)!important}\n";
    $css .= ".table_grid th,.table_grid td,.boardindex_table td,.boardindex_table th{border-color:var(--tl-border)!important}\n";
    /* Global SMF alternating rows: Package Manager, Error Log, admin/list tables,
     * and other generic Curve2 tables use Palette Studio's standard/alternate
     * surfaces. Specialist Board Index rules emitted later retain ownership there. */
    $css .= ".table_grid tbody tr:nth-child(odd),.table_grid tbody tr:nth-child(odd)>td,.generic_list_wrapper tbody tr:nth-child(odd),.generic_list_wrapper tbody tr:nth-child(odd)>td,.windowbg:not(.windowbg2){background-color:var(--tl-surface)!important;color:var(--tl-text)!important}\n";
    $css .= ".table_grid tbody tr:nth-child(even),.table_grid tbody tr:nth-child(even)>td,.generic_list_wrapper tbody tr:nth-child(even),.generic_list_wrapper tbody tr:nth-child(even)>td,.windowbg2,.alternate{background-color:var(--tl-surface-alt)!important;color:var(--tl-text)!important}\n";
    $css .= ".table_grid tbody td,.generic_list_wrapper tbody td,.windowbg,.windowbg2,.alternate{border-color:var(--tl-border)!important}\n";
    $css .= ".errorbox,.error,.error_message,.error_message td,.errorbox a{color:var(--tl-text)!important;border-color:var(--tl-border)!important}\n";

    /* Information, descriptive and low-emphasis text. */
    $css .= ".description,.smalltext,.lastpost,.board_description,.board_stats,.information .smalltext,.windowbg .smalltext,.windowbg2 .smalltext{color:var(--tl-muted-text)!important}\n";

    /* Links and separators. */
    $css .= "a,.linklevel1,.linklevel2{color:var(--tl-link)!important}a:visited{color:var(--tl-link_visited,var(--tl-link))!important}a:hover,a:focus,a:visited:hover{color:var(--tl-link_hover,var(--tl-primary))!important}\n";
    $css .= "hr,.hrcolor,.separator,.bordercolor{border-color:var(--tl-border)!important;color:var(--tl-border)!important}\n";

    /* Inputs retain their own surface while embedded in alternate panels. */
    $css .= "input,select,textarea{background-color:var(--tl-surface)!important;color:var(--tl-text)!important}\n";

    /* Package Manager operation buttons are intentionally compact.
       The global button family is too generous for three controls in the narrow
       operations column and causes Delete to wrap below Uninstall/List Files. */
    $css .= "table[id^=packages_lists_] td.righttext{white-space:nowrap!important;min-width:190px!important}\n";
    $css .= "table[id^=packages_lists_] td.righttext .button{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:26px!important;padding:3px 7px!important;margin:2px 0 2px 4px!important;font-size:.9em!important;line-height:1.1!important;white-space:nowrap!important;vertical-align:middle!important}\n";

    return $css;
}


/**
 * Assemble Curve2 header bars and their following content into one visual card.
 *
 * Curve2 often renders a heading bar and body as adjacent siblings. The preview
 * presents those pieces as one card, so the generated theme must join their
 * borders, radii, backgrounds, and spacing instead of rounding each piece.
 */
function themelab_compile_card_assembly($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('board_index_container_radius', $v('border_radius', 6)), 6);
    $panel_padding = $px($v('board_index_panel_padding', 12), 12);
    $border_width = $px($v('board_index_border_width', 1), 1);

    $bars = '.cat_bar,.title_bar,.sub_bar';
    $bar_titles = '.cat_bar .catbg,.title_bar .titlebg,.sub_bar .subbg';
    $joined_bodies = implode(',', array(
        '.cat_bar + .windowbg', '.title_bar + .windowbg', '.sub_bar + .windowbg',
        '.cat_bar + .windowbg2', '.title_bar + .windowbg2', '.sub_bar + .windowbg2',
        '.cat_bar + .information', '.title_bar + .information', '.sub_bar + .information',
        '.cat_bar + .roundframe', '.title_bar + .roundframe', '.sub_bar + .roundframe',
        '.cat_bar + .roundframe2', '.title_bar + .roundframe2', '.sub_bar + .roundframe2',
        '.cat_bar + .main_container', '.title_bar + .main_container', '.sub_bar + .main_container',
        '.cat_bar + .generic_list_wrapper', '.title_bar + .generic_list_wrapper', '.sub_bar + .generic_list_wrapper',
        '.cat_bar + fieldset', '.title_bar + fieldset', '.sub_bar + fieldset'
    ));

    $css = "/* Card assembly translator: join Curve2 heading bars to their bodies. */\n";
    $css .= "{$bars}{box-sizing:border-box!important;margin-bottom:0!important;border:{$border_width} solid var(--tl-border)!important;border-bottom-width:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:hidden!important}\n";
    $css .= "{$bar_titles}{box-sizing:border-box!important;margin:0!important;border:0!important;border-radius:0!important}\n";
    $css .= "{$joined_bodies}{box-sizing:border-box!important;margin-top:0!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:{$border_width} solid var(--tl-border)!important;border-top-width:0!important;border-radius:0 0 {$radius} {$radius}!important;padding:{$panel_padding}!important;box-shadow:none!important}\n";

    /* Common admin/theme-management bodies that are not consistently windowbg. */
    $css .= "fieldset{box-sizing:border-box;background:var(--tl-surface)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important;border-radius:{$radius}!important}\n";
    $css .= "fieldset legend{box-sizing:border-box!important;margin:0!important;padding:0 6px!important;background:var(--tl-surface)!important;background-image:none!important;color:var(--tl-text)!important;border:0!important;border-radius:0!important;box-shadow:none!important;text-shadow:none!important}\n";    $css .= "#admincenter fieldset legend,#admin_content fieldset legend,.admincenter fieldset legend{background:var(--tl-surface)!important;background-image:none!important;color:var(--tl-text)!important;padding:0 6px!important;border:0!important;box-shadow:none!important;text-shadow:none!important}\n";
    $css .= ".settings,.settings .windowbg,.admincenter .windowbg,#admin_content .windowbg{background:var(--tl-surface)!important;color:var(--tl-text)!important}\n";

    /* Remove gaps that visually split a header from its associated body. */
    $css .= "{$bars} + .windowbg,{$bars} + .windowbg2,{$bars} + .information,{$bars} + .roundframe,{$bars} + .roundframe2,{$bars} + fieldset{margin-bottom:var(--tl-content-spacing,12px)!important}\n";
    /* Theme Lab Workspace uses a form wrapper between its bar and body. */
    $css .= ".themelab_workspace_primary>.cat_bar{margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_workspace_primary>form{margin:0!important;padding:0!important}\n";
    $css .= ".themelab_workspace_primary>form>.windowbg{margin-top:0!important;border-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";

    return $css;
}


/**
 * Translate the real forum-facing board, topic, pagination, legend, and list
 * surfaces. Curve2 owns these with selectors that are more specific than the
 * generic windowbg rules, so they need a dedicated specialist to reproduce
 * the Preview's surface hierarchy and joined header/body components.
 */
function themelab_compile_forum_surfaces($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('board_index_container_radius', $v('border_radius', 6)), 6);
    $border_width = $px(max(1, (int) $v('board_index_border_width', 1)), 1);
    $row_gap = $px(max(0, (int) $v('board_index_row_spacing', 0)), 0);

    $css = "/* Forum Surface specialist: board/topic/list parity. */\n";

    /* Real board-index and topic-list tables must own their row surfaces. */
    $css .= ".boardindex_table,.topic_table,.table_grid,#topic_container,.topic_listing{box-sizing:border-box!important;background:transparent!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important;margin-top:0!important}\n";
    $css .= ".boardindex_table .windowbg,.boardindex_table .windowbg2,.boardindex_table tbody tr,.topic_table .windowbg,.topic_table .windowbg2,.topic_table tbody tr,#topic_container .windowbg,#topic_container .windowbg2,.topic_listing .windowbg,.topic_listing .windowbg2{background:var(--tl-surface)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important}\n";
    $css .= ".boardindex_table .windowbg2,.boardindex_table tbody tr:nth-child(even),.topic_table .windowbg2,.topic_table tbody tr:nth-child(even),#topic_container .windowbg2,.topic_listing .windowbg2{background:var(--tl-surface-alt)!important;color:var(--tl-text)!important}\n";
    $css .= ".boardindex_table td,.boardindex_table th,.topic_table td,.topic_table th,.table_grid td,.table_grid th,#topic_container td,#topic_container th{background-color:transparent!important;color:inherit!important;border-color:var(--tl-border)!important}\n";

    /* Sticky, locked, poll and selected rows keep hierarchy without reverting to Curve2. */
    $css .= ".topic_table .stickybg,.topic_table .lockedbg,.topic_table .stickybg2,.topic_table .lockedbg2,#topic_container .stickybg,#topic_container .lockedbg{background:var(--tl-surface-alt)!important;color:var(--tl-text)!important}\n";
    $css .= ".topic_table tr.selected,.topic_table .windowbg.selected,#topic_container .selected{background:color-mix(in srgb,var(--tl-primary) 14%,var(--tl-surface))!important}\n";

    /* Board/topic text ownership. */
    $css .= ".boardindex_table .info,.boardindex_table .stats,.boardindex_table .lastpost,.topic_table .info,.topic_table .stats,.topic_table .lastpost,#topic_container .info,#topic_container .stats,#topic_container .lastpost{color:var(--tl-text)!important;background:transparent!important}\n";
    $css .= ".boardindex_table .board_description,.boardindex_table .smalltext,.topic_table .smalltext,#topic_container .smalltext,.topic_details,.lastpost{color:var(--tl-muted-text)!important}\n";

    /* Pagination, button rows, legends and forum information areas. */
    $css .= ".pagesection,.buttonlist,.topic_pages,.additional_row,#posting_icons,.posting_icons,.topic_legend,.topic_legend ul,.topic_legend li,.mark_read,.forum_stats,.forum_online{box-sizing:border-box!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important}\n";
    $css .= ".pagesection{padding:6px 8px!important;margin:0!important}\n";
    $css .= "#posting_icons,.posting_icons,.topic_legend{border:{$border_width} solid var(--tl-border)!important;border-radius:{$radius}!important;padding:var(--tl-content-spacing,12px)!important}\n";
    $css .= ".pagelinks,.pagelinks a,.navPages{color:var(--tl-link)!important}\n";

    /* Join real Curve2 header/body pairs that are not windowbg siblings. */
    $joined = implode(',', array(
        '.cat_bar + .boardindex_table', '.title_bar + .boardindex_table',
        '.cat_bar + .topic_table', '.title_bar + .topic_table', '.sub_bar + .topic_table',
        '.cat_bar + .table_grid', '.title_bar + .table_grid', '.sub_bar + .table_grid',
        '.cat_bar + #topic_container', '.title_bar + #topic_container', '.sub_bar + #topic_container',
        '.cat_bar + .generic_list_wrapper', '.title_bar + .generic_list_wrapper', '.sub_bar + .generic_list_wrapper',
        '.cat_bar + .main_container', '.title_bar + .main_container', '.sub_bar + .main_container'
    ));
    $css .= "{$joined}{margin-top:0!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important;background:var(--tl-surface)!important}\n";
    $css .= ".cat_bar:has(+ .boardindex_table),.title_bar:has(+ .boardindex_table),.cat_bar:has(+ .topic_table),.title_bar:has(+ .topic_table),.sub_bar:has(+ .topic_table),.cat_bar:has(+ #topic_container),.title_bar:has(+ #topic_container){margin-bottom:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";

    /* Eliminate stock gaps between rows while respecting an explicit Lab row gap. */
    $css .= ".boardindex_table .windowbg,.boardindex_table .windowbg2,.topic_table .windowbg,.topic_table .windowbg2,#topic_container .windowbg,#topic_container .windowbg2{margin-block:{$row_gap}!important}\n";

    return $css;
}



/**
 * Translate the complete Curve2 topic-display screen as one visual system.
 *
 * Topic pages contain independent pagination, action, post, moderator,
 * breadcrumb, and quick-reply wrappers. Generic surface rules leave those
 * wrappers competing with Curve2 floats and margins, so this specialist owns
 * their flow, spacing, surfaces, controls, and editor chrome together.
 */
function themelab_compile_topic_toolbars($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('button_border_radius', $v('border_radius', 6)), 6);
    $gap = $px($v('button_gap', 6), 6);
    $pad_y = $px($v('button_padding_vertical', 8), 8);
    $pad_x = $px($v('button_padding_horizontal', 16), 16);
    $border_width = $px(max(1, (int) $v('button_border_width', 1)), 1);
    $section_gap = $v('content_spacing', 'medium') === 'tight' ? '8px' : ($v('content_spacing', 'medium') === 'relaxed' ? '18px' : '12px');
    $depth = $v('button_depth', 'soft');
    $shadow = $depth === 'flat' ? 'none' : ($depth === 'raised' ? '0 3px 0 rgba(0,0,0,.24),0 4px 8px rgba(0,0,0,.16)' : '0 2px 5px rgba(0,0,0,.14)');

    $css = "/* Topic toolbar specialist: compact families, not full-width color slabs. */\n";

    /* Pagination/action rows remain structural and visually quiet. */
    $css .= "#forumposts>.pagesection,.topic_wrapper>.pagesection,.topic_display>.pagesection{box-sizing:border-box!important;display:flex!important;align-items:center!important;justify-content:space-between!important;flex-wrap:wrap!important;gap:{$gap}!important;clear:both!important;float:none!important;position:relative!important;inset:auto!important;width:100%!important;min-height:0!important;margin:{$section_gap} 0!important;padding:0!important;background:transparent!important;border:0!important;border-radius:0!important;overflow:visible!important}\n";
    $css .= ".pagesection .floatleft,.pagesection .floatright,.pagesection .pagelinks{float:none!important;position:static!important;margin:0!important}\n";

    /* Only the top-level lists become flex toolbars; nested More menus stay menus. */
    $css .= ".pagesection>.buttonlist,.topic_actions>.buttonlist,.moderatorbar>.buttonlist,.moderation,.quickbuttons{box-sizing:border-box!important;background:var(--tl-surface-alt)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;box-shadow:none!important;padding:5px!important;margin:0!important}\n";
    $css .= ".buttonlist>ul,.quickbuttons{display:flex!important;align-items:center!important;justify-content:flex-end!important;flex-wrap:wrap!important;gap:{$gap}!important;margin:0!important;padding:0!important;list-style:none!important}\n";
    $css .= ".buttonlist>ul>li,.quickbuttons>li{float:none!important;position:relative!important;margin:0!important;padding:0!important;background:transparent!important;border:0!important;list-style:none!important}\n";

    $buttons = '.buttonlist>ul>li>a,.quickbuttons>li>a,.moderation>a,.moderation>ul>li>a';
    $css .= "{$buttons}{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:32px!important;margin:0!important;padding:{$pad_y} {$pad_x}!important;background:var(--tl-secondary)!important;color:var(--tl-button-secondary-text)!important;border:{$border_width} solid var(--tl-secondary)!important;border-radius:{$radius}!important;box-shadow:{$shadow}!important;text-shadow:none!important;text-decoration:none!important;font-family:var(--tl-font-family)!important;font-weight:var(--tl-button-font-weight)!important;line-height:1.2!important;white-space:nowrap!important}\n";
    $css .= "{$buttons}:hover,{$buttons}:focus{background:var(--tl-primary)!important;color:var(--tl-button-primary-text)!important;border-color:var(--tl-primary)!important;transform:none!important}\n";
    $css .= ".buttonlist>ul>li>a.active,.buttonlist>ul>li.active>a,.buttonlist .button_strip_reply,.buttonlist .button_strip_new_topic{background:var(--tl-primary)!important;color:var(--tl-button-primary-text)!important;border-color:var(--tl-primary)!important}\n";
    /*
     * 1.0.1-dev6: `remove_button` is SMF's icon class, not a destructive
     * button class. Styling `.quickbuttons .remove_button` as a danger button
     * paints the icon span itself red (most visible in Personal Messages).
     * Keep real moderation Delete/Remove buttons semantic, but leave the
     * native glyph transparent so the surrounding quickbutton owns the fill.
     */
    $css .= ".moderation .button_strip_remove,.moderation .button_strip_delete{background:var(--tl-button-danger-background)!important;color:var(--tl-button-danger-text)!important;border-color:var(--tl-button-danger-border)!important}\n";
    $css .= ".quickbuttons .main_icons.remove_button{background-color:transparent!important;border-color:transparent!important;box-shadow:none!important}\n";

    /* Restore Curve2's More menu as a dropdown rather than exposing its children. */
    $css .= ".quickbuttons>li>ul,.buttonlist>ul>li>ul{display:none!important;position:absolute!important;z-index:100!important;right:0!important;top:100%!important;min-width:150px!important;margin:4px 0 0!important;padding:5px!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;box-shadow:0 5px 14px rgba(0,0,0,.2)!important;list-style:none!important}\n";
    $css .= ".quickbuttons>li:hover>ul,.quickbuttons>li:focus-within>ul,.quickbuttons>li.open>ul,.buttonlist>ul>li:hover>ul,.buttonlist>ul>li:focus-within>ul{display:block!important}\n";
    $css .= ".quickbuttons>li>ul>li,.buttonlist>ul>li>ul>li{display:block!important;margin:0!important;padding:0!important}\n";
    $css .= ".quickbuttons>li>ul a,.buttonlist>ul>li>ul a{display:block!important;padding:7px 10px!important;background:transparent!important;color:var(--tl-text)!important;border:0!important;border-radius:3px!important;box-shadow:none!important;white-space:nowrap!important}\n";
    $css .= ".quickbuttons>li>ul a:hover,.buttonlist>ul>li>ul a:hover{background:var(--tl-surface-alt)!important;color:var(--tl-link)!important}\n";

    /* Moderator and jump controls are compact peer toolbars. */
    $css .= ".topic_navigation,.topic_actions,.moderatorbar,.jump_to{box-sizing:border-box!important;clear:both!important;float:none!important;position:relative!important;margin:{$section_gap} 0!important}\n";
    $css .= ".topic_navigation,.topic_actions,.moderatorbar{display:flex!important;align-items:center!important;justify-content:space-between!important;flex-wrap:wrap!important;gap:{$gap}!important;background:transparent!important;color:var(--tl-text)!important;border:0!important;padding:0!important}\n";
    $css .= ".jump_to{display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:{$gap}!important;background:var(--tl-surface-alt)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;padding:5px!important}\n";

    return $css;
}

/**
 * Assemble the author column, message body, and post actions into one coherent
 * Theme Lab post component without rewriting Curve2's template markup.
 */
function themelab_compile_post_component($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('border_radius', 6), 6);
    $section_gap = $v('content_spacing', 'medium') === 'tight' ? '8px' : ($v('content_spacing', 'medium') === 'relaxed' ? '18px' : '12px');

    $css = "/* Post component specialist: author, message, and actions as one card. */\n";
    $css .= "#forumposts,.topic_wrapper,.topic_display{box-sizing:border-box!important;position:relative!important;clear:both!important}\n";
    $css .= "#forumposts>.cat_bar,#forumposts>.title_bar,.topic_wrapper>.cat_bar,.topic_wrapper>.title_bar{margin:0!important;position:relative!important;z-index:1!important}\n";
    $css .= "#forumposts .windowbg,#forumposts .windowbg2{box-sizing:border-box!important;position:relative!important;clear:both!important;margin:0 0 {$section_gap}!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;overflow:visible!important}\n";
    $css .= "#forumposts .post_wrapper{box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(150px,22%) minmax(0,1fr)!important;align-items:stretch!important;background:transparent!important;color:var(--tl-text)!important;border:0!important}\n";
    $css .= "#forumposts .poster{box-sizing:border-box!important;float:none!important;width:auto!important;margin:0!important;padding:var(--tl-content-spacing,12px)!important;background:var(--tl-surface-alt)!important;color:var(--tl-text)!important;border:0!important;border-right:1px solid var(--tl-border)!important;border-radius:{$radius} 0 0 {$radius}!important}\n";
    $css .= "#forumposts .postarea{box-sizing:border-box!important;float:none!important;width:auto!important;min-width:0!important;margin:0!important;padding:var(--tl-content-spacing,12px)!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:0!important;border-radius:0 {$radius} {$radius} 0!important}\n";
    $css .= "#forumposts .post,#forumposts .inner{color:var(--tl-text)!important;background:transparent!important}\n";
    $css .= "#forumposts .keyinfo,#forumposts .post_subject,#forumposts .postarea .smalltext{color:var(--tl-muted-text)!important}\n";
    $css .= "#forumposts .quickbuttons{position:relative!important;clear:both!important;float:none!important;width:auto!important;margin:{$section_gap} 0 0 auto!important;padding:5px!important;border:1px solid var(--tl-border)!important}\n";
    $css .= "#forumposts .signature{clear:both!important;margin-top:{$section_gap}!important;padding-top:8px!important;border-top:1px solid var(--tl-border)!important;color:var(--tl-muted-text)!important}\n";
    $css .= "@media(max-width:700px){#forumposts .post_wrapper{display:block!important}#forumposts .poster{border-right:0!important;border-bottom:1px solid var(--tl-border)!important;border-radius:{$radius} {$radius} 0 0!important}#forumposts .postarea{border-radius:0 0 {$radius} {$radius}!important}}\n";

    return $css;
}

/**
 * Translate SCEditor chrome while preserving its icon sprites and behavior.
 */
function themelab_compile_sceditor($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('forms_border_radius', $v('border_radius', 6)), 6);
    $border_width = $px(max(1, (int) $v('forms_border_width', 1)), 1);
    $section_gap = $v('content_spacing', 'medium') === 'tight' ? '8px' : ($v('content_spacing', 'medium') === 'relaxed' ? '18px' : '12px');

    $css = "/* SCEditor specialist: themed frame and canvas, native icons intact. */\n";
    $css .= "#quickreplybox,.quickreplybox,#quickReplyOptions,.quick_reply{box-sizing:border-box!important;clear:both!important;margin:0 0 {$section_gap}!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:0 0 {$radius} {$radius}!important;padding:var(--tl-content-spacing,12px)!important;overflow:visible!important}\n";
    $css .= ".cat_bar+#quickreplybox,.title_bar+#quickreplybox,.cat_bar+.quickreplybox,.title_bar+.quickreplybox{margin-top:0!important;border-top:0!important}\n";
    $css .= ".sceditor-container{box-sizing:border-box!important;width:100%!important;background:var(--tl-surface)!important;border:{$border_width} solid var(--tl-border)!important;border-radius:0!important;overflow:hidden!important;box-shadow:none!important}\n";
    $css .= ".sceditor-toolbar{box-sizing:border-box!important;background:var(--tl-surface-alt)!important;border:0!important;border-bottom:{$border_width} solid var(--tl-border)!important;padding:6px!important}\n";
    $css .= ".sceditor-group{box-sizing:border-box!important;background:transparent!important;border:0!important;margin:2px!important;padding:0!important}\n";
    $css .= ".sceditor-button{background-color:transparent!important;border:1px solid transparent!important;border-radius:3px!important;box-shadow:none!important}\n";
    $css .= ".sceditor-button:hover,.sceditor-button.active{background-color:var(--tl-surface)!important;border-color:var(--tl-border)!important}\n";
    $css .= ".sceditor-container iframe,.sceditor-container textarea,#message{box-sizing:border-box!important;width:100%!important;min-height:180px!important;background:var(--tl-input-background,var(--tl-surface))!important;color:var(--tl-input-text,var(--tl-text))!important;border:0!important;border-radius:0!important;box-shadow:none!important}\n";
    $css .= "#quickreplybox .buttonlist,.quickreplybox .buttonlist{margin-top:{$section_gap}!important}\n";

    /* Full post composer: one outer surface; every nested seam remains square. */
    $css .= "#post_header{box-sizing:border-box!important;position:relative!important;z-index:40!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:visible!important}\n";
    $css .= "#post_header.cat_bar,#post_header.title_bar,#postmodify>.cat_bar,#postmodify>.title_bar,#post_area>.cat_bar,#post_area>.title_bar,.post_wrapper>.cat_bar,.post_wrapper>.title_bar{margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:hidden!important}\n";
    $css .= "#postmodify .sceditor-container,#post_area .sceditor-container,.post_wrapper .sceditor-container{position:relative!important;z-index:1!important}\n";
    $css .= "#post_header+form,#post_header+#postmodify,#post_header+.roundframe,#postmodify>.cat_bar+.roundframe,#postmodify>.title_bar+.roundframe,#post_area>.cat_bar+.roundframe,#post_area>.title_bar+.roundframe,.post_wrapper>.cat_bar+.roundframe,.post_wrapper>.title_bar+.roundframe{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";
    $css .= "#postmodify .sceditor-container,#post_area .sceditor-container,.post_wrapper .sceditor-container,#postmodify .sceditor-toolbar,#post_area .sceditor-toolbar{border-radius:0!important}\n";
    $css .= "#postmodify .drop_area,#postmodify .drop_zone,#postmodify .dropzone,#postmodify #drop_zone,#postmodify #dropzone,#postmodify .attachdrop,#postmodify .attachment_drop_zone,#postmodify .attachment_dropzone,#postmodify #attachment_dropzone,#postmodify .attachments,#post_area .drop_area,#post_area .drop_zone,#post_area .dropzone,#post_area #drop_zone,#post_area #dropzone,#post_area .attachdrop,#post_area .attachment_drop_zone,#post_area .attachment_dropzone,#post_area #attachment_dropzone,#post_area .attachments{box-sizing:border-box!important;margin-top:0!important;border-radius:0!important;box-shadow:none!important}\n";

    return $css;
}

/**
 * Translate the complete Curve2 topic-display screen through focused component
 * specialists rather than one broad repaint.
 */
function themelab_compile_topic_display($s)
{
    return themelab_compile_topic_toolbars($s)
        . themelab_compile_post_component($s)
        . themelab_compile_sceditor($s);
}

/**
 * Translate Curve2's top utility, primary navigation, breadcrumbs, admin tabs,
 * and admin quick-search controls from the saved Navigation, Header Buttons,
 * Forms, and Palette studio state.
 *
 * This area uses several unrelated Curve2 structures, so generic button rules
 * are not sufficient. These selectors deliberately target the real desktop
 * menu, user/date strip, linktree, generic admin tabs, and quick-search form.
 */
function themelab_compile_top_navigation_utility($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $gap = $px($v('navigation_menu_gap', 6), 6);
    $radius = $px($v('navigation_menu_radius', $v('button_border_radius', 5)), 5);
    $pad_y = $px($v('navigation_menu_padding_vertical', 6), 6);
    $pad_x = $px($v('navigation_menu_padding_horizontal', 12), 12);
    $border_width = $px(max(1, (int) $v('button_border_width', 1)), 1);
    $field_radius = $px($v('forms_border_radius', 4), 4);
    $field_border = $px(max(1, (int) $v('forms_border_width', 1)), 1);

    $css = "/* Top Navigation and Utility translator: real Curve2 structures. */\n";

    /* Top account/search strip and the utility area under the header. */
    $css .= "#top_section{background:var(--tl-surface)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important;box-shadow:none!important}\n";
    $css .= "#top_section .inner_wrap{background:transparent!important;color:var(--tl-text)!important}\n";
    $css .= "#top_info,#top_info>li,#top_info>li>a,#top_section a{color:var(--tl-link)!important;text-shadow:none!important}\n";
    $css .= "#top_info>li>a:hover,#top_info>li>a:focus,#top_info>li>a.open,#top_section a:hover{color:var(--tl-primary)!important}\n";
    $css .= "#upper_section,#inner_section{background:var(--tl-surface)!important;color:var(--tl-text)!important}\n";
    $css .= "#inner_wrap{border-color:var(--tl-border)!important}#inner_wrap .user,#inner_wrap .news{color:var(--tl-muted-text)!important}\n";

    /* Real desktop primary menu. Include .menu_nav because Curve2 can move it. */
    $css .= "#main_menu,.generic_menu{background:transparent!important}\n";
    /* 1.0.1-dev3h: keep the primary navigation and its dropdowns above topic
     * action toolbars. Theme Lab gives topic quickbuttons their own positioned
     * stacking contexts, so a long Home submenu could otherwise paint behind
     * Reply / Add Poll even when the submenu itself had a high z-index. */
    $css .= "#main_menu{position:relative!important;z-index:2200!important;overflow:visible!important}\n";
    $css .= "#main_menu>.dropmenu{position:relative!important;z-index:2210!important;overflow:visible!important}\n";
    $css .= "#main_menu .dropmenu>li{position:relative!important;z-index:auto!important;overflow:visible!important}\n";
    $css .= "#main_menu .dropmenu>li:hover,#main_menu .dropmenu>li:focus-within{z-index:2230!important}\n";
    $css .= "#main_menu .dropmenu>li>ul{z-index:2240!important}\n";

    /* 1.0.1-dev5: SMF renders secondary menus inside #genericmenu and the
     * page body (including PM pagination) inside the sibling #admin_content.
     * Raising only .generic_menu cannot escape a lower sibling stacking
     * context, so own the wrapper ordering as well. This keeps PM/Profile/
     * Administration dropdowns above page rows without disturbing the main
     * header, alerts, chat overlays, or modal layers. */
    $css .= "#genericmenu{position:relative!important;z-index:2100!important;overflow:visible!important;isolation:auto!important}\n";
    $css .= "#genericmenu+.mobile_generic_menu,#admin_content{position:relative!important;z-index:1!important}\n";
    $css .= "body.action_pm #genericmenu{z-index:2100!important;overflow:visible!important}\n";
    $css .= "body.action_pm #admin_content{z-index:1!important;overflow:visible!important}\n";
    $css .= "body.action_pm #admin_content .pagesection{position:relative!important;z-index:1!important}\n";
    $css .= ".generic_menu{position:relative!important;z-index:2110!important;overflow:visible!important}\n";
    $css .= ".generic_menu>.dropmenu{position:relative!important;z-index:2120!important;overflow:visible!important}\n";
    $css .= ".generic_menu .dropmenu>li{position:relative!important;z-index:auto!important;overflow:visible!important}\n";
    $css .= ".generic_menu .dropmenu>li:hover,.generic_menu .dropmenu>li:focus-within{z-index:2140!important}\n";
    $css .= ".generic_menu .dropmenu>li>ul{z-index:2150!important}\n";

    $css .= "#main_menu>.dropmenu,#main_menu .menu_nav,.generic_menu>.dropmenu,.menu_nav{display:flex!important;flex-wrap:wrap!important;gap:{$gap}!important;align-items:center!important;justify-content:var(--tl-navigation-alignment)!important;margin:0!important;padding:0!important}\n";
    $css .= "#main_menu .dropmenu>li,.generic_menu .dropmenu>li,.menu_nav>li{margin:0!important;padding:0!important;background:transparent!important}\n";
    $css .= "#main_menu .dropmenu>li>a,.generic_menu .dropmenu>li>a,.menu_nav>li>a{box-sizing:border-box!important;display:flex!important;align-items:center!important;gap:5px!important;background:var(--tl-button-header-background)!important;color:var(--tl-button-header-text)!important;border:{$border_width} solid var(--tl-button-header-border)!important;border-radius:{$radius}!important;padding:{$pad_y} {$pad_x}!important;box-shadow:none!important;text-shadow:none!important;font-weight:var(--tl-navigation-font-weight)!important;text-transform:var(--tl-navigation-text-transform)!important;text-decoration:none!important}\n";
    $css .= "#main_menu .dropmenu>li:hover>a,#main_menu .dropmenu>li>a:focus,.generic_menu .dropmenu>li:hover>a,.generic_menu .dropmenu>li>a:focus,.menu_nav>li:hover>a,.menu_nav>li>a:focus{background:var(--tl-button-header-hover-background)!important;color:var(--tl-button-header-hover-text)!important;border-color:var(--tl-button-header-border)!important;box-shadow:none!important;text-shadow:none!important}\n";
    $css .= ".generic_menu .dropmenu>li>a.active,.generic_menu .dropmenu>li>a.chosen{background:var(--tl-button-header-current-background)!important;color:var(--tl-button-header-current-text)!important;border-color:var(--tl-button-header-border)!important;box-shadow:none!important;text-shadow:none!important}\n";
    $css .= ".dropmenu .amt,#top_info .amt{background:var(--tl-primary)!important;color:var(--tl-button-primary-text)!important;text-shadow:none!important}\n";

    /* Dropdown submenus retain readable, themed surfaces and usable spacing. */
    $css .= ".dropmenu li ul,.top_menu{box-sizing:border-box!important;min-width:220px!important;width:max-content!important;max-width:min(360px,calc(100vw - 24px))!important;padding:6px 0!important;background:var(--tl-surface)!important;border-color:var(--tl-border)!important;box-shadow:0 4px 12px rgba(0,0,0,.16)!important}\n";
    $css .= ".dropmenu li li{box-sizing:border-box!important;display:block!important;width:100%!important;margin:0!important;padding:0!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;text-shadow:none!important}\n";
    $css .= ".dropmenu li li a{box-sizing:border-box!important;display:block!important;width:100%!important;min-width:220px!important;margin:0!important;padding:9px 16px!important;white-space:nowrap!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:0!important;border-radius:0!important;box-shadow:none!important;text-shadow:none!important;line-height:1.35!important}\n";
    $css .= ".dropmenu li li+li{border-top:1px solid color-mix(in srgb,var(--tl-border) 55%,transparent)!important}\n";
    $css .= ".dropmenu li li:hover,.dropmenu li li:hover>a,.dropmenu li li a:hover,.dropmenu li li a:focus,.dropmenu li li a.chosen{background:var(--tl-surface-alt)!important;color:var(--tl-link)!important;text-shadow:none!important}\n";

    /* RC6.1.21bn: keep SMF's user/PM/alerts popups attached to the item that opened them.
     * IMPORTANT: pm_menu and alerts_menu are initialized by SMF's jquery.custom-scrollbar.
     * That plugin measures the popup after moving the AJAX content into an absolutely
     * positioned .overview. If the popup itself is border-box with padding, its collapsed
     * padding/border height is mistaken for an intentional fixed viewport height, producing
     * the thin-strip popup. Preserve content-box for the scrollable menus and let SMF's
     * custom scrollbar own vertical overflow/viewport sizing. */
    $css .= "#top_info>li{position:relative!important}\n";
    $css .= "#top_info #profile_menu{box-sizing:border-box!important;left:0!important;right:auto!important;top:100%!important;width:320px!important;min-width:320px!important;max-width:min(360px,calc(100vw - 24px))!important;height:auto!important;margin-top:4px!important;padding:8px!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;box-shadow:0 6px 18px rgba(0,0,0,.28)!important}\n";
    $css .= "#top_info #pm_menu,#top_info #alerts_menu{box-sizing:content-box!important;left:0!important;right:auto!important;top:100%!important;width:320px!important;min-width:320px!important;max-width:min(360px,calc(100vw - 24px))!important;height:auto!important;max-height:30em!important;margin-top:4px!important;padding:8px!important;overflow:visible!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;box-shadow:0 6px 18px rgba(0,0,0,.28)!important}\n";
    $css .= "#top_info #pm_menu>.viewport,#top_info #alerts_menu>.viewport{width:100%!important}\n";
    $css .= "#top_info .top_menu .block,#top_info .top_menu .pm_bar,#top_info .top_menu .alert_bar,#top_info .top_menu .pm_unread,#top_info .top_menu .alerts_unread{box-sizing:border-box!important;display:block!important;width:100%!important;color:var(--tl-text)!important}\n";
    $css .= "#top_info .top_menu .pm_sending,#top_info .top_menu .alerts_opts{min-height:28px!important;padding:4px 2px 8px!important;color:var(--tl-text)!important}\n";
    $css .= "#top_info .top_menu .pm_mailbox,#top_info .top_menu .alerts_box{padding:8px 0!important;border-top:1px solid var(--tl-border)!important}\n";
    $css .= "#top_info .top_menu .pm_unread,#top_info .top_menu .alerts_unread{margin-top:0!important;border-top:1px solid var(--tl-border)!important}\n";
    $css .= "#top_info .top_menu .unread_notify{box-sizing:border-box!important;width:100%!important;padding:8px 5px!important;background:transparent!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important}\n";
    $css .= "#top_info .top_menu .unread_notify:hover{background:var(--tl-surface-alt)!important}\n";
    $css .= "#top_info .top_menu a{color:var(--tl-link)!important;text-shadow:none!important}#top_info .top_menu a:hover{color:var(--tl-primary)!important}\n";

    /* Icon-only system/admin controls must not inherit ordinary button surfaces. */
    $css .= "a.button:has(> .main_icons:only-child),button:has(> .main_icons:only-child),.button.icon_only,.button.icon-only{min-width:0!important;padding:3px!important;background:transparent!important;border-color:transparent!important;box-shadow:none!important}\n";
    $css .= "a.button:has(> .main_icons:only-child):hover,a.button:has(> .main_icons:only-child):focus,button:has(> .main_icons:only-child):hover,button:has(> .main_icons:only-child):focus,.button.icon_only:hover,.button.icon-only:hover{background:transparent!important;border-color:transparent!important;box-shadow:none!important}\n";

    /* SMF theme-list action icons are plain links/images inside header bars, not .button controls. */
    $css .= ".cat_bar .floatright>a,.title_bar .floatright>a,.cat_bar .floatright>button,.title_bar .floatright>button,.cat_bar .floatright>input[type=button],.title_bar .floatright>input[type=button]{min-width:0!important;margin:0 0 0 6px!important;padding:0!important;background:transparent!important;background-image:none!important;border:0!important;border-radius:0!important;box-shadow:none!important;text-shadow:none!important}\n";
    $css .= ".cat_bar .floatright>a:hover,.cat_bar .floatright>a:focus,.title_bar .floatright>a:hover,.title_bar .floatright>a:focus,.cat_bar .floatright>button:hover,.title_bar .floatright>button:hover{background:transparent!important;background-image:none!important;border-color:transparent!important;box-shadow:none!important}\n";
    $css .= ".cat_bar .floatright img,.title_bar .floatright img,.cat_bar .floatright .main_icons,.title_bar .floatright .main_icons{display:inline-block!important;margin:0!important;padding:0!important;background:transparent!important;background-color:transparent!important;background-image:initial!important;border:0!important;border-radius:0!important;box-shadow:none!important;vertical-align:middle!important}\n";

    /* Breadcrumb/linktree: use Navigation and Palette state instead of Curve2 white. */
    $css .= ".navigate_section{background:transparent!important;color:var(--tl-muted-text)!important}\n";
    $css .= ".navigate_section ul{box-sizing:border-box!important;background:var(--tl-surface-alt)!important;color:var(--tl-muted-text)!important;border:1px solid var(--tl-border)!important;border-radius:{$field_radius}!important;box-shadow:none!important}\n";
    $css .= ".navigate_section ul li{color:var(--tl-muted-text)!important;text-shadow:none!important}.navigate_section ul li a,.navigate_section ul li a span{color:var(--tl-link)!important;text-shadow:none!important}.navigate_section ul li .dividers{color:var(--tl-border)!important}\n";

    /* Admin subsection tabs are links, not ordinary buttons. */
    $css .= "#adm_submenus,.generic_menu{color:var(--tl-text)!important}#adm_submenus .dropmenu>li>a,.generic_menu .dropmenu>li>a{white-space:nowrap!important}\n";

    /* Admin quick search lives inside a cat_bar and needs its own control map. */
    $css .= ".admin_search{display:flex!important;align-items:center!important;gap:{$gap}!important;margin:0!important;padding:0!important}\n";
    $css .= ".admin_search .main_icons{color:var(--tl-board-index-header-text)!important}\n";
    $css .= ".admin_search input[type=search],.admin_search select{box-sizing:border-box!important;min-height:34px!important;margin:0!important;background:var(--tl-button-header-background)!important;color:var(--tl-button-header-text)!important;border:{$field_border} solid var(--tl-button-header-border)!important;border-radius:{$field_radius}!important;padding:5px 10px!important;box-shadow:none!important;outline:none!important}\n";
    $css .= ".admin_search input[type=search]::placeholder{color:var(--tl-button-header-text)!important;opacity:.72}\n";
    $css .= ".admin_search input[type=search]:focus,.admin_search select:focus{border-color:var(--tl-button-header-hover-background)!important;box-shadow:0 0 0 2px color-mix(in srgb,var(--tl-button-header-hover-background) 25%,transparent)!important}\n";
    $css .= ".admin_search input[type=submit],.admin_search #search_go{min-height:34px!important;margin:0!important;background:var(--tl-button-header-current-background)!important;color:var(--tl-button-header-current-text)!important;border:{$border_width} solid var(--tl-button-header-border)!important;border-radius:{$radius}!important;padding:5px 12px!important;box-shadow:none!important;text-shadow:none!important}\n";
    $css .= ".admin_search input[type=submit]:hover,.admin_search #search_go:hover{background:var(--tl-button-header-hover-background)!important;color:var(--tl-button-header-hover-text)!important}\n";

    return $css;
}

/**
 * Compile Footer Studio state onto Curve2's real footer structure.
 *
 * SMF's copyright and utility links remain intact. Theme Lab owns the
 * surrounding layout, presentation, optional custom text, and optional
 * Theme Lab credit.
 */

/**
 * Compile Typography Studio state onto Curve2's real text hierarchy.
 *
 * The preview applies typography at its root and lets purpose-built elements
 * inherit it. Curve2 contains many explicit font declarations, so the
 * published theme must translate the same values onto each semantic group.
 */
function themelab_compile_typography($s)
{
	$v = function ($key, $fallback = '') use ($s) {
		return array_key_exists($key, $s) ? $s[$key] : $fallback;
	};

	$base = (int) (is_numeric($v('font_size', 14)) ? $v('font_size', 14) : 14);
	$base = max(10, min(22, $base));
	$small = max(9, (int) round($base * 0.82));
	$meta = max(9, (int) round($base * 0.88));
	$large = max($base + 1, (int) round($base * 1.14));
	$h3 = max($base + 2, (int) round($base * 1.25));
	$h2 = max($base + 4, (int) round($base * 1.45));
	$h1 = max($base + 7, (int) round($base * 1.75));
	$weight = preg_match('~^(?:[1-9]00|normal|bold)$~', (string) $v('heading_weight', '700'))
		? (string) $v('heading_weight', '700')
		: '700';
	$family = trim((string) $v('font_family', 'Verdana, Geneva, sans-serif'));
	$mono = trim((string) $v('monospace_font', 'Consolas, "Courier New", monospace'));
	if ($family === '') $family = 'Verdana, Geneva, sans-serif';
	if (function_exists('themelab_get_monospace_fonts'))
	{
		$mono_fonts = themelab_get_monospace_fonts();
		if (isset($mono_fonts[$mono]['family']))
			$mono = (string) $mono_fonts[$mono]['family'];
	}
	if ($mono === '') $mono = 'Consolas, "Courier New", monospace';

	$css = "/* Typography Studio compiler: semantic Curve2 groups, no global control squeezing. */\n";
	$css .= "html,body,#wrapper,#content_section,#main_content_section,.main_container,.windowbg,.windowbg2,.roundframe,.roundframe2,.information,.generic_list_wrapper,.table_grid,.boardindex_table{font-family:{$family}!important}\n";
	$css .= "body,#wrapper,.windowbg,.windowbg2,.roundframe,.roundframe2,.information,.generic_list_wrapper,.table_grid,.boardindex_table{font-size:{$base}px!important;line-height:var(--tl-line-height)!important}\n";
	$css .= "p,li,dt,dd,td,th,legend,.content,.post,.inner,.info,.lastpost,.stats{font-family:{$family}!important}\n";
	$css .= "h1{font-size:{$h1}px!important}h2{font-size:{$h2}px!important}h3{font-size:{$h3}px!important}h4,h5,h6{font-size:{$large}px!important}\n";
	$css .= "h1,h2,h3,h4,h5,h6,.cat_bar .catbg,.title_bar .titlebg,.sub_bar .subbg,.forumtitle,.board_name,.topic_subject,.subject_title{font-family:{$family}!important;font-weight:{$weight}!important;line-height:1.25!important}\n";
	$css .= ".cat_bar .catbg,.title_bar .titlebg,.sub_bar .subbg{font-size:{$large}px!important}\n";
	$css .= "h1.forumtitle,.forumtitle{font-size:{$h1}px!important;font-weight:{$weight}!important}\n";
	$css .= "#main_menu a,.dropmenu a,.navigate_section,.breadcrumb,.button,.button_submit,input,select,textarea,label{font-family:{$family}!important}\n";
	$css .= "#main_menu a,.dropmenu a,.button,.button_submit,input[type=submit]{font-size:{$base}px!important;line-height:1.2!important}\n";
	$css .= "input[type=text],input[type=email],input[type=password],input[type=search],input[type=url],input[type=number],select,textarea{font-size:{$base}px!important;line-height:1.25!important}\n";
	$css .= ".smalltext,.description,.lastpost,.board_description,.board_stats,.poster,.keyinfo,.topic_details,.navigate_section,.pagesection,.additional_row{font-size:{$small}px!important;line-height:var(--tl-line-height)!important}\n";
	$css .= ".board_name,.topic_subject,.subject_title{font-size:{$large}px!important}\n";
	$css .= ".stats,.lastpost,.poster,.keyinfo{font-size:{$meta}px!important}\n";
	$css .= "code,pre,kbd,samp,.code,.bbc_code,.phpcode{font-family:{$mono}!important;font-size:.95em!important;line-height:1.45!important}\n";

	/* 1.0.1-dev13f: FINAL interactive-control text-state authority.
	 *
	 * SMF frequently uses <a> elements as buttons. Global :visited link colors
	 * must never recolor those controls. Keep content links free to use the
	 * theme's visited palette, but button-like UI owns its foreground in every
	 * link/pointer state.
	 */
	$css .= ".button,.button:link,.button:visited,.button:hover,.button:focus,.button:active,"
		. ".button_submit,.buttonlist a,.buttonlist a:link,.buttonlist a:visited,.buttonlist a:hover,.buttonlist a:focus,.buttonlist a:active,"
		. ".quickbuttons a,.quickbuttons a:link,.quickbuttons a:visited,.quickbuttons a:hover,.quickbuttons a:focus,.quickbuttons a:active,"
		. ".moderation a,.moderation a:link,.moderation a:visited,.moderation a:hover,.moderation a:focus,.moderation a:active,"
		. ".pagesection a.button,.pagesection a.button:link,.pagesection a.button:visited,.pagesection a.button:hover,.pagesection a.button:focus,.pagesection a.button:active"
		. "{color:var(--tl-button-secondary-text,#fff)!important;-webkit-text-fill-color:var(--tl-button-secondary-text,#fff)!important;text-decoration:none!important;text-shadow:none!important;}\n";

	/* Active/current/primary controls retain primary-button contrast in all states. */
	$css .= ".button.primary,.button.primary:link,.button.primary:visited,.button.primary:hover,.button.primary:focus,.button.primary:active,"
		. ".button.active,.button.active:link,.button.active:visited,.button.active:hover,.button.active:focus,.button.active:active,"
		. ".button.current,.button.current:link,.button.current:visited,.button.current:hover,.button.current:focus,.button.current:active,"
		. ".buttonlist a.active,.buttonlist a.active:link,.buttonlist a.active:visited,.buttonlist a.active:hover,.buttonlist a.active:focus,.buttonlist a.active:active,"
		. ".buttonlist .button_strip_reply,.buttonlist .button_strip_reply:link,.buttonlist .button_strip_reply:visited,.buttonlist .button_strip_reply:hover,.buttonlist .button_strip_reply:focus,.buttonlist .button_strip_reply:active,"
		. ".buttonlist .button_strip_new_topic,.buttonlist .button_strip_new_topic:link,.buttonlist .button_strip_new_topic:visited,.buttonlist .button_strip_new_topic:hover,.buttonlist .button_strip_new_topic:focus,.buttonlist .button_strip_new_topic:active,"
		. ".button_submit,input[type=submit]"
		. "{color:var(--tl-button-primary-text,#fff)!important;-webkit-text-fill-color:var(--tl-button-primary-text,#fff)!important;}\n";

	/* Danger controls keep their own readable foreground. */
	$css .= ".button.danger,.button.danger:link,.button.danger:visited,.button.danger:hover,.button.danger:focus,.button.danger:active,"
		. ".button.red,.button.red:link,.button.red:visited,.button.red:hover,.button.red:focus,.button.red:active,"
		. ".moderation .remove,.moderation .remove:link,.moderation .remove:visited,.moderation .remove:hover,.moderation .remove:focus,.moderation .remove:active,"
		. ".moderation .delete,.moderation .delete:link,.moderation .delete:visited,.moderation .delete:hover,.moderation .delete:focus,.moderation .delete:active"
		. "{color:var(--tl-button-danger-text,#fff)!important;-webkit-text-fill-color:var(--tl-button-danger-text,#fff)!important;}\n";

	/* Disabled is allowed to look subdued, never illegible. */
	$css .= ".button[disabled],.button.disabled,.button.disabled:link,.button.disabled:visited,.button.disabled:hover,.button.disabled:focus,.button.disabled:active,"
		. ".button[aria-disabled=true],.buttonlist a.disabled,.buttonlist a.disabled:link,.buttonlist a.disabled:visited,.buttonlist a.disabled:hover,.buttonlist a.disabled:focus,.buttonlist a.disabled:active,"
		. ".buttonlist a[aria-disabled=true],.quickbuttons a.disabled,.quickbuttons a[aria-disabled=true],"
		. ".button_submit[disabled],input[type=submit][disabled]"
		. "{color:var(--tl-button-disabled-text,#ccc)!important;-webkit-text-fill-color:var(--tl-button-disabled-text,#ccc)!important;background:var(--tl-button-disabled-background,var(--tl-surface-alt))!important;border-color:var(--tl-button-disabled-border,var(--tl-border))!important;opacity:1!important;}\n";

	/* Descendants (icons/spans/strong) inherit the protected control color. */
	$css .= ".button>*,.buttonlist a>*,.quickbuttons a>*,.moderation a>*,.pagesection a.button>*"
		. "{color:inherit!important;-webkit-text-fill-color:currentColor!important;text-shadow:inherit!important;}\n";


	return $css;
}


/**
 * Compile structural spacing and Curve2 chrome without changing project data.
 *
 * This normalizes the real SMF wrappers that surround the header, menus,
 * breadcrumbs, admin search controls, and content sections. It also removes
 * Curve2's inherited blue gloss from category/title bars so the saved Theme
 * Lab gradient or palette color can render faithfully.
 */
function themelab_compile_structural_translation($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $spacing = array(
        'tight' => '8px',
        'medium' => '12px',
        'relaxed' => '18px',
    );
    $section_gap = isset($spacing[$v('content_spacing', 'medium')])
        ? $spacing[$v('content_spacing', 'medium')]
        : $spacing['medium'];

    $category_bg = !empty($s['category_header_gradient_enabled']) && !empty($s['category_header_gradient_css'])
        ? $s['category_header_gradient_css']
        : $v('primary', '#3465A4');
    $card_bg = !empty($s['card_header_gradient_enabled']) && !empty($s['card_header_gradient_css'])
        ? $s['card_header_gradient_css']
        : $v('secondary', $v('primary', '#5B87B2'));

    $css = "/* Structural spacing and header-bar translator. */\n";

    /* Header-to-content rhythm and the white utility/navigation slab. */
    $css .= "#top_section{min-height:0!important;margin:0!important;padding:0!important}\n";
    $css .= "#top_section .inner_wrap{box-sizing:border-box;padding-top:6px!important;padding-bottom:6px!important}\n";
    $css .= "#upper_section{box-sizing:border-box;margin:0!important;padding:6px 0!important;min-height:0!important}\n";
    $css .= "#upper_section .inner_wrap{padding-top:0!important;padding-bottom:0!important}\n";
    $css .= "#main_menu{box-sizing:border-box;margin:6px 0!important;padding:0!important;min-height:0!important}\n";
    $css .= ".navigate_section,.breadcrumb{box-sizing:border-box;margin:6px 0 8px!important;padding:6px 10px!important;min-height:0!important}\n";
    $css .= "#content_section,#main_content_section{margin-top:0!important;padding-top:0!important}\n";
    $css .= ".main_container,.generic_list_wrapper,.roundframe,.roundframe2{margin-bottom:{$section_gap}!important}\n";

    /* Real admin header/search row: keep controls aligned and separated. */
    $css .= "#admin_menu .floatright,#admin_menu .righttext,.admin_search,.quick_search{display:flex!important;align-items:center!important;gap:5px!important;flex-wrap:nowrap!important}\n";
    $css .= "#admin_menu input,#admin_menu select,#admin_menu button,#admin_menu .button,.admin_search input,.admin_search select,.admin_search button{margin:0!important;vertical-align:middle!important}\n";

    /* Remove inherited Curve2 gloss, blue gradients, text shadows and chrome. */
    $css .= ".cat_bar,.cat_bar .catbg,.title_bar,.title_bar .titlebg,.sub_bar,.sub_bar .subbg{background-image:none!important;box-shadow:none!important;text-shadow:none!important}\n";
    $css .= ".cat_bar,.cat_bar .catbg{background:{$category_bg}!important;color:var(--tl-board-index-header-text)!important}\n";
    $css .= ".title_bar,.title_bar .titlebg,.sub_bar,.sub_bar .subbg{background:{$card_bg}!important;color:var(--tl-board-index-header-text)!important}\n";
    $css .= ".cat_bar,.title_bar,.sub_bar{border-color:var(--tl-border)!important;border-radius:" . $px($v('board_index_container_radius', 6), 6) . "!important;overflow:hidden!important}\n";
    $css .= ".cat_bar .catbg,.title_bar .titlebg,.sub_bar .subbg{margin:0!important;padding:8px 12px!important;border:0!important;border-radius:0!important}\n";
    $css .= ".cat_bar a,.title_bar a,.sub_bar a,.catbg a,.titlebg a,.subbg a{color:var(--tl-board-index-header-link)!important;text-shadow:none!important}\n";

    /* Visual compatibility for common floating chat bars; positioning remains the mod's responsibility. */
    $css .= ".chat_bar,.chat_header,#chat .cat_bar,#chatbox .cat_bar,[class*=chat]>.cat_bar{background:{$category_bg}!important;color:var(--tl-board-index-header-text)!important;text-shadow:none!important;box-shadow:none!important}\n";

    return $css;
}

function themelab_compile_footer($s)
{
	$v = function ($key, $fallback = '') use ($s) {
		return array_key_exists($key, $s) ? $s[$key] : $fallback;
	};
	$px = function ($value, $fallback = 0) {
		return (int) (is_numeric($value) ? $value : $fallback) . 'px';
	};

	$alignment = in_array($v('footer_text_alignment', 'center'), array('left', 'center', 'right'), true)
		? $v('footer_text_alignment', 'center')
		: 'center';
	$movable_align = $alignment === 'left' ? 'flex-start' : ($alignment === 'right' ? 'flex-end' : 'center');
	$footer_bg = !empty($s['footer_gradient_enabled']) && !empty($s['footer_gradient_css'])
		? $s['footer_gradient_css']
		: $v('footer_background_color', $v('footer', '#D5DEE5'));
	$custom_text = themelab_publish_css_string($v('footer_text', ''));
	$brand_text = themelab_publish_css_string('Made with Theme Lab');
	$show_smf_credit = !empty($s['footer_show_credit']);

	$css = "/* Footer Studio compiler: fixed SMF/system zones + movable Theme Lab text. */\n";
	$css .= "#footer{min-height:" . $px($v('footer_height', 70), 70) . ";box-sizing:border-box;background:{$footer_bg}!important;color:var(--tl-footer_text_color)!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:12px 0!important}\n";
	$css .= "#footer .inner_wrap{box-sizing:border-box;width:100%!important;max-width:none!important;margin:0!important;padding:0 var(--tl-content-spacing,14px)!important;display:flex!important;flex-direction:column!important;align-items:stretch!important;justify-content:center!important;gap:6px!important;background:transparent!important;border:0!important;box-shadow:none!important}\n";

	/* SMF/system row is fixed: copyright left, utility links right. */
	$css .= "#footer .inner_wrap>ul,#footer ul{float:none!important;width:100%!important;margin:0!important;padding:0!important;display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;grid-template-areas:'copyright utility'!important;align-items:start!important;gap:6px 18px!important;list-style:none!important}\n";
	$css .= "#footer li{float:none!important;margin:0!important;padding:0!important;display:block!important}\n";
	$css .= "#footer li.copyright{grid-area:copyright!important;justify-self:start!important;text-align:left!important;color:var(--tl-footer_text_color)!important}\n";
	$css .= "#footer li.floatright{grid-area:utility!important;justify-self:end!important;text-align:right!important;white-space:normal!important;color:var(--tl-footer_text_color)!important}\n";
	if (!$show_smf_credit)
		$css .= "#footer li.copyright{display:none!important}\n";

	/* Load-time/system diagnostics remain fixed left and never follow Studio alignment. */
	$css .= "#footer p,#footer .smalltext{float:none!important;width:100%!important;margin:0!important;padding:0!important;color:var(--tl-footer_text_color)!important;text-align:left!important;align-self:stretch!important}\n";
	$css .= "#footer a{color:var(--tl-footer_link_color)!important}\n";

	/* Only administrator text and the Theme Lab brand follow Footer Studio alignment. */
	if ($custom_text !== '')
		$css .= '#footer .inner_wrap::before{content:"' . $custom_text . '";display:block;order:-2;align-self:' . $movable_align . ';max-width:100%;color:var(--tl-footer_text_color)!important;font:inherit;text-align:' . $alignment . '!important;white-space:normal}' . "\n";

	if ($brand_text !== '')
		$css .= '#footer .inner_wrap::after{content:"' . $brand_text . '";display:block;order:20;align-self:' . $movable_align . ';max-width:100%;color:var(--tl-footer_text_color)!important;font-size:.9em;opacity:.82;text-align:' . $alignment . '!important;white-space:normal}' . "\n";

	$css .= "#footer .button,#footer a.button{background:var(--tl-button-footer-background)!important;color:var(--tl-button-footer-text)!important;border-color:var(--tl-button-footer-border)!important}\n";
	$css .= "#footer .button:hover,#footer a.button:hover{background:var(--tl-button-footer-hover-background)!important;color:var(--tl-button-footer-hover-text)!important}\n";
	$css .= "#footer .button.active,#footer .button.current,#footer a.button.active,#footer a.button.current{background:var(--tl-button-footer-current-background)!important;color:var(--tl-button-footer-current-text)!important}\n";

	return $css;
}


/**
 * Final Preview Reference parity layer.
 *
 * This is intentionally emitted after the older translators.  It treats the
 * mini-forum preview as the visual contract and resolves the remaining Curve2
 * specificity conflicts at the component level.
 */
function themelab_compile_preview_reference_parity($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('board_index_container_radius', $v('border_radius', 6)), 6);
    $small_radius = $px(max(2, (int) $v('forms_border_radius', 4)), 4);
    $gap = $v('content_spacing', 'medium') === 'compact' ? '8px' : ($v('content_spacing', 'medium') === 'relaxed' ? '18px' : '12px');
    $category_bg = !empty($s['category_header_gradient_enabled']) && !empty($s['category_header_gradient_css']) ? $s['category_header_gradient_css'] : $v('primary', '#3465A4');
    $card_bg = !empty($s['card_header_gradient_enabled']) && !empty($s['card_header_gradient_css']) ? $s['card_header_gradient_css'] : $v('secondary', $v('primary', '#5B87B2'));

    $css = "/* Preview Reference parity: the mini-forum is the visual contract. */\n";

    /* Keep the site canvas separate from the forum component surfaces. */
    $css .= "body{background:var(--tl-background)!important}\n";
    $css .= "#wrapper,#content_section,#main_content_section{background:transparent!important}\n";

    /* The preview uses one coherent card language for every structural block. */
    $css .= ".main_container,.generic_list_wrapper,.roundframe,.roundframe2,.information,.windowbg,.windowbg2,#forumposts .windowbg,#forumposts .windowbg2,.quick_reply,.post_wrapper{border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;box-sizing:border-box!important}\n";
    $css .= ".windowbg2,.alternate,.topic_table .windowbg2,.boardindex_table .windowbg2{background:var(--tl-surface-alt)!important}\n";

    /* Preview category/card bars retain their selected solid or gradient fill.
     * Do not clear background-image after assigning a gradient through the background shorthand. */
    $css .= ".cat_bar,.title_bar,.sub_bar{background:{$category_bg}!important;border:1px solid var(--tl-border)!important;box-shadow:none!important;text-shadow:none!important;color:var(--tl-board-index-header-text)!important}\n";
    $css .= ".title_bar,.sub_bar{background:{$card_bg}!important}\n";
    $css .= ".cat_bar .catbg,.title_bar .titlebg,.sub_bar .subbg{background:transparent!important;background-image:none!important;color:inherit!important;text-shadow:none!important}\n";

    /* Join every common bar/body pair into the single component shown in preview. */
    $css .= ".cat_bar:has(+ .windowbg),.title_bar:has(+ .windowbg),.sub_bar:has(+ .windowbg),.cat_bar:has(+ .windowbg2),.title_bar:has(+ .windowbg2),.cat_bar:has(+ .information),.title_bar:has(+ .information),.cat_bar:has(+ .boardindex_table),.title_bar:has(+ .topic_table),.title_bar:has(+ #forumposts),.cat_bar:has(+ .generic_list_wrapper),.title_bar:has(+ .generic_list_wrapper){margin-bottom:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= ".cat_bar + .windowbg,.title_bar + .windowbg,.sub_bar + .windowbg,.cat_bar + .windowbg2,.title_bar + .windowbg2,.cat_bar + .information,.title_bar + .information,.cat_bar + .boardindex_table,.title_bar + .topic_table,.title_bar + #forumposts,.cat_bar + .generic_list_wrapper,.title_bar + .generic_list_wrapper{margin-top:0!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important}\n";

    /* Board and topic rows mirror the preview cards instead of transparent cells. */
    $css .= ".boardindex_table tbody tr,.topic_table tbody tr,#topic_container .windowbg,#topic_container .windowbg2{background:var(--tl-surface)!important;color:var(--tl-text)!important}\n";
    $css .= ".boardindex_table tbody tr:nth-child(even),.topic_table tbody tr:nth-child(even){background:var(--tl-surface-alt)!important}\n";
    $css .= ".boardindex_table td,.topic_table td,#topic_container td{border-color:var(--tl-border)!important}\n";

    /* Topic display: quiet toolbar surfaces with controls carrying the color. */
    $css .= "#forumposts>.pagesection,.topic_wrapper>.pagesection,.topic_display>.pagesection,.moderatorbar,.topic_actions{background:var(--tl-surface-alt)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;padding:6px!important;margin:{$gap} 0!important}\n";
    $css .= ".pagesection>.buttonlist,.topic_actions>.buttonlist,.moderatorbar>.buttonlist,.moderation,.quickbuttons{background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important}\n";

    /* Post cards match the preview's author/content/actions hierarchy. */
    $css .= "#forumposts .windowbg,#forumposts .windowbg2{display:grid!important;grid-template-columns:minmax(150px,22%) minmax(0,1fr)!important;column-gap:{$gap}!important;padding:{$gap}!important;overflow:visible!important}\n";
    $css .= "#forumposts .poster{background:var(--tl-surface-alt)!important;border-right:1px solid var(--tl-border)!important;padding:{$gap}!important;border-radius:{$small_radius}!important}\n";
    $css .= "#forumposts .postarea,#forumposts .post{background:transparent!important;color:var(--tl-text)!important;min-width:0!important}\n";
    $css .= "#forumposts .quickbuttons{grid-column:2!important;justify-self:end!important;max-width:100%!important}\n";

    /* Forms and editor surfaces use the exact preview surface roles. */
    $css .= ".sceditor-container,.sceditor-toolbar,.sceditor-container iframe,.sceditor-container textarea,.quick_reply textarea{background:var(--tl-surface)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important}\n";
    $css .= ".sceditor-toolbar{background:var(--tl-surface-alt)!important;border-bottom:1px solid var(--tl-border)!important}\n";

    /* Consistent component rhythm. */
    $css .= ".cat_bar,.title_bar,.sub_bar,.main_container,.generic_list_wrapper,.roundframe,.roundframe2,.information,#forumposts,.quick_reply{margin-bottom:{$gap}!important}\n";
    $css .= ".cat_bar + .windowbg,.title_bar + .windowbg,.cat_bar + .boardindex_table,.title_bar + .topic_table,.title_bar + #forumposts{margin-bottom:{$gap}!important}\n";

    return $css;
}


/**
 * RC6.1.16 layout reconstruction.
 *
 * Curve2's legacy floats and percentage widths can survive color/surface
 * translation and collapse topic titles, posts, attachments, and board rows.
 * This final specialist reconstructs those page layouts without changing SMF
 * templates or feature behavior.
 */
function themelab_compile_layout_reconstruction($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('border_radius', 6), 6);
    $button_radius = $px($v('button_border_radius', 5), 5);
    $gap = $v('content_spacing', 'medium') === 'compact' ? '8px' : ($v('content_spacing', 'medium') === 'relaxed' ? '18px' : '12px');
    $row_padding = $px($v('board_index_row_padding', 12), 12);

    $css = "/* RC6.1.16 Layout Reconstruction: topic, post, board, and header parity. */\n";

    /* Topic heading: subject/author and previous-next links get separate columns. */
    $css .= ".display_title,.topic_title,#forumposts>.title_bar,.topic_wrapper>.title_bar{box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;align-items:end!important;gap:{$gap}!important;clear:both!important;float:none!important;position:relative!important;width:100%!important;overflow:visible!important}\n";
    $css .= ".display_title .nextlinks,.display_title .next_topic,.topic_title .nextlinks,.topic_title .next_topic,#forumposts>.title_bar .floatright,.topic_wrapper>.title_bar .floatright{grid-column:2!important;justify-self:end!important;align-self:end!important;float:none!important;position:static!important;inset:auto!important;width:auto!important;max-width:100%!important;margin:0!important;padding:0!important;white-space:nowrap!important}\n";
    $css .= ".display_title .floatleft,.topic_title .floatleft,#forumposts>.title_bar .floatleft,.topic_wrapper>.title_bar .floatleft{grid-column:1!important;min-width:0!important;float:none!important;position:static!important;width:auto!important;margin:0!important}\n";

    /* Action rows: one flex line, no legacy floats, no text overlap. */
    $css .= ".pagesection{box-sizing:border-box!important;display:flex!important;align-items:center!important;justify-content:space-between!important;flex-wrap:wrap!important;gap:{$gap}!important;clear:both!important;float:none!important;position:relative!important;width:100%!important;min-height:0!important;overflow:visible!important}\n";
    $css .= ".pagesection>.buttonlist,.pagesection>.pagelinks,.pagesection>.floatleft,.pagesection>.floatright{float:none!important;position:static!important;width:auto!important;max-width:100%!important;margin:0!important}\n";
    $css .= ".pagesection>.buttonlist ul{display:flex!important;align-items:center!important;flex-wrap:wrap!important;gap:6px!important}\n";

    /* Button families: retain readable foregrounds and consistent dimensions. */
    $css .= ".buttonlist a,.quickbuttons a,.moderation a,.pagesection a.button,.pagesection .button{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:30px!important;width:auto!important;max-width:100%!important;padding:6px 12px!important;border-radius:{$button_radius}!important;line-height:1.15!important;white-space:nowrap!important;opacity:1!important;filter:none!important}\n";
    $css .= ".buttonlist a:not(.active):not(.button_strip_reply):not(.button_strip_new_topic),.quickbuttons a,.moderation a{background:var(--tl-button-secondary-background,var(--tl-secondary))!important;color:var(--tl-button-secondary-text,#fff)!important;border-color:var(--tl-button-secondary-border,var(--tl-secondary))!important}\n";
    $css .= ".buttonlist .button_strip_reply,.buttonlist .button_strip_new_topic,.buttonlist a.active{background:var(--tl-button-primary-background,var(--tl-primary))!important;color:var(--tl-button-primary-text,#fff)!important;border-color:var(--tl-button-primary-border,var(--tl-primary))!important}\n";

    /* Reconstruct each post from Curve2's actual post wrapper, not the outer row. */
    $css .= "#forumposts .windowbg,#forumposts .windowbg2{display:block!important;padding:0!important;min-height:0!important}\n";
    $css .= "#forumposts .post_wrapper{box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(160px,210px) minmax(0,1fr)!important;align-items:stretch!important;width:100%!important;min-width:0!important;min-height:0!important;gap:0!important}\n";
    $css .= "#forumposts .poster{grid-column:1!important;box-sizing:border-box!important;float:none!important;position:static!important;width:auto!important;min-width:0!important;max-width:none!important;height:auto!important;margin:0!important;padding:{$gap}!important;overflow-wrap:anywhere!important}\n";
    $css .= "#forumposts .postarea{grid-column:2!important;box-sizing:border-box!important;float:none!important;position:relative!important;width:auto!important;min-width:0!important;max-width:none!important;height:auto!important;min-height:160px!important;margin:0!important;padding:{$gap}!important;overflow:visible!important}\n";
    $css .= "#forumposts .post,#forumposts .inner{box-sizing:border-box!important;display:block!important;width:100%!important;min-width:0!important;max-width:100%!important;height:auto!important;overflow-wrap:anywhere!important}\n";
    $css .= "#forumposts .attachments,#forumposts .attachment,#forumposts .attachments_top,#forumposts .postarea .attachments{box-sizing:border-box!important;display:flex!important;align-items:flex-start!important;flex-wrap:wrap!important;gap:{$gap}!important;clear:both!important;float:none!important;position:static!important;width:100%!important;max-width:100%!important;margin:{$gap} 0 0!important;padding-top:{$gap}!important;border-top:1px solid var(--tl-border)!important}\n";
    $css .= "#forumposts .attachments .attachment,#forumposts .attachment{width:auto!important;max-width:220px!important;margin:0!important}\n";
    $css .= "#forumposts .quickbuttons{box-sizing:border-box!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;flex-wrap:wrap!important;gap:6px!important;clear:both!important;float:none!important;position:relative!important;right:auto!important;bottom:auto!important;width:auto!important;max-width:100%!important;margin:{$gap} 0 0 auto!important}\n";

    /* Board index rows become stable four-column components. */
    $css .= ".boardindex_table .windowbg,.boardindex_table .windowbg2{box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(44px,64px) minmax(0,1fr) minmax(80px,110px) minmax(190px,28%)!important;align-items:center!important;column-gap:{$gap}!important;width:100%!important;min-width:0!important;padding:{$row_padding}!important}\n";
    $css .= ".boardindex_table .board_icon,.boardindex_table .icon{grid-column:1!important;float:none!important;width:auto!important;margin:0!important;text-align:center!important}\n";
    $css .= ".boardindex_table .info{grid-column:2!important;float:none!important;width:auto!important;min-width:0!important;margin:0!important;padding:0!important}\n";
    $css .= ".boardindex_table .board_stats,.boardindex_table .stats{grid-column:3!important;float:none!important;width:auto!important;margin:0!important;padding:0!important;text-align:center!important}\n";
    $css .= ".boardindex_table .lastpost,.boardindex_table .last_post{grid-column:4!important;float:none!important;width:auto!important;min-width:0!important;margin:0!important;padding:0!important}\n";

    /* Header image uses the preview's isolated, clipped layering and full box. */
    $css .= "#header{background-clip:border-box!important;background-origin:border-box!important}\n";
    $css .= "#header::before{inset:0!important;width:100%!important;height:100%!important;transform:none!important;background-origin:border-box!important;background-clip:border-box!important}\n";

    $css .= "@media(max-width:760px){.display_title,.topic_title,#forumposts>.title_bar,.topic_wrapper>.title_bar{grid-template-columns:1fr!important}.display_title .nextlinks,.display_title .next_topic,.topic_title .nextlinks,.topic_title .next_topic,#forumposts>.title_bar .floatright,.topic_wrapper>.title_bar .floatright{grid-column:1!important;justify-self:start!important;white-space:normal!important}.boardindex_table .windowbg,.boardindex_table .windowbg2{grid-template-columns:48px minmax(0,1fr)!important}.boardindex_table .board_stats,.boardindex_table .stats,.boardindex_table .lastpost,.boardindex_table .last_post{grid-column:2!important;text-align:left!important;margin-top:6px!important}#forumposts .post_wrapper{grid-template-columns:1fr!important}#forumposts .poster,#forumposts .postarea{grid-column:1!important;border-right:0!important}#forumposts .poster{border-bottom:1px solid var(--tl-border)!important}}\n";

    return $css;
}


/**
 * RC6.1.17 preview-to-reality parity pass.
 *
 * This pass deliberately touches only the three largest remaining visual
 * mismatches: Board Index component geometry, Topic/Post component geometry,
 * and header image layering/crop.  It is emitted last so legacy Curve2 rules
 * cannot reclaim those layouts after the specialists have translated them.
 */
function themelab_compile_preview_reality_pass($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('board_index_container_radius', $v('border_radius', 6)), 6);
    $gap = $v('content_spacing', 'medium') === 'compact' ? '8px' : ($v('content_spacing', 'medium') === 'relaxed' ? '18px' : '12px');
    $row_padding = $px($v('board_index_row_padding', 12), 12);
    $button_radius = $px($v('button_border_radius', 5), 5);
    $button_py = $px($v('button_padding_vertical', 8), 8);
    $button_px = $px($v('button_padding_horizontal', 16), 16);

    $css = "/* RC6.1.17 Preview to Reality: final component authority. */\n";

    /* Header image parity: one isolated layer, same crop box as the preview. */
    $css .= "#header{position:relative!important;isolation:isolate!important;overflow:visible!important;background-clip:border-box!important;background-origin:border-box!important}\n";
    $css .= "#header::before,#header::after{border-radius:inherit!important}\n";
    $css .= "#header::before{position:absolute!important;inset:0!important;z-index:0!important;width:auto!important;height:auto!important;min-width:100%!important;min-height:100%!important;background-position:var(--tl-header-background-position-x,50%) var(--tl-header-background-position-y,50%)!important;background-size:var(--tl-header-background-size,cover)!important;background-repeat:no-repeat!important;transform:none!important}\n";
    $css .= "#header::after{z-index:1!important}#header>*{position:relative!important;z-index:2!important}\n";

    /* Topic heading: make the title and previous/next navigation true peers. */
    $css .= "#forumposts>.cat_bar,#forumposts>.title_bar,.topic_wrapper>.cat_bar,.topic_wrapper>.title_bar,.display_title,.topic_title{display:grid!important;grid-template-columns:minmax(0,1fr) max-content!important;align-items:center!important;gap:{$gap}!important;width:100%!important;min-width:0!important;box-sizing:border-box!important;overflow:visible!important}\n";
    $css .= "#forumposts>.cat_bar .catbg,#forumposts>.title_bar .titlebg,.topic_wrapper>.cat_bar .catbg,.topic_wrapper>.title_bar .titlebg,.display_title .floatleft,.topic_title .floatleft{grid-column:1!important;float:none!important;position:static!important;min-width:0!important;width:auto!important;margin:0!important}\n";
    $css .= "#forumposts>.cat_bar .floatright,#forumposts>.title_bar .floatright,.topic_wrapper>.cat_bar .floatright,.topic_wrapper>.title_bar .floatright,.display_title .floatright,.topic_title .floatright,.display_title .nextlinks,.topic_title .nextlinks{grid-column:2!important;float:none!important;position:static!important;justify-self:end!important;width:auto!important;max-width:100%!important;margin:0!important;white-space:nowrap!important}\n";

    /* Post card: author, message, attachments, and actions stay in one component. */
    $css .= "#forumposts .windowbg,#forumposts .windowbg2{display:block!important;padding:0!important;overflow:hidden!important}\n";
    $css .= "#forumposts .post_wrapper{display:grid!important;grid-template-columns:minmax(170px,220px) minmax(0,1fr)!important;grid-template-areas:'poster post' 'poster actions'!important;gap:0!important;width:100%!important;min-width:0!important;align-items:stretch!important}\n";
    $css .= "#forumposts .poster{grid-area:poster!important;float:none!important;position:static!important;width:auto!important;min-width:0!important;max-width:none!important;margin:0!important;padding:{$gap}!important;border-right:1px solid var(--tl-border)!important;border-radius:0!important;background:var(--tl-surface-alt)!important}\n";
    $css .= "#forumposts .postarea{grid-area:post!important;float:none!important;position:relative!important;width:auto!important;min-width:0!important;max-width:none!important;min-height:180px!important;margin:0!important;padding:{$gap}!important;background:var(--tl-surface)!important}\n";
    $css .= "#forumposts .post,#forumposts .inner{display:block!important;width:100%!important;min-width:0!important;max-width:100%!important;overflow-wrap:anywhere!important}\n";
    $css .= "#forumposts .attachments,#forumposts .attachments_top,#forumposts .postarea .attachments{display:flex!important;flex-wrap:wrap!important;align-items:flex-start!important;gap:{$gap}!important;clear:both!important;float:none!important;position:static!important;width:100%!important;max-width:100%!important;margin:{$gap} 0 0!important;padding:{$gap} 0 0!important;border-top:1px solid var(--tl-border)!important}\n";
    $css .= "#forumposts .quickbuttons{grid-area:actions!important;display:flex!important;flex-direction:row!important;align-items:center!important;justify-content:flex-end!important;flex-wrap:wrap!important;gap:6px!important;float:none!important;position:static!important;width:auto!important;max-width:100%!important;margin:0!important;padding:0 {$gap} {$gap}!important;background:var(--tl-surface)!important}\n";
    $css .= "#forumposts .quickbuttons li,#forumposts .quickbuttons ul{display:flex!important;flex-direction:row!important;align-items:center!important;gap:6px!important;float:none!important;margin:0!important;padding:0!important}\n";

    /* Button families: never allow white-on-white or inherited Curve2 chrome. */
    $css .= ".buttonlist a,.quickbuttons a,.moderation a,.pagesection a.button,.pagesection .button{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:auto!important;min-height:32px!important;padding:{$button_py} {$button_px}!important;border-radius:{$button_radius}!important;border-style:solid!important;line-height:1.15!important;white-space:nowrap!important;text-decoration:none!important;opacity:1!important;filter:none!important}\n";
    $css .= ".buttonlist a:not(.active):not(.button_strip_reply):not(.button_strip_new_topic),.quickbuttons a,.moderation a{background:var(--tl-secondary)!important;color:var(--tl-button-secondary-text,var(--tl-text))!important;border-color:var(--tl-secondary)!important}\n";
    $css .= ".buttonlist .button_strip_reply,.buttonlist .button_strip_new_topic,.buttonlist a.active{background:var(--tl-primary)!important;color:var(--tl-button-primary-text,#fff)!important;border-color:var(--tl-primary)!important}\n";

    /* Board Index reconstruction: one coherent preview-like row component. */
    $css .= "#boardindex_table .windowbg,#boardindex_table .windowbg2,.boardindex_table .windowbg,.boardindex_table .windowbg2{display:grid!important;grid-template-columns:minmax(48px,64px) minmax(0,1fr) 250px minmax(230px,27%)!important;align-items:center!important;gap:{$gap}!important;width:100%!important;min-width:0!important;box-sizing:border-box!important;margin:0!important;padding:{$row_padding}!important;border:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= "#boardindex_table .windowbg2,.boardindex_table .windowbg2{background:var(--tl-surface-alt)!important}\n";
    $css .= "#boardindex_table .board_icon,#boardindex_table .icon,.boardindex_table .board_icon,.boardindex_table .icon{grid-column:1!important;float:none!important;position:static!important;width:auto!important;margin:0!important;text-align:center!important}\n";
    $css .= "#boardindex_table .info,.boardindex_table .info{grid-column:2!important;float:none!important;position:static!important;width:auto!important;min-width:0!important;margin:0!important;padding:0!important}\n";
    $css .= "#boardindex_table .board_stats,#boardindex_table .stats,.boardindex_table .board_stats,.boardindex_table .stats{grid-column:3!important;float:none!important;position:static!important;width:auto!important;margin:0!important;padding:0!important;text-align:center!important}\n";
    $css .= "#boardindex_table .lastpost,#boardindex_table .last_post,.boardindex_table .lastpost,.boardindex_table .last_post{grid-column:4!important;float:none!important;position:static!important;width:auto!important;min-width:0!important;margin:0!important;padding:0!important}\n";
    $css .= "#boardindex_table .windowbg:last-child,#boardindex_table .windowbg2:last-child,.boardindex_table .windowbg:last-child,.boardindex_table .windowbg2:last-child{border-radius:0 0 {$radius} {$radius}!important}\n";

    $css .= "@media(max-width:760px){#forumposts>.cat_bar,#forumposts>.title_bar,.topic_wrapper>.cat_bar,.topic_wrapper>.title_bar,.display_title,.topic_title{grid-template-columns:1fr!important}#forumposts>.cat_bar .floatright,#forumposts>.title_bar .floatright,.topic_wrapper>.cat_bar .floatright,.topic_wrapper>.title_bar .floatright,.display_title .floatright,.topic_title .floatright,.display_title .nextlinks,.topic_title .nextlinks{grid-column:1!important;justify-self:start!important;white-space:normal!important}#forumposts .post_wrapper{grid-template-columns:1fr!important;grid-template-areas:'poster' 'post' 'actions'!important}#forumposts .poster{border-right:0!important;border-bottom:1px solid var(--tl-border)!important}#boardindex_table .windowbg,#boardindex_table .windowbg2,.boardindex_table .windowbg,.boardindex_table .windowbg2{grid-template-columns:48px minmax(0,1fr)!important}#boardindex_table .board_stats,#boardindex_table .stats,#boardindex_table .lastpost,#boardindex_table .last_post,.boardindex_table .board_stats,.boardindex_table .stats,.boardindex_table .lastpost,.boardindex_table .last_post{grid-column:2!important;text-align:left!important;margin-top:6px!important}}\n";

    return $css;
}



/**
 * RC6.1.20 Surface Continuity authority.
 *
 * This final pass maps the Preview's canvas, surfaces, board spacing, header
 * text, control families, and resolved forum width onto Curve2 after every
 * earlier specialist has run.
 */
function themelab_compile_surface_translation_authority($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $fixed = $v('width_mode', 'fixed') !== 'fluid';
    $max_width = max(800, min(2400, (int) $v('max_width', 1200)));
    $content_gap_value = max(20, (int) $v('board_index_panel_padding', $v('panel_padding', 12)));
    $content_gap = $px($content_gap_value, 20);
    $board_edge_inset = $px(max(10, (int) ceil($content_gap_value / 2)), 10);
    $row_gap_map = array('tight' => 10, 'standard' => 14, 'spacious' => 20);
    $row_gap_key = (string) $v('board_index_row_spacing', 'standard');
    $row_gap = $px(isset($row_gap_map[$row_gap_key]) ? $row_gap_map[$row_gap_key] : 12, 12);
    $radius = $px($v('board_index_container_radius', $v('border_radius', 6)), 6);
    $header_text = $v('board_index_header_text', '#FFFFFF');
    $header_link = $v('board_index_header_link', $header_text);

    $button_radius = $px($v('button_border_radius', 5), 5);
    $button_border = $px($v('button_border_width', 1), 1);
    $button_py = $px($v('button_padding_vertical', 8), 8);
    $button_px = $px($v('button_padding_horizontal', 16), 16);

    $css = "/* RC6.1.20c Header Fill Recovery: preserve selected bar gradients and row breathing room. */\n";

    /* One outer frame owner. Remove Curve2's remaining horizontal content inset. */
    if ($fixed)
    {
        $css .= "#top_section .inner_wrap,body #header,body #wrapper,#footer .inner_wrap{box-sizing:border-box!important;width:calc(100% - 28px)!important;max-width:{$max_width}px!important;margin-left:auto!important;margin-right:auto!important}\n";
    }
    else
    {
        $css .= "#top_section .inner_wrap,body #header,body #wrapper,#footer .inner_wrap{box-sizing:border-box!important;width:100%!important;max-width:none!important;margin-left:auto!important;margin-right:auto!important}\n";
    }
    $css .= "#content_section,#main_content_section,#header_section .inner_wrap{box-sizing:border-box!important;width:100%!important;max-width:none!important;margin-left:0!important;margin-right:0!important;padding-left:0!important;padding-right:0!important}\n";
    $css .= "#wrapper{padding-left:0!important;padding-right:0!important}\n";
    $css .= "#upper_section{box-sizing:border-box!important;width:100%!important;border-radius:0 0 {$radius} {$radius}!important;overflow:visible}\n";
    $css .= "#boardindex_table,#boardindex_table>.main_container,.boardindex_table,.main_container{box-sizing:border-box!important;max-width:none!important}#boardindex_table,#boardindex_table>.main_container,.boardindex_table{width:100%!important;margin-left:0!important;margin-right:0!important}\n";

    /* Canvas versus forum surfaces: remove the final stock Curve2 washes. */
    $css .= "html,body{background-color:var(--tl-background)!important;background-image:none!important}\n";
    $css .= "#content_section,#main_content_section,#wrapper{background:transparent!important}\n";
    $css .= ".windowbg,.windowbg2,.roundframe,.roundframe2,.information,.generic_list_wrapper,.main_container,.topic_table,.boardindex_table,#info_center{background-image:none!important}\n";
    $css .= ".windowbg,.roundframe,.roundframe2,.information,.generic_list_wrapper,.main_container{background-color:var(--tl-surface)!important;color:var(--tl-text)!important}\n";
    $css .= ".windowbg2,.alternate,.topic_table tbody tr:nth-child(even),.table_grid tbody tr:nth-child(even) td{background-color:var(--tl-surface-alt)!important;color:var(--tl-text)!important}\n";

    /* Category and card headers use their own saved white/link text values. */
    $css .= ".cat_bar,.title_bar,.sub_bar,.cat_bar .catbg,.title_bar .titlebg,.sub_bar .subbg{color:{$header_text}!important}\n";
    $css .= ".cat_bar a,.title_bar a,.sub_bar a,.cat_bar .catbg a,.title_bar .titlebg a,.sub_bar .subbg a{color:{$header_link}!important}\n";
    $css .= ".cat_bar .catbg,.title_bar .titlebg,.sub_bar .subbg{text-shadow:none!important}\n";

    /* Board rows: Preview-like breathing room and true surface ownership. */
    $rows = "#boardindex_table .windowbg,#boardindex_table .windowbg2,.boardindex_table .windowbg,.boardindex_table .windowbg2";
    $css .= "{$rows}{box-sizing:border-box!important;padding:{$row_gap} {$content_gap}!important;column-gap:{$content_gap}!important;row-gap:6px!important;background-image:none!important;border-color:var(--tl-border)!important}\n";
    $css .= "#boardindex_table .windowbg,.boardindex_table .windowbg{background-color:var(--tl-surface)!important}\n";
    $css .= "#boardindex_table .windowbg2,.boardindex_table .windowbg2{background-color:var(--tl-surface-alt)!important}\n";
    $css .= "#boardindex_table .board_icon,#boardindex_table .icon,.boardindex_table .board_icon,.boardindex_table .icon{box-sizing:border-box!important;padding:2px 2px 2px {$board_edge_inset}!important}\n";
    $css .= "#boardindex_table .info,.boardindex_table .info{box-sizing:border-box!important;padding:0 {$content_gap}!important}\n";
    $css .= "#boardindex_table .board_stats,#boardindex_table .stats,.boardindex_table .board_stats,.boardindex_table .stats{box-sizing:border-box!important;padding:0 {$board_edge_inset}!important}\n";
    $css .= "#boardindex_table .lastpost,#boardindex_table .last_post,.boardindex_table .lastpost,.boardindex_table .last_post{box-sizing:border-box!important;padding:0 {$board_edge_inset} 0 {$content_gap}!important;border-left:1px solid var(--tl-border)!important}\n";

    /*
     * Child-board rows use SMF's .up_contain markup instead of .windowbg.
     * Give them the same four-column geometry as the Board Index so stats and
     * last-post content do not fall back to Curve2's narrow float/divider
     * layout. This applies globally to generated themes, not just Portal.
     */
    $child_rows = '.boardindex_table.main_container>.up_contain';
    $css .= "{$child_rows}{display:grid!important;grid-template-columns:minmax(52px,68px) minmax(0,1fr) minmax(118px,136px) minmax(235px,31%)!important;align-items:center!important;column-gap:{$content_gap}!important;row-gap:6px!important;box-sizing:border-box!important;width:100%!important;min-width:0!important;margin:0!important;padding:{$row_gap} {$content_gap}!important;background:var(--tl-surface)!important;background-image:none!important;border:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0!important}\n";
    $css .= "{$child_rows} .board_icon,{$child_rows} .icon{grid-column:1!important;float:none!important;position:static!important;width:auto!important;min-width:0!important;margin:0!important;padding:2px!important;text-align:center!important}\n";
    $css .= "{$child_rows} .info{grid-column:2!important;float:none!important;position:static!important;width:auto!important;min-width:0!important;margin:0!important;padding:0 {$board_edge_inset}!important;border:0!important}\n";
    $css .= "{$child_rows} .board_stats,{$child_rows} .stats{grid-column:3!important;align-self:center!important;justify-self:stretch!important;float:none!important;position:static!important;width:100%!important;min-width:0!important;max-width:136px!important;margin:0!important;padding:8px 12px!important;text-align:left!important;background:var(--tl-surface-alt)!important;border:1px solid var(--tl-border)!important;border-left:0!important;border-right:0!important;border-radius:6px!important;box-shadow:0 3px 10px rgba(0,0,0,.14)!important}\n";
    $css .= "{$child_rows} .board_stats p,{$child_rows} .stats p{margin:0!important;padding:0!important;line-height:1.45!important}\n";
    $css .= "{$child_rows} .lastpost,{$child_rows} .last_post{grid-column:4!important;float:none!important;position:static!important;width:auto!important;min-width:0!important;margin:0!important;padding:0 0 0 {$board_edge_inset}!important;border:0!important;border-left:0!important;border-right:0!important;background:transparent!important}\n";
    $css .= "{$child_rows} .lastpost p,{$child_rows} .last_post p{margin:0!important;padding:0!important;line-height:1.45!important}\n";
    $css .= "{$child_rows} .board_stats::before,{$child_rows} .board_stats::after,{$child_rows} .stats::before,{$child_rows} .stats::after,{$child_rows} .lastpost::before,{$child_rows} .lastpost::after,{$child_rows} .last_post::before,{$child_rows} .last_post::after{content:none!important;display:none!important;border:0!important}\n";
    $css .= "{$child_rows} .board_stats,{$child_rows} .stats,{$child_rows} .lastpost,{$child_rows} .last_post,{$child_rows} .board_stats>*,{$child_rows} .stats>*,{$child_rows} .lastpost>*,{$child_rows} .last_post>*{border-left:0!important;border-right:0!important}\n";
    $css .= "{$child_rows}:last-child{border-radius:0 0 {$radius} {$radius}!important}\n";

    /* Topic rows and the Info Center use the same surface roles as the Preview. */
    $css .= ".topic_table .windowbg,.topic_table .windowbg2,.topic_table tbody tr,.topic_table tbody td{background-image:none!important;border-color:var(--tl-border)!important}\n";
    $css .= ".topic_table .windowbg,.topic_table tbody tr:nth-child(odd){background-color:var(--tl-surface)!important}\n";
    $css .= ".topic_table .windowbg2,.topic_table tbody tr:nth-child(even){background-color:var(--tl-surface-alt)!important}\n";
    $css .= "#info_center{box-sizing:border-box!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;padding:{$content_gap}!important}\n";
    $css .= "#info_center .windowbg,#info_center .windowbg2,#info_center .roundframe,#info_center .information{box-sizing:border-box!important;background-image:none!important;border-color:var(--tl-border)!important;padding:{$content_gap}!important}\n";
    $css .= "#info_center .windowbg{background-color:var(--tl-surface)!important}#info_center .windowbg2{background-color:var(--tl-surface-alt)!important}\n";

    /* Final button-family normalization: readable labels and predictable geometry. */
    $controls = ".button,.button_submit,.buttonlist a,.quickbuttons a,.moderation a,.pagesection a.button,.pagesection .button,input[type=submit]";
    $control_hover = str_replace(',', ':hover,', $controls) . ':hover';
    $control_focus = str_replace(',', ':focus,', $controls) . ':focus';
    $css .= "{$controls}{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;width:auto!important;min-width:0!important;min-height:32px!important;padding:{$button_py} {$button_px}!important;border:{$button_border} solid var(--tl-secondary)!important;border-radius:{$button_radius}!important;background-image:none!important;background-color:var(--tl-secondary)!important;color:var(--tl-button-secondary-text,var(--tl-text))!important;line-height:1.15!important;white-space:nowrap!important;text-decoration:none!important;opacity:1!important}\n";
    $css .= ".button.primary,.button_submit,.buttonlist .button_strip_reply,.buttonlist .button_strip_new_topic,.buttonlist a.active,input[type=submit]{background-color:var(--tl-primary)!important;color:var(--tl-button-primary-text,#fff)!important;border-color:var(--tl-primary)!important}\n";
    $css .= ".button.danger,.button.red,.moderation .remove,.moderation .delete{background-color:var(--tl-button-danger-background)!important;color:var(--tl-button-danger-text,#fff)!important;border-color:var(--tl-button-danger-border,var(--tl-button-danger-background))!important}\n";
    $css .= "{$control_hover},{$control_focus}{filter:brightness(.94)}\n";

    /* Board-view surface continuity: square every joined middle seam and round only the final lower edge. */
    $css .= ".topic_table,.topic_table table,#topic_container,.topic_listing{box-sizing:border-box!important;width:100%!important;margin-left:0!important;margin-right:0!important;margin-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:hidden!important}\n";
    $css .= ".topic_table .cat_bar,.topic_table .title_bar,.topic_table .sub_bar,#topic_container .cat_bar,#topic_container .title_bar{margin-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= ".topic_table .windowbg,.topic_table .windowbg2,.topic_table tbody,.topic_table tbody tr,.topic_table tbody td,#topic_container .windowbg,#topic_container .windowbg2,.topic_listing .windowbg,.topic_listing .windowbg2{border-radius:0!important}\n";
    $css .= ".topic_table+.pagesection,#topic_container+.pagesection,.topic_listing+.pagesection{box-sizing:border-box!important;width:100%!important;margin-top:0!important;padding:10px!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:visible!important}\n";

    /* Keep narrower layouts readable without sacrificing the selected frame. */
    $css .= "@media(max-width:760px){#boardindex_table .lastpost,#boardindex_table .last_post,.boardindex_table .lastpost,.boardindex_table .last_post{padding-left:0!important;border-left:0!important}.boardindex_table.main_container>.up_contain{grid-template-columns:48px minmax(0,1fr)!important}.boardindex_table.main_container>.up_contain .board_icon,.boardindex_table.main_container>.up_contain .icon{grid-column:1!important;grid-row:1 / span 3!important}.boardindex_table.main_container>.up_contain .info,.boardindex_table.main_container>.up_contain .board_stats,.boardindex_table.main_container>.up_contain .stats,.boardindex_table.main_container>.up_contain .lastpost,.boardindex_table.main_container>.up_contain .last_post{grid-column:2!important}.boardindex_table.main_container>.up_contain .board_stats,.boardindex_table.main_container>.up_contain .stats{justify-self:start!important;width:auto!important;max-width:none!important;margin-top:6px!important;padding:6px 10px!important}.boardindex_table.main_container>.up_contain .lastpost,.boardindex_table.main_container>.up_contain .last_post{margin-top:6px!important;padding-left:0!important;border-left:0!important}}\n";

    return $css;
}



/**
 * RC6.1.20 Premium Polish authority.
 *
 * Final, narrowly scoped ownership for topic-view toolbars, the More menu,
 * Quick Reply, and the full composer. This pass intentionally runs after the
 * shared-frame authority so completed Board Index and admin work stay intact.
 */
function themelab_compile_premium_polish_authority($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('border_radius', 6), 6);
    $gap = $px($v('button_gap', 6), 6);
    $section_gap = $v('content_spacing', 'medium') === 'tight' ? '8px' : ($v('content_spacing', 'medium') === 'relaxed' ? '18px' : '12px');

    $css = "/* RC6.1.20 Premium Polish: composer and topic-view ownership. */\n";

    /* Moderator controls align to the same content guide without becoming a slab. */
    $css .= ".moderatorbar,.topic_actions{box-sizing:border-box!important;width:100%!important;margin:{$section_gap} 0!important;padding:0 8px!important;background:transparent!important;border:0!important;border-radius:0!important}\n";
    $css .= ".moderatorbar>.buttonlist,.topic_actions>.buttonlist{margin:0!important}\n";

    /* Jump-to is a compact, right-aligned control group rather than a floating card. */
    $css .= ".jump_to,.jump_to_form,#jump_to{box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(180px,auto) auto!important;align-items:end!important;justify-content:end!important;gap:6px!important;width:auto!important;max-width:100%!important;margin:{$section_gap} 0 {$section_gap} auto!important;padding:0!important;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important}\n";
    $css .= ".jump_to label,.jump_to .smalltext,.jump_to_form label,.jump_to_form .smalltext,#jump_to label,#jump_to .smalltext{grid-column:1 / -1!important;display:block!important;margin:0!important;padding:0 4px 2px!important;text-align:right!important}\n";
    $css .= ".jump_to select,.jump_to_form select,#jump_to select{box-sizing:border-box!important;min-width:220px!important;width:100%!important;margin:0!important}\n";
    $css .= ".jump_to .button,.jump_to button,.jump_to input[type=submit],.jump_to_form .button,.jump_to_form button,.jump_to_form input[type=submit],#jump_to .button,#jump_to button,#jump_to input[type=submit]{margin:0!important;align-self:stretch!important}\n";

    /* More is a true dropdown: hidden at rest, vertical, padded, and detached from the toolbar. */
    $css .= "#forumposts .quickbuttons>li>ul,#forumposts .buttonlist>ul>li>ul{display:none!important;position:absolute!important;z-index:999!important;right:0!important;left:auto!important;top:calc(100% + 6px)!important;min-width:220px!important;width:max-content!important;max-width:min(320px,90vw)!important;margin:0!important;padding:6px!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;box-shadow:0 8px 20px rgba(0,0,0,.22)!important;list-style:none!important;white-space:nowrap!important}\n";
    $css .= "#forumposts .quickbuttons>li:hover>ul,#forumposts .quickbuttons>li:focus-within>ul,#forumposts .quickbuttons>li.open>ul,#forumposts .buttonlist>ul>li:hover>ul,#forumposts .buttonlist>ul>li:focus-within>ul{display:block!important}\n";
    $css .= "#forumposts .quickbuttons>li>ul>li,#forumposts .buttonlist>ul>li>ul>li{display:block!important;float:none!important;width:100%!important;margin:0!important;padding:0!important;border:0!important}\n";
    $css .= "#forumposts .quickbuttons>li>ul a,#forumposts .buttonlist>ul>li>ul a{box-sizing:border-box!important;display:flex!important;align-items:center!important;gap:8px!important;width:100%!important;min-height:36px!important;margin:0!important;padding:8px 12px!important;background:transparent!important;color:var(--tl-text)!important;border:0!important;border-radius:4px!important;box-shadow:none!important;text-align:left!important;justify-content:flex-start!important}\n";
    $css .= "#forumposts .quickbuttons>li>ul a:hover,#forumposts .quickbuttons>li>ul a:focus,#forumposts .buttonlist>ul>li>ul a:hover,#forumposts .buttonlist>ul>li>ul a:focus{background:var(--tl-surface-alt)!important;color:var(--tl-link)!important;filter:none!important}\n";

    /* Quick Reply is one continuous surface from title bar through actions. */
    $css .= ".cat_bar:has(+ #quickreplybox),.title_bar:has(+ #quickreplybox),.cat_bar:has(+ .quickreplybox),.title_bar:has(+ .quickreplybox),.cat_bar:has(+ #quickReplyOptions),.title_bar:has(+ #quickReplyOptions){box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:hidden!important}\n";
    $css .= "#quickreplybox,.quickreplybox,#quickReplyOptions,.quick_reply{box-sizing:border-box!important;margin-top:0!important;border-top:1px solid var(--tl-border)!important;border-radius:0 0 {$radius} {$radius}!important;box-shadow:none!important}\n";
    $css .= "#quickreplybox .sceditor-container,.quickreplybox .sceditor-container,#quickReplyOptions .sceditor-container,.quick_reply .sceditor-container,#quickreplybox .sceditor-toolbar,.quickreplybox .sceditor-toolbar,#quickReplyOptions .sceditor-toolbar,.quick_reply .sceditor-toolbar{border-radius:0!important}\n";

    /* Full composer: only the outside surface owns radius; nested editor and attachment seams are square. */
    $css .= "#post_header,#post_header.cat_bar,#post_header.title_bar,#postmodify>.cat_bar,#postmodify>.title_bar,#post_area>.cat_bar,#post_area>.title_bar{margin-bottom:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "#post_header+form,#post_header+#postmodify,#post_header+.roundframe,#postmodify>.roundframe,#post_area>.roundframe,#postmodify form,#post_area form{margin-top:0!important;border-top:1px solid var(--tl-border)!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";
    $css .= "#postmodify .sceditor-container,#post_area .sceditor-container,#postmodify .sceditor-toolbar,#post_area .sceditor-toolbar,#postmodify .sceditor-container iframe,#post_area .sceditor-container iframe,#postmodify .sceditor-container textarea,#post_area .sceditor-container textarea{border-radius:0!important}\n";
    $css .= "#postmodify .drop_area,#postmodify .drop_zone,#postmodify .dropzone,#postmodify #drop_zone,#postmodify #dropzone,#postmodify .attachdrop,#postmodify .attachment_drop_zone,#postmodify .attachment_dropzone,#postmodify #attachment_dropzone,#postmodify .attachments,#post_area .drop_area,#post_area .drop_zone,#post_area .dropzone,#post_area #drop_zone,#post_area #dropzone,#post_area .attachdrop,#post_area .attachment_drop_zone,#post_area .attachment_dropzone,#post_area #attachment_dropzone,#post_area .attachments{margin-top:0!important;border-radius:0!important;box-shadow:none!important}\n";

    $css .= "@media(max-width:700px){.jump_to,.jump_to_form,#jump_to{grid-template-columns:minmax(0,1fr) auto!important;width:100%!important;margin-left:0!important}.jump_to select,.jump_to_form select,#jump_to select{min-width:0!important}.moderatorbar,.topic_actions{padding-left:0!important;padding-right:0!important}}\n";

    return $css;
}


/**
 * RC6.1.20n Premium Polish ownership cleanup.
 *
 * One final authority for Topic View and composer seams. This pass replaces
 * the earlier overlapping Premium Polish authority so later CSS cannot restore
 * radius or toolbar surfaces that this pass removes.
 */
function themelab_compile_premium_polish_ownership_cleanup($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };
    $px = function ($value, $fallback = 0) {
        return (int) (is_numeric($value) ? $value : $fallback) . 'px';
    };

    $radius = $px($v('border_radius', 6), 6);
    $section_gap = $v('content_spacing', 'medium') === 'tight' ? '8px' : ($v('content_spacing', 'medium') === 'relaxed' ? '18px' : '12px');

    $css = "/* RC6.1.20n Premium Polish - single Topic View/composer authority. */\n";

    /* Topic action rows are layout wrappers, never painted surfaces. */
    $css .= ".pagesection:has(>.buttonlist),.pagesection:has(.moderation),#moderationbuttons,.moderationbuttons,.moderatorbar,.topic_actions,.topic_pages{box-sizing:border-box!important;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important}\n";
    $css .= "#moderationbuttons,.moderationbuttons,.moderatorbar,.topic_actions,.pagesection:has(>.buttonlist){padding-left:8px!important;padding-right:8px!important}\n";
    $css .= ".pagesection:has(>.buttonlist)>.buttonlist,.moderatorbar>.buttonlist,.topic_actions>.buttonlist,#moderationbuttons>.buttonlist,.moderationbuttons>.buttonlist{background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important;padding:0!important;margin:0!important}\n";

    /* Jump-to remains compact and aligned with the page frame. */
    $css .= "#jump_to,.jump_to,.jump_to_form{box-sizing:border-box!important;margin-right:8px!important;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important}\n";

    /* More menu escapes post clipping and owns the top stacking layer. */
    $css .= "#forumposts,#forumposts .windowbg,#forumposts .post_wrapper,#forumposts .postarea,#forumposts .post,#forumposts .inner,#forumposts .quickbuttons_wrap,#forumposts .quickbuttons,#forumposts .buttonlist{overflow:visible!important}\n";
    $css .= "#forumposts .post_wrapper,#forumposts .windowbg{position:relative!important;z-index:auto!important}\n";
    $css .= "#forumposts .post_wrapper:hover,#forumposts .post_wrapper:focus-within,#forumposts .windowbg:hover,#forumposts .windowbg:focus-within{z-index:2000!important}\n";
    $css .= "#forumposts .quickbuttons,#forumposts .buttonlist{position:relative!important;z-index:2010!important}\n";
    $css .= "#forumposts .quickbuttons>li,#forumposts .buttonlist>ul>li{position:relative!important;overflow:visible!important}\n";
    $css .= "#forumposts .quickbuttons>li:hover,#forumposts .quickbuttons>li:focus-within,#forumposts .quickbuttons>li.open,#forumposts .buttonlist>ul>li:hover,#forumposts .buttonlist>ul>li:focus-within{z-index:2020!important}\n";
    $css .= "#forumposts .quickbuttons>li>ul,#forumposts .buttonlist>ul>li>ul{z-index:2030!important}\n";

    /* Quick Reply: one outer surface, square internal editor seams. */
    $css .= ".cat_bar:has(+ #quickreplybox),.title_bar:has(+ #quickreplybox),.cat_bar:has(+ #quickReplyOptions),.title_bar:has(+ #quickReplyOptions),.cat_bar:has(+ .quickreplybox),.title_bar:has(+ .quickreplybox){margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar+#quickreplybox,.title_bar+#quickreplybox,.cat_bar+#quickReplyOptions,.title_bar+#quickReplyOptions,.cat_bar+.quickreplybox,.title_bar+.quickreplybox{margin-top:0!important;border-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";
    $css .= "#quickreplybox>.cat_bar,#quickreplybox>.title_bar,#quickReplyOptions>.cat_bar,#quickReplyOptions>.title_bar,.quickreplybox>.cat_bar,.quickreplybox>.title_bar{margin:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#quickreplybox>.roundframe,#quickReplyOptions>.roundframe,.quickreplybox>.roundframe,#quickreplybox form,#quickReplyOptions form,.quickreplybox form{margin-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";
    $css .= "#quickreplybox .sceditor-container,#quickReplyOptions .sceditor-container,.quickreplybox .sceditor-container,#quickreplybox .sceditor-toolbar,#quickReplyOptions .sceditor-toolbar,.quickreplybox .sceditor-toolbar,#quickreplybox iframe,#quickReplyOptions iframe,.quickreplybox iframe,#quickreplybox textarea,#quickReplyOptions textarea,.quickreplybox textarea{border-radius:0!important}\n";

    /* Full composer: all nested children are square; no attachment child owns radius. */
    $css .= "#post_header,#post_header.cat_bar,#post_header.title_bar,#postmodify>.cat_bar,#postmodify>.title_bar,#post_area>.cat_bar,#post_area>.title_bar{margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#post_header+form,#post_header+#postmodify,#post_header+.roundframe,#postmodify>.roundframe,#post_area>.roundframe,#postmodify form,#post_area form{margin-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";
    $css .= "#postmodify .sceditor-container,#post_area .sceditor-container,#postmodify .sceditor-toolbar,#post_area .sceditor-toolbar,#postmodify .sceditor-container iframe,#post_area .sceditor-container iframe,#postmodify .sceditor-container textarea,#post_area .sceditor-container textarea{border-radius:0!important}\n";

    $attachment = '#postmodify .drop_area,#postmodify .drop_zone,#postmodify .dropzone,#postmodify #drop_zone,#postmodify #dropzone,#postmodify .attachdrop,#postmodify .attachment_drop_zone,#postmodify .attachment_dropzone,#postmodify #attachment_dropzone,#postmodify .attachments,#postmodify [class*="attachment"][class*="drop"],#postmodify [id*="attachment"][id*="drop"],#postmodify [class*="dropzone"],#postmodify [id*="dropzone"],#post_area .drop_area,#post_area .drop_zone,#post_area .dropzone,#post_area #drop_zone,#post_area #dropzone,#post_area .attachdrop,#post_area .attachment_drop_zone,#post_area .attachment_dropzone,#post_area #attachment_dropzone,#post_area .attachments,#post_area [class*="attachment"][class*="drop"],#post_area [id*="attachment"][id*="drop"],#post_area [class*="dropzone"],#post_area [id*="dropzone"]';
    $css .= $attachment . "{box-sizing:border-box!important;margin-top:0!important;border-radius:0!important;box-shadow:none!important;overflow:visible!important}\n";
    $css .= $attachment . " *{border-radius:0!important}\n";

    /* Topic Summary: title/body join with radius only at the outside of the pair. */
    $css .= ".cat_bar:has(+ #recent),.title_bar:has(+ #recent),.cat_bar:has(+ .topic_summary),.title_bar:has(+ .topic_summary),#postmodify .cat_bar:has(+ #recent),#postmodify .title_bar:has(+ #recent),#post_area .cat_bar:has(+ #recent),#post_area .title_bar:has(+ #recent),.topic_summary .cat_bar,.topic_summary .title_bar,#topic_summary .cat_bar,#topic_summary .title_bar{margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#recent,.topic_summary,#topic_summary,.topic_summary .roundframe,#topic_summary .roundframe,.topic_summary_body{margin-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";
    $css .= "#recent,.topic_summary_body,.topic_summary>.roundframe,#topic_summary>.roundframe{border-bottom-left-radius:{$radius}!important;border-bottom-right-radius:{$radius}!important}\n";

    $css .= "@media(max-width:700px){#moderationbuttons,.moderationbuttons,.moderatorbar,.topic_actions,.pagesection:has(>.buttonlist){padding-left:0!important;padding-right:0!important}#jump_to,.jump_to,.jump_to_form{margin-right:0!important}}\n";

    return $css;
}


/**
 * RC6.1.20n Header branding and menu completion.
 *
 * Final narrow authority for the remaining verified live-render issues.
 */
function themelab_compile_header_menu_completion($s)
{
    $v = function ($key, $fallback = '') use ($s) {
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    };

    $text = $v('header_text_color', '#FFFFFF');
    $smf_visibility = $v('smf_logo_visibility', 'show') === 'hide' ? 'hide' : 'show';
    $smf_position = in_array($v('smf_logo_position', 'right'), array('left', 'center', 'right'), true)
        ? $v('smf_logo_position', 'right')
        : 'right';

    $css = "/* RC6.1.20n Header and Menu Completion - final verified authority. */\n";

    /* Header text color must reach the real Curve2 title descendants. */
    $css .= "#header,#header h1,#header h1 *,#header h2,#header h2 *,#header .forumtitle,#header .forumtitle *,#header .forumtitle a,#header #siteslogan,#header #siteslogan *{color:{$text}!important}\n";

    /*
     * Stock SMF branding control. Curve2 renders the stock Simple Machines artwork as the direct #smflogo
     * child of #header when no site slogan is configured. #siteslogan is a
     * separate administrator slogan. RC6.1.20n targets the real generated
     * img#smflogo element verified from an exported Theme Lab theme.
     */
    $smf_logo = 'img#smflogo,#header img#smflogo';
    if ($smf_visibility === 'hide')
    {
        $css .= $smf_logo . "{display:none!important}\n";
    }
    else
    {
        $css .= $smf_logo . "{display:block!important;position:absolute!important;z-index:4!important;top:50%!important;margin:0!important;max-width:45%!important;height:auto!important}\n";
        if ($smf_position === 'left')
            $css .= $smf_logo . "{left:var(--tl-content-spacing,14px)!important;right:auto!important;transform:translateY(-50%)!important}\n";
        elseif ($smf_position === 'center')
            $css .= $smf_logo . "{left:50%!important;right:auto!important;transform:translate(-50%,-50%)!important}\n";
        else
            $css .= $smf_logo . "{left:auto!important;right:var(--tl-content-spacing,14px)!important;transform:translateY(-50%)!important}\n";
    }

    /* More... submenu is hidden at rest and exposed only by the owning item. */
    $menu = '#forumposts .quickbuttons>li>ul,#forumposts .buttonlist>ul>li>ul,#forumposts .quickbuttons li ul,#forumposts .buttonlist li ul';
    $css .= $menu . "{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;position:absolute!important;z-index:5000!important}\n";
    $css .= "#forumposts .quickbuttons>li:hover>ul,#forumposts .quickbuttons>li:focus-within>ul,#forumposts .quickbuttons>li.open>ul,#forumposts .buttonlist>ul>li:hover>ul,#forumposts .buttonlist>ul>li:focus-within>ul,#forumposts .buttonlist li.open>ul{display:block!important;visibility:visible!important;opacity:1!important;pointer-events:auto!important}\n";

    /* Posting attachment bars are internal seams and never own rounded corners. */
    $css .= "#postmodify [class*=\"attach\"],#postmodify [id*=\"attach\"],#postmodify [class*=\"drop\"],#postmodify [id*=\"drop\"],#post_area [class*=\"attach\"],#post_area [id*=\"attach\"],#post_area [class*=\"drop\"],#post_area [id*=\"drop\"]{border-radius:0!important}\n";
    $css .= "#postmodify .sceditor-container,#post_area .sceditor-container{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";

    /* Topic Summary header/body form one surface with no inter-panel gap. */
    $css .= "#postmodify .cat_bar+#recent,#postmodify .title_bar+#recent,#post_area .cat_bar+#recent,#post_area .title_bar+#recent,#postmodify .cat_bar+.windowbg,#postmodify .title_bar+.windowbg,#post_area .cat_bar+.windowbg,#post_area .title_bar+.windowbg,.topic_summary .cat_bar+.windowbg,#topic_summary .cat_bar+.windowbg{margin-top:0!important;border-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";
    $css .= "#postmodify .cat_bar:has(+ #recent),#postmodify .title_bar:has(+ #recent),#post_area .cat_bar:has(+ #recent),#post_area .title_bar:has(+ #recent),.topic_summary>.cat_bar,#topic_summary>.cat_bar{margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";

    return $css;
}


/**
 * RC6.1.20n Seamless Polish Completion.
 *
 * Final narrow authority for the two verified live-render defects remaining
 * after RC6.1.20j: the quick-button group wrapper border and rounded top
 * corners on composer body cells that join title bars.
 */
function themelab_compile_seamless_polish_completion($s)
{
    $css = "/* RC6.1.20n Seamless Polish Completion - final live authority. */\n";

    /* Quick-button links remain styled, but their flex-bank wrapper is invisible. */
    $css .= "#forumposts .quickbuttons,#forumposts .quickbuttons_wrap,#forumposts .postarea>.buttonlist,#forumposts .postarea>.quickbuttons{background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important;outline:0!important;padding:0!important}\n";

    /* Quick Reply title and body meet on one straight seam. */
    $css .= ".cat_bar:has(+ #quickreplybox),.title_bar:has(+ #quickreplybox),.cat_bar:has(+ #quickReplyOptions),.title_bar:has(+ #quickReplyOptions),.cat_bar:has(+ .quickreplybox),.title_bar:has(+ .quickreplybox),.cat_bar:has(+ .quick_reply),.title_bar:has(+ .quick_reply){margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#quickreplybox,#quickReplyOptions,.quickreplybox,.quick_reply,#quickreplybox>.roundframe,#quickReplyOptions>.roundframe,.quickreplybox>.roundframe,.quick_reply>.roundframe,#quickreplybox>form,#quickReplyOptions>form,.quickreplybox>form,.quick_reply>form{margin-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";
    $css .= ".cat_bar+#quickreplybox,.title_bar+#quickreplybox,.cat_bar+#quickReplyOptions,.title_bar+#quickReplyOptions,.cat_bar+.quickreplybox,.title_bar+.quickreplybox,.cat_bar+.quick_reply,.title_bar+.quick_reply{border-top:0!important}\n";

    /* Full Reply/New Topic body cells also join their header with square top corners. */
    $css .= "#post_header,#post_header.cat_bar,#post_header.title_bar,#postmodify>.cat_bar,#postmodify>.title_bar,#post_area>.cat_bar,#post_area>.title_bar{margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#post_header+form,#post_header+#postmodify,#post_header+.roundframe,#postmodify,#post_area,#postmodify>.roundframe,#post_area>.roundframe,#postmodify>form,#post_area>form{margin-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important}\n";

    return $css;
}


/**
 * RC6.1.21f Quick Reply Seam Polish.
 *
 * A header immediately followed by a content surface is one visual component:
 * the header owns only the top corners and the body owns only the bottom corners.
 * This is intentionally relationship-based so standalone bars keep all four corners.
 */
function themelab_compile_connected_surface_polish($s)
{
    $radius = max(0, (int) (isset($s['border_radius']) ? $s['border_radius'] : 6)) . 'px';
    $css = "/* RC6.1.21f Info Center Connected Body Authority. */\n";

    /*
     * Quick Reply -- verified against stock SMF 2.1 Display.template.php:
     *   #quickreply > .cat_bar
     *   #quickreply_options > .roundframe
     * The title owns only the top corners.  The body owns only the bottom
     * corners.  No generic/guessed quick-reply aliases are needed here.
     */
    $css .= "#quickreply{clear:both!important;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important;overflow:visible!important}\n";
    $css .= "#quickreply>.cat_bar{margin:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "#quickreply_options{margin:0!important;padding:0!important;background:transparent!important;border:0!important;border-radius:0!important}\n";
    $css .= "#quickreply_options>.roundframe{box-sizing:border-box!important;margin:0!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important;color:var(--tl-text)!important}\n";

    /*
     * Info Center -- verified against stock SMF 2.1 BoardIndex.template.php:
     *   #info_center.roundframe
     *     .title_bar
     *     #upshrink_stats
     *       .sub_bar + p.inline
     * The outer Info Center is the card.  Its inner bars are section dividers,
     * therefore deliberately square rather than mini-cards.
     */
    /*
     * Info Center is assembled exactly like Quick Reply: one outer card, with
     * the header owning the top corners and the body beginning immediately
     * beneath it.  The card itself owns the continuous left/right/bottom
     * border.  A 12px bottom shell remains visible when SMF collapses
     * #upshrink_stats, preserving the rounded lower corners.
     */
    $css .= "#info_center{box-sizing:border-box!important;clear:both!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;padding:0 0 12px!important;overflow:hidden!important}\n";
    $css .= "#info_center>.title_bar{box-sizing:border-box!important;margin:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "#info_center>.title_bar .titlebg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "#upshrink_stats{box-sizing:border-box!important;margin:0!important;padding:0!important;background:var(--tl-surface)!important;border:0!important;border-radius:0!important}\n";
    $css .= "#info_center #upshrink_stats>.sub_bar{box-sizing:border-box!important;margin:0!important;border-radius:0!important;overflow:visible!important}\n";
    $css .= "#info_center #upshrink_stats>.sub_bar .subbg{border-radius:0!important}\n";
    $css .= "#info_center #upshrink_stats>p.inline{box-sizing:border-box!important;margin:0!important;padding:8px 29px 10px!important;background:var(--tl-surface)!important;border:0!important;border-radius:0!important}\n";

    return $css;
}

/**
 * RC6.1.21g Reply & Topic Summary Connected Surfaces.
 *
 * Verified against stock SMF 2.1 Post.template.php:
 *   form#postmodify > .cat_bar + #post_area > .roundframe.noup
 *   #recent.main_section > .cat_bar.cat_bar_round + #new_replies + .windowbg...
 *
 * The page title bar owns only the upper corners. The reply body and Topic
 * Summary outer shell own the lower corners using the Theme Lab radius.
 */
function themelab_compile_reply_topic_summary_polish($s)
{
    $radius = max(0, (int) (isset($s['border_radius']) ? $s['border_radius'] : 6)) . 'px';
    $css = "/* RC6.1.21g Reply & Topic Summary Connected Surface Authority. */\n";

    /* Full Reply/New Topic composer: same construction as the now-correct Quick Reply. */
    $css .= "form#postmodify>.cat_bar{box-sizing:border-box!important;margin:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:hidden!important}\n";
    $css .= "form#postmodify>.cat_bar>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "form#postmodify>#post_area{box-sizing:border-box!important;margin:0!important;padding:0!important;background:transparent!important;border:0!important;border-radius:0!important}\n";
    $css .= "form#postmodify>#post_area>.roundframe{box-sizing:border-box!important;margin:0!important;border-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important;border-bottom-left-radius:{$radius}!important;border-bottom-right-radius:{$radius}!important;overflow:visible!important}\n";

    /* Topic Summary: one connected outer shell, with individual post cards retained inside it. */
    $css .= "#recent.main_section{box-sizing:border-box!important;margin-top:var(--tl-content-spacing,14px)!important;padding:0 8px 8px!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;overflow:hidden!important}\n";
    $css .= "#recent.main_section>.cat_bar{box-sizing:border-box!important;margin:0 -8px 8px!important;border:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:hidden!important}\n";
    $css .= "#recent.main_section>.cat_bar>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "#recent.main_section>#new_replies{display:block!important;height:0!important;margin:0!important;padding:0!important}\n";
    $css .= "#recent.main_section>.windowbg{box-sizing:border-box!important;margin:0 0 8px!important;border-radius:{$radius}!important}\n";
    $css .= "#recent.main_section>.windowbg:last-of-type{margin-bottom:0!important}\n";

    return $css;
}


/**
 * RC6.1.21h Search Connected Surfaces.
 *
 * Verified against stock SMF 2.1 Search.template.php:
 *   form#searchform > .cat_bar + #advanced_search.roundframe
 *   form#searchform > fieldset.flow_hidden > .roundframe.alt
 *     > .title_bar + #advanced_panel_div + .padding
 *
 * Search Parameters follows the same connected header/body rule as Quick Reply.
 * The board chooser is one outer card: its internal title bar is square and the
 * outer card owns the Theme Lab radius and continuous border.
 */
function themelab_compile_search_surface_polish($s)
{
    $radius = max(0, (int) (isset($s['border_radius']) ? $s['border_radius'] : 6)) . 'px';
    $css = "/* RC6.1.21h Search Connected Surface Authority. */\n";

    /* Set Search Parameters: title and #advanced_search are one connected component. */
    $css .= "#searchform>.cat_bar{box-sizing:border-box!important;margin:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:hidden!important}\n";
    $css .= "#searchform>.cat_bar>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "#searchform>#advanced_search.roundframe{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;border-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important;border-bottom-left-radius:{$radius}!important;border-bottom-right-radius:{$radius}!important}\n";

    /* Board chooser: one Info-Center-style outer shell with square internal section bar. */
    $css .= "#searchform>fieldset.flow_hidden{box-sizing:border-box!important;margin:0!important;padding:0!important;border:0!important;min-width:0!important}\n";
    $css .= "#searchform>fieldset.flow_hidden>.roundframe.alt{box-sizing:border-box!important;margin:0!important;padding:18px 18px 12px!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;overflow:hidden!important}\n";
    $css .= "#searchform>fieldset.flow_hidden>.roundframe.alt>.title_bar{box-sizing:border-box!important;margin:0!important;border-radius:0!important;overflow:visible!important}\n";
    $css .= "#searchform>fieldset.flow_hidden>.roundframe.alt>.title_bar>.titlebg{border-radius:0!important}\n";
    $css .= "#searchform #advanced_panel_div{box-sizing:border-box!important;margin:0!important;border:0!important;border-radius:0!important}\n";
    $css .= "#searchform>fieldset.flow_hidden>.roundframe.alt>.padding{box-sizing:border-box!important;margin:0!important;border:0!important;border-radius:0!important}\n";

    return $css;
}


/**
 * RC6.1.21i Members Connected Surfaces.
 *
 * Verified against generated Theme Lab/SMF 2.1 markup:
 *   #memberlist > .cat_bar + #mlist > table.table_grid
 *   .cat_bar + table#watch_user_list.table_grid (GenericList.template.php)
 *
 * These are deliberately narrow selectors. The goal is only to remove the
 * artificial vertical seam between the section header and its table; tabs,
 * pagination, member rows, and unrelated generic lists keep their existing
 * spacing and radius rules.
 */
function themelab_compile_member_surface_polish($s)
{
    $radius = max(0, (int) (isset($s['border_radius']) ? $s['border_radius'] : 6)) . 'px';
    $css = "/* RC6.1.21i Members Connected Surface Authority. */\n";

    /* Public Members List: Members List bar touches the table header row. */
    $css .= "#memberlist>.cat_bar{box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#memberlist>.cat_bar>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#memberlist>.cat_bar+#mlist,#memberlist>#mlist{box-sizing:border-box!important;margin-top:0!important;padding-top:0!important}\n";
    $css .= "#memberlist>#mlist>table.table_grid{margin-top:0!important}\n";

    /* Moderation Center watched-member list: generic-list title touches table. */
    $css .= ".cat_bar:has(+ table#watch_user_list.table_grid){box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar:has(+ table#watch_user_list.table_grid)>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar+table#watch_user_list.table_grid{margin-top:0!important}\n";

    /*
     * RC6.1.21j Admin Members bordered connectors.
     *
     * SMF's admin member lists/search groups place a cat_bar directly before a
     * table_grid. Keep the small breathing strip, but make it part of the same
     * bordered surface instead of leaving an unowned gap. The table's surface-
     * colored top border becomes that strip while its left/right borders carry
     * the outline continuously down to the content.
     */
    $css .= "#admin_form_wrapper>.cat_bar:has(+ table.table_grid),.cat_bar:has(+ table#approve_list.table_grid){box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#admin_form_wrapper>.cat_bar:has(+ table.table_grid)>.catbg,.cat_bar:has(+ table#approve_list.table_grid)>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#admin_form_wrapper>.cat_bar+table.table_grid,.cat_bar+table#approve_list.table_grid{box-sizing:border-box!important;margin-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:10px solid var(--tl-surface)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";
    $css .= "#admin_form_wrapper>.cat_bar+table.table_grid+table.table_grid{margin-top:0!important;border-top:0!important}\n";

    /*
     * RC6.1.21k Membergroups connector pass.
     *
     * GenericList renders many SMF moderation/membergroup lists as a cat_bar
     * immediately followed by table.table_grid. Keep SMF's small separator
     * strip, but make it owned by the same surface: continuous left/right
     * borders, surface-colored connector strip, and Theme Lab bottom radius.
     * The already-verified watched-members list is explicitly excluded because
     * that surface intentionally has no spacer at all.
     */
    $css .= ".cat_bar:has(+ table.table_grid:not(#watch_user_list)){box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar:has(+ table.table_grid:not(#watch_user_list))>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar+table.table_grid:not(#watch_user_list){box-sizing:border-box!important;margin-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:10px solid var(--tl-surface)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";

    /*
     * RC6.1.21bo Moderation Center connected report/request surfaces.
     *
     * Reported Posts and Reported Members render a cat_bar followed by a
     * pagesection, then one or more windowbg rows, then a final pagesection.
     * The generic Theme Lab spacing left an exposed strip between the blue
     * title and the report body. Own that entire direct-child stack as one
     * connected component without changing pagination behavior.
     */
    $report_forms = "#reported_posts,#reported_members";
    $css .= ":is({$report_forms})>.cat_bar{box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ":is({$report_forms})>.cat_bar>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ":is({$report_forms})>.cat_bar+.pagesection{box-sizing:border-box!important;margin-top:0!important;margin-bottom:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0!important;background:var(--tl-surface-alt)!important}\n";
    $css .= ":is({$report_forms})>.pagesection+.windowbg{box-sizing:border-box!important;margin-top:0!important;margin-bottom:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-top:1px solid var(--tl-border)!important;border-bottom:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ":is({$report_forms})>.windowbg+.windowbg{box-sizing:border-box!important;margin-top:0!important;margin-bottom:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-top:1px solid var(--tl-border)!important;border-bottom:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ":is({$report_forms})>.windowbg+.pagesection{box-sizing:border-box!important;margin-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:1px solid var(--tl-border)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface-alt)!important}\n";

    /* Group Requests is a GenericList table (#group_request_list). Unlike the
       generic membergroup connector, this moderation surface should have no
       10px spacer between its title and table. */
    $css .= ".cat_bar:has(+table#group_request_list.table_grid){box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar:has(+table#group_request_list.table_grid)>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar+table#group_request_list.table_grid{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";

    /* RC6.1.21cm Membergroup Requests structural GenericList body. */
    $css .= ".themelab_group_requests_body{box-sizing:border-box!important;margin:0 0 {$radius} 0!important;padding:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important;overflow:hidden!important}\n";
    $css .= ".themelab_group_requests_body>table#group_request_list.table_grid{box-sizing:border-box!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".cat_bar:has(+.themelab_group_requests_body){box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar:has(+.themelab_group_requests_body)>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";

    /* RC6.1.21bp Moderation Center > Membergroup Requests.
       The moderation dashboard/request branch also uses the native
       #group_requests_panel windowbg directly after its cat_bar. Connect that
       pair without changing unrelated windowbg panels. */
    $css .= ".cat_bar:has(+#group_requests_panel.windowbg){box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar:has(+#group_requests_panel.windowbg)>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar+#group_requests_panel.windowbg{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";

    /*
     * RC6.1.21m Profile connected surfaces.
     *
     * Profile view/edit screens use several Curve2 variants: cat_bar followed
     * by windowbg/roundframe/information/form content rather than a table_grid.
     * Scope the repair to profile containers/forms and turn the normal SMF
     * breathing strip into an owned part of the component.
     */
    $profile_scope = "#profileview,#profile_form,#creator,form[action*=\"action=profile\"]";
    $profile_bodies = ".windowbg,.roundframe,.information,.generic_list,.profile_content,fieldset";
    $css .= ":is({$profile_scope}) .cat_bar:has(+ :is({$profile_bodies})){box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ":is({$profile_scope}) .cat_bar:has(+ :is({$profile_bodies}))>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ":is({$profile_scope}) .cat_bar+:is({$profile_bodies}){box-sizing:border-box!important;margin-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:10px solid var(--tl-surface)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";

    /* Profile tables receive the same connector rule, including tables nested
       in the profile form rather than emitted by GenericList. */
    $css .= ":is({$profile_scope}) .cat_bar:has(+ table){box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ":is({$profile_scope}) .cat_bar:has(+ table)>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ":is({$profile_scope}) .cat_bar+table{box-sizing:border-box!important;margin-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:10px solid var(--tl-surface)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";

    /*
     * RC6.1.21m Profile summary/statistics connected-surface authority.
     *
     * Summary and Show Stats render their main profile body as #profileview,
     * with the profile heading emitted immediately before that container.
     * Own SMF's breathing strip with the body border instead of leaving raw
     * page background between the heading and the profile content.
     */
    $css .= ".cat_bar:has(+ #profileview.roundframe){box-sizing:border-box!important;margin-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar:has(+ #profileview.roundframe)>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar+#profileview.roundframe{box-sizing:border-box!important;margin-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:10px solid var(--tl-surface)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";

    /* The statistics page contains title bars inside that owned body. They are
       section dividers, not independent rounded cards (same visual hierarchy
       as Forum Stats / Users Online inside Info Center). */
    $css .= "#profileview #activitytime>.title_bar,#profileview .half_content>.title_bar{border-radius:0!important}\n";

    /*
     * RC6.1.21n Profile Statistics Tight Surface Authority.
     *
     * The RC6.1.21m connector deliberately preserved a 10px breathing strip.
     * The live Profile Summary/Statistics pages proved that this particular
     * surface should instead follow the verified Quick Reply pattern: the
     * profile header and #profileview body meet directly, with no exposed or
     * simulated gap and no upper body radius.
     */
    $css .= ".cat_bar:has(+ #profileview.roundframe){margin-bottom:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= ".cat_bar:has(+ #profileview.roundframe)>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= ".cat_bar+#profileview.roundframe{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";

    /* Show Stats uses #generalstats inside #profileview. Remove the outer
       roundframe side padding so its blue section dividers can span cleanly to
       the owned body borders. Restore intentional padding only to the content
       beneath those dividers. */
    $css .= "#profileview.roundframe:has(>#generalstats){padding:0!important;overflow:hidden!important}\n";
    $css .= "#profileview>#generalstats{box-sizing:border-box!important;margin:0!important;padding:12px 16px!important}\n";
    $css .= "#profileview>#activitytime{box-sizing:border-box!important;margin:0!important;padding:0!important}\n";
    $css .= "#profileview>#activitytime>.title_bar,#profileview>.flow_hidden>.half_content>.title_bar{box-sizing:border-box!important;margin:0!important;border-radius:0!important}\n";
    $css .= "#profileview>#activitytime>.activity_stats{box-sizing:border-box!important;margin:0!important;padding:10px 12px 6px!important}\n";
    $css .= "#profileview>.flow_hidden{box-sizing:border-box!important;margin:0!important;padding:0!important}\n";
    $css .= "#profileview>.flow_hidden>.half_content{box-sizing:border-box!important;margin:0!important;padding:0!important}\n";
    $css .= "#profileview>.flow_hidden>.half_content>dl.stats,#profileview>.flow_hidden>.half_content>p.padding{box-sizing:border-box!important;margin:0!important;padding:10px 16px 12px!important}\n";
    $css .= "@media(max-width:760px){#profileview>.flow_hidden>.half_content{width:100%!important;display:block!important}}\n";

    /*
     * RC6.1.21o Profile seam owner fix.
     *
     * Live testing proved the Profile title bar is not reliably adjacent to
     * #profileview, so the adjacent-sibling authority above can miss the real
     * header and leave Theme Lab's normal section spacing underneath it.
     * Match the actual relationship with a general-sibling :has() selector,
     * then make #profileview itself authoritative for the lower corners.
     */
    $css .= ".cat_bar:has(~ #profileview.roundframe.noup){box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= ".cat_bar:has(~ #profileview.roundframe.noup)>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "#profileview.roundframe.noup{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-top-left-radius:0!important;border-top-right-radius:0!important;border-bottom-left-radius:{$radius}!important;border-bottom-right-radius:{$radius}!important;box-shadow:none!important}\n";

    /* The statistics intro needs only a small content inset; the previous
       roundframe padding made that inset read as another disconnected strip. */
    $css .= "#profileview.roundframe.noup:has(>#generalstats)>#generalstats{padding:5px 16px 10px!important}\n";

    /* Restore the verified two-column board statistics layout without relying
       on Curve2's whitespace-sensitive inline-block negative margins. */
    $css .= "#profileview>.flow_hidden>.half_content{float:left!important;display:block!important;width:50%!important;margin:0!important;padding:0!important}\n";
    $css .= "#profileview>.flow_hidden::after{content:\"\";display:block!important;clear:both!important}\n";
    $css .= "@media(max-width:760px){#profileview>.flow_hidden>.half_content{float:none!important;width:100%!important}}\n";

    /*
     * RC6.1.21r Profile Show Posts structural body.
     *
     * RC6.1.21p tried to simulate an outer body with pseudo-elements on each
     * message card. On live Curve2 those pseudo-elements expanded the scroll
     * area of every .windowbg, producing both horizontal and vertical
     * scrollbars. RC6.1.21r moves ownership to real markup injected into the
     * cloned Profile.template.php at publish time. The wrapper owns the side
     * and lower borders; individual cards retain their normal overflow.
     */
    $showposts_head = ".cat_bar.cat_bar_round:has(+.themelab_profile_showposts_body)";
    $css .= "{$showposts_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "{$showposts_head}>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= ".themelab_profile_showposts_body{box-sizing:border-box!important;margin:0!important;padding:6px 10px 10px!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:visible!important}\n";
    $css .= ".themelab_profile_showposts_body>.pagesection{box-sizing:border-box!important;margin:0 0 6px!important;padding:0!important;background:transparent!important;border:0!important;border-radius:0!important;overflow:visible!important}\n";
    $css .= ".themelab_profile_showposts_body>.windowbg,.themelab_profile_showposts_body>.windowbg2{box-sizing:border-box!important;display:flow-root!important;margin:0 0 10px!important;width:100%!important;max-width:100%!important;border-radius:{$radius}!important;overflow:visible!important}\n";
    $css .= ".themelab_profile_showposts_body>.windowbg::after,.themelab_profile_showposts_body>.windowbg2::after{content:\"\";display:block!important;clear:both!important}\n";
    $css .= ".themelab_profile_showposts_body>.windowbg .quickbuttons,.themelab_profile_showposts_body>.windowbg2 .quickbuttons{max-width:100%!important;margin-bottom:0!important}\n";
    $css .= ".themelab_profile_showposts_body>.windowbg:last-of-type,.themelab_profile_showposts_body>.windowbg2:last-of-type{margin-bottom:6px!important}\n";
    $css .= ".themelab_profile_showposts_body>.pagesection:last-child{margin:0!important;padding:0!important;min-height:0!important}\n";

    /*
     * RC6.1.21t Profile final connected surfaces.
     *
     * Attachments uses the same structural Show Posts wrapper as Messages,
     * but its heading intentionally omits cat_bar_round. Own that variant too
     * and remove the wrapper's top inset so the attachment table meets the
     * heading with one continuous bordered surface.
     */
    $showposts_any_head = ".cat_bar:has(+.themelab_profile_showposts_body)";
    $css .= "{$showposts_any_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$showposts_any_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".cat_bar:not(.cat_bar_round):has(+.themelab_profile_showposts_body)+.themelab_profile_showposts_body{padding-top:0!important}\n";
    $css .= ".cat_bar:not(.cat_bar_round):has(+.themelab_profile_showposts_body)+.themelab_profile_showposts_body>.generic_list,.cat_bar:not(.cat_bar_round):has(+.themelab_profile_showposts_body)+.themelab_profile_showposts_body>table{margin-top:0!important}\n";

    /* Alert Preferences is a direct cat_bar + table pair inside #notify_options.
       Earlier generic Profile rules deliberately inserted a connector strip;
       this final Profile pass removes that strip only for the alert matrix. */
    $css .= "#notify_options>.cat_bar:has(+table.table_grid){box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#notify_options>.cat_bar:has(+table.table_grid)>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "#notify_options>.cat_bar+table.table_grid{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0 0 {$radius} {$radius}!important;background:var(--tl-surface)!important}\n";

    /*
     * RC6.1.21t Admin Support and Credits connected surface.
     * Keep this deliberately scoped to the credits page: the outer roundframe
     * becomes the body owned by the main Admin cat bar, while the three title
     * bars inside it act as square, edge-to-edge section dividers.
     */
    $support_body = ".cat_bar:has(+.roundframe.noup #support_resources)+.roundframe.noup";
    $support_head = ".cat_bar:has(+.roundframe.noup #support_resources)";
    $css .= "{$support_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$support_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$support_body}{box-sizing:border-box!important;margin-top:0!important;padding:0!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= "{$support_body}>.title_bar{box-sizing:border-box!important;margin:0!important;width:100%!important;border-radius:0!important}\n";
    $css .= "{$support_body}>.title_bar>.titlebg{box-sizing:border-box!important;margin:0!important;border-radius:0!important}\n";
    $css .= "{$support_body}>.padding{box-sizing:border-box!important;margin:0!important}\n";

    /*
     * RC6.1.21w Package Manager structural body.
     *
     * GenericList renders the Modification packages title and packages_lists_*
     * table as siblings with no body element between them. CSS alone cannot give
     * the intentional spacer continuous side walls when the table uses collapsed
     * borders, so publish now injects a real .themelab_package_list_body wrapper
     * into the cloned GenericList.template.php for package lists only.
     */
    $package_head = ".cat_bar:has(+.themelab_package_list_body)";
    $css .= "{$package_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$package_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_package_list_body{box-sizing:border-box!important;margin:0!important;padding:10px 0 0!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_package_list_body>table.table_grid[id^=\"packages_lists_\"]{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_package_list_body>table.table_grid[id^=\"packages_lists_\"]>thead>tr.title_bar,.themelab_package_list_body>table.table_grid[id^=\"packages_lists_\"]>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_package_list_body>table.table_grid[id^=\"packages_lists_\"]>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /*
     * RC6.1.21x Package Manager File Permissions bordered body.
     * Packages.template.php renders the File Permissions cat bar directly
     * before a collapsed table, with no owned body around the table. Publish
     * injects a real wrapper so the intentional separator strip can have
     * continuous side walls and a rounded lower shell.
     */
    $file_perms_head = ".cat_bar:has(+.themelab_file_permissions_body)";
    $css .= "{$file_perms_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$file_perms_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_file_permissions_body{box-sizing:border-box!important;margin:0!important;padding:10px 0 0!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_file_permissions_body>table.table_grid{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_file_permissions_body>table.table_grid>thead>tr.title_bar,.themelab_file_permissions_body>table.table_grid>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_file_permissions_body>table.table_grid>tbody:last-of-type>tr:last-child>td{border-bottom:0!important}\n";

    /*
     * RC6.1.21z Features & Options > Profile Fields structural bodies.
     *
     * Both Standard Profile Fields and Custom Profile Fields are GenericList
     * consumers. SMF renders each list as cat_bar + table + optional controls
     * with no owned body element between them. Publish injects a dedicated
     * wrapper around only these two list IDs so the intentional breathing strip,
     * table, and Save/New Field controls read as one bordered component.
     */
    $profile_fields_head = ".cat_bar:has(+.themelab_profile_fields_body)";
    $css .= "{$profile_fields_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$profile_fields_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_profile_fields_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_profile_fields_body>table.table_grid{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_profile_fields_body>table.table_grid>thead>tr.title_bar,.themelab_profile_fields_body>table.table_grid>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_profile_fields_body>.flow_auto,.themelab_profile_fields_body>.additional_row{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_profile_fields_body>table.table_grid>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /*
     * RC6.1.21z Features & Options > Notifications structural body.
     *
     * The admin default Alert Preferences matrix is rendered by
     * Profile.template.php as cat_bar + table + save controls with no owned
     * shell. Publish injects .themelab_admin_alert_preferences_body only when
     * the template is being used from action=admin, preserving the completed
     * member Profile notification surface.
     */
    $admin_alert_head = ".cat_bar:has(+.themelab_admin_alert_preferences_body)";
    $css .= "{$admin_alert_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$admin_alert_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_admin_alert_preferences_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_admin_alert_preferences_body>table.table_grid{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_admin_alert_preferences_body>table.table_grid>tbody>tr.title_bar,.themelab_admin_alert_preferences_body>table.table_grid>tbody>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_admin_alert_preferences_body>.themelab_admin_alert_actions{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important;overflow:auto!important}\n";
    $css .= ".themelab_admin_alert_preferences_body>.themelab_admin_alert_actions>#notify_submit{float:right!important}\n";
    $css .= ".themelab_admin_alert_preferences_body>table.table_grid>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /*
     * RC6.1.21aa Languages > Edit Languages structural body.
     *
     * SMF renders the language_list GenericList as cat_bar + table + Save
     * controls without an owned body. Give that list one continuous component
     * so the header, intentional spacer, column header, rows, and Save action
     * visually belong together.
     */
    $language_edit_head = ".cat_bar:has(+.themelab_language_edit_body)";
    $css .= "{$language_edit_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$language_edit_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_language_edit_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_language_edit_body>table.table_grid#language_list{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_language_edit_body>table.table_grid#language_list>thead>tr.title_bar,.themelab_language_edit_body>table.table_grid#language_list>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_language_edit_body>.flow_auto,.themelab_language_edit_body>.additional_row{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_language_edit_body>table.table_grid#language_list>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /*
     * RC6.1.21ab Themes & Layout > Theme Settings unified component.
     *
     * Themes.template.php already has one outer settings body, but Curve2
     * renders the internal Edit Theme / URL Configuration / Theme Options
     * title bars as independent blue slabs. Publish tags only template_set_settings()
     * so Theme Lab can turn that page into one deliberate outer component
     * containing three calm internal sections.
     */
    $theme_settings = ".themelab_theme_settings_component";
    $css .= "{$theme_settings}{box-sizing:border-box!important;margin-bottom:var(--tl-content-spacing,14px)!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;overflow:hidden!important;box-shadow:var(--tl-shadow,none)!important}\n";
    $css .= "{$theme_settings}>form>.cat_bar.themelab_theme_settings_head{box-sizing:border-box!important;margin:0!important;border:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "{$theme_settings}>form>.cat_bar.themelab_theme_settings_head>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body{box-sizing:border-box!important;margin:0!important;padding:10px!important;background:var(--tl-surface)!important;border:0!important;border-radius:0!important;box-shadow:none!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>.title_bar{box-sizing:border-box!important;margin:0!important;padding:0!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important;box-shadow:none!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>.title_bar>.titlebg,{$theme_settings} .themelab_theme_settings_body>.title_bar>.titlebg.config_hd{box-sizing:border-box!important;margin:0!important;padding:11px 14px 5px!important;min-height:0!important;background:transparent!important;background-image:none!important;color:var(--tl-link)!important;border:0!important;border-radius:{$radius} {$radius} 0 0!important;font-weight:700!important;text-shadow:none!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>.title_bar+.windowbg,{$theme_settings} .themelab_theme_settings_body>.title_bar+dl.settings{box-sizing:border-box!important;margin:0 0 10px!important;padding:6px 14px 14px!important;width:100%!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;box-shadow:none!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>.title_bar+.windowbg>ul{margin:0!important;padding:4px 0 0 18px!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>dl.settings{display:grid!important;grid-template-columns:minmax(220px,42%) minmax(0,1fr)!important;column-gap:18px!important;row-gap:8px!important;align-items:center!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>dl.settings>dt,{$theme_settings} .themelab_theme_settings_body>dl.settings>dd{box-sizing:border-box!important;margin:0!important;min-width:0!important;width:auto!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>dl.settings>dd input[type=text],{$theme_settings} .themelab_theme_settings_body>dl.settings>dd input[type=url],{$theme_settings} .themelab_theme_settings_body>dl.settings>dd input[type=number],{$theme_settings} .themelab_theme_settings_body>dl.settings>dd select{max-width:100%!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>hr{margin:10px 0!important;border:0!important;border-top:1px solid var(--tl-border)!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body>input.button[name=save]{float:right!important;margin:0 2px 0 0!important}\n";
    $css .= "{$theme_settings} .themelab_theme_settings_body:after{content:\"\";display:block!important;clear:both!important}\n";
    $css .= "@media(max-width:760px){{$theme_settings} .themelab_theme_settings_body>dl.settings{grid-template-columns:1fr!important;row-gap:4px!important}{$theme_settings} .themelab_theme_settings_body>dl.settings>dd{margin-bottom:8px!important}}\n";

    /*
     * RC6.1.21ad Themes & Layout > Theme Settings list collection.
     *
     * Remove Curve2's redundant Theme Settings title bar and make the actual
     * installed-theme cards one bordered collection directly beneath the
     * Theme Settings overview copy. Reset URLs remains its own component.
     */
    $theme_list = ".themelab_theme_list_collection";
    $css .= "{$theme_list}{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;overflow:hidden!important;box-shadow:var(--tl-shadow,none)!important}\n";
    $css .= "{$theme_list}>.cat_bar{box-sizing:border-box!important;margin:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "{$theme_list}>.cat_bar>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "{$theme_list}>.windowbg{box-sizing:border-box!important;margin:0 0 10px!important;border:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;box-shadow:none!important}\n";
    $css .= "{$theme_list}>.windowbg:last-child{margin-bottom:0!important}\n";

    /* RC6.1.21ae: Smileys and Message Icons > Smiley Sets structural body. */
    $smiley_sets_head = ".cat_bar:has(+.themelab_smiley_sets_body)";
    $css .= "{$smiley_sets_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$smiley_sets_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_smiley_sets_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_smiley_sets_body>table.table_grid{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_smiley_sets_body>table.table_grid>thead>tr.title_bar,.themelab_smiley_sets_body>table.table_grid>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_smiley_sets_body>.flow_auto,.themelab_smiley_sets_body>.additional_row{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_smiley_sets_body>table.table_grid>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /* RC6.1.21af: Attachments & Avatars > Browse Files structural body. */
    $attachment_browser_head = ".cat_bar:has(+.themelab_attachment_browser_body)";
    $css .= "{$attachment_browser_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$attachment_browser_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_attachment_browser_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_attachment_browser_body>table.table_grid#file_list{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_attachment_browser_body>table.table_grid#file_list>thead>tr.title_bar,.themelab_attachment_browser_body>table.table_grid#file_list>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_attachment_browser_body>.flow_auto,.themelab_attachment_browser_body>.additional_row{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_attachment_browser_body>table.table_grid#file_list>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /* RC6.1.21ag: Admin > Members > View all Members structural body. */
    $admin_members_head = ".cat_bar:has(+.themelab_admin_members_body)";
    $css .= "{$admin_members_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$admin_members_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_admin_members_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_admin_members_body>table.table_grid#member_list{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_admin_members_body>table.table_grid#member_list>thead>tr.title_bar,.themelab_admin_members_body>table.table_grid#member_list>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_admin_members_body>.flow_auto,.themelab_admin_members_body>.additional_row{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_admin_members_body>table.table_grid#member_list>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /*
     * RC6.1.21aj Membergroups > Edit membergroups structural bodies.
     *
     * Both regular_membergroups_list and post_count_membergroups_list are
     * GenericList consumers. Give each list its own owned body so the Add group
     * controls, square column header, group rows, and rounded lower edge read as
     * one intentional component beneath the blue list title.
     */
    $membergroups_head = ".cat_bar:has(+.themelab_membergroups_body)";
    $css .= "{$membergroups_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$membergroups_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_membergroups_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_membergroups_body>table.table_grid[id=\"regular_membergroups_list\"],.themelab_membergroups_body>table.table_grid[id=\"post_count_membergroups_list\"]{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_membergroups_body>table.table_grid>thead>tr.title_bar,.themelab_membergroups_body>table.table_grid>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_membergroups_body>.flow_auto,.themelab_membergroups_body>.additional_row{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_membergroups_body>table.table_grid>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /* RC6.1.21aj: the Regular groups list already has a bottom Add group action. Hide only the redundant top action immediately before its table. */
    $css .= ".themelab_membergroups_regular_body>.additional_row:has(+table#regular_membergroups_list),.themelab_membergroups_regular_body>.flow_auto:has(+table#regular_membergroups_list){display:none!important}\n";

    /* RC6.1.21al: Admin > Ban list structural body. */
    $ban_list_head = ".cat_bar:has(+.themelab_ban_list_body)";
    $css .= "{$ban_list_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$ban_list_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_ban_list_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_ban_list_body>table.table_grid#ban_list{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_ban_list_body>table.table_grid#ban_list>thead>tr.title_bar,.themelab_ban_list_body>table.table_grid#ban_list>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_ban_list_body>.flow_auto,.themelab_ban_list_body>.additional_row{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_ban_list_body>table.table_grid#ban_list>tbody>tr:last-child>td{border-bottom:0!important}\n";
    /* The bottom Remove selected action is the natural action point. Hide only the redundant top action row immediately before the ban table. */
    $css .= ".themelab_ban_list_body>.additional_row:has(+table#ban_list),.themelab_ban_list_body>.flow_auto:has(+table#ban_list){display:none!important}\n";


    /* RC6.1.21am: Admin > Mail > Browse Queue structural body. */
    $mail_queue_head = ".cat_bar:has(+.themelab_mail_queue_body)";
    $css .= "{$mail_queue_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$mail_queue_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_mail_queue_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_mail_queue_body>table.table_grid#mail_queue{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_mail_queue_body>table.table_grid#mail_queue>thead>tr.title_bar,.themelab_mail_queue_body>table.table_grid#mail_queue>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_mail_queue_body>.flow_auto,.themelab_mail_queue_body>.additional_row{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_mail_queue_body>table.table_grid#mail_queue>tbody>tr:last-child>td{border-bottom:0!important}\n";
    /* Keep the bottom queue actions; hide only the duplicate action row immediately before the table. */
    $css .= ".themelab_mail_queue_body>.additional_row:has(+table#mail_queue),.themelab_mail_queue_body>.flow_auto:has(+table#mail_queue){display:none!important}\n";

    /* RC6.1.21aj: Permissions > General permissions connected table body. */
    $permissions_head = ".cat_bar:has(+.themelab_manage_permissions_body)";
    $css .= "{$permissions_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$permissions_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_manage_permissions_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 0!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_manage_permissions_body>table.table_grid{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_manage_permissions_body>table.table_grid>thead>tr.title_bar,.themelab_manage_permissions_body>table.table_grid>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_manage_permissions_body>table.table_grid>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /* RC6.1.21ak: Permissions > Edit profiles becomes one connected component. */
    $edit_profiles_head = ".cat_bar:has(+.themelab_permission_profiles_body)";
    $css .= "{$edit_profiles_head}{margin-bottom:0!important}\n";
    $css .= "{$edit_profiles_head} .catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= ".themelab_permission_profiles_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 6px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_permission_profiles_body>table.table_grid{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_permission_profiles_body>table.table_grid>thead>tr.title_bar,.themelab_permission_profiles_body>table.table_grid>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_permission_profiles_body>.flow_auto{box-sizing:border-box!important;margin:0!important;padding:6px 8px 0!important;background:transparent!important;border:0!important}\n";
    $css .= ".themelab_permission_profiles_body>table.table_grid>tbody>tr:last-child>td{border-bottom:0!important}\n";

    /* RC6.1.21ak: Permissions > Board permissions already has a native windowbg body.
     * Own it instead of adding another wrapper, square the internal category bars, and
     * keep Edit All inside the connected component. */
    $board_permissions_bar = ".cat_bar:has(>#board_permissions)";
    $css .= "{$board_permissions_bar}{margin-bottom:0!important}\n";
    $css .= "{$board_permissions_bar} .catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "{$board_permissions_bar}+.windowbg{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 8px 8px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= "{$board_permissions_bar}+.windowbg>.sub_bar{box-sizing:border-box!important;margin:0 -8px!important;border-radius:0!important}\n";
    $css .= "{$board_permissions_bar}+.windowbg>.sub_bar .subbg{border-radius:0!important}\n";
    $css .= "{$board_permissions_bar}+.windowbg>.perm_boards{box-sizing:border-box!important;margin:0 -8px!important;padding:0!important;border-radius:0!important}\n";
    $css .= "{$board_permissions_bar}+.windowbg>.perm_boards>li{box-sizing:border-box!important;margin:0!important;padding-left:8px!important;padding-right:8px!important}\n";
    $css .= "{$board_permissions_bar}+.windowbg>.button{float:right!important;margin-top:8px!important}\n";
    $css .= "{$board_permissions_bar}+.windowbg:after{content:\"\";display:block!important;clear:both!important}\n";

    /* RC6.1.21aj: Admin > Members > Search for Members structural membergroup body. */
    $member_search_head = ".cat_bar:has(+.themelab_member_search_groups_body)";
    $css .= "{$member_search_head}{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= "{$member_search_head}>.catbg{border-bottom-left-radius:0!important;border-bottom-right-radius:0!important}\n";
    $css .= ".themelab_member_search_groups_body{box-sizing:border-box!important;margin:0 0 var(--tl-content-spacing,14px)!important;padding:10px 0 8px!important;background:var(--tl-surface)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= ".themelab_member_search_groups_body>table.table_grid{box-sizing:border-box!important;width:100%!important;margin:0!important;border:0!important;border-radius:0!important;background:var(--tl-surface)!important}\n";
    $css .= ".themelab_member_search_groups_body>table.table_grid+table.table_grid{margin-top:0!important}\n";
    $css .= ".themelab_member_search_groups_body>table.table_grid>thead>tr.title_bar,.themelab_member_search_groups_body>table.table_grid>thead>tr.title_bar>th{border-radius:0!important}\n";
    $css .= ".themelab_member_search_groups_body>table.table_grid>tbody>tr:last-child>td{border-bottom:0!important}\n";
    $css .= ".themelab_member_search_groups_actions{box-sizing:border-box!important;display:flex!important;justify-content:flex-end!important;align-items:center!important;margin:0!important;padding:10px 8px 0!important;clear:both!important}\n";
    $css .= ".themelab_member_search_groups_actions>.button{margin:0!important}\n";

    /* RC6.1.21ad: Boards & Categories > Modify Boards connected collection. */
    $boards = "#manage_boards.themelab_manage_boards_collection";
    $css .= "{$boards}{box-sizing:border-box!important;margin-bottom:var(--tl-content-spacing,14px)!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:{$radius}!important;overflow:hidden!important;box-shadow:var(--tl-shadow,none)!important}\n";
    $css .= "{$boards}>.cat_bar{margin:0!important;border:0!important;border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "{$boards}>.cat_bar>.catbg{border-radius:{$radius} {$radius} 0 0!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body{box-sizing:border-box!important;margin:0!important;padding:10px!important;background:var(--tl-surface)!important;border:0!important;border-radius:0!important;box-shadow:none!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>.sub_bar{box-sizing:border-box!important;margin:0!important;border:1px solid var(--tl-border)!important;border-bottom:0!important;border-radius:{$radius} {$radius} 0 0!important;overflow:hidden!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>.sub_bar>.subbg{margin:0!important;border-radius:0!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>form{box-sizing:border-box!important;margin:0 0 12px!important;padding:0 8px 8px!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$radius} {$radius}!important;overflow:hidden!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>form:last-child{margin-bottom:0!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>form>ul{margin:0!important;padding:0!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>form>ul>li.windowbg{box-sizing:border-box!important;min-height:44px!important;padding-top:9px!important;padding-bottom:9px!important;background:var(--tl-surface)!important;border-color:var(--tl-border)!important;border-left:0!important;border-right:0!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>form>ul>li.windowbg:first-child{border-top:0!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>form>ul>li.windowbg:last-child{border-bottom:1px solid var(--tl-border)!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>form>.button{float:right!important;margin:8px 0 0 8px!important}\n";
    $css .= "{$boards}>.themelab_manage_boards_body>form:after{content:\"\";display:block!important;clear:both!important}\n";
    $css .= "{$boards} li .floatleft{display:flex!important;align-items:center!important;min-height:28px!important;padding-left:8px!important}\n";
    $css .= "{$boards} li .floatright{display:flex!important;align-items:center!important;gap:8px!important}\n";
    $css .= "{$boards} .button{margin:0!important}\n";
    $css .= "@media(max-width:760px){{$boards}>.themelab_manage_boards_body{padding:7px!important}{$boards} li .floatright{float:none!important;clear:both!important;justify-content:flex-end!important;padding-top:8px!important;flex-wrap:wrap!important}}\n";



    /* 1.0.1-dev12: Ancestor-aware primary-navigation stacking authority.
     * A submenu cannot escape an ancestor stacking context. Theme Lab still
     * placed #top_section/#upper_section at z-index 30, which allowed admin/
     * profile secondary menus around z-index 2100 to paint over the primary
     * Admin dropdown even when the submenu itself was 5040. Raise the entire
     * primary-header ancestor chain, then own the submenu surface as well. */
    $css .= "#top_section,#header_section,#header,#upper_section{position:relative!important;z-index:5000!important;overflow:visible!important;isolation:auto!important}\n";
    $css .= "#top_section .inner_wrap,#header_section .inner_wrap,#header .inner_wrap,#upper_section .inner_wrap{position:relative!important;z-index:auto!important;overflow:visible!important}\n";
    $css .= "#main_menu{position:relative!important;z-index:5010!important;overflow:visible!important;isolation:auto!important}\n";
    $css .= "#main_menu>.dropmenu,#main_menu .menu_nav{position:relative!important;z-index:5020!important;overflow:visible!important}\n";
    $css .= "#main_menu .dropmenu>li{position:relative!important;overflow:visible!important}\n";
    $css .= "#main_menu .dropmenu>li:hover,#main_menu .dropmenu>li:focus-within,#main_menu .dropmenu>li.open{z-index:5030!important}\n";
    $css .= "#main_menu .dropmenu>li>ul,#main_menu .dropmenu>li>ul ul{position:absolute!important;z-index:5040!important;background:var(--tl-surface)!important;background-image:none!important;opacity:1!important;overflow:visible!important;box-shadow:0 5px 16px rgba(0,0,0,.28)!important}\n";
    $css .= "#main_menu .dropmenu>li>ul>li,#main_menu .dropmenu>li>ul>li>a{background:var(--tl-surface)!important;background-image:none!important;opacity:1!important}\n";

    return $css;
}

function themelab_publish_css($project, &$audit = array(), $publish_assets = array(), $publish_options = array())
{
	$s = themelab_build_preview_settings($project);

	// Published themes must reference their packaged artwork, never Theme Lab's
	// workspace asset URLs.
	if (!empty($publish_assets['background_css_url']))
		$s['header_background_image_url'] = $publish_assets['background_css_url'];
	elseif (!empty($s['header_background_image']))
		$s['header_background_image_url'] = '';
	$project_data = !empty($project['project_data_array']) && is_array($project['project_data_array']) ? $project['project_data_array'] : array();
	$saved = themelab_publish_flatten_tokens($project_data);

	/*
	 * RC6.0.7 compiler synchronization contract:
	 *
	 * 1. Every saved project value enters the compiler inventory.
	 * 2. Every normalized preview value is written to the generated CSS manifest.
	 * 3. Semantic rules below consume that same normalized state.
	 *
	 * Some saved values are metadata, upload bookkeeping, or source selections rather
	 * than literal CSS declarations. They are still accounted for by the compiler and
	 * are represented by the normalized settings they produce.
	 */
	$clean = function ($value) {
		if (is_bool($value)) return $value ? '1' : '0';
		if ($value === null || $value === '') return 'initial';
		$value = preg_replace('~[\r\n\x00-\x1F{};]+~', ' ', (string) $value);
		return trim($value) === '' ? 'initial' : trim($value);
	};
	$name = function ($key) {
		$key = strtolower(preg_replace('~[^a-zA-Z0-9_-]+~', '-', (string) $key));
		return trim($key, '-');
	};
	$v = function ($key, $fallback = '') use ($s) {
		return array_key_exists($key, $s) ? $s[$key] : $fallback;
	};
	$px = function ($value, $fallback = 0) {
		return (int) (is_numeric($value) ? $value : $fallback) . 'px';
	};
	$mix_hex = function ($base, $tint, $tint_weight = 0.28) {
		$normalize = function ($hex, $fallback) {
			$hex = strtoupper(trim((string) $hex));
			return preg_match('~^#[0-9A-F]{6}$~', $hex) ? $hex : $fallback;
		};

		$base = $normalize($base, '#F5F7F8');
		$tint = $normalize($tint, '#3465A4');
		$tint_weight = max(0, min(1, (float) $tint_weight));
		$base_weight = 1 - $tint_weight;

		$parts = array();
		for ($i = 1; $i <= 5; $i += 2)
		{
			$b = hexdec(substr($base, $i, 2));
			$t = hexdec(substr($tint, $i, 2));
			$parts[] = max(0, min(255, (int) round(($b * $base_weight) + ($t * $tint_weight))));
		}

		return sprintf('#%02X%02X%02X', $parts[0], $parts[1], $parts[2]);
	};
	$shadow_map = array(
		'none' => 'none',
		'soft' => '0 2px 8px rgba(0,0,0,.12)',
		'medium' => '0 4px 14px rgba(0,0,0,.18)',
		'strong' => '0 8px 24px rgba(0,0,0,.28)',
	);
	$spacing_map = array('compact' => '8px', 'small' => '10px', 'medium' => '14px', 'large' => '20px', 'relaxed' => '24px');
	$field_height_map = array('compact' => '30px', 'standard' => '36px', 'large' => '44px');

	$manifest = array();
	foreach ($s as $key => $value)
	{
		if (is_array($value) || is_object($value)) continue;
		$manifest[] = '--tl-' . $name($key) . ':' . $clean($value);
	}

	$css = "/* Generated by Theme Lab 1.0.0 - Header Studio Parity Recovery */\n";
	$css .= "/* Compiler synchronization: " . count($saved) . " saved values accounted; " . count($manifest) . " normalized CSS values written. */\n";
	$css .= ":root{" . implode(';', $manifest) . ";}\n";
	/* RC6.1.21dl: canonical Buttons Studio CSS variable aliases.
	 * The manifest intentionally preserves saved key underscores, while the
	 * semantic button compiler uses hyphenated public CSS variable names.
	 * Bridge those names explicitly so published buttons consume the saved
	 * Buttons Studio colors instead of falling back to Curve2/theme text. */
	$button_primary_text = $clean($v('button_primary_text', '#FFFFFF'));
	$button_secondary_text = $clean($v('button_secondary_text', '#FFFFFF'));
	$button_danger_background = $clean($v('button_danger_background', '#B00020'));
	$button_danger_text = $clean($v('button_danger_text', '#FFFFFF'));
	$button_danger_border = $clean($v('button_danger_border', $v('button_danger_background', '#B00020')));
	$button_disabled_background = $clean($v('button_disabled_background', '#F5F7F8'));
	$button_disabled_text = $clean($v('button_disabled_text', '#666666'));
	$button_disabled_border = $clean($v('button_disabled_border', '#C7CDD1'));
	$css .= ":root{--tl-button-primary-text:{$button_primary_text};--tl-button-secondary-text:{$button_secondary_text};--tl-button-danger-background:{$button_danger_background};--tl-button-danger-text:{$button_danger_text};--tl-button-danger-border:{$button_danger_border};--tl-button-disabled-background:{$button_disabled_background};--tl-button-disabled-text:{$button_disabled_text};--tl-button-disabled-border:{$button_disabled_border};}\n";

	if (!empty($publish_options['color_modes']))
	{
		$dark_primary = $clean($v('primary', '#3465A4'));
		$dark_accent = $clean($v('accent', '#5B87B2'));
		$dark_link = $mix_hex($dark_primary, '#FFFFFF', 0.42);
		$dark_link_hover = $mix_hex($dark_primary, '#FFFFFF', 0.64);
		$dark_link_visited = $mix_hex($dark_accent, '#FFFFFF', 0.48);
		$dark_background = $mix_hex('#0D1117', $dark_primary, 0.06);
		$dark_surface = $mix_hex('#151A21', $dark_primary, 0.08);
		$dark_surface_alt = $mix_hex('#1D242D', $dark_primary, 0.10);
		$dark_border = $mix_hex('#39424E', $dark_primary, 0.12);

		$css .= "html[data-themelab-mode=dark]{"
			. "--tl-background:{$dark_background};"
			. "--tl-surface:{$dark_surface};--tl-surface_alt:{$dark_surface_alt};--tl-surface-alt:{$dark_surface_alt};"
			. "--tl-text:#F2F4F7;--tl-muted_text:#AEB7C2;--tl-muted-text:#AEB7C2;"
			. "--tl-border:{$dark_border};"
			. "--tl-link:{$dark_link};--tl-link_hover:{$dark_link_hover};--tl-link-hover:{$dark_link_hover};"
			. "--tl-link_visited:{$dark_link_visited};--tl-link-visited:{$dark_link_visited};"
			. "--tl-input_background:{$dark_surface};--tl-input-background:{$dark_surface};"
			. "--tl-input_text:#F2F4F7;--tl-input-text:#F2F4F7;"
			. "--tl-button_disabled_background:#2A313B;--tl-button-disabled-background:#2A313B;"
			. "--tl-button_disabled_text:#8994A2;--tl-button-disabled-text:#8994A2;"
			. "--tl-button_disabled_border:#46515E;--tl-button-disabled-border:#46515E;"
			. "color-scheme:dark;}\n";

		$css .= "html[data-themelab-mode=light]{color-scheme:light;}\n";
		$css .= ".themelab_mode_toggle{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;width:30px!important;height:24px!important;min-width:30px!important;margin:0!important;padding:0!important;border:1px solid color-mix(in srgb,var(--tl-header-text-color) 38%,transparent)!important;border-radius:999px!important;background:color-mix(in srgb,var(--tl-header-text-color) 12%,transparent)!important;color:var(--tl-header-text-color)!important;box-shadow:none!important;text-shadow:none!important;cursor:pointer!important;line-height:1!important;vertical-align:middle!important}\n";
		$css .= ".themelab_mode_toggle:hover,.themelab_mode_toggle:focus-visible{border-color:color-mix(in srgb,var(--tl-header-text-color) 62%,transparent)!important;background:color-mix(in srgb,var(--tl-header-text-color) 22%,transparent)!important;color:var(--tl-header-text-color)!important;outline:none!important}\n";
		$css .= ".themelab_mode_toggle svg{display:block!important;width:15px!important;height:15px!important;fill:none!important;stroke:currentColor!important;stroke-width:2!important;stroke-linecap:round!important;stroke-linejoin:round!important}\n";
	}




	/* Palette, page canvas, layout, and global typography. */
	$width = $v('width_mode', 'fixed') === 'fluid' ? '100%' : $px($v('max_width', 1200), 1200);
	$radius = $px($v('border_radius', 6), 6);
	$surface_shadow = isset($shadow_map[$v('shadow_style', 'soft')]) ? $shadow_map[$v('shadow_style', 'soft')] : $shadow_map['soft'];
	$content_spacing = isset($spacing_map[$v('content_spacing', 'medium')]) ? $spacing_map[$v('content_spacing', 'medium')] : $spacing_map['medium'];
	/* RC6.1.21cm: canonical Board Index stats scale.
	 * Values derive from Typography Studio's same small-text calculation. */
	$board_base_font = max(10, min(22, (int) $v('font_size', 14)));
	$board_stats_font = max(9, (int) round($board_base_font * 0.82));
	$board_stats_label = max(8, (int) round($board_stats_font * 0.82));
	$board_stats_value = max(10, (int) round($board_stats_font * 1.10));
	$board_stats_icon = max(20, (int) round($board_stats_font * 2.05));
	$board_stats_icon_svg = max(11, (int) round($board_stats_font * 1.08));
	$board_stats_copy = max(40, (int) round($board_stats_font * 4.6));
	$board_stats_item = $board_stats_icon + $board_stats_copy + 6;
	$board_stats_lane = ($board_stats_item * 2) + 13;
	$board_stats_divider = max(30, (int) round($board_stats_font * 3.6));
	$board_stats_surface = $mix_hex($v('surface_alt', '#F5F7F8'), $v('primary', '#3465A4'), 0.28);
	$board_stats_border_color = $mix_hex($v('border', '#C7CDD1'), $v('primary', '#3465A4'), 0.34);
	$css .= "#top_section .inner_wrap,body #wrapper,body #header,#footer .inner_wrap{max-width:{$width}!important;margin-inline:auto!important;width:calc(100% - (2 * {$content_spacing}))!important}\nbody #wrapper{padding:0!important}\n";
	$css .= themelab_compile_typography($s);
	$css .= themelab_compile_palette_surfaces($s);
	$css .= themelab_compile_structural_translation($s);
	$css .= themelab_compile_card_assembly($s);
	$css .= themelab_compile_forum_surfaces($s);
	$css .= ".main_container,.generic_list_wrapper,.roundframe,.roundframe2,.windowbg,.information{box-shadow:{$surface_shadow}}\n";

	/* Header: base gradient/solid, optional image layer, then overlay. */
	$header_bg = !empty($s['header_gradient_enabled']) && !empty($s['header_gradient_css']) ? $s['header_gradient_css'] : $v('header_background_color', $v('header','#3465A4'));
	$header_image = !empty($s['header_background_image_url']) ? "url('" . str_replace(array("'", "\\"), array("\\'", "\\\\"), $s['header_background_image_url']) . "')" : 'none';
	$header_image_opacity = max(0, min(100, (int) $v('header_image_opacity', 100))) / 100;
	$header_overlay_opacity = max(0, min(100, (int) $v('header_background_opacity', 35))) / 100;
	$header_size = in_array($v('header_background_size', 'cover'), array('cover','contain','auto'), true) ? $v('header_background_size', 'cover') : 'cover';
	$logo_text_align = in_array($v('logo_alignment','left'), array('left','center','right'), true) ? $v('logo_alignment','left') : 'left';
	$logo_margin = $logo_text_align === 'center' ? '0 auto' : ($logo_text_align === 'right' ? '0 0 0 auto' : '0 auto 0 0');
	$logo_scale = max(25, min(100, (int) $v('logo_size',100)));
	$logo_safe_height = max(1, (int) floor((int) $v('header_height',130) * 0.82));
	$logo_max_height = max(1, (int) floor($logo_safe_height * ($logo_scale / 100)));
	$logo_max_width = max(1, (int) floor(70 * ($logo_scale / 100)));
	$css .= "#header{position:relative!important;isolation:isolate;overflow:hidden;box-sizing:border-box;min-height:" . $px($v('header_height',130),130) . "!important;background:{$header_bg}!important;color:var(--tl-header-text-color)!important;display:block!important;padding:var(--tl-content-spacing,14px)!important}\n";
	$css .= "#header::before,#header::after{content:\"\";position:absolute;inset:0;pointer-events:none}\n";
	$css .= "#header::before{z-index:0;background-image:{$header_image};background-position:" . (int)$v('header_background_position_x',50) . "% " . (int)$v('header_background_position_y',50) . "%;background-size:{$header_size};background-repeat:no-repeat;opacity:{$header_image_opacity}}\n";
	$css .= "#header::after{z-index:1;background:rgba(0,0,0,{$header_overlay_opacity})}\n";
	$css .= "#header>*,#header .inner_wrap{position:relative;z-index:2}\n";
	$css .= "#header h1.forumtitle,#header h1.forumtitle a,#header h2,#header a,#siteslogan{color:var(--tl-header-text-color)!important}\n";
	$css .= "#header h1.forumtitle{display:block!important;width:100%!important;max-width:100%!important;margin:0!important;padding:0!important;text-align:{$logo_text_align}!important}\n";
	$css .= "#siteslogan{position:absolute!important;z-index:3!important;right:var(--tl-content-spacing,14px)!important;top:50%!important;transform:translateY(-50%)!important;margin:0!important;text-align:right!important}\n";
	$css .= "#header img.logo,#header h1.forumtitle img,#header .forumtitle img,#header .themelab_custom_logo{display:block!important;width:auto!important;height:auto!important;max-width:{$logo_max_width}%!important;max-height:{$logo_max_height}px!important;object-fit:contain!important;margin:{$logo_margin}!important}\n";

	/* Category/card headers and Board Index ownership. */
	$category_bg = !empty($s['category_header_gradient_enabled']) && !empty($s['category_header_gradient_css']) ? $s['category_header_gradient_css'] : $v('primary','#3465A4');
	$card_bg = !empty($s['card_header_gradient_enabled']) && !empty($s['card_header_gradient_css']) ? $s['card_header_gradient_css'] : $v('secondary',$v('primary','#5B87B2'));
	$css .= ".cat_bar,.title_bar,.sub_bar{min-height:" . $px($v('board_index_header_height',38),38) . ";color:var(--tl-board-index-header-text)!important;text-align:var(--tl-board-index-header-alignment);font-weight:var(--tl-board-index-header-weight)}\n";
	$css .= ".boardindex_table,.boardindex_table .windowbg{border-width:" . $px($v('board_index_border_width',1),1) . ";border-color:var(--tl-border)!important;border-radius:" . $px($v('board_index_container_radius',6),6) . ";padding:" . $px($v('board_index_panel_padding',12),12) . "}\n";
	$css .= ".board_description,.boardindex_table .info p{color:var(--tl-board-index-description-text)!important}.board_stats,.boardindex_table .stats{color:var(--tl-board-index-stats-text)!important;text-align:var(--tl-board-index-stats-position)}\n";

	/* Buttons and navigation states. */
	$button_shadow = $v('button_depth','soft') === 'flat' ? 'none' : ($v('button_depth','soft') === 'raised' ? '0 3px 0 rgba(0,0,0,.25),0 4px 8px rgba(0,0,0,.18)' : '0 2px 5px rgba(0,0,0,.16)');
	$button_selectors = '.button,.button_submit,.buttonlist a,.quickbuttons a,.moderation a,.pagesection a.button,.pagesection .button,input[type=submit]';
	$css .= "{$button_selectors}{appearance:none;border-style:solid!important;border-radius:" . $px($v('button_border_radius',5),5) . "!important;border-width:" . $px($v('button_border_width',0),0) . "!important;padding:" . $px($v('button_padding_vertical',8),8) . ' ' . $px($v('button_padding_horizontal',16),16) . "!important;font-family:var(--tl-font-family)!important;font-weight:var(--tl-button-font-weight)!important;text-transform:var(--tl-button-text-transform)!important;box-shadow:{$button_shadow}!important}\n";
	$css .= ".button.primary,.button_submit,input[type=submit]{background:var(--tl-primary)!important;color:var(--tl-button-primary-text)!important;border-color:var(--tl-primary)!important}\n";
	$css .= ".button.secondary,.buttonlist a,.quickbuttons a,.moderation a,.pagesection a.button,.pagesection .button{background:var(--tl-secondary)!important;color:var(--tl-button-secondary-text)!important;border-color:var(--tl-secondary)!important}\n";
	$css .= ".button.danger,.button.red{background:var(--tl-button-danger-background)!important;color:var(--tl-button-danger-text)!important;border-color:var(--tl-button-danger-border)!important}\n";
	$css .= ".button[disabled],.button_submit[disabled],input[type=submit][disabled]{background:var(--tl-button-disabled-background)!important;color:var(--tl-button-disabled-text)!important;border-color:var(--tl-button-disabled-border)!important;opacity:.75}\n";
	$css .= "#main_menu .dropmenu>li>a,#main_menu .button,#main_menu a{background:var(--tl-button-header-background)!important;color:var(--tl-button-header-text)!important;border-color:var(--tl-button-header-border)!important}#main_menu .dropmenu>li:hover>a,#main_menu .dropmenu>li>a:focus,#main_menu a:hover{background:var(--tl-button-header-hover-background)!important;color:var(--tl-button-header-hover-text)!important}#main_menu .dropmenu>li>a.active,#main_menu .active a,#main_menu .current a{background:var(--tl-button-header-current-background)!important;color:var(--tl-button-header-current-text)!important}\n";
	$css .= "#main_menu>.dropmenu,#main_menu .dropmenu{display:flex!important;flex-wrap:wrap!important;gap:" . $px($v('navigation_menu_gap',6),6) . ";justify-content:var(--tl-navigation-alignment)!important;margin:0!important}#main_menu .dropmenu>li{margin:0!important}#main_menu a{border-radius:" . $px($v('navigation_menu_radius',5),5) . "!important;padding:" . $px($v('navigation_menu_padding_vertical',6),6) . ' ' . $px($v('navigation_menu_padding_horizontal',12),12) . "!important;font-weight:var(--tl-navigation-font-weight)!important;text-transform:var(--tl-navigation-text-transform)!important}\n";

	/* RC6.1.21at: make the current location unmistakable across forum, Admin, and Theme Lab navigation. */
	$active_nav = '#main_menu .dropmenu>li>a.active,#main_menu .dropmenu>li>a.active:hover,#main_menu .dropmenu>li>a.active:focus,.menu_nav>li>a.active,.menu_nav>li>a.active:hover,.menu_nav>li>a.active:focus,.generic_menu>.dropmenu>li>a.active,.generic_menu>.dropmenu>li>a.active:hover,.generic_menu>.dropmenu>li>a.active:focus,.generic_menu>.dropmenu>li>a.chosen,.generic_menu>.dropmenu>li>a.chosen:hover,.generic_menu>.dropmenu>li>a.chosen:focus,#adm_submenus .generic_menu .dropmenu>li>a.active,#adm_submenus .generic_menu .dropmenu>li>a.chosen';
	$active_mode = $v('navigation_active_emphasis', 'filled');
	if ($active_mode === 'underline')
		$css .= "{$active_nav}{background:transparent!important;color:var(--tl-primary)!important;border-color:transparent!important;border-radius:0!important;box-shadow:inset 0 -3px 0 var(--tl-primary)!important;text-shadow:none!important}\n";
	elseif ($active_mode === 'outline')
		$css .= "{$active_nav}{background:transparent!important;color:var(--tl-primary)!important;border-color:var(--tl-primary)!important;border-radius:" . $px($v('navigation_menu_radius',5),5) . "!important;box-shadow:0 0 0 1px color-mix(in srgb,var(--tl-primary) 18%,transparent)!important;text-shadow:none!important}\n";
	else
		$css .= "{$active_nav}{background:var(--tl-primary)!important;color:var(--tl-button-primary-text,#fff)!important;border-color:var(--tl-primary)!important;border-radius:" . $px($v('navigation_menu_radius',5),5) . "!important;box-shadow:0 2px 5px rgba(0,0,0,.16)!important;text-shadow:none!important}\n";
	$css .= ".navigate_section,.breadcrumb{font-style:" . ($v('navigation_breadcrumb_style','plain') === 'italic' ? 'italic' : 'normal') . "}\n";

	/* RC6.1.21dl: Navigation Studio owns real SMF pagination as an independent component. */
	$pagination_style = $v('navigation_pagination_style', 'boxed');
	$pagination_active = $v('navigation_pagination_active_emphasis', 'filled');
	$pagination_size = $v('navigation_pagination_size', 'standard');
	$pagination_gap = max(0, min(16, (int) $v('navigation_pagination_gap', 5)));
	$pagination_radius = max(0, min(24, (int) $v('navigation_pagination_radius', 5)));
	$pagination_weight_setting = (string) $v('navigation_pagination_active_weight', 'balanced');
	$pagination_weight = $pagination_weight_setting === 'balanced'
		? ($pagination_active === 'underline' ? '600' : '500')
		: (in_array($pagination_weight_setting, array('400','500','600','700'), true) ? $pagination_weight_setting : '500');
	$pagination_items = '.pagelinks a,.pagelinks .navPages,.pagelinks .current_page';
	$pagination_padding = $pagination_size === 'compact' ? '2px 6px' : ($pagination_size === 'large' ? '5px 10px' : '3px 7px');
	$pagination_font_size = $pagination_size === 'compact' ? '.92em' : ($pagination_size === 'large' ? '1.06em' : '1em');
	$css .= ".pagelinks{display:flex!important;align-items:center!important;flex-wrap:wrap!important;gap:{$pagination_gap}px!important}\n";
	$css .= "{$pagination_items}{box-sizing:border-box!important;display:inline-block!important;line-height:1.35!important;padding:{$pagination_padding}!important;font-size:{$pagination_font_size}!important;font-weight:400!important;text-decoration:none!important;color:var(--tl-link)!important;border:1px solid transparent!important;background:transparent!important;box-shadow:none!important}\n";
	if ($pagination_style === 'pills')
		$css .= "{$pagination_items}{background:var(--tl-surface)!important;border-color:var(--tl-secondary)!important;border-radius:999px!important}\n";
	elseif ($pagination_style === 'boxed')
		$css .= "{$pagination_items}{background:var(--tl-surface)!important;border-color:var(--tl-secondary)!important;border-radius:{$pagination_radius}px!important}\n";
	else
		$css .= "{$pagination_items}{background:transparent!important;border-color:transparent!important;border-radius:0!important}\n";
	$css .= ".pagelinks .current_page::before,.pagelinks .current_page::after{content:none!important;display:none!important}\n";
	$pagination_current = '.pagelinks .current_page';
	if ($pagination_active === 'underline')
		$css .= "{$pagination_current}{background:transparent!important;color:var(--tl-primary)!important;border-color:transparent!important;border-radius:0!important;box-shadow:inset 0 -3px 0 var(--tl-primary)!important;text-shadow:none!important;font-weight:{$pagination_weight}!important}\n";
	elseif ($pagination_active === 'outline')
		$css .= "{$pagination_current}{background:transparent!important;color:var(--tl-primary)!important;border-color:var(--tl-primary)!important;border-radius:" . ($pagination_style === 'pills' ? '999px' : ($pagination_style === 'plain' ? '0' : $pagination_radius . 'px')) . "!important;box-shadow:0 0 0 1px color-mix(in srgb,var(--tl-primary) 18%,transparent)!important;text-shadow:none!important;font-weight:{$pagination_weight}!important}\n";
	else
		$css .= "{$pagination_current}{background:var(--tl-primary)!important;color:var(--tl-button-primary-text,#fff)!important;border-color:var(--tl-primary)!important;border-radius:" . ($pagination_style === 'pills' ? '999px' : ($pagination_style === 'plain' ? '0' : $pagination_radius . 'px')) . "!important;box-shadow:none!important;text-shadow:none!important;font-weight:{$pagination_weight}!important}\n";

	/* Form controls. */
	$field_size = $v('forms_field_size','standard');
	$field_height = isset($field_height_map[$field_size]) ? $field_height_map[$field_size] : $field_height_map['standard'];
	$field_padding_y = $field_size === 'compact' ? '4px' : ($field_size === 'large' ? '10px' : '7px');
	$field_padding_x = $px($v('forms_padding_horizontal',9),9);
	$css .= "input[type=text],input[type=email],input[type=password],input[type=search],input[type=url],input[type=number],select,textarea{box-sizing:border-box!important;min-height:{$field_height};background:var(--tl-surface)!important;color:var(--tl-text)!important;border:" . $px($v('forms_border_width',1),1) . " solid var(--tl-border)!important;border-radius:" . $px($v('forms_border_radius',4),4) . "!important;padding:{$field_padding_y} {$field_padding_x}!important;line-height:1.25!important}\n";
	$css .= "select{padding-right:max(28px,{$field_padding_x})!important}textarea{min-height:" . $px($v('forms_textarea_height',82),82) . ";resize:var(--tl-forms-textarea-resize);line-height:var(--tl-line-height)!important}label{font-weight:var(--tl-forms-label-weight)}\n";
	/* RC6.1.21bg: Login forms must remain readable on dark Theme Lab surfaces. */
	$css .= ".login dt,.login dd,.login label,#admin_login label,#admin_login dt,#admin_login dd{color:var(--tl-text)!important}\n";
	$focus_style = $v('forms_focus_style', 'glow');
	$focus_selector = 'input[type=text]:focus,input[type=email]:focus,input[type=password]:focus,input[type=search]:focus,input[type=url]:focus,input[type=number]:focus,select:focus,textarea:focus';
	$focus_border_width = max(2, (int) $v('forms_border_width', 1));
	if ($focus_style === 'none')
		$css .= $focus_selector . "{border-color:var(--tl-border)!important;outline:none!important;box-shadow:none!important}\n";
	elseif ($focus_style === 'border')
		$css .= $focus_selector . "{border-color:var(--tl-primary)!important;border-width:{$focus_border_width}px!important;outline:none!important;box-shadow:none!important}\n";
	elseif ($focus_style === 'underline')
		$css .= $focus_selector . "{border-color:var(--tl-border)!important;border-bottom-color:var(--tl-primary)!important;border-radius:0!important;outline:none!important;box-shadow:inset 0 -2px 0 var(--tl-primary)!important}\n";
	else
		$css .= $focus_selector . "{border-color:var(--tl-primary)!important;outline:none!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--tl-primary) 22%,transparent)!important}\n";

	/* Top Navigation and Utility specialist. */
	$css .= themelab_compile_top_navigation_utility($s);

	/* Topic Display specialist. */
	$css .= themelab_compile_topic_display($s);

	/* Footer Studio specialist. */
	$css .= themelab_compile_footer($s);

	/* Final preview-reference authority. */
	$css .= themelab_compile_preview_reference_parity($s);

	/* Final layout reconstruction authority. */
	$css .= themelab_compile_layout_reconstruction($s);

	/* Final preview-to-reality component authority. */
	$css .= themelab_compile_preview_reality_pass($s);

	/* RC6.1.20 final shared-frame and joined-surface authority. */
	$css .= themelab_compile_surface_translation_authority($s);

	/* RC6.1.20n Premium Polish single ownership authority. */
	$css .= themelab_compile_premium_polish_ownership_cleanup($s);
	$css .= themelab_compile_header_menu_completion($s);
	$css .= themelab_compile_seamless_polish_completion($s);
	$css .= themelab_compile_connected_surface_polish($s);
	$css .= themelab_compile_reply_topic_summary_polish($s);
	$css .= themelab_compile_search_surface_polish($s);
	$css .= themelab_compile_member_surface_polish($s);

	/* RC6.1.21cm: final Header Studio image-placement authority.
	 * Reassert the saved image geometry after every legacy parity pass so the
	 * published header uses the exact same cover/contain/auto and X/Y placement
	 * that Header Studio previews. */
	$header_final_image = !empty($s['header_background_image_url']) ? "url('" . str_replace(array("'", "\\"), array("\\'", "\\\\"), $s['header_background_image_url']) . "')" : 'none';
	$header_final_size = in_array($v('header_background_size', 'cover'), array('cover', 'contain', 'auto'), true) ? $v('header_background_size', 'cover') : 'cover';
	$header_final_x = max(0, min(100, (int) $v('header_background_position_x', 50)));
	$header_final_y = max(0, min(100, (int) $v('header_background_position_y', 50)));
	$header_final_opacity = max(0, min(100, (int) $v('header_image_opacity', 100))) / 100;
	$css .= "#header::before{content:\"\"!important;position:absolute!important;inset:0!important;width:100%!important;height:100%!important;min-width:0!important;min-height:0!important;z-index:0!important;background-image:{$header_final_image}!important;background-position:{$header_final_x}% {$header_final_y}%!important;background-size:{$header_final_size}!important;background-repeat:no-repeat!important;opacity:{$header_final_opacity}!important;transform:none!important;background-origin:border-box!important;background-clip:border-box!important}\\n";
	$css .= "#header{background-clip:border-box!important;background-origin:border-box!important}\\n";

	/* RC6.1.21cm: final generic SMF alternating-surface authority.
	 * Board/topic/post specialists below remain free to override these selectors. */
	$css .= ".table_grid:not(.boardindex_table) tbody tr:nth-child(odd)>td,.generic_list_wrapper tbody tr:nth-child(odd)>td{background-color:var(--tl-surface)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important}\\n";
	$css .= ".table_grid:not(.boardindex_table) tbody tr:nth-child(even)>td,.generic_list_wrapper tbody tr:nth-child(even)>td,.alternate{background-color:var(--tl-surface-alt)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important}\\n";
	$css .= ".errorbox,.error_message,.error_message td{background-color:var(--tl-surface-alt)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important}\\n";


	/* RC6.1.21cm: Board Index Stats Card Style #1 — clean, modern and palette-driven. */
	$css .= "#boardindex_table .board_stats,.boardindex_table .board_stats{box-sizing:border-box!important;justify-self:center!important;align-self:center!important;min-width:250px!important;max-width:250px!important;width:250px!important;margin:0!important;padding:0!important;text-align:left!important;color:var(--tl-board-index-stats-text)!important;font-size:14px!important;line-height:1.2!important}\n";	$css .= "#boardindex_table .themelab_board_stat,.boardindex_table .themelab_board_stat{box-sizing:border-box!important;display:grid!important;grid-template-columns:42px 64px!important;align-items:center!important;justify-content:center!important;gap:8px!important;width:114px!important;min-width:114px!important;margin:0!important;padding:0!important}\n";	$css .= "#boardindex_table .themelab_board_stat_topics .themelab_board_stat_icon,.boardindex_table .themelab_board_stat_topics .themelab_board_stat_icon{border-color:color-mix(in srgb,var(--tl-accent) 72%,var(--tl-border))!important;background:color-mix(in srgb,var(--tl-accent) 18%,transparent)!important;color:var(--tl-accent)!important}\n";	$css .= "#boardindex_table .themelab_board_stat_copy,.boardindex_table .themelab_board_stat_copy{display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;width:64px!important;min-width:64px!important;text-align:center!important;line-height:1.05!important}\n";	$css .= "#boardindex_table .themelab_board_stat_value,.boardindex_table .themelab_board_stat_value{display:block!important;width:100%!important;margin:0!important;color:var(--tl-primary)!important;font-size:20px!important;font-weight:700!important;line-height:1!important;text-align:center!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_topics .themelab_board_stat_value,.boardindex_table .themelab_board_stat_topics .themelab_board_stat_value{color:var(--tl-accent)!important}\n";	$css .= "#boardindex_table .lastpost,#boardindex_table .last_post,.boardindex_table .lastpost,.boardindex_table .last_post{box-sizing:border-box!important;align-self:center!important;justify-self:stretch!important;min-width:0!important;width:auto!important;margin:0!important;padding-left:18px!important;border-left:1px solid color-mix(in srgb,var(--tl-border) 82%,var(--tl-primary) 18%)!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_divider,.boardindex_table .themelab_board_stat_divider{display:block!important;align-self:stretch!important;width:1px!important;min-height:52px!important;background:color-mix(in srgb,var(--tl-border) 82%,var(--tl-primary) 18%)!important}\n";
	$css .= "@media(max-width:980px){#boardindex_table .board_stats,.boardindex_table .board_stats{min-width:220px!important;max-width:220px!important;width:220px!important}.boardindex_table .themelab_board_stats_card,#boardindex_table .themelab_board_stats_card{grid-template-columns:99px 1px 99px!important;width:220px!important;gap:10px!important}.boardindex_table .themelab_board_stat,#boardindex_table .themelab_board_stat{grid-template-columns:34px 57px!important;width:99px!important;min-width:99px!important;gap:8px!important}.boardindex_table .themelab_board_stat_copy,#boardindex_table .themelab_board_stat_copy{width:57px!important;min-width:57px!important}.boardindex_table .themelab_board_stat_icon,#boardindex_table .themelab_board_stat_icon{width:30px!important;height:30px!important}}\n";
	$css .= "@media(max-width:760px){#boardindex_table .board_stats,.boardindex_table .board_stats{grid-column:2!important;min-width:0!important;max-width:210px!important;width:210px!important;margin:8px 0 0!important}.boardindex_table .themelab_board_stats_card,#boardindex_table .themelab_board_stats_card{min-height:54px!important;margin:0!important}}\n";



	/* RC6.1.21cm: Board Index sub-board rail and stats refinement.
	 * Sub-boards become a full-width footer rail under the board row content.
	 * Stats typography is reduced to sit naturally beside board titles/descriptions,
	 * while both vertical separators use the exact same border treatment. */
	$css .= "#boardindex_table .up_contain>.children,.boardindex_table .up_contain>.children,#boardindex_table .up_contain>.board_children,.boardindex_table .up_contain>.board_children{grid-column:1/-1!important;grid-row:auto!important;box-sizing:border-box!important;float:none!important;position:static!important;display:flex!important;align-items:center!important;flex-wrap:wrap!important;gap:6px 10px!important;width:calc(100% + 28px)!important;max-width:none!important;min-width:0!important;margin:10px -14px -12px!important;padding:8px 14px!important;background:color-mix(in srgb,var(--tl-surface-alt) 76%,var(--tl-surface) 24%)!important;border-top:1px solid var(--tl-border)!important;border-left:0!important;border-right:0!important;border-bottom:0!important;border-radius:0!important;clear:both!important;text-align:left!important}\\n";
	$css .= "#boardindex_table .up_contain>.children strong,.boardindex_table .up_contain>.children strong,#boardindex_table .up_contain>.board_children strong,.boardindex_table .up_contain>.board_children strong{flex:0 0 auto!important;margin:0!important;color:var(--tl-board-index-stats-text)!important;font-size:inherit!important;line-height:1.2!important}\\n";
	$css .= "#boardindex_table .up_contain>.children a,.boardindex_table .up_contain>.children a,#boardindex_table .up_contain>.board_children a,.boardindex_table .up_contain>.board_children a{display:inline-block!important;margin:0!important;padding:0!important;line-height:1.2!important}\\n";

	$board_stat_separator = "1px solid color-mix(in srgb,var(--tl-border) 82%,var(--tl-primary) 18%)";
	$css .= "#boardindex_table .themelab_board_stat_divider,.boardindex_table .themelab_board_stat_divider{display:block!important;align-self:stretch!important;width:1px!important;min-height:44px!important;background:transparent!important;border-left:{$board_stat_separator}!important}\\n";
	$css .= "#boardindex_table .up_contain>.lastpost,.boardindex_table .up_contain>.lastpost{border-left:{$board_stat_separator}!important}\\n";


	$css .= "@media(max-width:760px){#boardindex_table .up_contain>.children,.boardindex_table .up_contain>.children,#boardindex_table .up_contain>.board_children,.boardindex_table .up_contain>.board_children{grid-column:1/-1!important;width:calc(100% + 24px)!important;margin:8px -12px -10px!important;padding:8px 12px!important}}\\n";


	$board_row_radius = max(0, (int) $v('board_index_container_radius', 6));


	/* RC6.1.21cm: real Curve2 Board Index row authority.
	 * Standard boards render as .up_contain, not .windowbg. Own the actual row
	 * structure so icon, board info, modern stats, and last post share fixed
	 * vertical guides exactly like the Theme Lab preview. */
	$css .= "#boardindex_table .up_contain,.boardindex_table .up_contain{box-sizing:border-box!important;display:grid!important;grid-template-columns:64px minmax(260px,1fr) {$board_stats_lane}px minmax(250px,28%)!important;align-items:center!important;column-gap:16px!important;width:100%!important;min-width:0!important;margin:0!important;padding:12px 14px!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0!important;overflow:hidden!important}\n";
	$css .= "#boardindex_table .up_contain:nth-child(even),.boardindex_table .up_contain:nth-child(even){background:var(--tl-board-index-row-background-alt,var(--tl-surface-alt))!important}\n";
	$css .= "#boardindex_table .up_contain>.board_icon,.boardindex_table .up_contain>.board_icon{grid-column:1!important;float:none!important;position:static!important;align-self:center!important;justify-self:center!important;width:64px!important;min-width:64px!important;margin:0!important;padding:0!important;text-align:center!important}\n";
	$css .= "#boardindex_table .up_contain>.info,.boardindex_table .up_contain>.info{grid-column:2!important;float:none!important;position:static!important;align-self:center!important;width:auto!important;min-width:0!important;margin:0!important;padding:0!important}\n";
	$css .= "#boardindex_table .up_contain>.board_stats,.boardindex_table .up_contain>.board_stats{grid-column:3!important;float:none!important;position:static!important;align-self:center!important;justify-self:center!important;width:{$board_stats_lane}px!important;min-width:{$board_stats_lane}px!important;max-width:{$board_stats_lane}px!important;margin:0!important;padding:0!important;font-size:{$board_stats_font}px!important;line-height:var(--tl-line-height)!important;text-align:left!important}\n";
	$css .= "#boardindex_table .up_contain>.lastpost,.boardindex_table .up_contain>.lastpost{grid-column:4!important;float:none!important;position:static!important;align-self:center!important;justify-self:stretch!important;width:auto!important;min-width:0!important;max-width:none!important;margin:0!important;padding:0 0 0 18px!important;border-left:1px solid color-mix(in srgb,var(--tl-border) 82%,var(--tl-primary) 18%)!important;text-align:left!important;overflow:hidden!important}\n";
	$css .= "#boardindex_table .up_contain>.lastpost>* ,.boardindex_table .up_contain>.lastpost>*{max-width:100%!important;overflow-wrap:anywhere!important}\n";
	$css .= "#boardindex_table .up_contain:last-child,.boardindex_table .up_contain:last-child{border-radius:0 0 {$board_row_radius}px {$board_row_radius}px!important}\n";
	$css .= "@media(max-width:980px){#boardindex_table .up_contain,.boardindex_table .up_contain{grid-template-columns:56px minmax(200px,1fr) minmax(12em,13.05em) minmax(200px,25%)!important;column-gap:12px!important;padding:10px 12px!important}#boardindex_table .up_contain>.board_stats,.boardindex_table .up_contain>.board_stats{width:{$board_stats_lane}px!important;min-width:{$board_stats_lane}px!important;max-width:{$board_stats_lane}px!important}#boardindex_table .up_contain>.lastpost,.boardindex_table .up_contain>.lastpost{padding-left:12px!important}}\n";
	$css .= "@media(max-width:760px){#boardindex_table .up_contain,.boardindex_table .up_contain{grid-template-columns:48px minmax(0,1fr)!important;grid-template-areas:'icon info' 'icon stats' 'icon last'!important;row-gap:8px!important;column-gap:10px!important}#boardindex_table .up_contain>.board_icon,.boardindex_table .up_contain>.board_icon{grid-area:icon!important;width:48px!important;min-width:48px!important}#boardindex_table .up_contain>.info,.boardindex_table .up_contain>.info{grid-area:info!important}#boardindex_table .up_contain>.board_stats,.boardindex_table .up_contain>.board_stats{grid-area:stats!important;justify-self:start!important;width:{$board_stats_lane}px!important;min-width:{$board_stats_lane}px!important;max-width:{$board_stats_lane}px!important}#boardindex_table .up_contain>.lastpost,.boardindex_table .up_contain>.lastpost{grid-area:last!important;border-left:0!important;padding-left:0!important}}\n";

	/* RC6.1.21cm: single final Board Index stats authority. */
	$css .= "#boardindex_table .up_contain>.board_stats,.boardindex_table .up_contain>.board_stats{box-sizing:border-box!important;width:{$board_stats_lane}px!important;min-width:{$board_stats_lane}px!important;max-width:{$board_stats_lane}px!important;font-size:{$board_stats_font}px!important;line-height:var(--tl-line-height)!important;overflow:hidden!important}\n";
	$css .= "#boardindex_table .themelab_board_stats_card,.boardindex_table .themelab_board_stats_card{box-sizing:border-box!important;display:grid!important;grid-template-columns:{$board_stats_item}px 1px {$board_stats_item}px!important;align-items:center!important;justify-content:center!important;gap:6px!important;width:{$board_stats_lane}px!important;min-width:{$board_stats_lane}px!important;max-width:{$board_stats_lane}px!important;min-height:0!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important}\n";
	$css .= "#boardindex_table .themelab_board_stat,.boardindex_table .themelab_board_stat{box-sizing:border-box!important;display:grid!important;grid-template-columns:{$board_stats_icon}px {$board_stats_copy}px!important;align-items:center!important;justify-content:center!important;gap:6px!important;width:{$board_stats_item}px!important;min-width:{$board_stats_item}px!important;max-width:{$board_stats_item}px!important;margin:0!important;padding:0!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_icon,.boardindex_table .themelab_board_stat_icon{display:grid!important;place-items:center!important;width:{$board_stats_icon}px!important;height:{$board_stats_icon}px!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_icon svg,.boardindex_table .themelab_board_stat_icon svg{width:{$board_stats_icon_svg}px!important;height:{$board_stats_icon_svg}px!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_copy,.boardindex_table .themelab_board_stat_copy{display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;width:{$board_stats_copy}px!important;min-width:{$board_stats_copy}px!important;max-width:{$board_stats_copy}px!important;text-align:center!important;line-height:1!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_label,.boardindex_table .themelab_board_stat_label{display:block!important;width:100%!important;margin:0 0 2px!important;font-size:{$board_stats_label}px!important;font-weight:700!important;line-height:1!important;letter-spacing:.02em!important;text-align:center!important;text-transform:uppercase!important;white-space:nowrap!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_value,.boardindex_table .themelab_board_stat_value{display:block!important;width:100%!important;margin:0!important;font-size:{$board_stats_value}px!important;font-weight:700!important;line-height:1!important;text-align:center!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_divider,.boardindex_table .themelab_board_stat_divider{display:block!important;align-self:center!important;width:0!important;height:{$board_stats_divider}px!important;min-height:{$board_stats_divider}px!important;background:transparent!important;border-left:{$board_stat_separator}!important}\n";
	$css .= "#boardindex_table .up_contain>.lastpost,.boardindex_table .up_contain>.lastpost{border-left:{$board_stat_separator}!important}\n";

	/* RC6.1.21dl: Board Index stats-card production parity.
	 * The semantic card markup is already published by
	 * themelab_publish_patch_boardindex_stats(). This block is deliberately
	 * after the historical compact-stat authority so production keeps the same
	 * bordered card, soft surface, round icons and stroked SVGs as the preview. */
	$css .= "#boardindex_table .themelab_board_stats_card,.boardindex_table .themelab_board_stats_card{box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(0,1fr) 1px minmax(0,1fr)!important;align-items:center!important;justify-content:center!important;gap:8px!important;width:{$board_stats_lane}px!important;min-width:{$board_stats_lane}px!important;max-width:{$board_stats_lane}px!important;min-height:0!important;margin:0!important;padding:8px 9px!important;border:1px solid {$board_stats_border_color}!important;border-radius:9px!important;background:{$board_stats_surface}!important;box-shadow:0 2px 7px rgba(0,0,0,.08)!important;overflow:hidden!important}\n";
	$css .= "#boardindex_table .themelab_board_stat,.boardindex_table .themelab_board_stat{box-sizing:border-box!important;display:grid!important;grid-template-columns:2.15em minmax(0,3.6em)!important;align-items:center!important;justify-content:center!important;gap:.45em!important;width:auto!important;min-width:0!important;max-width:none!important;margin:0!important;padding:0!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_icon,.boardindex_table .themelab_board_stat_icon{box-sizing:border-box!important;display:grid!important;place-items:center!important;width:2.15em!important;height:2.15em!important;border:1px solid color-mix(in srgb,var(--tl-primary) 72%,var(--tl-border))!important;border-radius:50%!important;background:color-mix(in srgb,var(--tl-primary) 18%,transparent)!important;color:var(--tl-primary)!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_topics .themelab_board_stat_icon,.boardindex_table .themelab_board_stat_topics .themelab_board_stat_icon{border-color:color-mix(in srgb,var(--tl-accent) 72%,var(--tl-border))!important;background:color-mix(in srgb,var(--tl-accent) 18%,transparent)!important;color:var(--tl-accent)!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_icon svg,.boardindex_table .themelab_board_stat_icon svg{display:block!important;width:1.15em!important;height:1.15em!important;fill:none!important;stroke:currentColor!important;stroke-width:1.8!important;stroke-linecap:round!important;stroke-linejoin:round!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_copy,.boardindex_table .themelab_board_stat_copy{display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;width:3.6em!important;min-width:0!important;max-width:3.6em!important;text-align:center!important;line-height:1!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_label,.boardindex_table .themelab_board_stat_label{display:block!important;width:100%!important;margin:0 0 .18em!important;color:var(--tl-board-index-stats-text)!important;font-size:.72em!important;font-weight:700!important;line-height:1!important;letter-spacing:.025em!important;text-align:center!important;text-transform:uppercase!important;white-space:nowrap!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_value,.boardindex_table .themelab_board_stat_value{display:block!important;width:100%!important;margin:0!important;color:var(--tl-primary)!important;font-size:1.08em!important;font-weight:700!important;line-height:1!important;text-align:center!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_topics .themelab_board_stat_value,.boardindex_table .themelab_board_stat_topics .themelab_board_stat_value{color:var(--tl-accent)!important}\n";
	$css .= "#boardindex_table .themelab_board_stat_divider,.boardindex_table .themelab_board_stat_divider{display:block!important;align-self:stretch!important;width:1px!important;height:auto!important;min-height:38px!important;background:color-mix(in srgb,var(--tl-border) 82%,var(--tl-primary) 18%)!important;border:0!important}\n";
	$css .= "@media(max-width:980px){#boardindex_table .themelab_board_stats_card,.boardindex_table .themelab_board_stats_card{gap:7px!important;padding:7px 8px!important}.boardindex_table .themelab_board_stat,#boardindex_table .themelab_board_stat{grid-template-columns:2em minmax(0,3.35em)!important;gap:.4em!important}.boardindex_table .themelab_board_stat_icon,#boardindex_table .themelab_board_stat_icon{width:2em!important;height:2em!important}}\n";

	/* RC6.1.21bi: final Board Index shell authority. Curve2 nests every
	 * category bar and its _boards wrapper inside .main_container, so target
	 * that real structure directly. The category bar owns the top edge, the
	 * rows continue the same side edges, and only the last row owns the bottom. */
	$board_row_background = $clean($v('board_index_row_background', $v('surface', '#FFFFFF')));
	$board_row_background_alt = $clean($v('board_index_row_background_alt', $v('surface_alt', '#F5F7F8')));

	/* 1.0.1-dev14f: Board Index row colors need mode-aware ownership.
	 * Keep the user-selected Board Index colors in light mode, but derive a
	 * coordinated dark pair from Theme Lab's dark surfaces. */
	$css .= ":root{--tl-board-index-row-background:{$board_row_background};--tl-board-index-row-background-alt:{$board_row_background_alt};}\n";

	if (!empty($publish_options['color_modes']))
	{
		$dark_board_row = $mix_hex($dark_surface, $dark_primary, 0.04);
		$dark_board_row_alt = $mix_hex($dark_surface_alt, $dark_primary, 0.06);

		$css .= "html[data-themelab-mode=dark]{"
			. "--tl-board-index-row-background:{$dark_board_row};"
			. "--tl-board-index-row-background-alt:{$dark_board_row_alt};"
			. "}\n";
	}

	/* #boardindex_table and its per-category main_container are structural only. */
	$css .= "#boardindex_table.boardindex_table,.boardindex_table#boardindex_table{box-sizing:border-box!important;border:0!important;border-radius:0!important;padding:0!important;background:transparent!important;overflow:visible!important}\n";
	$css .= "#boardindex_table>.main_container{box-sizing:border-box!important;margin:0 0 12px!important;padding:0!important;border:0!important;border-radius:{$board_row_radius}px!important;background:transparent!important;box-shadow:none!important;overflow:visible!important}\n";

	/* Real Curve2 category header: direct child of .main_container. */
	$css .= "#boardindex_table>.main_container>.cat_bar{box-sizing:border-box!important;margin:0!important;border:1px solid var(--tl-border)!important;border-bottom:0!important;border-radius:{$board_row_radius}px {$board_row_radius}px 0 0!important;box-shadow:none!important;overflow:hidden!important}\n";
	$css .= "#boardindex_table>.main_container>.cat_bar>.catbg{border-radius:{$board_row_radius}px {$board_row_radius}px 0 0!important}\n";

	/* The _boards element is a wrapper only; no second border or background. */
	$css .= "#boardindex_table>.main_container>[id$=\"_boards\"]{box-sizing:border-box!important;margin:0!important;padding:0!important;border:0!important;border-radius:0 0 {$board_row_radius}px {$board_row_radius}px!important;background:transparent!important;overflow:hidden!important}\n";

	/* Rows continue the exact left/right line begun by the category bar. */
	$css .= "#boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain{box-sizing:border-box!important;margin:0!important;border:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0!important;background-color:var(--tl-board-index-row-background,var(--tl-surface))!important;background-image:none!important;color:var(--tl-text)!important;box-shadow:none!important}\n";
	$css .= "#boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain:first-child{border-top:0!important}\n";
	$css .= "#boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain:last-child{border-radius:0 0 {$board_row_radius}px {$board_row_radius}px!important}\n";
	$css .= "#boardindex_table .up_contain .children{border-top:1px solid var(--tl-border)!important;background:transparent!important}\n";	/* RC6.1.21cm: final Theme Lab small-text typography guard for modern board stats. */
	$css .= "#boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain>.board_stats{font-size:{$board_stats_font}px!important;line-height:var(--tl-line-height)!important}\n";

	if ($v('board_index_alternating_rows', 'on') === 'on')
		$css .= "#boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain:nth-child(even){background-color:var(--tl-board-index-row-background-alt,var(--tl-surface-alt))!important;background-image:none!important}\n";

	if (!empty($publish_options['color_modes']))
	{
		$css .= "html[data-themelab-mode=dark] #boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain,"
			. "html[data-themelab-mode=dark] #boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain .info,"
			. "html[data-themelab-mode=dark] #boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain .lastpost,"
			. "html[data-themelab-mode=dark] #boardindex_table>.main_container>[id$=\"_boards\"]>.up_contain .board_description"
			. "{color:var(--tl-text)!important;}\n";
	}

	/* RC6.1.21bg: dark-surface completion for stock SMF tables and the
	 * Package Manager install/uninstall action screen. These selectors run
	 * after Curve2's white table cells/gradients and use Theme Lab surfaces. */
	$css .= ".table_grid>tbody>tr:not(.title_bar)>td,.table_grid>tbody>tr:not(.title_bar)>th{color:var(--tl-text)!important;border-color:var(--tl-border)!important}\n";
	$css .= ".table_grid>tbody>tr:nth-child(odd):not(.title_bar)>td,.table_grid>tbody>tr:nth-child(odd):not(.title_bar)>th,.table_grid>tbody>tr.bg.odd>td{background:var(--tl-surface)!important;background-image:none!important}\n";
	$css .= ".table_grid>tbody>tr:nth-child(even):not(.title_bar)>td,.table_grid>tbody>tr:nth-child(even):not(.title_bar)>th,.table_grid>tbody>tr.bg.even>td{background:var(--tl-surface-alt)!important;background-image:none!important}\n";
	$css .= "#view_package>.information,#view_package table.table_grid,#view_package table.full_width,#view_package table.full_width td{color:var(--tl-text)!important;border-color:var(--tl-border)!important}\n";
	$css .= "#view_package table.table_grid>tbody>tr.bg.odd>td,#view_package table.full_width>tbody>tr.bg.odd>td{background:var(--tl-surface)!important;background-image:none!important}\n";
	$css .= "#view_package table.table_grid>tbody>tr.bg.even>td,#view_package table.full_width>tbody>tr.bg.even>td{background:var(--tl-surface-alt)!important;background-image:none!important}\n";
	/* RC6.1.21cm: Native SMF Admin Error Log authority.
	 * Targets the actual Errors.template.php form directly; no admin wrapper assumptions. */
	$error_log_scope = 'form[action*="area=logs"][action*="sa=errorlog"]';
	$error_log_radius = max(0, min(10, (int) $v('border_radius', 6)));
	$css .= "{$error_log_scope}{color:var(--tl-text)!important}\n";
	$css .= "{$error_log_scope}>.cat_bar{margin-bottom:0!important;border-bottom:0!important;border-radius:{$error_log_radius}px {$error_log_radius}px 0 0!important}\n";
	$css .= "{$error_log_scope}>.information{box-sizing:border-box!important;margin:0 0 10px!important;padding:10px 12px!important;background:var(--tl-surface-alt)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-top:0!important;border-radius:0 0 {$error_log_radius}px {$error_log_radius}px!important}\n";
	$css .= "{$error_log_scope}>.information .additional_row{margin:0!important;padding:0!important;background:transparent!important;color:var(--tl-text)!important}\n";
	$css .= "{$error_log_scope}>.pagesection{box-sizing:border-box!important;min-height:34px!important;margin:0 0 8px!important;padding:6px 10px!important;background:var(--tl-surface-alt)!important;color:var(--tl-muted-text)!important;border:1px solid var(--tl-border)!important;border-radius:{$error_log_radius}px!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break{box-sizing:border-box!important;display:grid!important;grid-template-columns:44px minmax(0,1fr)!important;grid-template-rows:auto auto auto!important;width:100%!important;margin:0 0 10px!important;padding:0!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:{$error_log_radius}px!important;overflow:hidden!important;box-shadow:none!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break>.counter{grid-column:1!important;grid-row:1/-1!important;display:flex!important;align-items:flex-start!important;justify-content:center!important;min-width:44px!important;margin:0!important;padding:12px 6px!important;background:var(--tl-surface-alt)!important;color:var(--tl-muted-text)!important;border-right:1px solid var(--tl-border)!important;font-size:1.05em!important;font-weight:700!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break>.topic_details{grid-column:2!important;grid-row:1!important;box-sizing:border-box!important;margin:0!important;padding:9px 12px!important;background:var(--tl-surface-alt)!important;color:var(--tl-text)!important;border-bottom:1px solid var(--tl-border)!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break>.topic_details h5{margin:0!important;color:var(--tl-text)!important;font-size:1em!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break>.topic_details hr{display:none!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break>div:not(.counter):not(.topic_details):not(.post){grid-column:2!important;grid-row:2!important;box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:12px!important;margin:0!important;padding:10px 12px 8px!important;background:transparent!important;color:var(--tl-text)!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break .half_content{float:none!important;width:auto!important;min-width:0!important;margin:0!important;padding:0!important;color:var(--tl-text)!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break>.post{grid-column:2!important;grid-row:3!important;box-sizing:border-box!important;margin:0!important;padding:8px 12px 12px!important;background:transparent!important;color:var(--tl-text)!important;border-top:1px solid var(--tl-border)!important}\n";
	$css .= "{$error_log_scope}>.windowbg.word_break>.post br.clear:first-child{display:none!important}\n";
	$css .= "{$error_log_scope} .codeheader{box-sizing:border-box!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:8px!important;min-height:30px!important;margin:7px 0 0!important;padding:6px 9px!important;background:var(--tl-surface-alt)!important;color:var(--tl-muted-text)!important;border:1px solid var(--tl-border)!important;border-bottom:0!important;border-radius:{$error_log_radius}px {$error_log_radius}px 0 0!important}\n";
	$css .= "{$error_log_scope} .codeheader .code,{$error_log_scope} .codeheader .codeoperation{float:none!important;margin:0!important;color:var(--tl-muted-text)!important;font-family:inherit!important;font-size:.9em!important}\n";
	$css .= "{$error_log_scope} code.bbc_code{box-sizing:border-box!important;display:block!important;width:100%!important;max-width:100%!important;min-height:38px!important;margin:0!important;padding:9px 10px!important;background:var(--tl-surface-alt)!important;background-image:none!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:0 0 {$error_log_radius}px {$error_log_radius}px!important;box-shadow:none!important;font-family:monospace!important;font-size:.92em!important;line-height:1.4!important;white-space:pre-wrap!important;overflow-wrap:anywhere!important;overflow-x:auto!important}\n";
	$css .= "{$error_log_scope} code.bbc_code::selection{background:var(--tl-primary)!important;color:#fff!important}\n";
	$css .= "{$error_log_scope} a{overflow-wrap:anywhere!important}\n";
	$css .= "{$error_log_scope}>.pagesection:last-of-type{margin-top:10px!important;margin-bottom:0!important}\n";
	$css .= "@media(max-width:760px){{$error_log_scope}>.windowbg.word_break{grid-template-columns:34px minmax(0,1fr)!important}{$error_log_scope}>.windowbg.word_break>.counter{grid-row:1/-1!important;min-width:34px!important;padding:8px 5px!important}{$error_log_scope}>.windowbg.word_break>div:not(.counter):not(.topic_details):not(.post){display:block!important}{$error_log_scope}>.windowbg.word_break .half_content+ .half_content{margin-top:8px!important;text-align:left!important}}\n";


	$css .= "#view_package .windowbg,#view_package .information{background:var(--tl-surface)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important}\n";

	/* RC6.1.21bi: Theme Lab Publish runs inside the active generated theme.
	 * Give its non-windowbg cards explicit resolved colors so ThemeLab.css
	 * light fallbacks can never create white islands in a dark theme. */
	$publish_surface = $v('surface', '#FFFFFF');
	$publish_surface_alt = $v('surface_alt', '#F5F7F8');
	$publish_text = $v('text', '#243447');
	$publish_muted = $v('muted_text', $publish_text);
	$publish_border = $v('borders', $v('border', '#D5D9DD'));
	$css .= ".themelab_publish_pipeline,.themelab_publish_panel,.themelab_publish_hero{background:{$publish_surface}!important;color:{$publish_text}!important;border-color:{$publish_border}!important}\n";
	$css .= ".themelab_publish_pipeline_step,.themelab_publish_check,.themelab_publish_metrics span,.themelab_publish_structure pre{background:{$publish_surface_alt}!important;color:{$publish_text}!important;border-color:{$publish_border}!important}\n";
	$css .= ".themelab_publish_pipeline_step small,.themelab_publish_check small,.themelab_publish_option small,.themelab_publish_action_help{color:{$publish_muted}!important}\n";
	$css .= ".themelab_publish_action.is_published,.themelab_publish_success_summary{background:{$publish_surface}!important;color:{$publish_text}!important;border-color:{$publish_border}!important}\n";

	/* RC6.1.21bj: authentication + registration connected-component cleanup.
	 * Curve2 leaves vertical rhythm between the bar and its roundframe body.
	 * Own only the stock login/admin-login/register structures so the header
	 * and form surface read as one continuous Theme Lab component. */
	$auth_radius = max(0, (int) $v('border_radius', 6));
	$css .= ".login>.cat_bar,#admin_login>.cat_bar,#maintenance_mode>.cat_bar{box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-radius:{$auth_radius}px {$auth_radius}px 0 0!important;overflow:hidden!important}\n";
	$css .= ".login>.cat_bar>.catbg,#admin_login>.cat_bar>.catbg,#maintenance_mode>.cat_bar>.catbg{border-radius:{$auth_radius}px {$auth_radius}px 0 0!important}\n";
	$css .= ".login>.cat_bar+.roundframe,#admin_login>.cat_bar+.roundframe,#maintenance_mode>.cat_bar+.roundframe{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0 0 {$auth_radius}px {$auth_radius}px!important;background:var(--tl-surface)!important;color:var(--tl-text)!important}\n";
	$css .= ".login>.cat_bar+.roundframe dl,.login>.cat_bar+.roundframe dt,.login>.cat_bar+.roundframe dd,.login>.cat_bar+.roundframe strong,#admin_login>.cat_bar+.roundframe strong{color:var(--tl-text)!important}\n";

	/* RC6.1.21bl: registration title separation without spilling into the
	 * agreement screen. Only a cat_bar immediately followed by a title_bar is
	 * the standalone Registration Form title. A cat_bar followed by a
	 * roundframe is the Registration Agreement and remains one joined shell. */
	$registration_title_gap = 12;
	$css .= "#registration>.cat_bar:has(+.title_bar){box-sizing:border-box!important;margin-bottom:{$registration_title_gap}px!important;border-radius:{$auth_radius}px!important;overflow:hidden!important}\n";
	$css .= "#registration>.cat_bar:has(+.title_bar)>.catbg{border-radius:{$auth_radius}px!important}\n";
	$css .= "#registration>.cat_bar:has(+.roundframe){box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-radius:{$auth_radius}px {$auth_radius}px 0 0!important;overflow:hidden!important}\n";
	$css .= "#registration>.cat_bar:has(+.roundframe)>.catbg{border-radius:{$auth_radius}px {$auth_radius}px 0 0!important}\n";
	$css .= "#registration>.cat_bar+.roundframe{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0 0 {$auth_radius}px {$auth_radius}px!important;background:var(--tl-surface)!important;color:var(--tl-text)!important}\n";
	$css .= "#registration>.cat_bar+.title_bar{box-sizing:border-box!important;margin-top:0!important;margin-bottom:0!important;border-bottom:0!important;border-radius:{$auth_radius}px {$auth_radius}px 0 0!important}\n";
	$css .= "#registration>.cat_bar+.title_bar>.titlebg{border-radius:{$auth_radius}px {$auth_radius}px 0 0!important}\n";
	$css .= "#registration>.title_bar:has(+.roundframe.noup){box-sizing:border-box!important;margin-bottom:0!important;border-bottom:0!important;border-radius:{$auth_radius}px {$auth_radius}px 0 0!important}\n";
	$css .= "#registration>.title_bar:has(+.roundframe.noup)>.titlebg{border-radius:{$auth_radius}px {$auth_radius}px 0 0!important}\n";
	$css .= "#registration>.title_bar+.roundframe.noup{box-sizing:border-box!important;margin-top:0!important;border-top:0!important;border-left:1px solid var(--tl-border)!important;border-right:1px solid var(--tl-border)!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0 0 {$auth_radius}px {$auth_radius}px!important;background:var(--tl-surface)!important;color:var(--tl-text)!important}\n";
	$css .= "#registration>.roundframe.noup label,#registration>.roundframe.noup dt,#registration>.roundframe.noup dd,#registration>.roundframe.noup strong,#registration>.roundframe.noup .smalltext{color:var(--tl-text)!important}\n";

	$audit = array(
		'saved_values' => count($saved),
		'accounted_saved_values' => count($saved),
		'unaccounted_saved_values' => 0,
		'resolved_values' => count($s),
		'consumed_values' => count($s),
		'written_values' => count($manifest),
		'metadata_values' => max(0, count($saved) - count($s)),
		'bytes' => strlen($css),
	);
    /* RC6.1.21dl: integrated header utility preview-to-production parity.
     * Keep native SMF #top_info and #search_form behavior while presenting them
     * with the same compact translucent treatment used by the mini preview. */
    /* 1.0.1-dev3e: keep the native #top_section in the document flow as a
     * zero-height anchor. The integrated header moves its useful children out,
     * but display:none makes SMF's historical #top_section jump target dead. */
    $css .= ".themelab_utility_source_hidden{display:block!important;box-sizing:border-box!important;width:100%!important;height:0!important;min-height:0!important;margin:0!important;padding:0!important;border:0!important;overflow:hidden!important;visibility:hidden!important;pointer-events:none!important}\n";

    /* 1.0.1-dev3e: modern floating Scroll to Top control. It is injected by
     * themelab-generated.js after 300px of scroll and replaces the old footer
     * Go Up link without changing SMF core templates. */
    $css .= ".themelab_scroll_top{box-sizing:border-box!important;position:fixed!important;left:24px!important;right:auto!important;bottom:24px!important;z-index:45000!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;width:44px!important;height:44px!important;margin:0!important;padding:0!important;border:1px solid color-mix(in srgb,var(--tl-primary) 64%,var(--tl-border))!important;border-radius:50%!important;background:var(--tl-surface)!important;color:var(--tl-primary)!important;box-shadow:0 3px 12px rgba(0,0,0,.16)!important;cursor:pointer!important;opacity:0!important;visibility:hidden!important;transform:translateY(8px)!important;transition:opacity .18s ease,transform .18s ease,visibility .18s ease,background-color .18s ease,color .18s ease,border-color .18s ease!important}\n";
    $css .= ".themelab_scroll_top.is-visible{opacity:.92!important;visibility:visible!important;transform:translateY(0)!important}\n";
    $css .= ".themelab_scroll_top:hover,.themelab_scroll_top:focus-visible{background:var(--tl-primary)!important;color:var(--tl-primary-text,#fff)!important;border-color:var(--tl-primary)!important;opacity:1!important;outline:none!important}\n";
    $css .= ".themelab_scroll_top:focus-visible{box-shadow:0 0 0 3px color-mix(in srgb,var(--tl-primary) 28%,transparent),0 3px 12px rgba(0,0,0,.16)!important}\n";
    $css .= ".themelab_scroll_top svg{display:block!important;width:20px!important;height:20px!important;fill:none!important;stroke:currentColor!important;stroke-width:2.4!important;stroke-linecap:round!important;stroke-linejoin:round!important}\n";
    $css .= "#footer a.themelab_native_go_up_hidden{display:none!important}\n";
    $css .= "@media(max-width:700px){.themelab_scroll_top{left:14px!important;right:auto!important;bottom:14px!important;width:42px!important;height:42px!important}}\n";
    $css .= "@media(prefers-reduced-motion:reduce){.themelab_scroll_top{transition:none!important}}\n";
    $css .= "#header.themelab_integrated_utilities{box-sizing:border-box!important;position:relative!important}\n";
    $css .= "#themelab_header_utilities{box-sizing:border-box!important;display:flex!important;align-items:center!important;gap:8px!important;width:100%!important;min-height:0!important;margin:0 0 8px!important;padding:5px 8px 0!important;position:relative!important;z-index:40!important;font-size:.78em!important;line-height:1!important;color:var(--tl-header-text-color)!important}\n";
    $css .= "#themelab_header_utilities[data-position=left]{justify-content:flex-start!important}#themelab_header_utilities[data-position=right]{justify-content:flex-end!important}\n";
    $css .= "#themelab_header_utilities #top_info{box-sizing:border-box!important;display:flex!important;align-items:center!important;flex-wrap:nowrap!important;gap:5px!important;float:none!important;margin:0!important;padding:0!important;width:auto!important;height:auto!important;color:inherit!important}\n";
    $css .= "#themelab_header_utilities #top_info>li{display:block!important;float:none!important;margin:0!important;padding:0!important;height:auto!important;line-height:1!important}\n";
    $css .= "#themelab_header_utilities #top_info>li>a{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:3px!important;min-height:0!important;height:24px!important;margin:0!important;padding:4px 7px!important;border:1px solid color-mix(in srgb,var(--tl-header-text-color) 38%,transparent)!important;border-radius:999px!important;background:color-mix(in srgb,var(--tl-header-text-color) 12%,transparent)!important;color:var(--tl-header-text-color)!important;font:inherit!important;font-weight:500!important;line-height:1!important;text-decoration:none!important;text-shadow:0 1px 1px rgba(0,0,0,.28)!important;white-space:nowrap!important;box-shadow:none!important}\n";
    $css .= "#themelab_header_utilities #top_info>li>a *,#themelab_header_utilities #top_info>li>a span,#themelab_header_utilities #top_info>li>a strong{color:inherit!important;font:inherit!important;line-height:1!important;text-shadow:inherit!important}\n";
    $css .= "#themelab_header_utilities #top_info>li>a:hover,#themelab_header_utilities #top_info>li>a:focus,#themelab_header_utilities #top_info>li>a.open{border-color:color-mix(in srgb,var(--tl-header-text-color) 58%,transparent)!important;background:color-mix(in srgb,var(--tl-header-text-color) 22%,transparent)!important;color:var(--tl-header-text-color)!important;outline:none!important}\n";
    $css .= "#themelab_header_utilities #top_info .amt{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:1.35em!important;height:1.35em!important;margin-left:2px!important;padding:0 .25em!important;border:0!important;border-radius:999px!important;background:color-mix(in srgb,var(--tl-header-text-color) 24%,transparent)!important;color:inherit!important;font-size:.82em!important;line-height:1!important}\n";
    $css .= "#themelab_header_utilities #search_form{box-sizing:border-box!important;display:flex!important;align-items:center!important;gap:4px!important;float:none!important;position:static!important;inset:auto!important;width:auto!important;height:24px!important;margin:0!important;padding:0!important;background:transparent!important;border:0!important;box-shadow:none!important}\n";
    $css .= "#themelab_header_utilities #search_form input,#themelab_header_utilities #search_form input[type=search],#themelab_header_utilities #search_form input[type=text],#themelab_header_utilities #search_form select,#themelab_header_utilities #search_form button,#themelab_header_utilities #search_form input[type=submit]{box-sizing:border-box!important;height:24px!important;min-height:24px!important;margin:0!important;border:1px solid color-mix(in srgb,var(--tl-header-text-color) 38%,transparent)!important;border-radius:4px!important;background:color-mix(in srgb,var(--tl-header-text-color) 12%,transparent)!important;color:var(--tl-header-text-color)!important;font:inherit!important;font-weight:500!important;line-height:22px!important;text-shadow:0 1px 1px rgba(0,0,0,.22)!important;box-shadow:none!important}\n";
    $css .= "#themelab_header_utilities #search_form input[type=search],#themelab_header_utilities #search_form input[type=text]{width:88px!important;min-width:88px!important;max-width:110px!important;padding:3px 7px!important}\n";
    $css .= "#themelab_header_utilities #search_form select{width:auto!important;min-width:92px!important;max-width:118px!important;padding:2px 20px 2px 6px!important}\n";
    $css .= "#themelab_header_utilities #search_form button,#themelab_header_utilities #search_form input[type=submit]{width:auto!important;min-width:0!important;padding:2px 8px!important;cursor:pointer!important}\n";
    $css .= "#themelab_header_utilities #search_form input::placeholder{color:color-mix(in srgb,var(--tl-header-text-color) 76%,transparent)!important;opacity:1!important}\n";
    $css .= "#themelab_header_utilities #search_form input:focus,#themelab_header_utilities #search_form select:focus,#themelab_header_utilities #search_form button:hover,#themelab_header_utilities #search_form input[type=submit]:hover{border-color:color-mix(in srgb,var(--tl-header-text-color) 62%,transparent)!important;background:color-mix(in srgb,var(--tl-header-text-color) 22%,transparent)!important;outline:none!important}\n";
    $css .= "#themelab_header_utilities #top_info .top_menu{font-size:1.22em!important;line-height:1.35!important;color:var(--tl-text)!important;text-shadow:none!important}\n";
    $css .= "#themelab_header_utilities #top_info .top_menu *{text-shadow:none!important}\n";
    $css .= "@media(max-width:900px){#themelab_header_utilities{flex-wrap:wrap!important;gap:5px!important;padding:4px 6px 0!important}#themelab_header_utilities #top_info{flex-wrap:wrap!important}#themelab_header_utilities #search_form input[type=search],#themelab_header_utilities #search_form input[type=text]{width:78px!important;min-width:78px!important}#themelab_header_utilities #search_form select{min-width:84px!important;max-width:104px!important}}\n";
    $css .= "@media(max-width:640px){#themelab_header_utilities{align-items:center!important;justify-content:center!important;width:100%!important;padding:4px 4px 0!important}#themelab_header_utilities #top_info{justify-content:center!important}#themelab_header_utilities #search_form{width:100%!important;justify-content:center!important}#themelab_header_utilities #search_form input[type=search],#themelab_header_utilities #search_form input[type=text]{width:96px!important;min-width:96px!important;max-width:120px!important}}\n";

    /* RC6.1.21dl: exact integrated utility parity authority.
     * Curve2's top strip uses tiny relative fonts and sprite icons. Neutralize
     * those inherited presentation rules and render the native controls with
     * the same compact pill language as Theme Lab's mini preview. */
    $header_utility_text = strtoupper((string) $v('header_text_color', '#FFFFFF'));
    $header_utility_rgb = preg_match('~^#[0-9A-F]{6}$~', $header_utility_text)
        ? array(hexdec(substr($header_utility_text, 1, 2)), hexdec(substr($header_utility_text, 3, 2)), hexdec(substr($header_utility_text, 5, 2)))
        : array(255, 255, 255);
    $header_utility_luma = (($header_utility_rgb[0] * 299) + ($header_utility_rgb[1] * 587) + ($header_utility_rgb[2] * 114)) / 255000;
    $header_utility_surface = $header_utility_luma >= 0.56 ? 'rgba(0,0,0,.30)' : 'rgba(255,255,255,.76)';
    $header_utility_surface_hover = $header_utility_luma >= 0.56 ? 'rgba(0,0,0,.42)' : 'rgba(255,255,255,.90)';
    $header_utility_border = $header_utility_luma >= 0.56 ? 'rgba(255,255,255,.42)' : 'rgba(0,0,0,.30)';
    $header_utility_shadow = $header_utility_luma >= 0.56 ? '0 1px 3px rgba(0,0,0,.22)' : '0 1px 3px rgba(255,255,255,.18)';
    $css .= "#themelab_header_utilities{gap:6px!important;margin:0 0 7px!important;padding:7px 10px 0!important;font-size:12px!important;line-height:1!important;color:{$header_utility_text}!important}\n";
    $css .= "#themelab_header_utilities #top_info{gap:5px!important;font-size:12px!important;line-height:1!important}\n";
    $css .= "#themelab_header_utilities #top_info>li,#themelab_header_utilities #top_info>li>a{font-size:12px!important;line-height:1!important}\n";
    $css .= "#themelab_header_utilities #top_info>li>a{height:25px!important;min-height:25px!important;padding:5px 9px!important;border:1px solid {$header_utility_border}!important;border-radius:999px!important;background:{$header_utility_surface}!important;color:{$header_utility_text}!important;font-family:inherit!important;font-size:12px!important;font-weight:600!important;line-height:13px!important;text-shadow:0 1px 1px rgba(0,0,0,.24)!important;backdrop-filter:blur(4px)!important;-webkit-backdrop-filter:blur(4px)!important;box-shadow:{$header_utility_shadow}!important}\n";
    $css .= "#themelab_header_utilities #top_info>li>a:hover,#themelab_header_utilities #top_info>li>a:focus,#themelab_header_utilities #top_info>li>a.open{background:{$header_utility_surface_hover}!important;border-color:{$header_utility_border}!important;color:{$header_utility_text}!important}\n";
    $css .= "#themelab_header_utilities #top_info>li>a>.main_icons,#themelab_header_utilities #top_info>li>a .main_icons{display:none!important;width:0!important;height:0!important;margin:0!important;padding:0!important;background:none!important}\n";
    $css .= "#themelab_header_utilities #top_info .amt{min-width:1.45em!important;height:1.45em!important;padding:0 .32em!important;background:{$header_utility_surface_hover}!important;color:{$header_utility_text}!important;font-size:10px!important;font-weight:700!important}\n";
    $css .= "#themelab_header_utilities #search_form{display:flex!important;align-items:center!important;gap:0!important;width:126px!important;height:25px!important;overflow:hidden!important;border:1px solid {$header_utility_border}!important;border-radius:999px!important;background:{$header_utility_surface}!important;backdrop-filter:blur(4px)!important;-webkit-backdrop-filter:blur(4px)!important;box-shadow:{$header_utility_shadow}!important}\n";
    $css .= "#themelab_header_utilities #search_form select{position:absolute!important;width:1px!important;height:1px!important;margin:-1px!important;padding:0!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;clip-path:inset(50%)!important;white-space:nowrap!important;border:0!important}\n";
    $css .= "#themelab_header_utilities #search_form input[name=search],#themelab_header_utilities #search_form input[type=search],#themelab_header_utilities #search_form input[type=text]{display:block!important;flex:1 1 auto!important;width:96px!important;min-width:0!important;max-width:none!important;height:23px!important;margin:0!important;padding:4px 7px 4px 9px!important;border:0!important;border-radius:999px 0 0 999px!important;background:transparent!important;color:{$header_utility_text}!important;font-family:inherit!important;font-size:12px!important;font-weight:500!important;line-height:15px!important;text-shadow:0 1px 1px rgba(0,0,0,.22)!important;outline:0!important;box-shadow:none!important}\n";
    $css .= "#themelab_header_utilities #search_form input[name=search]::placeholder,#themelab_header_utilities #search_form input[type=search]::placeholder,#themelab_header_utilities #search_form input[type=text]::placeholder{color:{$header_utility_text}!important;opacity:.82!important}\n";
    $css .= "#themelab_header_utilities #search_form button,#themelab_header_utilities #search_form input[type=submit]{display:block!important;flex:0 0 29px!important;width:29px!important;min-width:29px!important;max-width:29px!important;height:23px!important;margin:0!important;padding:0!important;border:0!important;border-left:1px solid {$header_utility_border}!important;border-radius:0 999px 999px 0!important;background-color:transparent!important;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23FFFFFF' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='6'/%3E%3Cpath d='m16 16 4 4'/%3E%3C/svg%3E\")!important;background-repeat:no-repeat!important;background-position:center!important;background-size:13px 13px!important;color:transparent!important;font-size:0!important;text-indent:-9999px!important;overflow:hidden!important;cursor:pointer!important;box-shadow:none!important}\n";
    if ($header_utility_luma < 0.56)
        $css .= "#themelab_header_utilities #search_form button,#themelab_header_utilities #search_form input[type=submit]{filter:invert(1)!important}\n";
    $css .= "#themelab_header_utilities #search_form:focus-within{background:{$header_utility_surface_hover}!important;border-color:{$header_utility_border}!important}\n";
    $css .= "@media(max-width:900px){#themelab_header_utilities{font-size:12px!important}#themelab_header_utilities #search_form{width:118px!important}}\n";
    $css .= "@media(max-width:640px){#themelab_header_utilities{gap:5px!important;padding-top:6px!important}#themelab_header_utilities #search_form{width:126px!important}#themelab_header_utilities #top_info>li>a{height:25px!important}}\n";
    /* 1.0.1-dev14h: mobile utility + primary-nav authority.
     *
     * Do not depend on theme-specific li IDs for PM/Alerts. Select the actual
     * semantic destinations so this survives SMF theme markup differences.
     * Also stop desktop header-button colors from bleaching the SMF mobile menu.
     */
    $css .= "@media(max-width:720px){"
        /* Messages / Alerts: always retain a recognizable icon. */
        . "#themelab_header_utilities #top_info a[href*='action=pm'],"
        . "#themelab_header_utilities #top_info a[href*='area=showalerts']"
        . "{min-width:30px!important;padding-left:7px!important;padding-right:7px!important;gap:4px!important}"
        . "#themelab_header_utilities #top_info a[href*='action=pm']::before,"
        . "#themelab_header_utilities #top_info a[href*='area=showalerts']::before"
        . "{content:''!important;display:inline-block!important;flex:0 0 14px!important;width:14px!important;height:14px!important;background-color:currentColor!important}"
        . "#themelab_header_utilities #top_info a[href*='action=pm']::before"
        . "{-webkit-mask:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23000' d='M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 2 9 6 9-6H3Zm18 10V9.4l-8.45 5.63a1 1 0 0 1-1.1 0L3 9.4V17h18Z'/%3E%3C/svg%3E\") center/contain no-repeat!important;mask:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23000' d='M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 2 9 6 9-6H3Zm18 10V9.4l-8.45 5.63a1 1 0 0 1-1.1 0L3 9.4V17h18Z'/%3E%3C/svg%3E\") center/contain no-repeat!important}"
        . "#themelab_header_utilities #top_info a[href*='area=showalerts']::before"
        . "{-webkit-mask:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23000' d='M12 2a6 6 0 0 0-6 6v3.6L4.3 15a1 1 0 0 0 .9 1.45h13.6a1 1 0 0 0 .9-1.45L18 11.6V8a6 6 0 0 0-6-6Zm0 20a3 3 0 0 0 2.82-2H9.18A3 3 0 0 0 12 22Z'/%3E%3C/svg%3E\") center/contain no-repeat!important;mask:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23000' d='M12 2a6 6 0 0 0-6 6v3.6L4.3 15a1 1 0 0 0 .9 1.45h13.6a1 1 0 0 0 .9-1.45L18 11.6V8a6 6 0 0 0-6-6Zm0 20a3 3 0 0 0 2.82-2H9.18A3 3 0 0 0 12 22Z'/%3E%3C/svg%3E\") center/contain no-repeat!important}"
        . "#themelab_header_utilities #top_info a[href*='action=pm']>.amt,"
        . "#themelab_header_utilities #top_info a[href*='area=showalerts']>.amt"
        . "{display:inline-flex!important;margin-left:1px!important}"

        /* Mobile primary menu: own the panel and readable inactive state. */
        . "#main_menu{box-sizing:border-box!important;width:100%!important;margin:0!important;padding:0!important;background:var(--tl-surface)!important;border:1px solid var(--tl-border)!important;border-radius:0 0 var(--tl-radius) var(--tl-radius)!important;box-shadow:0 4px 12px rgba(0,0,0,.14)!important;overflow:visible!important}"
        . "#main_menu>.dropmenu,#main_menu .menu_nav{display:block!important;width:100%!important;margin:0!important;padding:6px!important;background:var(--tl-surface)!important;box-sizing:border-box!important}"
        . "#main_menu>.dropmenu>li,#main_menu .menu_nav>li{display:block!important;width:100%!important;margin:2px 0!important;background:transparent!important}"
        . "#main_menu>.dropmenu>li>a,#main_menu .menu_nav>li>a"
        . "{box-sizing:border-box!important;display:flex!important;align-items:center!important;width:100%!important;min-height:38px!important;margin:0!important;padding:8px 12px!important;background:transparent!important;background-image:none!important;color:var(--tl-text)!important;-webkit-text-fill-color:var(--tl-text)!important;border:0!important;border-radius:6px!important;text-shadow:none!important;box-shadow:none!important;opacity:1!important}"
        . "#main_menu>.dropmenu>li>a *,#main_menu .menu_nav>li>a *{color:inherit!important;-webkit-text-fill-color:currentColor!important;opacity:1!important}"
        . "#main_menu>.dropmenu>li:hover>a,#main_menu>.dropmenu>li>a:focus,#main_menu .menu_nav>li:hover>a,#main_menu .menu_nav>li>a:focus"
        . "{background:var(--tl-surface-alt)!important;color:var(--tl-link)!important;-webkit-text-fill-color:var(--tl-link)!important}"
        . "#main_menu>.dropmenu>li>a.active,#main_menu>.dropmenu>li.current>a,#main_menu>.dropmenu>li.active>a,"
        . "#main_menu .menu_nav>li>a.active,#main_menu .menu_nav>li.current>a,#main_menu .menu_nav>li.active>a"
        . "{background:var(--tl-button-header-current-background)!important;color:var(--tl-button-header-current-text)!important;-webkit-text-fill-color:var(--tl-button-header-current-text)!important}"
        /* Let mobile submenus participate in normal flow instead of desktop absolute stacking. */
        . "#main_menu .dropmenu>li>ul,#main_menu .dropmenu>li>ul ul"
        . "{position:static!important;width:100%!important;max-width:none!important;margin:2px 0 4px!important;padding:4px!important;background:var(--tl-surface-alt)!important;border:1px solid var(--tl-border)!important;border-radius:6px!important;box-shadow:none!important;overflow:visible!important}"
        . "#main_menu .dropmenu>li>ul>li,#main_menu .dropmenu>li>ul>li>a"
        . "{background:transparent!important;color:var(--tl-text)!important;-webkit-text-fill-color:var(--tl-text)!important;opacity:1!important}"
        . "}\\n";


	/* RC6.1.21dl: final integrated-header dropdown + guest welcome polish. */


	/* RC6.1.21dl: live-selector authority for Integrated Header Utilities.
	 * The runtime marker is #header.themelab_integrated_utilities. Previous dg
	 * rules incorrectly targeted a body class that is never created. */
	/* 1.0.1-dev3h: the header itself must sit above forum action bars. A child
	 * dropdown cannot escape a lower parent stacking context merely by increasing
	 * its own z-index. */
	$css .= "#header.themelab_integrated_utilities{overflow:visible!important;z-index:2000!important}\n";
	$css .= "#header.themelab_integrated_utilities #main_menu{position:relative!important;z-index:2200!important;overflow:visible!important}\n";
	$css .= "#header.themelab_integrated_utilities::before,#header.themelab_integrated_utilities::after{border-radius:inherit!important}\n";
	$css .= "#header.themelab_integrated_utilities .inner_wrap,#header.themelab_integrated_utilities #themelab_header_utilities{overflow:visible!important}\n";
	$css .= "#header.themelab_integrated_utilities #themelab_header_utilities{position:relative!important;z-index:2300!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info{position:relative!important;z-index:2310!important;overflow:visible!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info>li{position:relative!important;overflow:visible!important;color:{$header_utility_text}!important;text-shadow:0 1px 2px rgba(0,0,0,.45)!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info>li:hover,#header.themelab_integrated_utilities #top_info>li:focus-within{z-index:2320!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info #profile_menu,#header.themelab_integrated_utilities #top_info #pm_menu,#header.themelab_integrated_utilities #top_info #alerts_menu{position:absolute!important;z-index:2330!important;border-radius:10px!important;box-shadow:0 10px 28px rgba(0,0,0,.28)!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info #profile_menu{overflow:visible!important;height:auto!important;max-height:none!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info #pm_menu,#header.themelab_integrated_utilities #top_info #alerts_menu{overflow:hidden!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info #pm_menu>.viewport,#header.themelab_integrated_utilities #top_info #alerts_menu>.viewport{border-radius:inherit!important;overflow:hidden!important}\n";
	/* 1.0.1 release: utility popups must overlay the main navigation without
	 * depending on the runtime integrated-header marker. Keep every relevant
	 * ancestor overflow-visible, then use a small, predictable stacking ladder. */
	$css .= "#header,#top_section,#upper_section,#top_section .inner_wrap,#upper_section .inner_wrap,#top_info,#top_info>li{overflow:visible!important}\n";
	$css .= "#top_section,#top_info{position:relative!important;z-index:30!important}\n";
	$css .= "#top_info>li{position:relative!important}#top_info>li:hover,#top_info>li:focus-within{z-index:40!important}\n";
	$css .= "#profile_menu,#pm_menu,#alerts_menu{position:absolute!important;z-index:60!important}\n";
	$css .= "#header.themelab_integrated_utilities #profile_menu{overflow:visible!important;height:auto!important;max-height:none!important}\n";

	$css .= "#header.themelab_integrated_utilities #top_info>li:not(:has(>a)){display:inline-flex!important;align-items:center!important;min-height:25px!important;padding:5px 8px!important;border:1px solid {$header_utility_border}!important;border-radius:999px!important;background:{$header_utility_surface}!important;color:{$header_utility_text}!important;font-size:12px!important;font-weight:600!important;line-height:13px!important;text-shadow:0 1px 1px rgba(0,0,0,.28)!important;backdrop-filter:blur(4px)!important;-webkit-backdrop-filter:blur(4px)!important}\n";




	/* RC6.1.21dl: guest welcome text-flow authority.
	 * The logged-out welcome entry is mixed text/nested markup, not a clickable
	 * site-name link. Keep normal inline text flow inside the capsule so native
	 * whitespace between "Welcome to", the forum name and punctuation survives. */
	$css .= "#header.themelab_integrated_utilities #top_info>li:not(:has(>a)){display:inline-block!important;white-space:normal!important;word-spacing:normal!important;letter-spacing:normal!important;vertical-align:middle!important;color:{$header_utility_text}!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info>li:not(:has(>a))>*{display:inline!important;float:none!important;margin:0!important;padding:0!important;color:inherit!important;opacity:1!important;text-shadow:inherit!important;white-space:inherit!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info>li:not(:has(>a))>*+*{margin-left:.25em!important}\n";


	/* RC6.1.21dl: PM/Alerts Typography + native SMF login overlay parity.
	 *
	 * PM/Alerts are AJAX-populated .top_menu scrollables whose nested content
	 * otherwise falls back to Curve2's relative menu/smalltext sizing. Give
	 * those two popup bodies the exact Typography Studio base size/family.
	 *
	 * reqOverlayDiv() appends #smf_popup directly to <body>, outside the
	 * integrated header. It therefore needs its own z-index and theme surface
	 * authority instead of relying on header-scoped rules. */
	$popup_base_font = max(10, min(22, (int) $v('font_size', 14)));
	$popup_family = trim((string) $v('font_family', 'Verdana, Geneva, sans-serif'));
	if ($popup_family === '')
		$popup_family = 'Verdana, Geneva, sans-serif';
	$popup_radius = max(0, (int) $v('border_radius', 6));

	$css .= "#header.themelab_integrated_utilities #pm_menu,#header.themelab_integrated_utilities #alerts_menu{font-family:{$popup_family}!important;font-size:{$popup_base_font}px!important;line-height:var(--tl-line-height)!important}\n";
	$css .= "#header.themelab_integrated_utilities #pm_menu .viewport,#header.themelab_integrated_utilities #pm_menu .overview,#header.themelab_integrated_utilities #alerts_menu .viewport,#header.themelab_integrated_utilities #alerts_menu .overview{font:inherit!important;line-height:inherit!important}\n";
	$css .= "#header.themelab_integrated_utilities #pm_menu .block,#header.themelab_integrated_utilities #pm_menu .pm_bar,#header.themelab_integrated_utilities #pm_menu .pm_sending,#header.themelab_integrated_utilities #pm_menu .pm_mailbox,#header.themelab_integrated_utilities #pm_menu .pm_unread,#header.themelab_integrated_utilities #pm_menu .unread_notify,#header.themelab_integrated_utilities #pm_menu .details,#header.themelab_integrated_utilities #pm_menu a,#header.themelab_integrated_utilities #pm_menu span,#header.themelab_integrated_utilities #pm_menu strong,#header.themelab_integrated_utilities #alerts_menu .block,#header.themelab_integrated_utilities #alerts_menu .alert_bar,#header.themelab_integrated_utilities #alerts_menu .alerts_opts,#header.themelab_integrated_utilities #alerts_menu .alerts_box,#header.themelab_integrated_utilities #alerts_menu .alerts_unread,#header.themelab_integrated_utilities #alerts_menu .unread_notify,#header.themelab_integrated_utilities #alerts_menu .details,#header.themelab_integrated_utilities #alerts_menu a,#header.themelab_integrated_utilities #alerts_menu span,#header.themelab_integrated_utilities #alerts_menu strong{font-family:inherit!important;font-size:inherit!important;line-height:inherit!important}\n";
	$css .= "#header.themelab_integrated_utilities #pm_menu .smalltext,#header.themelab_integrated_utilities #alerts_menu .smalltext{font-family:inherit!important;font-size:.9em!important;line-height:1.35!important}\n";

	$css .= "#smf_popup.popup_container{z-index:30000!important}\n";
	$css .= "#smf_popup>.popup_window{box-sizing:border-box!important;max-width:min(520px,calc(100vw - 28px))!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:{$popup_radius}px!important;box-shadow:0 18px 48px rgba(0,0,0,.38)!important;overflow:hidden!important;font-family:{$popup_family}!important;font-size:{$popup_base_font}px!important;line-height:var(--tl-line-height)!important}\n";
	$css .= "#smf_popup .popup_heading{box-sizing:border-box!important;margin:0!important;padding:10px 14px!important;background:var(--themelab-card-header-background,var(--tl-primary))!important;color:var(--tl-card-header-text,var(--tl-button-primary-text,#fff))!important;border:0!important;border-bottom:1px solid var(--tl-border)!important;border-radius:{$popup_radius}px {$popup_radius}px 0 0!important;font-family:inherit!important;font-size:1.08em!important;font-weight:700!important;line-height:1.25!important;text-shadow:none!important}\n";
	$css .= "#smf_popup .popup_heading .main_icons,#smf_popup .popup_heading .hide_popup{color:inherit!important;opacity:.9!important}\n";
	$css .= "#smf_popup .popup_content{box-sizing:border-box!important;padding:14px 16px 16px!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:0!important;font:inherit!important;line-height:inherit!important}\n";
	$css .= "#smf_popup .popup_content .login,#smf_popup .popup_content form,#smf_popup .popup_content dl,#smf_popup .popup_content dt,#smf_popup .popup_content dd,#smf_popup .popup_content label,#smf_popup .popup_content p,#smf_popup .popup_content span,#smf_popup .popup_content strong,#smf_popup .popup_content a{font-family:inherit!important;color:var(--tl-text)!important;line-height:inherit!important}\n";
	$css .= "#smf_popup .popup_content a{color:var(--tl-link)!important}#smf_popup .popup_content a:hover{color:var(--tl-link-hover,var(--tl-primary))!important}\n";
	$css .= "#smf_popup .popup_content input[type=text],#smf_popup .popup_content input[type=password],#smf_popup .popup_content input[type=email],#smf_popup .popup_content select{box-sizing:border-box!important;background:var(--tl-surface-alt)!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important;border-radius:var(--tl-forms-border-radius,4px)!important;font-family:inherit!important;font-size:1em!important}\n";
	$css .= "#smf_popup .popup_content input::placeholder{color:var(--tl-muted-text)!important;opacity:1!important}\n";
	$css .= "#smf_popup .popup_content .button,#smf_popup .popup_content .button_submit,#smf_popup .popup_content input[type=submit]{font-family:inherit!important;font-size:1em!important}\n";
	$css .= "@media(max-width:640px){#smf_popup>.popup_window{width:calc(100vw - 20px)!important;max-width:calc(100vw - 20px)!important}#smf_popup .popup_content{padding:12px!important}}\n";

	/* 1.0.1-dev3f: SMF mobile User actions / Moderation popups must own the top stacking layer.
	 * Topic quickbuttons and their More dropdowns can reach z-index 999, while Curve2 leaves
	 * .popup_container at z-index 6. Keep these full-screen mobile dialogs above topic controls.
	 * Also give wrapped action buttons a real row/column gap so a second row never crowds
	 * the first on narrow screens. */
	$css .= "@media(max-width:720px){#mobile_action.popup_container,#mobile_moderation.popup_container{z-index:40000!important}#mobile_action>.popup_window,#mobile_moderation>.popup_window{position:relative!important;z-index:40001!important}#mobile_action .buttonlist,#mobile_moderation .buttonlist{box-sizing:border-box!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;flex-wrap:wrap!important;gap:6px!important;width:100%!important;margin:0!important;padding:6px!important}#mobile_action .buttonlist>.button,#mobile_moderation .buttonlist>.button{flex:0 1 auto!important;width:auto!important;margin:0!important}}\n";


	/* RC6.1.21dl: final semantic Secondary-button foreground authority.
	 * These stock SMF action controls are Secondary unless explicitly promoted
	 * to Primary/Danger. Descendants inherit the selected Secondary Text color. */
	$css .= ".button.secondary,.buttonlist a:not(.active):not(.button_strip_reply):not(.button_strip_new_topic),.quickbuttons a,.moderation a,.pagesection a.button:not(.primary):not(.danger):not(.red),.pagesection .button:not(.primary):not(.danger):not(.red){color:var(--tl-button-secondary-text,#fff)!important}\n";
	$css .= ".button.secondary>*,.buttonlist a:not(.active):not(.button_strip_reply):not(.button_strip_new_topic)>*,.quickbuttons a>*,.moderation a>*,.pagesection a.button:not(.primary):not(.danger):not(.red)>*,.pagesection .button:not(.primary):not(.danger):not(.red)>*{color:inherit!important;text-shadow:inherit!important}\n";

	/* 1.0.1-dev8: final composer popup stacking authority.
	 * Posting-field autocompletes and suggestion panels live inside #post_header,
	 * while SCEditor is a later sibling. Keep the entire composer chain unclipped
	 * and make the posting header own the higher local stacking layer. */
	$css .= "form#postmodify>#post_area,form#postmodify>#post_area>.roundframe,form#postmodify>#post_area>.roundframe>#post_header{overflow:visible!important}\n";
	$css .= "form#postmodify>#post_area>.roundframe{position:relative!important}\n";
	$css .= "form#postmodify>#post_area>.roundframe>#post_header{position:relative!important;z-index:5000!important}\n";
	$css .= "form#postmodify>#post_area>.roundframe>#post_header>dd{position:relative!important;overflow:visible!important}\n";
	$css .= "form#postmodify>#post_area>.roundframe>#post_header>dd:focus-within{z-index:5100!important}\n";
	$css .= "form#postmodify>#post_area>.roundframe .sceditor-container{position:relative!important;z-index:1!important}\n";
	$css .= "form#postmodify>#post_area>.roundframe #post_header .better_tags_suggestions{z-index:5200!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;opacity:1!important}\n";


	/* 1.0.1-dev13e: TRUE final global navigation stacking authority.
	 * There are two independent menu systems in the generated theme:
	 *
	 *   1) Primary navigation in #upper_section / #main_menu.
	 *   2) SMF account, PM and alert menus in #header / #top_info.
	 *
	 * dev12 correctly raised the primary navigation above admin secondary
	 * menus, but its 6000-level stack unintentionally outranked the utility
	 * menus that were still living around z-index 2300. The lower halves of
	 * Profile, Messages and Alerts were therefore painted behind the section
	 * below the header and looked "clipped".
	 *
	 * Own BOTH stacks here, after every other generated rule:
	 *   page/admin content    < 6000
	 *   primary navigation   = 6000-6040
	 *   header utilities     = 7000-7040
	 */
	$css .= "#upper_section{position:relative!important;z-index:6000!important;overflow:visible!important;isolation:auto!important}\n";
	$css .= "#upper_section .inner_wrap{position:relative!important;z-index:auto!important;overflow:visible!important}\n";
	$css .= "#main_menu{position:relative!important;z-index:6010!important;overflow:visible!important;isolation:auto!important}\n";
	$css .= "#main_menu>.dropmenu,#main_menu .menu_nav{position:relative!important;z-index:6020!important;overflow:visible!important}\n";
	$css .= "#main_menu .dropmenu>li{position:relative!important;overflow:visible!important}\n";
	$css .= "#main_menu .dropmenu>li:hover,#main_menu .dropmenu>li:focus-within,#main_menu .dropmenu>li.open{z-index:6030!important}\n";
	$css .= "#main_menu .dropmenu>li>ul,#main_menu .dropmenu>li>ul ul{position:absolute!important;z-index:6040!important;background:var(--tl-surface)!important;background-image:none!important;opacity:1!important;overflow:visible!important;box-shadow:0 5px 16px rgba(0,0,0,.28)!important}\n";
	$css .= "#main_menu .dropmenu>li>ul>li,#main_menu .dropmenu>li>ul>li>a{background:var(--tl-surface)!important;background-image:none!important;opacity:1!important}\n";

	/* Header utility menus must outrank the entire primary-navigation stack. */
	$css .= "#header.themelab_integrated_utilities{position:relative!important;z-index:7000!important;overflow:visible!important;isolation:isolate!important}\n";
	$css .= "#header.themelab_integrated_utilities .inner_wrap{overflow:visible!important}\n";
	$css .= "#header.themelab_integrated_utilities #themelab_header_utilities{position:relative!important;z-index:7010!important;overflow:visible!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info{position:relative!important;z-index:7020!important;overflow:visible!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info>li{position:relative!important;overflow:visible!important}\n";
	$css .= "#header.themelab_integrated_utilities #top_info>li:hover,#header.themelab_integrated_utilities #top_info>li:focus-within{z-index:7030!important}\n";

	/* 1.0.1-dev14k: mobile popup height repair.
	 * dev14j lifted the menus correctly, but position:relative made SMF's overlay
	 * participate in document flow, creating a page-tall empty cell. Keep the
	 * overlay fixed to the viewport and let only the popup window size to content.
	 */
	$css .= "@media(max-width:720px){"
		. ".popup_container{position:fixed!important;inset:0!important;width:100vw!important;height:100dvh!important;min-height:0!important;max-height:100dvh!important;margin:0!important;padding:0!important;background:transparent!important;overflow:visible!important}"
		. ".popup_container>.popup_window,.popup_container .popup_window{height:auto!important;min-height:0!important;max-height:calc(100dvh - 20px)!important;overflow:auto!important}"
		. ".popup_container .popup_content,.popup_container .generic_menu,.popup_container .dropmenu{height:auto!important;min-height:0!important;max-height:none!important}"
		. "}\\n";

	/* 1.0.1-dev14j: TRUE mobile popup shell + stacking authority.
	 *
	 * The remaining issue is not the list rows. SMF can place its responsive
	 * popup container under different ancestors depending on which menu opens,
	 * so direct-child selectors like body>.popup_container miss real cases.
	 * Target every mobile popup shell regardless of ancestry and lift any menu
	 * ancestor that owns one above Theme Lab's integrated header stack.
	 */
	$css .= "@media(max-width:720px){"
		/* Any responsive popup must outrank the integrated header while open. */
		. ".popup_container{position:fixed!important;inset:0!important;width:100vw!important;height:100dvh!important;min-height:0!important;margin:0!important;z-index:2147483000!important;isolation:isolate!important;overflow:visible!important;background:transparent!important}"
		. ".popup_container>.popup_window,.popup_container .popup_window{z-index:2147483001!important;box-sizing:border-box!important;height:auto!important;min-height:0!important;max-height:calc(100dvh - 20px)!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:8px!important;box-shadow:0 16px 42px rgba(0,0,0,.45)!important;overflow:auto!important}"
		/* If SMF nests the popup inside a menu stacking context, lift that owner too. */
		. "#main_menu:has(.popup_container),#adm_submenus:has(.popup_container),.generic_menu:has(.popup_container),#upper_section:has(.popup_container)"
		. "{position:relative!important;z-index:2147482000!important;isolation:auto!important;overflow:visible!important}"
		/* Main Menu/Admin Menu header strip must never fall back to Curve white. */
		. ".popup_container .popup_heading,.popup_container .title_bar,.popup_container .cat_bar,.popup_container .sub_bar"
		. "{box-sizing:border-box!important;margin:0!important;padding:9px 12px!important;background:var(--themelab-card-header-background,var(--tl-primary))!important;background-image:none!important;color:var(--tl-card-header-text,var(--tl-button-primary-text,#fff))!important;-webkit-text-fill-color:currentColor!important;border:0!important;border-bottom:1px solid var(--tl-border)!important;border-radius:0!important;text-shadow:none!important;box-shadow:none!important;font-weight:700!important;opacity:1!important}"
		. ".popup_container .popup_heading *,.popup_container .title_bar *,.popup_container .cat_bar *,.popup_container .sub_bar *"
		. "{color:inherit!important;-webkit-text-fill-color:currentColor!important;opacity:1!important;text-shadow:none!important}"
		/* Admin popup body/list: kill all white fallbacks and inherit Theme Lab. */
		. ".popup_container .popup_content,.popup_container .generic_menu,.popup_container .generic_menu>.dropmenu,.popup_container .dropmenu"
		. "{box-sizing:border-box!important;background:var(--tl-surface)!important;background-image:none!important;color:var(--tl-text)!important;border-color:var(--tl-border)!important;opacity:1!important}"
		. ".popup_container .generic_menu .dropmenu>li,.popup_container .dropmenu>li"
		. "{background:transparent!important;background-image:none!important;opacity:1!important}"
		. ".popup_container .generic_menu .dropmenu>li>a,.popup_container .dropmenu>li>a"
		. "{background:transparent!important;background-image:none!important;color:var(--tl-text)!important;-webkit-text-fill-color:var(--tl-text)!important;border-color:transparent!important;text-shadow:none!important;opacity:1!important}"
		. ".popup_container .generic_menu .dropmenu>li>a *, .popup_container .dropmenu>li>a *"
		. "{color:inherit!important;-webkit-text-fill-color:currentColor!important;opacity:1!important}"
		. ".popup_container .generic_menu .dropmenu>li:hover>a,.popup_container .generic_menu .dropmenu>li>a:focus,"
		. ".popup_container .dropmenu>li:hover>a,.popup_container .dropmenu>li>a:focus"
		. "{background:var(--tl-surface-alt)!important;color:var(--tl-link)!important;-webkit-text-fill-color:var(--tl-link)!important}"
		. ".popup_container .generic_menu .dropmenu>li>a.active,.popup_container .generic_menu .dropmenu>li>a.chosen,"
		. ".popup_container .dropmenu>li>a.active,.popup_container .dropmenu>li.current>a"
		. "{background:var(--tl-button-header-current-background)!important;color:var(--tl-button-header-current-text)!important;-webkit-text-fill-color:var(--tl-button-header-current-text)!important}"
		/* Close control must remain visible on themed heading. */
		. ".popup_container .hide_popup,.popup_container .popup_heading .main_icons"
		. "{color:inherit!important;-webkit-text-fill-color:currentColor!important;opacity:1!important;filter:none!important}"
		. "}\\n";

	/* dev14h final mobile override must come after desktop/global stacking authority. */
	$css .= "@media(max-width:720px){"
		. "#main_menu .dropmenu>li>ul,#main_menu .dropmenu>li>ul ul{position:static!important;inset:auto!important;z-index:auto!important;width:100%!important;max-width:none!important;background:var(--tl-surface-alt)!important;box-shadow:none!important}"
		. "#main_menu .dropmenu>li>ul>li,#main_menu .dropmenu>li>ul>li>a{background:transparent!important;background-image:none!important;color:var(--tl-text)!important;-webkit-text-fill-color:var(--tl-text)!important;opacity:1!important}"
		. "}\\n";

	/* 1.0.1-dev14i: respect SMF's mobile menu state machine.
	 *
	 * The original desktop menu trees must remain hidden on mobile. SMF opens
	 * cloned/copy menu content inside its popup container. Styling the source
	 * tree with display:block/flex created the duplicated/half-open menus seen
	 * in dev14h.
	 */
	$css .= "@media(max-width:720px){"
		/* Hide original desktop trees; the hamburger/popup copy is authoritative. */
		. "#main_menu>.dropmenu,#main_menu>.menu_nav{display:none!important}"
		. "#adm_submenus>.generic_menu>.dropmenu,#adm_submenus>.generic_menu>.menu_nav{display:none!important}"

		/* Mobile popup shell: theme-aware and readable for Main/Admin menus. */
		. "body>.popup_container{position:fixed!important;inset:0!important;width:100vw!important;height:100dvh!important;min-height:0!important;margin:0!important;z-index:2147483000!important;background:transparent!important;overflow:visible!important}"
		. "body>.popup_container>.popup_window{box-sizing:border-box!important;width:calc(100vw - 20px)!important;max-width:420px!important;height:auto!important;min-height:0!important;max-height:calc(100dvh - 20px)!important;background:var(--tl-surface)!important;color:var(--tl-text)!important;border:1px solid var(--tl-border)!important;border-radius:8px!important;box-shadow:0 14px 38px rgba(0,0,0,.42)!important;overflow:auto!important}"
		. "body>.popup_container>.popup_window>.popup_heading,"
		. "body>.popup_container>.popup_window .popup_heading"
		. "{box-sizing:border-box!important;margin:0!important;padding:9px 12px!important;background:var(--themelab-card-header-background,var(--tl-primary))!important;color:var(--tl-card-header-text,var(--tl-button-primary-text,#fff))!important;-webkit-text-fill-color:currentColor!important;border:0!important;border-bottom:1px solid var(--tl-border)!important;text-shadow:none!important;font-weight:700!important}"
		. "body>.popup_container>.popup_window>.popup_content,"
		. "body>.popup_container>.popup_window .popup_content"
		. "{box-sizing:border-box!important;padding:8px!important;background:var(--tl-surface)!important;color:var(--tl-text)!important}"

		/* Main/Admin menu lists inside the popup. */
		. "body>.popup_container .generic_menu,"
		. "body>.popup_container .generic_menu>.dropmenu,"
		. "body>.popup_container .dropmenu"
		. "{box-sizing:border-box!important;display:block!important;position:static!important;width:100%!important;margin:0!important;padding:4px!important;background:var(--tl-surface)!important;border:0!important;box-shadow:none!important;overflow:visible!important}"
		. "body>.popup_container .generic_menu .dropmenu>li,"
		. "body>.popup_container .dropmenu>li"
		. "{display:block!important;position:relative!important;width:100%!important;margin:2px 0!important;padding:0!important;background:transparent!important}"
		. "body>.popup_container .generic_menu .dropmenu>li>a,"
		. "body>.popup_container .dropmenu>li>a"
		. "{box-sizing:border-box!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:7px!important;width:100%!important;min-height:36px!important;margin:0!important;padding:8px 10px!important;background:transparent!important;background-image:none!important;color:var(--tl-text)!important;-webkit-text-fill-color:var(--tl-text)!important;border:0!important;border-radius:6px!important;box-shadow:none!important;text-shadow:none!important;opacity:1!important;white-space:normal!important}"
		. "body>.popup_container .generic_menu .dropmenu>li>a *,"
		. "body>.popup_container .dropmenu>li>a *"
		. "{color:inherit!important;-webkit-text-fill-color:currentColor!important;opacity:1!important}"
		. "body>.popup_container .generic_menu .dropmenu>li:hover>a,"
		. "body>.popup_container .generic_menu .dropmenu>li>a:focus,"
		. "body>.popup_container .dropmenu>li:hover>a,"
		. "body>.popup_container .dropmenu>li>a:focus"
		. "{background:var(--tl-surface-alt)!important;color:var(--tl-link)!important;-webkit-text-fill-color:var(--tl-link)!important}"
		. "body>.popup_container .generic_menu .dropmenu>li>a.active,"
		. "body>.popup_container .generic_menu .dropmenu>li>a.chosen,"
		. "body>.popup_container .dropmenu>li>a.active,"
		. "body>.popup_container .dropmenu>li.current>a"
		. "{background:var(--tl-button-header-current-background)!important;color:var(--tl-button-header-current-text)!important;-webkit-text-fill-color:var(--tl-button-header-current-text)!important}"

		/* Nested admin/main children stay in normal flow inside the popup. */
		. "body>.popup_container .dropmenu>li>ul,"
		. "body>.popup_container .dropmenu>li>ul ul"
		. "{display:block!important;position:static!important;inset:auto!important;width:100%!important;max-width:none!important;margin:2px 0 4px!important;padding:3px 0 3px 14px!important;background:var(--tl-surface-alt)!important;border:0!important;border-left:2px solid var(--tl-border)!important;border-radius:0!important;box-shadow:none!important}"
		. "body>.popup_container .dropmenu>li>ul>li>a"
		. "{background:transparent!important;color:var(--tl-text)!important;-webkit-text-fill-color:var(--tl-text)!important}"

		/* Close icon remains visible against any header color. */
		. "body>.popup_container .hide_popup{color:inherit!important;-webkit-text-fill-color:currentColor!important;opacity:1!important}"
		. "}\\n";
	$css .= "#header.themelab_integrated_utilities #profile_menu,#header.themelab_integrated_utilities #pm_menu,#header.themelab_integrated_utilities #alerts_menu{position:absolute!important;z-index:7040!important}\n";

	return $css;
}


/**
 * RC6.1.21cm — Modern Board Index stats card.
 *
 * Replace stock Curve2's plain "Posts / Topics" paragraph with semantic
 * stat items so the generated theme can render the clean Card Style #1.
 */
function themelab_publish_patch_boardindex_stats($path)
{
	if (!is_file($path))
		return false;

	$content = @file_get_contents($path);
	if ($content === false)
		return false;

	if (strpos($content, 'themelab_board_stats_card') !== false)
		return true;

	$pattern = '~function\s+template_bi_board_stats\s*\(\s*\$board\s*\)\s*\{.*?\n\}\s*(?=\n/\*\*|\nfunction\s+template_bi_redirect_stats)~s';

	$replacement = <<<'PHP'
function template_bi_board_stats($board)
{
	global $txt;

	echo '
	<div class="themelab_board_stats_card">
		<div class="themelab_board_stat themelab_board_stat_posts">
			<span class="themelab_board_stat_icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" focusable="false">
					<path d="M4.5 5.5h15v10h-8.2L6 19.2v-3.7H4.5z"></path>
					<path d="M8 9.5h.01M12 9.5h.01M16 9.5h.01"></path>
				</svg>
			</span>
			<span class="themelab_board_stat_copy">
				<span class="themelab_board_stat_label">', $txt['posts'], '</span>
				<strong class="themelab_board_stat_value">', comma_format($board['posts']), '</strong>
			</span>
		</div>
		<span class="themelab_board_stat_divider" aria-hidden="true"></span>
		<div class="themelab_board_stat themelab_board_stat_topics">
			<span class="themelab_board_stat_icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" focusable="false">
					<path d="M7 3.5h8l4 4v13H7z"></path>
					<path d="M15 3.5v4h4M10 11h6M10 14.5h6M10 18h4"></path>
				</svg>
			</span>
			<span class="themelab_board_stat_copy">
				<span class="themelab_board_stat_label">', $txt['board_topics'], '</span>
				<strong class="themelab_board_stat_value">', comma_format($board['topics']), '</strong>
			</span>
		</div>
	</div>';
}
PHP;

	$patched = preg_replace($pattern, $replacement . "\n", $content, 1, $count);
	if ($patched === null || $count !== 1)
		return false;

	return @file_put_contents($path, $patched) !== false;
}


function themelab_publish_patch_genericlist_packages($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	/* Already patched by a previous publish pass. */
	if (strpos($source, 'themelab_package_list_body') !== false)
		return true;

	/* Package Manager uses list ids like packages_lists_modification. Keep the
	 * structural wrapper strictly limited to those lists so every other
	 * GenericList consumer remains stock SMF. */
	$cur_list_needle = "\t\$cur_list = &\$context[\$list_id];";
	$cur_list_replacement = $cur_list_needle . "\n\n\t\$themelab_package_list = strpos(\$list_id, 'packages_lists_') === 0 && !empty(\$cur_list['title']);";
	if (strpos($source, $cur_list_needle) === false)
		return false;
	$source = str_replace($cur_list_needle, $cur_list_replacement, $source, $cur_count);
	if ($cur_count !== 1)
		return false;

	/* Open a genuine body immediately after the GenericList title bar. The 10px
	 * breathing space is padding on this body, so its left/right borders remain
	 * continuous from the main header down to the table. */
	$title_needle = <<<'TL_GENERIC_TITLE'
		</div>';

	if (isset($cur_list['additional_rows']['after_title']))
TL_GENERIC_TITLE;
	$title_replacement = <<<'TL_GENERIC_TITLE_REPLACEMENT'
		</div>';

	if ($themelab_package_list)
		echo '
		<div class="themelab_package_list_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_GENERIC_TITLE_REPLACEMENT;
	if (strpos($source, $title_needle) === false)
		return false;
	$source = str_replace($title_needle, $title_replacement, $source, $title_count);
	if ($title_count !== 1)
		return false;

	/* Close the owned body directly after the table. Any GenericList controls
	 * emitted below the table remain outside, matching stock behavior. */
	$table_needle = <<<'TL_GENERIC_TABLE'
		</table>';

	if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['below_table_data']))
TL_GENERIC_TABLE;
	$table_replacement = <<<'TL_GENERIC_TABLE_REPLACEMENT'
		</table>';

	if ($themelab_package_list)
		echo '
		</div><!-- .themelab_package_list_body -->';

	if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['below_table_data']))
TL_GENERIC_TABLE_REPLACEMENT;
	if (strpos($source, $table_needle) === false)
		return false;
	$source = str_replace($table_needle, $table_replacement, $source, $table_count);
	if ($table_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_genericlist_profile_fields($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_profile_fields_body') !== false)
		return true;

	$cur_list_needle = "\t\$cur_list = &\$context[\$list_id];";
	$cur_list_replacement = $cur_list_needle . "\n\n\t\$themelab_profile_fields_list = in_array(\$list_id, array('standard_profile_fields', 'custom_profile_fields'), true) && !empty(\$cur_list['title']);";
	if (strpos($source, $cur_list_needle) === false)
		return false;
	$source = str_replace($cur_list_needle, $cur_list_replacement, $source, $cur_count);
	if ($cur_count !== 1)
		return false;

	$title_needle = <<<'TL_PROFILE_FIELDS_TITLE'
		</div>';

	if ($themelab_package_list)
		echo '
		<div class="themelab_package_list_body">';
TL_PROFILE_FIELDS_TITLE;
	$title_replacement = <<<'TL_PROFILE_FIELDS_TITLE_REPLACEMENT'
		</div>';

	if ($themelab_package_list)
		echo '
		<div class="themelab_package_list_body">';

	if ($themelab_profile_fields_list)
		echo '
		<div class="themelab_profile_fields_body">';
TL_PROFILE_FIELDS_TITLE_REPLACEMENT;
	if (strpos($source, $title_needle) === false)
		return false;
	$source = str_replace($title_needle, $title_replacement, $source, $title_count);
	if ($title_count !== 1)
		return false;

	$close_needle = <<<'TL_PROFILE_FIELDS_CLOSE'
	if (isset($cur_list['additional_rows']['bottom_of_list']))
		template_additional_rows('bottom_of_list', $cur_list);

	if (isset($cur_list['form']))
TL_PROFILE_FIELDS_CLOSE;
	$close_replacement = <<<'TL_PROFILE_FIELDS_CLOSE_REPLACEMENT'
	if (isset($cur_list['additional_rows']['bottom_of_list']))
		template_additional_rows('bottom_of_list', $cur_list);

	if ($themelab_profile_fields_list)
		echo '
		</div><!-- .themelab_profile_fields_body -->';

	if (isset($cur_list['form']))
TL_PROFILE_FIELDS_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_genericlist_languages($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_language_edit_body') !== false)
		return true;

	/* Languages > Edit Languages uses GenericList id language_list. Keep the
	 * structural wrapper strictly scoped to that one admin list. */
	$profile_flag = "\t\$themelab_profile_fields_list = in_array(\$list_id, array('standard_profile_fields', 'custom_profile_fields'), true) && !empty(\$cur_list['title']);";
	$flag_replacement = $profile_flag . "\n\n\t\$themelab_language_edit_list = \$list_id === 'language_list' && !empty(\$cur_list['title']);";
	if (strpos($source, $profile_flag) === false)
		return false;
	$source = str_replace($profile_flag, $flag_replacement, $source, $flag_count);
	if ($flag_count !== 1)
		return false;

	$open_needle = <<<'TL_LANGUAGE_OPEN'
	if ($themelab_profile_fields_list)
		echo '
		<div class="themelab_profile_fields_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_LANGUAGE_OPEN;
	$open_replacement = <<<'TL_LANGUAGE_OPEN_REPLACEMENT'
	if ($themelab_profile_fields_list)
		echo '
		<div class="themelab_profile_fields_body">';

	if ($themelab_language_edit_list)
		echo '
		<div class="themelab_language_edit_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_LANGUAGE_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_LANGUAGE_CLOSE'
	if ($themelab_profile_fields_list)
		echo '
		</div><!-- .themelab_profile_fields_body -->';

	if (isset($cur_list['form']))
TL_LANGUAGE_CLOSE;
	$close_replacement = <<<'TL_LANGUAGE_CLOSE_REPLACEMENT'
	if ($themelab_profile_fields_list)
		echo '
		</div><!-- .themelab_profile_fields_body -->';

	if ($themelab_language_edit_list)
		echo '
		</div><!-- .themelab_language_edit_body -->';

	if (isset($cur_list['form']))
TL_LANGUAGE_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_genericlist_smiley_sets($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_smiley_sets_body') !== false)
		return true;

	/* Smileys and Message Icons > Smiley Sets uses GenericList id
	 * smiley_set_list. Add a real body around the top controls, table, and
	 * bottom controls so the entire list reads as one owned component. */
	$language_flag = "\t\$themelab_language_edit_list = \$list_id === 'language_list' && !empty(\$cur_list['title']);";
	$flag_replacement = $language_flag . "\n\n\t\$themelab_smiley_sets_list = \$list_id === 'smiley_set_list' && !empty(\$cur_list['title']);";
	if (strpos($source, $language_flag) === false)
		return false;
	$source = str_replace($language_flag, $flag_replacement, $source, $flag_count);
	if ($flag_count !== 1)
		return false;

	$open_needle = <<<'TL_SMILEY_SETS_OPEN'
	if ($themelab_language_edit_list)
		echo '
		<div class="themelab_language_edit_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_SMILEY_SETS_OPEN;
	$open_replacement = <<<'TL_SMILEY_SETS_OPEN_REPLACEMENT'
	if ($themelab_language_edit_list)
		echo '
		<div class="themelab_language_edit_body">';

	if ($themelab_smiley_sets_list)
		echo '
		<div class="themelab_smiley_sets_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_SMILEY_SETS_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_SMILEY_SETS_CLOSE'
	if ($themelab_language_edit_list)
		echo '
		</div><!-- .themelab_language_edit_body -->';

	if (isset($cur_list['form']))
TL_SMILEY_SETS_CLOSE;
	$close_replacement = <<<'TL_SMILEY_SETS_CLOSE_REPLACEMENT'
	if ($themelab_language_edit_list)
		echo '
		</div><!-- .themelab_language_edit_body -->';

	if ($themelab_smiley_sets_list)
		echo '
		</div><!-- .themelab_smiley_sets_body -->';

	if (isset($cur_list['form']))
TL_SMILEY_SETS_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_genericlist_attachment_browser($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_attachment_browser_body') !== false)
		return true;

	/* Attachments and Avatars > Browse Files uses GenericList id file_list.
	 * Keep the top/bottom Remove selected controls and the attachment table in
	 * one owned bordered body beneath the Browse files title. */
	$smiley_flag = "\t\$themelab_smiley_sets_list = \$list_id === 'smiley_set_list' && !empty(\$cur_list['title']);";
	$flag_replacement = $smiley_flag . "\n\n\t\$themelab_attachment_browser_list = \$list_id === 'file_list' && !empty(\$cur_list['title']);";
	if (strpos($source, $smiley_flag) === false)
		return false;
	$source = str_replace($smiley_flag, $flag_replacement, $source, $flag_count);
	if ($flag_count !== 1)
		return false;

	$open_needle = <<<'TL_ATTACHMENT_BROWSER_OPEN'
	if ($themelab_smiley_sets_list)
		echo '
		<div class="themelab_smiley_sets_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_ATTACHMENT_BROWSER_OPEN;
	$open_replacement = <<<'TL_ATTACHMENT_BROWSER_OPEN_REPLACEMENT'
	if ($themelab_smiley_sets_list)
		echo '
		<div class="themelab_smiley_sets_body">';

	if ($themelab_attachment_browser_list)
		echo '
		<div class="themelab_attachment_browser_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_ATTACHMENT_BROWSER_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_ATTACHMENT_BROWSER_CLOSE'
	if ($themelab_smiley_sets_list)
		echo '
		</div><!-- .themelab_smiley_sets_body -->';

	if (isset($cur_list['form']))
TL_ATTACHMENT_BROWSER_CLOSE;
	$close_replacement = <<<'TL_ATTACHMENT_BROWSER_CLOSE_REPLACEMENT'
	if ($themelab_smiley_sets_list)
		echo '
		</div><!-- .themelab_smiley_sets_body -->';

	if ($themelab_attachment_browser_list)
		echo '
		</div><!-- .themelab_attachment_browser_body -->';

	if (isset($cur_list['form']))
TL_ATTACHMENT_BROWSER_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_genericlist_admin_members($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_admin_members_body') !== false)
		return true;

	/* Admin > Members > View all Members uses GenericList id member_list.
	 * Keep the list table and Delete Selected Members controls inside one real
	 * bordered body so the title-to-table spacer is owned by the component. */
	$attachment_flag = "\t\$themelab_attachment_browser_list = \$list_id === 'file_list' && !empty(\$cur_list['title']);";
	$flag_replacement = $attachment_flag . "\n\n\t\$themelab_admin_members_list = \$list_id === 'member_list' && !empty(\$cur_list['title']);";
	if (strpos($source, $attachment_flag) === false)
		return false;
	$source = str_replace($attachment_flag, $flag_replacement, $source, $flag_count);
	if ($flag_count !== 1)
		return false;

	$open_needle = <<<'TL_ADMIN_MEMBERS_OPEN'
	if ($themelab_attachment_browser_list)
		echo '
		<div class="themelab_attachment_browser_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_ADMIN_MEMBERS_OPEN;
	$open_replacement = <<<'TL_ADMIN_MEMBERS_OPEN_REPLACEMENT'
	if ($themelab_attachment_browser_list)
		echo '
		<div class="themelab_attachment_browser_body">';

	if ($themelab_admin_members_list)
		echo '
		<div class="themelab_admin_members_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_ADMIN_MEMBERS_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_ADMIN_MEMBERS_CLOSE'
	if ($themelab_attachment_browser_list)
		echo '
		</div><!-- .themelab_attachment_browser_body -->';

	if (isset($cur_list['form']))
TL_ADMIN_MEMBERS_CLOSE;
	$close_replacement = <<<'TL_ADMIN_MEMBERS_CLOSE_REPLACEMENT'
	if ($themelab_attachment_browser_list)
		echo '
		</div><!-- .themelab_attachment_browser_body -->';

	if ($themelab_admin_members_list)
		echo '
		</div><!-- .themelab_admin_members_body -->';

	if (isset($cur_list['form']))
TL_ADMIN_MEMBERS_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_genericlist_membergroups($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_membergroups_body') !== false)
		return true;

	/* Admin > Membergroups > Edit membergroups uses two GenericList ids.
	 * Wrap each list independently so Regular groups and Post count based groups
	 * remain distinct, complete components with their own Add group controls. */
	$admin_members_flag = "\t\$themelab_admin_members_list = \$list_id === 'member_list' && !empty(\$cur_list['title']);";
	$flag_replacement = $admin_members_flag . "\n\n\t\$themelab_membergroups_list = in_array(\$list_id, array('regular_membergroups_list', 'post_count_membergroups_list'), true) && !empty(\$cur_list['title']);";
	if (strpos($source, $admin_members_flag) === false)
		return false;
	$source = str_replace($admin_members_flag, $flag_replacement, $source, $flag_count);
	if ($flag_count !== 1)
		return false;

	$open_needle = <<<'TL_MEMBERGROUPS_OPEN'
	if ($themelab_admin_members_list)
		echo '
		<div class="themelab_admin_members_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_MEMBERGROUPS_OPEN;
	$open_replacement = <<<'TL_MEMBERGROUPS_OPEN_REPLACEMENT'
	if ($themelab_admin_members_list)
		echo '
		<div class="themelab_admin_members_body">';

	if ($themelab_membergroups_list)
		echo '
		<div class="themelab_membergroups_body', $list_id === 'regular_membergroups_list' ? ' themelab_membergroups_regular_body' : '', '">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_MEMBERGROUPS_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_MEMBERGROUPS_CLOSE'
	if ($themelab_admin_members_list)
		echo '
		</div><!-- .themelab_admin_members_body -->';

	if (isset($cur_list['form']))
TL_MEMBERGROUPS_CLOSE;
	$close_replacement = <<<'TL_MEMBERGROUPS_CLOSE_REPLACEMENT'
	if ($themelab_admin_members_list)
		echo '
		</div><!-- .themelab_admin_members_body -->';

	if ($themelab_membergroups_list)
		echo '
		</div><!-- .themelab_membergroups_body -->';

	if (isset($cur_list['form']))
TL_MEMBERGROUPS_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_genericlist_ban_list($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_ban_list_body') !== false)
		return true;

	/* Admin > Ban list uses GenericList id ban_list. Keep the list table and
	 * bottom Remove selected controls inside one owned body. The duplicate top
	 * action is hidden by generated CSS, leaving one natural action point. */
	$membergroups_flag = "\t\$themelab_membergroups_list = in_array(\$list_id, array('regular_membergroups_list', 'post_count_membergroups_list'), true) && !empty(\$cur_list['title']);";
	$flag_replacement = $membergroups_flag . "\n\n\t\$themelab_ban_list = \$list_id === 'ban_list' && !empty(\$cur_list['title']);";
	if (strpos($source, $membergroups_flag) === false)
		return false;
	$source = str_replace($membergroups_flag, $flag_replacement, $source, $flag_count);
	if ($flag_count !== 1)
		return false;

	$open_needle = <<<'TL_BAN_LIST_OPEN'
	if ($themelab_membergroups_list)
		echo '
		<div class="themelab_membergroups_body', $list_id === 'regular_membergroups_list' ? ' themelab_membergroups_regular_body' : '', '">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_BAN_LIST_OPEN;
	$open_replacement = <<<'TL_BAN_LIST_OPEN_REPLACEMENT'
	if ($themelab_membergroups_list)
		echo '
		<div class="themelab_membergroups_body', $list_id === 'regular_membergroups_list' ? ' themelab_membergroups_regular_body' : '', '">';

	if ($themelab_ban_list)
		echo '
		<div class="themelab_ban_list_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_BAN_LIST_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_BAN_LIST_CLOSE'
	if ($themelab_membergroups_list)
		echo '
		</div><!-- .themelab_membergroups_body -->';

	if (isset($cur_list['form']))
TL_BAN_LIST_CLOSE;
	$close_replacement = <<<'TL_BAN_LIST_CLOSE_REPLACEMENT'
	if ($themelab_membergroups_list)
		echo '
		</div><!-- .themelab_membergroups_body -->';

	if ($themelab_ban_list)
		echo '
		</div><!-- .themelab_ban_list_body -->';

	if (isset($cur_list['form']))
TL_BAN_LIST_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_genericlist_mail_queue($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_mail_queue_body') !== false)
		return true;

	/* Admin > Mail > Browse Queue uses GenericList id mail_queue. Keep the
	 * queue table and bottom controls in one owned body, while generated CSS
	 * suppresses only the duplicate action row immediately above the table. */
	$ban_flag = "\t\$themelab_ban_list = \$list_id === 'ban_list' && !empty(\$cur_list['title']);";
	$flag_replacement = $ban_flag . "\n\n\t\$themelab_mail_queue = \$list_id === 'mail_queue' && !empty(\$cur_list['title']);";
	if (strpos($source, $ban_flag) === false)
		return false;
	$source = str_replace($ban_flag, $flag_replacement, $source, $flag_count);
	if ($flag_count !== 1)
		return false;

	$open_needle = <<<'TL_MAIL_QUEUE_OPEN'
	if ($themelab_ban_list)
		echo '
		<div class="themelab_ban_list_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_MAIL_QUEUE_OPEN;
	$open_replacement = <<<'TL_MAIL_QUEUE_OPEN_REPLACEMENT'
	if ($themelab_ban_list)
		echo '
		<div class="themelab_ban_list_body">';

	if ($themelab_mail_queue)
		echo '
		<div class="themelab_mail_queue_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_MAIL_QUEUE_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_MAIL_QUEUE_CLOSE'
	if ($themelab_ban_list)
		echo '
		</div><!-- .themelab_ban_list_body -->';

	if (isset($cur_list['form']))
TL_MAIL_QUEUE_CLOSE;
	$close_replacement = <<<'TL_MAIL_QUEUE_CLOSE_REPLACEMENT'
	if ($themelab_ban_list)
		echo '
		</div><!-- .themelab_ban_list_body -->';

	if ($themelab_mail_queue)
		echo '
		</div><!-- .themelab_mail_queue_body -->';

	if (isset($cur_list['form']))
TL_MAIL_QUEUE_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}


function themelab_publish_patch_genericlist_group_requests($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_group_requests_body') !== false)
		return true;

	/* RC6.1.21cm Moderation Center > Membergroup Requests.
	 * SMF renders this page through GenericList using group_request_list. Own
	 * the list body structurally so the title, table and bottom action controls
	 * form one connected Theme Lab component instead of relying on sibling CSS. */
	$mail_flag = "\t\$themelab_mail_queue = \$list_id === 'mail_queue' && !empty(\$cur_list['title']);";
	$flag_replacement = $mail_flag . "\n\n\t\$themelab_group_requests_list = \$list_id === 'group_request_list' && !empty(\$cur_list['title']);";
	if (strpos($source, $mail_flag) === false)
		return false;
	$source = str_replace($mail_flag, $flag_replacement, $source, $flag_count);
	if ($flag_count !== 1)
		return false;

	$open_needle = <<<'TL_GROUP_REQUESTS_OPEN'
	if ($themelab_mail_queue)
		echo '
		<div class="themelab_mail_queue_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_GROUP_REQUESTS_OPEN;
	$open_replacement = <<<'TL_GROUP_REQUESTS_OPEN_REPLACEMENT'
	if ($themelab_mail_queue)
		echo '
		<div class="themelab_mail_queue_body">';

	if ($themelab_group_requests_list)
		echo '
		<div class="themelab_group_requests_body">';

	if (isset($cur_list['additional_rows']['after_title']))
TL_GROUP_REQUESTS_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_GROUP_REQUESTS_CLOSE'
	if ($themelab_mail_queue)
		echo '
		</div><!-- .themelab_mail_queue_body -->';

	if (isset($cur_list['form']))
TL_GROUP_REQUESTS_CLOSE;
	$close_replacement = <<<'TL_GROUP_REQUESTS_CLOSE_REPLACEMENT'
	if ($themelab_mail_queue)
		echo '
		</div><!-- .themelab_mail_queue_body -->';

	if ($themelab_group_requests_list)
		echo '
		</div><!-- .themelab_group_requests_body -->';

	if (isset($cur_list['form']))
TL_GROUP_REQUESTS_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_manage_permissions_index($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_manage_permissions_body') !== false)
		return true;

	/* Admin > Permissions > General permissions is native template markup rather
	 * than GenericList. Wrap only the main permission-group table; Advanced
	 * Options remains its own existing component below. */
	$open_needle = <<<'TL_PERMISSIONS_OPEN'
	echo '
			<table class="table_grid">
TL_PERMISSIONS_OPEN;
	$open_replacement = <<<'TL_PERMISSIONS_OPEN_REPLACEMENT'
	echo '
			<div class="themelab_manage_permissions_body">
			<table class="table_grid">
TL_PERMISSIONS_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_PERMISSIONS_CLOSE'
			</table>
			<br>';
TL_PERMISSIONS_CLOSE;
	$close_replacement = <<<'TL_PERMISSIONS_CLOSE_REPLACEMENT'
			</table>
			</div><!-- .themelab_manage_permissions_body -->';
TL_PERMISSIONS_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_permission_profiles($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_permission_profiles_body') !== false)
		return true;

	/* Admin > Permissions > Edit profiles is native template markup. Restrict
	 * the patch to template_edit_profiles() so only the existing profiles list
	 * and its action row become one structural body; New Profile stays separate. */
	$start = strpos($source, 'function template_edit_profiles()');
	$end = $start === false ? false : strpos($source, 'function template_modify_group()', $start);
	if ($start === false || $end === false)
		return false;

	$segment = substr($source, $start, $end - $start);
	$open_needle = <<<'TL_PROFILES_OPEN'

			<table class="table_grid">
TL_PROFILES_OPEN;
	$open_replacement = <<<'TL_PROFILES_OPEN_REPLACEMENT'

			<div class="themelab_permission_profiles_body">
			<table class="table_grid">
TL_PROFILES_OPEN_REPLACEMENT;
	if (strpos($segment, $open_needle) === false)
		return false;
	$segment = str_replace($open_needle, $open_replacement, $segment, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_PROFILES_CLOSE'
			</div>
		</form>
		<br>
TL_PROFILES_CLOSE;
	$close_replacement = <<<'TL_PROFILES_CLOSE_REPLACEMENT'
			</div>
			</div><!-- .themelab_permission_profiles_body -->
		</form>
		<br>
TL_PROFILES_CLOSE_REPLACEMENT;
	if (strpos($segment, $close_needle) === false)
		return false;
	$segment = str_replace($close_needle, $close_replacement, $segment, $close_count);
	if ($close_count !== 1)
		return false;

	$source = substr($source, 0, $start) . $segment . substr($source, $end);
	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_package_permissions($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	/* Already patched by a previous publish pass. */
	if (strpos($source, 'themelab_file_permissions_body') !== false)
		return true;

	/* File Permissions is rendered directly as cat_bar + table inside
	 * template_file_permissions(). Give only this table a structural body. */
	$open_needle = <<<'TL_FILE_PERMS_OPEN'
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext" width="30%">', $txt['package_file_perms_name'], '</th>
TL_FILE_PERMS_OPEN;
	$open_replacement = <<<'TL_FILE_PERMS_OPEN_REPLACEMENT'
		</div>
		<div class="themelab_file_permissions_body">
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext" width="30%">', $txt['package_file_perms_name'], '</th>
TL_FILE_PERMS_OPEN_REPLACEMENT;
	if (strpos($source, $open_needle) === false)
		return false;
	$source = str_replace($open_needle, $open_replacement, $source, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_FILE_PERMS_CLOSE'
		</table>
		<br>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_file_perms_change'], '</h3>
TL_FILE_PERMS_CLOSE;
	$close_replacement = <<<'TL_FILE_PERMS_CLOSE_REPLACEMENT'
		</table>
		</div><!-- .themelab_file_permissions_body -->
		<br>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_file_perms_change'], '</h3>
TL_FILE_PERMS_CLOSE_REPLACEMENT;
	if (strpos($source, $close_needle) === false)
		return false;
	$source = str_replace($close_needle, $close_replacement, $source, $close_count);
	if ($close_count !== 1)
		return false;

	return @file_put_contents($file, $source) !== false;
}

function themelab_publish_patch_profile_showposts($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	/* Already patched by a previous publish pass. */
	if (strpos($source, 'themelab_profile_showposts_body') !== false)
		return true;

	$function_start = strpos($source, 'function template_showPosts()');
	if ($function_start === false)
		return false;

	$next_function = strpos($source, 'function template_showAlerts()', $function_start);
	if ($next_function === false)
		return false;

	$block = substr($source, $function_start, $next_function - $function_start);

	/* Open the genuine body wrapper immediately after the Messages/Topics
	 * title bar, BEFORE Curve2 emits the upper pagination. The wrapper must
	 * own that pagination too; otherwise an exposed strip remains between the
	 * blue header and the bordered body. */
	$open_needle = "\t\t</div>', !empty(\$context['page_index']) ? '";
	$open_replacement = "\t\t</div>\n\t\t<div class=\"themelab_profile_showposts_body\">', !empty(\$context['page_index']) ? '";
	if (strpos($block, $open_needle) === false)
		return false;
	$block = str_replace($open_needle, $open_replacement, $block, $open_count);
	if ($open_count !== 1)
		return false;

	/* Close the owned body after the lower pagination, immediately before the
	 * template function ends. */
	$close_needle = <<<'TL_SHOWPOSTS_CLOSE'
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';
}
TL_SHOWPOSTS_CLOSE;
	$close_replacement = <<<'TL_SHOWPOSTS_CLOSE_REPLACEMENT'
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';

	echo '
		</div><!-- .themelab_profile_showposts_body -->';
}
TL_SHOWPOSTS_CLOSE_REPLACEMENT;
	if (strpos($block, $close_needle) === false)
		return false;
	$block = str_replace($close_needle, $close_replacement, $block, $close_count);
	if ($close_count !== 1)
		return false;

	$patched = substr($source, 0, $function_start) . $block . substr($source, $next_function);
	return @file_put_contents($file, $patched) !== false;
}

function themelab_publish_patch_admin_alert_preferences($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_admin_alert_preferences_body') !== false)
		return true;

	$function_start = strpos($source, 'function template_alert_configuration()');
	if ($function_start === false)
		return false;

	$next_function = strpos($source, 'function template_alert_notifications_topics()', $function_start);
	if ($next_function === false)
		return false;

	$block = substr($source, $function_start, $next_function - $function_start);

	$open_needle = <<<'TL_ADMIN_ALERT_OPEN'
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['notify_what_how'], '
				</h3>
			</div>
			<table class="table_grid">';
TL_ADMIN_ALERT_OPEN;

	$open_replacement = <<<'TL_ADMIN_ALERT_OPEN_REPLACEMENT'
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['notify_what_how'], '
				</h3>
			</div>';

	if (strpos((string) $context['action'], 'action=admin') === 0)
		echo '
			<div class="themelab_admin_alert_preferences_body">';

	echo '
			<table class="table_grid">';
TL_ADMIN_ALERT_OPEN_REPLACEMENT;

	if (strpos($block, $open_needle) === false)
		return false;
	$block = str_replace($open_needle, $open_replacement, $block, $open_count);
	if ($open_count !== 1)
		return false;

	$close_needle = <<<'TL_ADMIN_ALERT_CLOSE'
			</table>
			<br>
			<div>
				<input id="notify_submit" type="submit" name="notify_submit" value="', $txt['notify_save'], '" class="button">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">', !empty($context['token_check']) ? '
				<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '">' : '', '
				<input type="hidden" name="u" value="', $context['id_member'], '">
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
			</div>
		</form>
TL_ADMIN_ALERT_CLOSE;

	$close_replacement = <<<'TL_ADMIN_ALERT_CLOSE_REPLACEMENT'
			</table>
			<br>
			<div', strpos((string) $context['action'], 'action=admin') === 0 ? ' class="themelab_admin_alert_actions"' : '', '>
				<input id="notify_submit" type="submit" name="notify_submit" value="', $txt['notify_save'], '" class="button">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">', !empty($context['token_check']) ? '
				<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '">' : '', '
				<input type="hidden" name="u" value="', $context['id_member'], '">
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
			</div>';

	if (strpos((string) $context['action'], 'action=admin') === 0)
		echo '
			</div><!-- .themelab_admin_alert_preferences_body -->';

	echo '
		</form>
TL_ADMIN_ALERT_CLOSE_REPLACEMENT;

	if (strpos($block, $close_needle) === false)
		return false;
	$block = str_replace($close_needle, $close_replacement, $block, $close_count);
	if ($close_count !== 1)
		return false;

	$patched = substr($source, 0, $function_start) . $block . substr($source, $next_function);
	return @file_put_contents($file, $patched) !== false;
}

function themelab_publish_patch_theme_settings($file)
{
	if (!is_file($file))
		return false;

	$source = @file_get_contents($file);
	if ($source === false)
		return false;

	if (strpos($source, 'themelab_theme_settings_component') !== false)
		return true;

	$function_start = strpos($source, 'function template_set_settings()');
	if ($function_start === false)
		return false;

	$next_function = strpos($source, 'function template_pick()', $function_start);
	if ($next_function === false)
		return false;

	$block = substr($source, $function_start, $next_function - $function_start);

	$wrapper_needle = '<div id="admin_form_wrapper">';
	$wrapper_replacement = '<div id="admin_form_wrapper" class="themelab_theme_settings_component">';
	if (strpos($block, $wrapper_needle) === false)
		return false;
	$block = str_replace($wrapper_needle, $wrapper_replacement, $block, $wrapper_count);
	if ($wrapper_count !== 1)
		return false;

	$head_needle = <<<'TL_THEME_SETTINGS_HEAD'
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=theme_settings"
TL_THEME_SETTINGS_HEAD;
	$head_replacement = <<<'TL_THEME_SETTINGS_HEAD_REPLACEMENT'
			<div class="cat_bar themelab_theme_settings_head">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=theme_settings"
TL_THEME_SETTINGS_HEAD_REPLACEMENT;
	if (strpos($block, $head_needle) === false)
		return false;
	$block = str_replace($head_needle, $head_replacement, $block, $head_count);
	if ($head_count !== 1)
		return false;

	$body_needle = "\t\t\t<div class=\"windowbg\">';";
	$body_replacement = "\t\t\t<div class=\"windowbg themelab_theme_settings_body\">';";
	if (strpos($block, $body_needle) === false)
		return false;
	$block = preg_replace('~' . preg_quote($body_needle, '~') . '~', $body_replacement, $block, 1, $body_count);
	if ($body_count !== 1)
		return false;

	$patched = substr($source, 0, $function_start) . $block . substr($source, $next_function);

	/* RC6.1.21ad: simplify the Theme Settings overview/list page. */
	$list_start = strpos($patched, 'function template_list_themes()');
	$list_next = $list_start === false ? false : strpos($patched, 'function template_reset_list()', $list_start);
	if ($list_start === false || $list_next === false) return false;
	$list = substr($patched, $list_start, $list_next - $list_start);
	$old_bar = "<div class=\"cat_bar\">\n\t\t\t\t<h3 class=\"catbg\">', \$txt['theme_settings'], '</h3>\n\t\t\t</div>\n\t\t\t<br>';";
	$new_bar = "<div class=\"themelab_theme_list_collection\">';";
	if (strpos($list, $old_bar) === false) return false;
	$list = str_replace($old_bar, $new_bar, $list, $open_count);
	if ($open_count !== 1) return false;
	$reset_head = "<div class=\"cat_bar\">\n\t\t\t\t<h3 class=\"catbg\">', \$txt['themeadmin_list_reset'], '</h3>";
	if (strpos($list, $reset_head) === false) return false;
	$list = str_replace($reset_head, "</div><!-- .themelab_theme_list_collection -->\n\t\t\t<div class=\"cat_bar\">\n\t\t\t\t<h3 class=\"catbg\">', \$txt['themeadmin_list_reset'], '</h3>", $list, $close_count);
	if ($close_count !== 1) return false;

	$patched = substr($patched, 0, $list_start) . $list . substr($patched, $list_next);
	return @file_put_contents($file, $patched) !== false;
}

function themelab_publish_patch_manage_member_search($file)
{
    if (!is_file($file))
        return false;

    $source = @file_get_contents($file);
    if ($source === false)
        return false;

    if (strpos($source, 'themelab_member_search_groups_body') !== false)
        return true;

    $start = strpos($source, 'function template_search_members()');
    $next = $start === false ? false : strpos($source, 'function template_admin_browse()', $start);
    if ($start === false || $next === false)
        return false;

    $block = substr($source, $start, $next - $start);

    $open_needle = <<<'TL_MEMBER_SEARCH_OPEN'
			<div class="cat_bar">
				<h3 class="catbg">', $txt['member_part_of_these_membergroups'], '</h3>
			</div>
			<table class="table_grid">
TL_MEMBER_SEARCH_OPEN;
    $open_replacement = <<<'TL_MEMBER_SEARCH_OPEN_REPLACEMENT'
			<div class="cat_bar">
				<h3 class="catbg">', $txt['member_part_of_these_membergroups'], '</h3>
			</div>
			<div class="themelab_member_search_groups_body">
			<table class="table_grid">
TL_MEMBER_SEARCH_OPEN_REPLACEMENT;
    if (strpos($block, $open_needle) === false)
        return false;
    $block = str_replace($open_needle, $open_replacement, $block, $open_count);
    if ($open_count !== 1)
        return false;

    $close_needle = <<<'TL_MEMBER_SEARCH_CLOSE'
			</table>
			<br>
			<input type="submit" value="', $txt['search'], '" class="button">
		</form>';
TL_MEMBER_SEARCH_CLOSE;
    $close_replacement = <<<'TL_MEMBER_SEARCH_CLOSE_REPLACEMENT'
			</table>
			<div class="themelab_member_search_groups_actions">
				<input type="submit" value="', $txt['search'], '" class="button">
			</div>
			</div><!-- .themelab_member_search_groups_body -->
		</form>';
TL_MEMBER_SEARCH_CLOSE_REPLACEMENT;
    if (strpos($block, $close_needle) === false)
        return false;
    $block = str_replace($close_needle, $close_replacement, $block, $close_count);
    if ($close_count !== 1)
        return false;

    $patched = substr($source, 0, $start) . $block . substr($source, $next);
    return @file_put_contents($file, $patched) !== false;
}

function themelab_publish_patch_manage_boards($file)
{
    $source = @file_get_contents($file);
    if ($source === false)
        return false;

    // Idempotent when rebuilding an already-patched theme.
    if (strpos($source, 'themelab_manage_boards_collection') !== false)
        return true;

    $start = strpos($source, 'function template_main()');
    $next = $start === false ? false : strpos($source, 'function template_modify_category()', $start);
    if ($start === false || $next === false)
        return false;

    $block = substr($source, $start, $next - $start);
    $block = str_replace('<div id="manage_boards">', '<div id="manage_boards" class="themelab_manage_boards_collection">', $block, $outer_count);
    if ($outer_count !== 1)
        return false;

    $block = str_replace('<div class="windowbg">\';', '<div class="windowbg themelab_manage_boards_body">\';', $block, $body_count);
    if ($body_count !== 1)
        return false;

    $patched = substr($source, 0, $start) . $block . substr($source, $next);
    return @file_put_contents($file, $patched) !== false;
}

function themelab_publish_header_utilities_js($project, $publish_options = array())
{
	$header = !empty($project['project_data_array']['header']) && is_array($project['project_data_array']['header'])
		? $project['project_data_array']['header'] : array();
	$mode = !empty($header['utility_mode']) && $header['utility_mode'] === 'integrated' ? 'integrated' : 'separate';
	$requested = !empty($header['utility_position']) && in_array($header['utility_position'], array('auto', 'left', 'right'), true)
		? $header['utility_position'] : 'auto';
	$logo = !empty($header['logo_alignment']) && in_array($header['logo_alignment'], array('left', 'center', 'right'), true)
		? $header['logo_alignment'] : 'left';

	$color_modes = !empty($publish_options['color_modes']);
	$storage_key = 'themelab-color-mode-' . themelab_publish_slug(!empty($project['project_name']) ? $project['project_name'] : 'theme');

	$js = "/* Generated by Theme Lab 1.0.1 - utilities + color modes + smooth Scroll to Top. */\n";
	$js .= "(function(){'use strict';var MODE=" . json_encode($mode) . ",REQUESTED=" . json_encode($requested) . ",LOGO=" . json_encode($logo) . ",COLOR_MODES=" . ($color_modes ? "'1'" : "'0'") . ",MODE_KEY=" . json_encode($storage_key) . ";";
	$js .= "function resolved(){if(REQUESTED==='auto')return LOGO==='right'?'left':'right';if(LOGO==='left'&&REQUESTED==='left')return'right';if(LOGO==='right'&&REQUESTED==='right')return'left';return REQUESTED;}";
	$js .= "function installHeader(){if(MODE!=='integrated')return;var header=document.getElementById('header'),top=document.getElementById('top_section'),info=document.getElementById('top_info'),search=document.getElementById('search_form');if(!header||!info)return;var lane=document.createElement('div');lane.id='themelab_header_utilities';lane.className='themelab_header_utilities';lane.setAttribute('data-position',resolved());header.insertBefore(lane,header.firstChild);lane.appendChild(info);if(search)lane.appendChild(search);if(top)top.classList.add('themelab_utility_source_hidden');header.classList.add('themelab_integrated_utilities');header.setAttribute('data-themelab-logo-position',LOGO);}";
	$js .= "function installScrollTop(){if(document.getElementById('themelab_scroll_top'))return;var footer=document.getElementById('footer');if(footer){var links=footer.querySelectorAll('a[href*=\\\"#top_section\\\"],a[href$=\\\"#top\\\"]');for(var i=0;i<links.length;i++){var label=(links[i].textContent||'').replace(/\\s+/g,' ').trim().toLowerCase();if(label==='go up'||label==='up'||label==='top')links[i].classList.add('themelab_native_go_up_hidden');}}var b=document.createElement('button');b.type='button';b.id='themelab_scroll_top';b.className='themelab_scroll_top';b.setAttribute('aria-label','Go to top');b.setAttribute('title','Go to top');b.innerHTML='<svg viewBox=\\\"0 0 24 24\\\" aria-hidden=\\\"true\\\" focusable=\\\"false\\\"><path d=\\\"M5 15l7-7 7 7\\\"/></svg>';document.body.appendChild(b);var update=function(){if((window.pageYOffset||document.documentElement.scrollTop||0)>300)b.classList.add('is-visible');else b.classList.remove('is-visible');};b.addEventListener('click',function(){var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;try{window.scrollTo({top:0,left:0,behavior:reduce?'auto':'smooth'});}catch(e){window.scrollTo(0,0);}});window.addEventListener('scroll',update,{passive:true});update();}";

	$js .= "function installColorMode(){if(COLOR_MODES!=='1'||document.getElementById('themelab_mode_toggle'))return;var info=document.getElementById('top_info');if(!info)return;var li=document.createElement('li'),b=document.createElement('button');li.className='themelab_mode_control';b.type='button';b.id='themelab_mode_toggle';b.className='themelab_mode_toggle';function mode(){return document.documentElement.getAttribute('data-themelab-mode')==='dark'?'dark':'light';}function paint(){var dark=mode()==='dark';b.setAttribute('aria-label',dark?'Switch to light mode':'Switch to dark mode');b.setAttribute('title',dark?'Switch to light mode':'Switch to dark mode');b.innerHTML=dark?'<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><circle cx=\"12\" cy=\"12\" r=\"4\"/><path d=\"M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41\"/></svg>':'<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z\"/></svg>';}b.addEventListener('click',function(){var next=mode()==='dark'?'light':'dark';document.documentElement.setAttribute('data-themelab-mode',next);try{localStorage.setItem(MODE_KEY,next);}catch(e){}paint();});li.appendChild(b);info.appendChild(li);paint();}";
	$js .= "function run(){installHeader();installColorMode();installScrollTop();}";
	$js .= "if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();})();\n";
	return $js;
}


function themelab_publish_color_mode_init_js($project)
{
	$storage_key = 'themelab-color-mode-' . themelab_publish_slug(!empty($project['project_name']) ? $project['project_name'] : 'theme');

	return "/* Theme Lab color-mode bootstrap: intentionally tiny and side-effect free. */\n"
		. "(function(){try{var k=" . json_encode($storage_key)
		. ",v=localStorage.getItem(k);if(v!=='light'&&v!=='dark'){v=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}"
		. "document.documentElement.setAttribute('data-themelab-mode',v);}catch(e){}})();\n";
}


function themelab_publish_patch_index($file, $color_modes = false)
{
	$content = @file_get_contents($file);
	if ($content === false)
		return false;

	if (!preg_match('~function\s+template_init\s*\(\s*\)\s*\{~', $content, $match, PREG_OFFSET_CAPTURE))
		return false;

	$matched_text = $match[0][0];
	$insert_at = $match[0][1] + strlen($matched_text);
	$lines = '';
	if (strpos($content, "'themelab_generated'") === false && strpos($content, '"themelab_generated"') === false)
		$lines .= "\n\tloadCSSFile('themelab-generated.css', array('minimize' => true, 'order_pos' => 9999), 'themelab_generated');";
	if ($color_modes
		&& strpos($content, "'themelab_color_mode_init'") === false
		&& strpos($content, '"themelab_color_mode_init"') === false)
		$lines .= "\n\tloadJavaScriptFile('themelab-color-mode-init.js', array('defer' => false, 'async' => false, 'minimize' => true, 'order_pos' => 1), 'themelab_color_mode_init');";

	if (strpos($content, "'themelab_generated_js'") === false && strpos($content, '"themelab_generated_js"') === false)
		$lines .= "\n\tloadJavaScriptFile('themelab-generated.js', array('defer' => true, 'minimize' => true, 'order_pos' => 9999), 'themelab_generated_js');";
	if ($lines !== '')
		$content = substr($content, 0, $insert_at) . $lines . substr($content, $insert_at);

	return @file_put_contents($file, $content) !== false;
}

function themelab_publish_remove_tree($dir)
{
	if (!is_dir($dir)) return;
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
	@rmdir($dir);
}

function themelab_publish_build_report_text($project, $pipeline, $inventory, $result = array())
{
	$lines = array('Theme Lab Build Report', str_repeat('=', 48), '');
	$lines[] = 'Theme: ' . $project['project_name'];
	$lines[] = 'Version: ' . $project['theme_version'];
	$lines[] = 'Original Author: ' . $project['author'];
	$lines[] = 'Publisher: ' . (!empty($project['publisher']) ? $project['publisher'] : themelab_publish_current_publisher());
	$lines[] = 'Build: Theme Lab 1.0.1';
	$lines[] = 'Generated: ' . date('c');
	$lines[] = '';
	$lines[] = 'Studio Token Inventory';
	foreach ($inventory as $studio)
		$lines[] = sprintf('[%s] %-18s %d saved values', $studio['count'] > 0 ? 'PASS' : 'WARN', $studio['label'], $studio['count']);
	$lines[] = '';
	$lines[] = 'Build Pipeline';
	foreach ($pipeline as $stage)
	{
		$lines[] = '[' . strtoupper($stage['status']) . '] ' . $stage['label'];
		foreach (!empty($stage['details']) ? $stage['details'] : array() as $detail)
			$lines[] = '  - ' . $detail;
	}
	if (!empty($result))
	{
		$lines[] = '';
		$lines[] = 'Artifact';
		foreach ($result as $key => $value)
			$lines[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
	}
	return implode("\n", $lines) . "\n";
}

function themelab_publish_build($project, $options)
{
	global $boarddir;
	$started = microtime(true);
	$pipeline = array();
	$add_stage = function ($key, $label, $status, $details = array()) use (&$pipeline) {
		$pipeline[] = array('key' => $key, 'label' => $label, 'status' => $status, 'details' => $details);
	};

	$source_version = (string) $project['theme_version'];
	$published_version = themelab_publish_increment_version($source_version);
	$publisher = themelab_publish_current_publisher();
	$project['publisher'] = $publisher;
	$project['theme_version'] = $published_version;

	$slug = themelab_publish_slug($project['project_name']);
	$version = preg_replace('~[^0-9A-Za-z._-]+~', '-', $published_version);
	$stamp = date('Ymd_His');
	$exports = $boarddir . '/Packages/ThemeLabExports';
	$work = $exports . '/build_' . $slug . '_' . $stamp;
	$theme_dir = $work . '/' . $slug;
	$inventory = themelab_publish_studio_inventory($project);
	$total_saved = 0;
	foreach ($inventory as $studio) $total_saved += $studio['count'];
	$add_stage('read_project', 'Reading project data', 'pass', array('Project: ' . $project['project_name'], 'Previous version: ' . $source_version, 'Publishing version: ' . $published_version, 'Original author: ' . $project['author'], 'Publisher: ' . $publisher));
	$inventory_details = array();
	foreach ($inventory as $studio) $inventory_details[] = $studio['label'] . ': ' . $studio['count'] . ' saved values';
	$add_stage('inventory', 'Inventorying saved studio values', $total_saved > 0 ? 'pass' : 'fail', array_merge(array('Total saved values: ' . $total_saved), $inventory_details));

	if (!is_dir($exports) && !@mkdir($exports, 0755, true))
		return array('success' => false, 'error' => 'Unable to create the export directory.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	if (!@mkdir($work, 0755, true))
		return array('success' => false, 'error' => 'Unable to create the build workspace.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	if (!themelab_publish_copy_tree($boarddir . '/Themes/default', $theme_dir))
	{
		$add_stage('clone', 'Cloning Curve2 base theme', 'fail', array('The base theme could not be copied.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to copy the Curve2 base theme.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('clone', 'Cloning Curve2 base theme', 'pass', array('Source: Themes/default', 'Destination workspace created'));

	$header_assets = themelab_publish_header_assets($project, $theme_dir);
	if (!empty($header_assets['errors']))
	{
		$add_stage('header_assets', 'Packaging Header Studio artwork', 'fail', $header_assets['errors']);
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Header Studio artwork could not be packaged.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage(
		'header_assets',
		'Packaging Header Studio artwork',
		'pass',
		!empty($header_assets['copied']) ? $header_assets['copied'] : array('No uploaded Header Studio artwork to package.')
	);

	$css_audit = array();
	$generated_css = themelab_publish_css($project, $css_audit, $header_assets, $options);
	$css_path = $theme_dir . '/css/themelab-generated.css';
	$css_written = @file_put_contents($css_path, $generated_css);
	if ($css_written === false)
	{
		$add_stage('css', 'Generating theme CSS', 'fail', array('Unable to write css/themelab-generated.css'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to write generated CSS.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('css', 'Generating theme CSS', 'pass', array(
		'Saved values accounted for: ' . $css_audit['accounted_saved_values'] . ' / ' . $css_audit['saved_values'],
		'Normalized CSS values: ' . $css_audit['resolved_values'],
		'Values consumed by compiler: ' . $css_audit['consumed_values'] . ' / ' . $css_audit['resolved_values'],
		'Values written to CSS manifest: ' . $css_audit['written_values'] . ' / ' . $css_audit['resolved_values'],
		'Non-CSS / metadata values accounted for: ' . $css_audit['metadata_values'],
		'Unaccounted saved values: ' . $css_audit['unaccounted_saved_values'],
		'Generated CSS: ' . number_format($css_audit['bytes']) . ' bytes',
	));

	if (!empty($options['color_modes']))
	{
		$mode_init_js = themelab_publish_color_mode_init_js($project);
		$mode_init_path = $theme_dir . '/scripts/themelab-color-mode-init.js';
		$mode_init_written = @file_put_contents($mode_init_path, $mode_init_js);

		if ($mode_init_written === false)
		{
			$add_stage('color_mode_init_js', 'Generating early color mode bootstrap', 'fail', array('Could not write themelab-color-mode-init.js.'));
			themelab_publish_remove_tree($work);
			return array('success' => false, 'error' => 'Could not write color mode bootstrap.', 'audit' => $audit);
		}

		$add_stage('color_mode_init_js', 'Generating early color mode bootstrap', 'pass', array('Only the saved light/dark preference is applied early; header utilities remain deferred.'));
	}

	$generated_js = themelab_publish_header_utilities_js($project, $options);
	$js_path = $theme_dir . '/scripts/themelab-generated.js';
	$js_written = @file_put_contents($js_path, $generated_js);
	if ($js_written === false)
	{
		$add_stage('header_utilities_js', 'Generating header utility behavior', 'fail', array('Unable to write scripts/themelab-generated.js'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to write generated header utility behavior.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('header_utilities_js', 'Generating header utility behavior', 'pass', array('Native SMF utility/search relocation behavior generated.'));

	if (!themelab_publish_patch_index($theme_dir . '/index.template.php', !empty($options['color_modes'])))
	{
		$add_stage('register_css', 'Registering generated stylesheet', 'fail', array('index.template.php could not be patched.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to register the generated stylesheet.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('register_css', 'Registering generated stylesheet', 'pass', array('themelab-generated.css and themelab-generated.js registered in template_init().'));

	$header_data = !empty($project['project_data_array']['header']) && is_array($project['project_data_array']['header'])
		? $project['project_data_array']['header']
		: array();
	$header_logo_text = isset($header_data['logo_text']) ? (string) $header_data['logo_text'] : (string) $project['project_name'];

	if (!themelab_publish_patch_header_identity(
		$theme_dir . '/index.template.php',
		$header_assets['logo_filename'],
		$header_logo_text
	))
	{
		$add_stage('header_identity', 'Publishing Header Studio identity', 'fail', array('Curve2 forum-title slot could not be patched.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to publish Header Studio logo identity.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage(
		'header_identity',
		'Publishing Header Studio identity',
		'pass',
		array(
			$header_assets['logo_filename'] !== ''
				? 'Uploaded logo is owned by the generated theme.'
				: 'Header Studio logo text is rendered by the generated theme.'
		)
	);

	if (!themelab_publish_patch_boardindex_stats($theme_dir . '/BoardIndex.template.php'))
	{
		$add_stage('board_stats_card', 'Building modern Board Index stats cards', 'fail', array('BoardIndex.template.php could not be patched.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the modern Board Index stats cards.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('board_stats_card', 'Building modern Board Index stats cards', 'pass', array('Posts and Topics now render as semantic Card Style #1 stat items.'));


	if (!themelab_publish_patch_genericlist_packages($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('package_manager_body', 'Building Package Manager body', 'fail', array('GenericList.template.php could not be patched.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Package Manager structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('package_manager_body', 'Building Package Manager body', 'pass', array('packages_lists_* now render inside a real bordered body'));

	if (!themelab_publish_patch_genericlist_profile_fields($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('profile_fields_body', 'Building Profile Fields bodies', 'fail', array('GenericList.template.php could not be patched for profile fields.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Profile Fields structural bodies.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('profile_fields_body', 'Building Profile Fields bodies', 'pass', array('standard_profile_fields and custom_profile_fields now render inside real bordered bodies'));

	if (!themelab_publish_patch_genericlist_languages($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('language_edit_body', 'Building Edit Languages body', 'fail', array('GenericList.template.php could not be patched for the language list.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Edit Languages structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('language_edit_body', 'Building Edit Languages body', 'pass', array('language_list now renders as one bordered component with its Save controls'));

	if (!themelab_publish_patch_genericlist_smiley_sets($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('smiley_sets_body', 'Building Smiley Sets body', 'fail', array('GenericList.template.php could not be patched for Smiley Sets.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Smiley Sets structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('smiley_sets_body', 'Building Smiley Sets body', 'pass', array('smiley_set_list now renders as one bordered component with both action rows'));

	if (!themelab_publish_patch_genericlist_attachment_browser($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('attachment_browser_body', 'Building Attachment Browser body', 'fail', array('GenericList.template.php could not be patched for Browse Files.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Attachment Browser structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('attachment_browser_body', 'Building Attachment Browser body', 'pass', array('file_list now renders as one bordered component with both Remove selected action rows'));

	if (!themelab_publish_patch_genericlist_admin_members($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('admin_members_body', 'Building Admin Members body', 'fail', array('GenericList.template.php could not be patched for the Members list.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Admin Members structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('admin_members_body', 'Building Admin Members body', 'pass', array('member_list now renders as one bordered component with Delete Selected Members controls'));

	if (!themelab_publish_patch_genericlist_membergroups($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('membergroups_body', 'Building Membergroups bodies', 'fail', array('GenericList.template.php could not be patched for Membergroups.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Membergroups structural bodies.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('membergroups_body', 'Building Membergroups bodies', 'pass', array('regular_membergroups_list and post_count_membergroups_list now render inside real bordered bodies'));

	if (!themelab_publish_patch_genericlist_ban_list($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('ban_list_body', 'Building Ban List body', 'fail', array('GenericList.template.php could not be patched for the Ban list.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Ban List structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('ban_list_body', 'Building Ban List body', 'pass', array('ban_list now renders inside one bordered body with only the bottom Remove selected action.'));


	if (!themelab_publish_patch_genericlist_mail_queue($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('mail_queue_body', 'Building Mail Queue body', 'fail', array('GenericList.template.php could not be patched for Browse Queue.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Mail Queue structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('mail_queue_body', 'Building Mail Queue body', 'pass', array('mail_queue now renders inside one bordered body with only the bottom queue actions.'));

	if (!themelab_publish_patch_genericlist_group_requests($theme_dir . '/GenericList.template.php'))
	{
		$add_stage('group_requests_body', 'Building Membergroup Requests body', 'fail', array('GenericList.template.php could not be patched for Membergroup Requests.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Membergroup Requests structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('group_requests_body', 'Building Membergroup Requests body', 'pass', array('group_request_list now renders inside one connected bordered body with its action controls.'));

	if (!themelab_publish_patch_manage_permissions_index($theme_dir . '/ManagePermissions.template.php'))
	{
		$add_stage('manage_permissions_body', 'Building Manage Permissions body', 'fail', array('ManagePermissions.template.php could not be patched for General permissions.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Manage Permissions structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('manage_permissions_body', 'Building Manage Permissions body', 'pass', array('General permissions table now renders inside a real bordered body.'));

	if (!themelab_publish_patch_permission_profiles($theme_dir . '/ManagePermissions.template.php'))
	{
		$add_stage('permission_profiles_body', 'Building Permission Profiles body', 'fail', array('ManagePermissions.template.php could not be patched for Edit profiles.'));
		return $result;
	}
	$add_stage('permission_profiles_body', 'Building Permission Profiles body', 'pass', array('Edit Profiles list and its actions now render inside a real bordered body; Board permissions uses its native windowbg as the connected body.'));

	if (!themelab_publish_patch_manage_member_search($theme_dir . '/ManageMembers.template.php'))
	{
		$add_stage('admin_member_search_body', 'Building Admin Member Search body', 'fail', array('ManageMembers.template.php could not be patched for Search for Members.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Admin Member Search structural body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('admin_member_search_body', 'Building Admin Member Search body', 'pass', array('Membergroups and post groups now render inside one bordered body with the Search action contained.'));

	if (!themelab_publish_patch_theme_settings($theme_dir . '/Themes.template.php'))
	{
		$add_stage('theme_settings_component', 'Building Theme Settings unified component', 'fail', array('Themes.template.php could not be patched for Theme Settings.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Theme Settings unified component.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('theme_settings_component', 'Building Theme Settings unified component', 'pass', array('Theme Settings now renders as one outer component with connected internal sections'));

	if (!themelab_publish_patch_package_permissions($theme_dir . '/Packages.template.php'))
	{
		$add_stage('package_permissions_body', 'Building File Permissions body', 'fail', array('Packages.template.php could not be patched.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the File Permissions bordered body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('package_permissions_body', 'Building File Permissions body', 'pass', array('File Permissions table now renders inside a real bordered body'));

	if (!themelab_publish_patch_profile_showposts($theme_dir . '/Profile.template.php'))
	{
		$add_stage('profile_showposts', 'Building Profile Show Posts body', 'fail', array('Profile.template.php could not be patched.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Profile Show Posts message body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('profile_showposts', 'Building Profile Show Posts body', 'pass', array('Messages/Topics/Attachments now render inside one connected Theme Lab body.'));

	if (!themelab_publish_patch_admin_alert_preferences($theme_dir . '/Profile.template.php'))
	{
		$add_stage('admin_alert_preferences_body', 'Building Admin Alert Preferences body', 'fail', array('Profile.template.php could not be patched for admin notification preferences.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Admin Alert Preferences bordered body.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('admin_alert_preferences_body', 'Building Admin Alert Preferences body', 'pass', array('Features and Options > Notifications alert matrix now renders inside a real bordered body'));

	if (!themelab_publish_patch_manage_boards($theme_dir . '/ManageBoards.template.php'))
	{
		$add_stage('manage_boards_collection', 'Building Modify Boards connected collection', 'fail', array('ManageBoards.template.php could not be patched.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to build the Modify Boards connected collection.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$add_stage('manage_boards_collection', 'Building Modify Boards connected collection', 'pass', array('Categories and board rows now render as one bordered admin collection.'));

	$theme_info = '<?xml version="1.0"?>' . "\n" . '<theme-info xmlns="http://www.simplemachines.org/xml/theme-info" xmlns:smf="http://www.simplemachines.org/">' . "\n" .
		"	<id>" . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</id>' . "\n" .
		"	<version>" . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '</version>' . "\n" .
		"	<name>" . htmlspecialchars(htmlspecialchars_decode((string) $project['project_name'], ENT_QUOTES), ENT_QUOTES, 'UTF-8') . '</name>' . "\n" .
		"	<author>" . htmlspecialchars(htmlspecialchars_decode((string) $project['author'], ENT_QUOTES), ENT_QUOTES, 'UTF-8') . '</author>' . "\n" .
		"	<install for=\"2.1 - 2.1.99\" />" . "\n" .
				'</theme-info>' . "\n";
	$theme_info_written = @file_put_contents($theme_dir . '/theme_info.xml', $theme_info);

	/* RC6.1.21: native round-trip Workspace manifest. */
	$workspace_project_data = !empty($project['project_data_array']) && is_array($project['project_data_array'])
		? $project['project_data_array']
		: array();
	if (empty($workspace_project_data) && !empty($project['project_data']))
	{
		$decoded_project_data = json_decode($project['project_data'], true);
		if (is_array($decoded_project_data))
			$workspace_project_data = $decoded_project_data;
	}
	if (empty($workspace_project_data['schema']))
		$workspace_project_data['schema'] = 1;

	$workspace_manifest = array(
		'generator' => 'Theme Lab',
		'schema' => 1,
		'compiler_version' => '1.0.2',
		'exported_at' => gmdate('c'),
		'source' => 'published-theme',
		'workspace' => array(
			'project_name' => (string) $project['project_name'],
			'author' => (string) $project['author'],
			'theme_version' => (string) $project['theme_version'],
			'description' => isset($project['description']) ? (string) $project['description'] : '',
			'base_theme' => isset($project['base_theme']) ? (string) $project['base_theme'] : 'default',
			'project_data' => $workspace_project_data,
		),
	);
	$workspace_manifest_written = @file_put_contents(
		$theme_dir . '/themelab-workspace.json',
		json_encode($workspace_manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
	);

	$cert = themelab_publish_validate($project);
	$report = "Theme Lab Certification Report\n\nTheme: {$project['project_name']}\nVersion: {$project['theme_version']}\nOriginal Author: {$project['author']}\nPublisher: {$publisher}\nSMF: 2.1.x\nStatus: " . ($cert['ready'] ? 'PASS' : 'FAIL') . "\n\n";
	foreach ($cert['items'] as $check) $report .= ($check['status'] ? '[PASS] ' : '[FAIL] ') . $check['label'] . "\n";
	@file_put_contents($theme_dir . '/THEMELAB_CERTIFICATION.txt', $report);
	if (!empty($options['readme'])) @file_put_contents($theme_dir . '/README.txt', $project['project_name'] . "\n\n" . $project['description'] . "\n\nOriginal Author: " . $project['author'] . "\nPublished By: " . $publisher . "\nGenerated by Theme Lab.\n");
	if (!empty($options['license'])) @file_put_contents($theme_dir . '/LICENSE.txt', "Copyright (c) " . date('Y') . ' ' . $project['author'] . "\n\nAll rights reserved unless the author states otherwise.\n");
	if (!empty($options['changelog'])) @file_put_contents($theme_dir . '/CHANGELOG.txt', "{$project['project_name']} {$project['theme_version']}\nPublished By: {$publisher}\nOriginal Author: {$project['author']}\n- Theme Lab publication.\n");
	$authors = "# " . $project['project_name'] . "\n\nOriginal Author: " . $project['author'] . "\nPublisher: " . $publisher . "\nTheme Version: " . $project['theme_version'] . "\nBuilt With: Theme Lab 1.0.2\n";
	@file_put_contents($theme_dir . '/AUTHORS.md', $authors);
	$add_stage('write_files', 'Writing theme files', $theme_info_written !== false ? 'pass' : 'fail', array('Complete SMF theme metadata written', 'Original author preserved: ' . $project['author'], 'Publisher recorded automatically: ' . $publisher, 'AUTHORS.md and certification records written', 'Native themelab-workspace.json manifest written for round-trip editing', 'Optional documentation files written'));

	$required = array('theme_info.xml', 'index.template.php', 'css/index.css', 'css/themelab-generated.css', 'scripts/themelab-generated.js', 'AUTHORS.md', 'themelab-workspace.json');
	$missing = array();
	foreach ($required as $file) if (!is_file($theme_dir . '/' . $file)) $missing[] = $file;
	$metadata_missing = array();
	if (is_file($theme_dir . '/theme_info.xml'))
	{
		$metadata_text = (string) @file_get_contents($theme_dir . '/theme_info.xml');
		foreach (array('id', 'version', 'name', 'author', 'install') as $field)
			if (!preg_match('~<' . preg_quote($field, '~') . '(?:\s|>)~i', $metadata_text)) $metadata_missing[] = $field;
	}
	$files_ok = empty($missing) && empty($metadata_missing);
	$verify_details = $files_ok ? array('All ' . count($required) . ' required files found.', 'theme_info.xml identity fields verified: id, version, name, author, install') : array();
	if (!empty($missing)) $verify_details[] = 'Missing files: ' . implode(', ', $missing);
	if (!empty($metadata_missing)) $verify_details[] = 'Missing theme metadata: ' . implode(', ', $metadata_missing);
	$add_stage('verify_files', 'Verifying required theme files', $files_ok ? 'pass' : 'fail', $verify_details);
	if (!empty($missing) || !empty($metadata_missing))
	{
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Required theme files are missing.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}

	$filename = $slug . '_' . ($version ?: '1.0.0') . '.zip';
	$zip_path = $exports . '/' . $filename;
	$build_report_path = $theme_dir . '/THEMELAB_BUILD_REPORT.txt';
	@file_put_contents($build_report_path, themelab_publish_build_report_text($project, $pipeline, $inventory));
	$zip = new ZipArchive();
	if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
	{
		$add_stage('archive', 'Creating installation archive', 'fail', array('ZipArchive could not create the output file.'));
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'Unable to create the ZIP archive.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}
	$archive_files = 0;
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme_dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
	foreach ($it as $file)
		if ($file->isFile())
		{
			$zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($theme_dir) + 1));
			$archive_files++;
		}
	$zip->close();
	$add_stage('archive', 'Creating installation archive', 'pass', array('Files archived: ' . $archive_files));

	$verify = new ZipArchive();
	$archive_ok = $verify->open($zip_path) === true;
	$zip_missing = array();
	if ($archive_ok)
	{
		foreach ($required as $file) if ($verify->locateName($file) === false) $zip_missing[] = $file;
		$verify->close();
	}
	$archive_ok = $archive_ok && empty($zip_missing);
	$checksum = '';
	$add_stage('verify_archive', 'Verifying installation archive', $archive_ok ? 'pass' : 'fail', $archive_ok ? array('Archive reopened successfully', 'Required root files confirmed') : array('Archive verification failed' . (!empty($zip_missing) ? ': ' . implode(', ', $zip_missing) : '.')));
	if (!$archive_ok)
	{
		@unlink($zip_path);
		themelab_publish_remove_tree($work);
		return array('success' => false, 'error' => 'The generated ZIP failed verification.', 'pipeline' => $pipeline, 'inventory' => $inventory);
	}

	$elapsed = microtime(true) - $started;
	$size = @filesize($zip_path);
	$add_stage('complete', 'Build complete', 'pass', array('Build time: ' . number_format($elapsed, 2) . ' seconds', 'Package size: ' . number_format($size / 1024, 1) . ' KB'));
	@file_put_contents($build_report_path, themelab_publish_build_report_text($project, $pipeline, $inventory, array('filename' => $filename, 'files' => $archive_files, 'build_seconds' => number_format($elapsed, 2))));
	// Refresh the report inside the already-created archive, then fingerprint the final bytes.
	$zip = new ZipArchive();
	if ($zip->open($zip_path) === true) { $zip->addFile($build_report_path, 'THEMELAB_BUILD_REPORT.txt'); $zip->close(); }
	clearstatcache(true, $zip_path);
	$size = @filesize($zip_path);
	$checksum = function_exists('hash_file') ? hash_file('sha256', $zip_path) : '';
	$pipeline[] = array('key' => 'fingerprint', 'label' => 'Fingerprinting final package', 'status' => 'pass', 'details' => array('Final size: ' . number_format($size / 1024, 1) . ' KB', 'SHA-256: ' . $checksum));
	$version_saved = !empty($project['id_project']) ? themelab_publish_store_version($project['id_project'], $published_version) : false;
	$pipeline[] = array('key' => 'version', 'label' => 'Recording published version', 'status' => $version_saved ? 'pass' : 'warn', 'details' => array($version_saved ? 'Project version advanced from ' . $source_version . ' to ' . $published_version . '.' : 'Theme package uses version ' . $published_version . '; project record could not be updated automatically.'));
	themelab_publish_remove_tree($work);
	return array('success' => true, 'previous_version' => $source_version, 'published_version' => $published_version, 'publisher' => $publisher, 'filename' => $filename, 'path' => $zip_path, 'size' => $size, 'built_at' => time(), 'pipeline' => $pipeline, 'inventory' => $inventory, 'token_total' => $total_saved, 'compiler_values' => $css_audit['accounted_saved_values'], 'css_values' => $css_audit['written_values'], 'unaccounted_values' => $css_audit['unaccounted_saved_values'], 'file_count' => $archive_files, 'checksum' => $checksum, 'duration' => $elapsed);
}

function themelab_publish_download()
{
	global $boarddir, $context;
	if (empty($_GET['download'])) return false;
	checkSession('get');
	$filename = basename((string) $_GET['download']);
	$path = $boarddir . '/Packages/ThemeLabExports/' . $filename;
	if (!is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'zip') fatal_lang_error('no_access', false);
	while (ob_get_level()) @ob_end_clean();
	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
	header('Content-Length: ' . filesize($path));
	header('Cache-Control: private, no-store');
	readfile($path);
	exit;
}

function themelab_prepare_publish_studio($project)
{
	global $context;
	themelab_publish_download();
	$context['themelab_publish_validation'] = themelab_publish_validate($project);
	$context['themelab_publish_theme_is_dark'] = themelab_publish_theme_is_dark($project);
	$palette = !empty($project['project_data_array']['palette']) && is_array($project['project_data_array']['palette'])
		? $project['project_data_array']['palette']
		: array();
	$context['themelab_publish_color_modes_requested'] = !empty($palette['color_mode']) && $palette['color_mode'] === 'light_dark';
	$context['themelab_publish_color_modes_enabled'] = $context['themelab_publish_color_modes_requested'] && empty($context['themelab_publish_theme_is_dark']);
	$context['themelab_publish_result'] = array();
	if (!empty($_POST['themelab_publish_theme']))
	{
		checkSession();
		if (!$context['themelab_publish_validation']['ready'])
			$context['themelab_publish_result'] = array('success' => false, 'error' => 'Certification must pass before publishing.');
		else
		{
			$options = array(
				'readme' => !empty($_POST['include_readme']),
				'license' => !empty($_POST['include_license']),
				'changelog' => !empty($_POST['include_changelog']),
				'color_modes' => !empty($context['themelab_publish_color_modes_enabled']),
			);
			$context['themelab_publish_result'] = themelab_publish_build($project, $options);
		}
	}
	$context['themelab_preview_settings'] = themelab_build_preview_settings($project);
}
