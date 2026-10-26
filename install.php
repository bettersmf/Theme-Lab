<?php

/**
 * Theme Lab Installer
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

global $sourcedir, $smcFunc;

$theme_lab_source = $sourcedir . '/ThemeLab.php';

if (file_exists($theme_lab_source))
	require_once($theme_lab_source);

if (function_exists('add_integration_function'))
{
	add_integration_function(
		'integrate_admin_areas',
		'themelab_admin_areas',
		true,
		$theme_lab_source
	);
}

// Create Project Manager table.
db_extend('packages');

$smcFunc['db_create_table'](
	'{db_prefix}themelab_projects',
	array(
		array(
			'name' => 'id_project',
			'type' => 'mediumint',
			'size' => 8,
			'unsigned' => true,
			'auto' => true,
		),
		array(
			'name' => 'project_name',
			'type' => 'varchar',
			'size' => 120,
			'default' => '',
		),
		array(
			'name' => 'author',
			'type' => 'varchar',
			'size' => 120,
			'default' => '',
		),
		array(
			'name' => 'theme_version',
			'type' => 'varchar',
			'size' => 30,
			'default' => '1.0',
		),
		array(
			'name' => 'description',
			'type' => 'text',
		),
		array(
			'name' => 'base_theme',
			'type' => 'varchar',
			'size' => 60,
			'default' => 'default',
		),
		array(
			'name' => 'project_data',
			'type' => 'mediumtext',
		),
		array(
			'name' => 'date_created',
			'type' => 'int',
			'size' => 10,
			'unsigned' => true,
			'default' => 0,
		),
		array(
			'name' => 'date_modified',
			'type' => 'int',
			'size' => 10,
			'unsigned' => true,
			'default' => 0,
		),
	),
	array(
		array(
			'type' => 'primary',
			'columns' => array('id_project'),
		),
		array(
			'type' => 'key',
			'name' => 'idx_date_modified',
			'columns' => array('date_modified'),
		),
	),
	array(),
	'ignore'
);