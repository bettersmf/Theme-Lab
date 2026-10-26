<?php

/**
 * Theme Lab Uninstaller
 *
 * Removes Theme Lab's SMF integration and runtime settings.
 * Workspace data is handled separately by uninstall-database.php so SMF's
 * native optional database-removal choice can preserve saved Workspaces.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

global $sourcedir, $smcFunc, $modSettings;

$theme_lab_source = $sourcedir . '/ThemeLab.php';

/*
 * Theme Lab was registered with a source-file path.  SMF stores that path as
 * part of the integration-hook entry, so removing only the function name can
 * leave the hook behind.  Remove both the exact current registration and the
 * old pathless form for backward compatibility with earlier RC builds.
 */
if (function_exists('remove_integration_function'))
{
	remove_integration_function(
		'integrate_admin_areas',
		'themelab_admin_areas',
		true,
		$theme_lab_source
	);

	remove_integration_function(
		'integrate_admin_areas',
		'themelab_admin_areas',
		true
	);
}

/*
 * Defensive cleanup for installations upgraded through older Theme Lab RCs.
 * If a stale integration value survived, remove only Theme Lab's entry and
 * preserve every other integration registered on the same hook.
 */
$request = $smcFunc['db_query']('', '
	SELECT value
	FROM {db_prefix}settings
	WHERE variable = {string:hook}
	LIMIT 1',
	array(
		'hook' => 'integrate_admin_areas',
	)
);

if ($row = $smcFunc['db_fetch_assoc']($request))
{
	$entries = array_filter(array_map('trim', explode(',', (string) $row['value'])), 'strlen');
	$clean = array();

	foreach ($entries as $entry)
	{
		$parts = explode('|', $entry, 2);
		if (isset($parts[0]) && trim($parts[0]) === 'themelab_admin_areas')
			continue;

		$clean[] = $entry;
	}

	$clean_value = implode(',', $clean);

	if ($clean_value !== (string) $row['value'])
	{
		if ($clean_value === '')
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}settings
				WHERE variable = {string:hook}',
				array(
					'hook' => 'integrate_admin_areas',
				)
			);
		}
		else
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}settings
				SET value = {string:value}
				WHERE variable = {string:hook}',
				array(
					'hook' => 'integrate_admin_areas',
					'value' => $clean_value,
				)
			);
		}
	}
}
$smcFunc['db_free_result']($request);

// Remove Theme Lab runtime settings left by current and legacy Workspace code.
$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable IN ({array_string:settings})',
	array(
		'settings' => array(
			'themelab_active_project',
			'themelab_project',
		),
	)
);

unset($modSettings['themelab_active_project'], $modSettings['themelab_project']);

if (isset($_SESSION['themelab_active_project']))
	unset($_SESSION['themelab_active_project']);

// Workspace data is intentionally not removed here. See uninstall-database.php.
