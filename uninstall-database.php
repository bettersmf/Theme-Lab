<?php

/**
 * Theme Lab optional database uninstaller.
 *
 * SMF executes package <database> uninstall actions only when the administrator
 * chooses to remove database changes. Keeping that option unchecked preserves
 * Theme Lab Workspaces across uninstall/reinstall cycles.
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

global $smcFunc;

db_extend('packages');
$smcFunc['db_drop_table']('{db_prefix}themelab_projects');
