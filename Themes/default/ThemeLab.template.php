<?php

/**
 * Theme Lab Template
 *
 * @package ThemeLab
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Theme Lab environment check page.
 */
function template_themelab_environment()
{
	global $context, $txt, $scripturl;

	$checks = !empty($context['themelab_checks']['items'])
		? $context['themelab_checks']['items']
		: array();

	$is_ready = !empty($context['themelab_checks']['ready']);

	$passed = isset($context['themelab_checks']['passed'])
		? (int) $context['themelab_checks']['passed']
		: 0;

	$total = isset($context['themelab_checks']['total'])
		? (int) $context['themelab_checks']['total']
		: count($checks);

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['themelab_environment'], '</h3>
	</div>

	<div class="windowbg">
		<p>', $txt['themelab_environment_intro'], '</p>

		<p>
			<strong>', $txt['themelab_summary'], ':</strong>
			', $passed, ' / ', $total, ' ', $txt['themelab_checks_passed'], '
		</p>';

	if ($is_ready)
	{
		echo '
		<div class="infobox">
			', $txt['themelab_ready'], '
		</div>

		<p>
			<a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=project">
				', $txt['themelab_continue_project'], '
			</a>
		</p>';
	}
	else
	{
		echo '
		<div class="errorbox">
			', $txt['themelab_not_ready'], '
		</div>';
	}

	echo '
	</div>

	<div class="cat_bar">
		<h3 class="catbg">', $txt['themelab_environment_results'], '</h3>
	</div>

	<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th scope="col">', $txt['themelab_check'], '</th>
				<th scope="col">', $txt['themelab_status'], '</th>
				<th scope="col">', $txt['themelab_details'], '</th>
			</tr>
		</thead>

		<tbody>';

	foreach ($checks as $check)
	{
		$status = !empty($check['status']);

		$is_optional = !empty($check['optional']);

		$status_text = $status
			? $txt['themelab_pass']
			: ($is_optional ? $txt['themelab_optional'] : $txt['themelab_fail']);

		$status_class = $status
			? 'success'
			: ($is_optional ? 'warning' : 'error');

		echo '
			<tr class="windowbg">
				<td>
					<strong>', $check['label'], '</strong>';

		if (!empty($check['help']))
		{
			echo '
					<br>
					<span class="smalltext">
						', $check['help'], '
					</span>';
		}

		if (!$status && !$is_optional && !empty($check['fix']))
		{
			echo '
					<br>
					<span class="smalltext">
						<strong>', $txt['themelab_suggested_fix'], ':</strong>
						', $check['fix'], '
					</span>';
		}

		echo '
				</td>

				<td>
					<strong class="', $status_class, '">
						', $status_text, '
					</strong>
				</td>

				<td>
					<code>', $check['details'], '</code>
				</td>
			</tr>';
	}

	echo '
		</tbody>
	</table>';
}

/**
 * Theme Lab project editor.
 */
function template_themelab_project()
{
	global $txt, $scripturl, $context;

	if (!empty($context['themelab_submitted_project']))
		$workspace = $context['themelab_submitted_project'];
	elseif (!empty($context['themelab_active_project']))
		$workspace = $context['themelab_active_project'];
	else
		$workspace = array();

	$id_project = !empty($workspace['id_project']) ? (int) $workspace['id_project'] : 0;
	$project_name = !empty($workspace['project_name']) ? (string) $workspace['project_name'] : '';
	$author = !empty($workspace['author']) ? (string) $workspace['author'] : '';
	$theme_version = !empty($workspace['theme_version']) ? (string) $workspace['theme_version'] : '1.0';
	$base_theme = !empty($workspace['base_theme']) ? (string) $workspace['base_theme'] : 'default';
	$description = !empty($workspace['description']) ? (string) $workspace['description'] : '';
	$workspaces = !empty($context['themelab_projects']) && is_array($context['themelab_projects']) ? $context['themelab_projects'] : array();

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['themelab_workspace'], '</h3>
	</div>

	<div class="windowbg themelab_workspace_intro" style="margin-bottom:0 !important;">
		<div>
			<p class="themelab_workspace_lead">', $txt['themelab_workspace_intro'], '</p>
			<p class="smalltext">', $txt['themelab_workspace_intro_detail'], '</p>
		</div>
		<div class="themelab_workspace_actions">
			<a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=project;new;', $context['session_var'], '=', $context['session_id'], '">+ ', $txt['themelab_new_workspace'], '</a>
			<a class="button" href="#themelab_import_workspace">', $txt['themelab_import_workspace'], '</a>
		</div>
	</div>';

	if (isset($_REQUEST['new_workspace']))
		echo '<div class="infobox">', $txt['themelab_new_workspace_ready'], '</div>';
	if (isset($_REQUEST['imported']))
		echo '<div class="infobox">', $txt['themelab_workspace_imported'], '</div>';
	if (isset($_REQUEST['opened']))
		echo '<div class="infobox">', $txt['themelab_workspace_opened'], '</div>';
	if (isset($_REQUEST['deleted']))
		echo '<div class="infobox">', $txt['themelab_workspace_deleted'], '</div>';

	if (!empty($context['themelab_project_errors']))
	{
		echo '<div class="errorbox"><strong>', $txt['themelab_workspace_has_errors'], '</strong><ul>';
		foreach ($context['themelab_project_errors'] as $error)
			if (!empty($txt[$error]))
				echo '<li>', $txt[$error], '</li>';
		echo '</ul></div>';
	}

	if (!empty($context['themelab_import_errors']))
	{
		echo '<div class="errorbox"><strong>', $txt['themelab_import_failed'], '</strong><ul>';
		foreach ($context['themelab_import_errors'] as $error)
			if (!empty($txt[$error]))
				echo '<li>', $txt[$error], '</li>';
		echo '</ul></div>';
	}

	echo '
	<div class="themelab_workspace_grid" style="margin:14px 0 0 !important; padding-top:0 !important;">
		<section class="themelab_workspace_primary">
			<div class="cat_bar" style="margin-top:0 !important;">
				<h3 class="catbg">', $id_project > 0 ? $txt['themelab_current_workspace'] : $txt['themelab_new_workspace'], '</h3>
			</div>

			<form action="', $scripturl, '?action=admin;area=themelab;sa=project" method="post" accept-charset="UTF-8">
				<div class="windowbg">
					<dl class="settings">
						<dt><strong>', $txt['themelab_theme_name'], '</strong><br><span class="smalltext">', $txt['themelab_theme_name_desc'], '</span></dt>
						<dd><input type="text" name="themelab_theme_name" value="', $project_name, '" size="40" required></dd>

						<dt><strong>', $txt['themelab_theme_author'], '</strong></dt>
						<dd><input type="text" name="themelab_theme_author" value="', $author, '" size="40" required></dd>

						<dt><strong>', $txt['themelab_theme_version'], '</strong></dt>
						<dd><input type="text" name="themelab_theme_version" value="', $theme_version, '" size="12" required></dd>

						<dt><strong>', $txt['themelab_base_theme'], '</strong></dt>
						<dd><select name="themelab_base_theme"><option value="default"', $base_theme === 'default' ? ' selected' : '', '>Curve2</option></select></dd>

						<dt><strong>', $txt['themelab_theme_description'], '</strong></dt>
						<dd><textarea name="themelab_theme_description" rows="4" cols="50">', $description, '</textarea></dd>
					</dl>

					<div class="righttext">
						<input type="submit" name="themelab_save_project" class="button" value="', $txt['themelab_save_workspace'], '">
					</div>
				</div>
				<input type="hidden" name="id_project" value="', $id_project, '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</section>

		<section class="themelab_workspace_secondary" id="themelab_import_workspace">
			<div class="cat_bar" style="margin-top:0 !important;">
				<h3 class="catbg">', $txt['themelab_import_workspace'], '</h3>
			</div>
			<div class="windowbg">
				<p>', $txt['themelab_import_workspace_desc'], '</p>
				<form action="', $scripturl, '?action=admin;area=themelab;sa=project#themelab_import_workspace" method="post" enctype="multipart/form-data" accept-charset="UTF-8">
					<input type="file" name="themelab_workspace_file" accept=".json,.zip,application/json,application/zip" required>
					<div class="themelab_workspace_import_action">
						<input type="submit" name="themelab_import_workspace" class="button" value="', $txt['themelab_import_workspace'], '">
					</div>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</form>
				<p class="smalltext">', $txt['themelab_import_workspace_help'], '</p>
			</div>
		</section>
	</div>';

	if (!empty($workspaces))
	{
		echo '
	<div class="cat_bar themelab_saved_workspaces_header">
		<h3 class="catbg">', $txt['themelab_saved_workspaces'], '</h3>
	</div>
	<div class="windowbg">
		<div class="themelab_workspace_list">';

		foreach ($workspaces as $saved)
		{
			$saved_id = (int) $saved['id_project'];
			$is_active = $saved_id === $id_project;
			$name = (string) $saved['project_name'];
			$saved_author = (string) $saved['author'];
			$saved_version = (string) $saved['theme_version'];

			echo '
			<div class="themelab_workspace_row', $is_active ? ' is_active' : '', '">
				<div class="themelab_workspace_identity">
					<strong>', $name, '</strong>', $is_active ? ' <span class="themelab_workspace_badge">' . $txt['themelab_workspace_active'] . '</span>' : '', '
					<div class="smalltext">', $saved_author, ' &middot; ', $txt['themelab_theme_version'], ' ', $saved_version, '</div>
				</div>
				<div class="themelab_workspace_row_actions">
					<a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=project;open=', $saved_id, ';', $context['session_var'], '=', $context['session_id'], '">', $is_active ? $txt['themelab_continue_editing'] : $txt['themelab_open_workspace'], '</a>
					<form action="', $scripturl, '?action=admin;area=themelab;sa=project" method="post" onsubmit="return confirm(\'', $txt['themelab_confirm_delete_workspace'], '\');">
						<input type="hidden" name="id_project" value="', $saved_id, '">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="submit" name="themelab_delete_project" class="button" value="', $txt['themelab_delete_workspace'], '">
					</form>
				</div>
			</div>';
		}

		echo '
		</div>
	</div>';
	}
}

/**
 * Theme Lab Palette Studio.
 */
function template_themelab_palette()
{
	global $context, $txt, $scripturl;

	$project = !empty($context['themelab_active_project'])
		? $context['themelab_active_project']
		: array();

	$presets = !empty($context['themelab_palette_presets'])
		? $context['themelab_palette_presets']
		: array();

	$tokens = !empty($context['themelab_palette_tokens'])
		? $context['themelab_palette_tokens']
		: array();

	$palette = !empty($context['themelab_project_palette'])
		? $context['themelab_project_palette']
		: array();

	$selected_preset = !empty($palette['preset'])
		? $palette['preset']
		: '';

	$colors = !empty($palette['colors']) && is_array($palette['colors'])
		? $palette['colors']
		: array();

	$color_mode = !empty($palette['color_mode']) && $palette['color_mode'] === 'light_dark'
		? 'light_dark'
		: 'single';

	// Backfill newly introduced editable tokens from the selected preset.
	if (!empty($selected_preset) && !empty($presets[$selected_preset]['colors']))
	{
		foreach ($tokens as $token_key => $token_label)
		{
			if (empty($colors[$token_key]) && !empty($presets[$selected_preset]['colors'][$token_key]))
				$colors[$token_key] = $presets[$selected_preset]['colors'][$token_key];
		}
	}

	// Link states are first-class Palette tokens. Older workspaces/presets are
	// upgraded non-destructively with sensible fallbacks until explicitly saved.
	if (empty($colors['link_hover']))
		$colors['link_hover'] = !empty($colors['primary']) ? $colors['primary'] : (!empty($colors['link']) ? $colors['link'] : '#3465A4');
	if (empty($colors['link_visited']))
		$colors['link_visited'] = !empty($colors['accent']) ? $colors['accent'] : (!empty($colors['link']) ? $colors['link'] : '#204A87');

	$project_name = !empty($project['project_name'])
		? $project['project_name']
		: '';

	$preview_settings = !empty($context['themelab_preview_settings'])
		&& is_array($context['themelab_preview_settings'])
			? $context['themelab_preview_settings']
			: array();

	// Only Palette-owned colors may override the shared preview here.
	foreach ($tokens as $token_key => $token_label)
	{
		if (!empty($colors[$token_key]))
			$preview_settings[$token_key] = $colors[$token_key];
	}

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['themelab_palette'], '</h3>
	</div>

	<div class="windowbg">
		<p>',
			sprintf(
				$txt['themelab_palette_project'],
				$project_name
			),
		'</p>

		<p>', $txt['themelab_palette_intro'], '</p>
	</div>';

	if (isset($_REQUEST['palette_saved']))
	{
		echo '
	<div class="infobox">
		', $txt['themelab_palette_saved'], '
	</div>';
	}

	if (isset($_REQUEST['palette_error']))
	{
		echo '
	<div class="errorbox">
		', $txt['themelab_palette_error'], '
	</div>';
	}

	echo '
	<div class="themelab_studio_layout themelab_palette_workspace">
		<div class="themelab_studio_controls">
	<form
		action="', $scripturl, '?action=admin;area=themelab;sa=palette"
		method="post"
		accept-charset="UTF-8"
	>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themelab_choose_palette'], '</h3>
		</div>

		<div class="windowbg">
			<div class="themelab_palette_grid">';

	foreach ($presets as $preset_key => $preset)
	{
		$is_selected = $selected_preset === $preset_key;

		echo '
				<label class="themelab_palette_card', $is_selected ? ' is_selected' : '', '">
					<div class="themelab_palette_card_header">
						<div class="themelab_palette_card_title">
							<input
								type="radio"
								name="themelab_palette_preset"
								value="', $preset_key, '"',
								$is_selected ? ' checked' : '',
							'>

							<strong>', $preset['name'], '</strong>
						</div>

						<span class="themelab_palette_selected_badge">
							&#10003; ', $txt['themelab_selected'], '
						</span>
					</div>

					<div class="themelab_palette_swatches">';

		foreach (array_slice($preset['colors'], 0, 6, true) as $color)
		{
			echo '
						<span
							class="themelab_palette_swatch"
							title="', $color, '"
							style="background-color:', $color, ';"
						></span>';
		}

		echo '
					</div>

					<div class="themelab_palette_description smalltext">
						', $preset['description'], '
					</div>

					<div class="themelab_palette_action">
						<button
							type="submit"
							name="themelab_load_palette"
							value="', $preset_key, '"
							class="button"
						>
							', $txt['themelab_use_palette'], '
						</button>
					</div>
				</label>';
	}

	echo '
			</div>
		</div>

		<input
			type="hidden"
			name="', $context['session_var'], '"
			value="', $context['session_id'], '"
		>
	</form>';

		if (!empty($colors))
	{
		$customized = !empty($palette['customized'])
			&& is_array($palette['customized'])
				? $palette['customized']
				: array();

		echo '
	<div class="themelab_palette_section">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themelab_palette_designer'], '</h3>
		</div>

		<div class="windowbg">
			<p>', $txt['themelab_palette_designer_intro'], '</p>';
			echo '<p class="smalltext"><strong>Global SMF rows:</strong> Standard Row / Surface and Alternate Row / Surface control general Curve2 rows and panels outside the Board Index, including Package Manager, Error Log, admin lists, and other alternating tables. Board Index Studio keeps its own row colors.</p>';
			echo '<p class="smalltext themelab_palette_authority_note"><strong>Studio ownership:</strong> Palette controls the global color foundation only. Applying a palette preserves Header, Footer, Gradients, Buttons, Navigation, and Board Index. “Reset Colors to Preset” is the deliberate exception and resets color-owned settings across the theme.</p>';

		if (isset($_REQUEST['colors_saved']))
		{
			echo '
			<div class="infobox">
				', $txt['themelab_colors_saved'], '
			</div>';
		}

		if (isset($_REQUEST['colors_reset']))
		{
			echo '
			<div class="infobox">
				', $txt['themelab_colors_reset'], '
			</div>';
		}

		if (isset($_REQUEST['color_error']))
		{
			echo '
			<div class="errorbox">
				', $txt['themelab_color_error'], '
			</div>';
		}

		echo '
			<form
				action="', $scripturl, '?action=admin;area=themelab;sa=palette"
				method="post"
				accept-charset="UTF-8"
			>
				<div class="themelab_palette_mode_panel">
					<strong>', $txt['themelab_palette_color_mode'], '</strong>
					<p class="smalltext">', $txt['themelab_palette_color_mode_desc'], '</p>
					<div class="themelab_palette_mode_choices">
						<label>
							<input type="radio" name="themelab_color_mode" value="single"', $color_mode === 'single' ? ' checked' : '', '>
							<span><strong>', $txt['themelab_palette_color_mode_single'], '</strong><small>', $txt['themelab_palette_color_mode_single_desc'], '</small></span>
						</label>
						<label>
							<input type="radio" name="themelab_color_mode" value="light_dark"', $color_mode === 'light_dark' ? ' checked' : '', '>
							<span><strong>', $txt['themelab_palette_color_mode_light_dark'], '</strong><small>', $txt['themelab_palette_color_mode_light_dark_desc'], '</small></span>
						</label>
					</div>
				</div>

				<div class="themelab_color_editor_grid">';

		foreach ($tokens as $token_key => $token_label)
		{
			if (empty($colors[$token_key]))
				continue;

			$is_customized = !empty($customized[$token_key]);

			echo '
					<div class="themelab_color_editor', $is_customized ? ' is_customized' : '', '">
						<div class="themelab_color_editor_heading">
							<strong>', $token_label, '</strong>';

			if ($is_customized)
			{
				echo '
							<span class="themelab_custom_badge">
								', $txt['themelab_customized'], '
							</span>';
			}

			echo '
						</div>

						<div class="themelab_color_controls">
							<input
								type="color"
								class="themelab_color_picker"
								value="', $colors[$token_key], '"
								data-token="', $token_key, '"
								aria-label="', $token_label, '"
							>

							<input
								type="text"
								name="themelab_color[', $token_key, ']"
								class="themelab_color_hex themelab_palette_color_hex"
								data-token="', $token_key, '"
								id="themelab_color_', $token_key, '"
								value="', $colors[$token_key], '"
								maxlength="7"
								pattern="^#[0-9A-Fa-f]{6}$"
								required
							>
						</div>
					</div>';
		}

		echo '
				</div>

				<div class="themelab_palette_form_actions">
					<button
						type="submit"
						name="themelab_reset_colors"
						value="1"
						class="button"
						onclick="return confirm(\'',
							$txt['themelab_confirm_reset_colors'],
						'\');"
					>
						', $txt['themelab_reset_colors'], '
					</button>

					<button
						type="submit"
						name="themelab_save_colors"
						value="1"
						class="button"
					>
						', $txt['themelab_save_colors'], '
					</button>
				</div>

				<input
					type="hidden"
					name="', $context['session_var'], '"
					value="', $context['session_id'], '"
				>
			</form>
		</div>
	</div>';

	}

	echo '
		</div>';

	$preview_settings['preview_mode'] = 'palette';
	template_themelab_mini_forum_preview($preview_settings);

	echo '
	</div>

	<script>
	document.addEventListener("DOMContentLoaded", function () {
		var cards = document.querySelectorAll(".themelab_palette_card");

		cards.forEach(function (card) {
			var radio = card.querySelector(\'input[type="radio"]\');

			card.addEventListener("click", function (event) {
				if (
					event.target.tagName.toLowerCase() === "button"
					|| event.target.closest("button")
				) {
					return;
				}

				cards.forEach(function (otherCard) {
					otherCard.classList.remove("is_selected");
				});

				if (radio) {
					radio.checked = true;
				}

				card.classList.add("is_selected");
			});
		});
	});
	</script>';
}

/**
 * Theme Lab Layout Studio.
 */
/**
 * Render previous/next navigation for registry-driven Theme Lab Studios.
 *
 * This helper intentionally lives in the shared Theme Lab template so any
 * Studio can use it without depending on a Studio-specific template file.
 */
function template_themelab_studio_navigation()
{
	global $context, $scripturl;

	$registry = !empty($context['themelab_registry']) && is_array($context['themelab_registry'])
		? $context['themelab_registry']
		: array();
	$previous = !empty($context['themelab_previous_studio'])
		? $context['themelab_previous_studio']
		: '';
	$next = !empty($context['themelab_next_studio'])
		? $context['themelab_next_studio']
		: '';

	if (($previous === '' || !isset($registry[$previous])) && ($next === '' || !isset($registry[$next])))
		return;

	echo "\n\t<div class=\"themelab_layout_actions themelab_studio_navigation\">";

	if ($previous !== '' && isset($registry[$previous]))
		echo "\n\t\t<a class=\"button\" href=\"", $scripturl, '?action=admin;area=themelab;sa=', rawurlencode($previous), "\">", htmlspecialchars($registry[$previous]['label'], ENT_QUOTES, 'UTF-8'), '</a>';

	if ($next !== '' && isset($registry[$next]))
		echo "\n\t\t<a class=\"button\" href=\"", $scripturl, '?action=admin;area=themelab;sa=', rawurlencode($next), "\">", htmlspecialchars($registry[$next]['label'], ENT_QUOTES, 'UTF-8'), '</a>';

	echo "\n\t</div>";
}

function template_themelab_gradients()
{
	global $context, $txt, $scripturl;

	$project = $context['themelab_active_project'];
	$gradients = $context['themelab_gradients'];
	$surfaces = $context['themelab_gradient_surfaces'];
	$project_name = htmlspecialchars($project['project_name'], ENT_QUOTES, 'UTF-8');

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['themelab_gradients'], '</h3></div>
	<div class="windowbg">
		<p>', sprintf($txt['themelab_gradients_project'], $project_name), '</p>
		<p>', $txt['themelab_gradients_intro'], '</p>
	</div>';

	if (!empty($_REQUEST['gradients_saved']))
		echo '<div class="noticebox">', $txt['themelab_gradients_saved'], '</div>';

	if (!empty($context['themelab_gradient_errors']))
	{
		echo '<div class="errorbox"><ul>';
		foreach ($context['themelab_gradient_errors'] as $error)
			echo '<li>', isset($txt[$error]) ? $txt[$error] : $error, '</li>';
		echo '</ul></div>';
	}

	echo '
	<form id="themelab_gradients_form" action="', $scripturl, '?action=admin;area=themelab;sa=gradients" method="post" accept-charset="UTF-8" novalidate>
		<input type="hidden" name="themelab_save_gradients" value="1">
		<div class="cat_bar"><h3 class="catbg">', $txt['themelab_gradients_settings'], '</h3></div>
		<div class="windowbg themelab_workspace_window">
			<div class="themelab_studio_layout">
				<div class="themelab_studio_controls themelab_gradient_studio_controls">';

	foreach ($surfaces as $surface => $definition)
	{
		$g = $gradients[$surface];
		$prefix = 'themelab_gradient_' . $surface;
		echo '
					<section class="themelab_gradient_surface" data-gradient-surface="', $surface, '">
						<div class="themelab_gradient_surface_heading">
							<div>
								<h4>', $txt[$definition['label']], '</h4>
								<p>', $txt['themelab_gradient_surface_help'], '</p>
							</div>
							<label class="themelab_switch_row">
								<input type="checkbox" id="', $prefix, '_enabled" name="', $prefix, '_enabled" value="1"', !empty($g['enabled']) ? ' checked' : '', '>
								<span>', $txt['themelab_gradient_enable'], '</span>
							</label>
						</div>
						<div class="themelab_gradient_sample', empty($g['enabled']) ? ' is_disabled' : '', '" id="', $prefix, '_sample" style="background:', htmlspecialchars(themelab_build_gradient_css($g), ENT_QUOTES, 'UTF-8'), '"></div>
						<div class="themelab_gradient_state', empty($g['enabled']) ? ' is_inactive' : '', '" id="', $prefix, '_state" aria-live="polite">', empty($g['enabled']) ? $txt['themelab_gradient_inactive'] : $txt['themelab_gradient_active'], '</div>
						<div class="themelab_gradient_fields">
							<label><strong>', $txt['themelab_gradient_start_color'], '</strong><span class="themelab_color_control"><input type="text" id="', $prefix, '_start" name="', $prefix, '_start" class="themelab_color_hex themelab_studio_color_hex" value="', htmlspecialchars($g['start'], ENT_QUOTES, 'UTF-8'), '" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$"><input type="color" data-color-target="', $prefix, '_start" value="', htmlspecialchars($g['start'], ENT_QUOTES, 'UTF-8'), '"></span></label>
							<label><strong>', $txt['themelab_gradient_end_color'], '</strong><span class="themelab_color_control"><input type="text" id="', $prefix, '_end" name="', $prefix, '_end" class="themelab_color_hex themelab_studio_color_hex" value="', htmlspecialchars($g['end'], ENT_QUOTES, 'UTF-8'), '" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$"><input type="color" data-color-target="', $prefix, '_end" value="', htmlspecialchars($g['end'], ENT_QUOTES, 'UTF-8'), '"></span></label>
							<label><strong>', $txt['themelab_gradient_type'], '</strong><select id="', $prefix, '_type" name="', $prefix, '_type">
								<option value="linear"', $g['type'] === 'linear' ? ' selected' : '', '>', $txt['themelab_gradient_type_linear'], '</option>
								<option value="center"', $g['type'] === 'center' ? ' selected' : '', '>', $txt['themelab_gradient_type_center'], '</option>
							</select></label>
							<label><strong>', $txt['themelab_gradient_flow'], '</strong><select id="', $prefix, '_flow" name="', $prefix, '_flow" data-linear-flow="', htmlspecialchars($g['flow'], ENT_QUOTES, 'UTF-8'), '">';

		if ($g['type'] === 'center')
		{
			echo '
								<option value="left_right"', $g['flow'] === 'left_right' ? ' selected' : '', '>', $txt['themelab_gradient_center_vertical'], '</option>
								<option value="top_bottom"', $g['flow'] === 'top_bottom' ? ' selected' : '', '>', $txt['themelab_gradient_center_horizontal'], '</option>';
		}
		else
		{
			echo '
								<option value="left_right"', $g['flow'] === 'left_right' ? ' selected' : '', '>', $txt['themelab_gradient_left_right'], '</option>
								<option value="right_left"', $g['flow'] === 'right_left' ? ' selected' : '', '>', $txt['themelab_gradient_right_left'], '</option>
								<option value="top_bottom"', $g['flow'] === 'top_bottom' ? ' selected' : '', '>', $txt['themelab_gradient_top_bottom'], '</option>
								<option value="bottom_top"', $g['flow'] === 'bottom_top' ? ' selected' : '', '>', $txt['themelab_gradient_bottom_top'], '</option>';
		}

		echo '
							</select><small class="themelab_gradient_flow_help" id="', $prefix, '_flow_help">', $g['type'] === 'center' ? $txt['themelab_gradient_center_flow_help'] : $txt['themelab_gradient_linear_flow_help'], '</small></label>
							<label class="themelab_gradient_softness"><strong>', $txt['themelab_gradient_softness'], ' <span id="', $prefix, '_softness_value">', (int) $g['softness'], '%</span></strong><input type="range" id="', $prefix, '_softness" name="', $prefix, '_softness" min="10" max="90" step="5" value="', (int) $g['softness'], '"><span class="themelab_range_ends"><span>', $txt['themelab_gradient_sharp'], '</span><span>', $txt['themelab_gradient_smooth'], '</span></span></label>
						</div>
					</section>';
	}

	echo '
				</div>';
	template_themelab_mini_forum_preview($context['themelab_preview_settings']);
	echo '
			</div>
			<div class="themelab_layout_actions">
				<a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=palette">', $txt['themelab_back_palette'], '</a>
				<button type="submit" name="themelab_save_gradients" value="1" form="themelab_gradients_form" class="button" formnovalidate>', $txt['themelab_gradients_save'], '</button>
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

function template_themelab_layout()
{
	global $context, $txt, $scripturl;

	$project = !empty($context['themelab_active_project'])
		? $context['themelab_active_project']
		: array();

	$layout = !empty($context['themelab_layout'])
		&& is_array($context['themelab_layout'])
			? $context['themelab_layout']
			: array();

	$project_name = !empty($project['project_name'])
		? $project['project_name']
		: '';

	$width_mode = !empty($layout['width_mode'])
		? $layout['width_mode']
		: 'fixed';

	$max_width = isset($layout['max_width'])
		? (int) $layout['max_width']
		: 1200;

	$border_radius = isset($layout['border_radius'])
		? (int) $layout['border_radius']
		: 6;

	$content_spacing = !empty($layout['content_spacing'])
		? $layout['content_spacing']
		: 'medium';

	$shadow_style = !empty($layout['shadow_style'])
		? $layout['shadow_style']
		: 'soft';


	$preview_settings = !empty($context['themelab_preview_settings'])
		&& is_array($context['themelab_preview_settings'])
			? $context['themelab_preview_settings']
			: array();

	$preview_settings['width_mode'] = $width_mode;
	$preview_settings['max_width'] = $max_width;
	$preview_settings['border_radius'] = $border_radius;
	$preview_settings['content_spacing'] = $content_spacing;
	$preview_settings['shadow_style'] = $shadow_style;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['themelab_layout'], '</h3>
	</div>

	<div class="windowbg">
		<p>',
			sprintf(
				$txt['themelab_layout_project'],
				$project_name
			),
		'</p>

		<p>', $txt['themelab_layout_intro'], '</p>
	</div>';

	if (isset($_REQUEST['layout_saved']))
	{
		echo '
	<div class="infobox">
		', $txt['themelab_layout_saved'], '
	</div>';
	}

	if (!empty($context['themelab_layout_errors']))
	{
		echo '
	<div class="errorbox">
		<strong>', $txt['themelab_layout_has_errors'], '</strong>

		<ul>';

		foreach ($context['themelab_layout_errors'] as $error)
		{
			if (!empty($txt[$error]))
				echo '
			<li>', $txt[$error], '</li>';
		}

		echo '
		</ul>
	</div>';
	}

	echo '
	<form
		action="', $scripturl, '?action=admin;area=themelab;sa=layout"
		method="post"
		accept-charset="UTF-8"
		novalidate
	>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themelab_layout_canvas'], '</h3>
		</div>

		<div class="windowbg themelab_workspace_window">
			<div class="themelab_studio_layout themelab_layout_workspace">
				<div class="themelab_studio_controls">
					<div class="themelab_layout_grid">

				<section class="themelab_layout_panel">
					<h4>', $txt['themelab_layout_width'], '</h4>

					<p class="smalltext">
						', $txt['themelab_layout_width_desc'], '
					</p>

					<div class="themelab_choice_group">
						<label>
							<input
								type="radio"
								name="themelab_width_mode"
								value="fixed"',
								$width_mode === 'fixed' ? ' checked' : '',
							'>
							', $txt['themelab_layout_fixed'], '
						</label>

						<label>
							<input
								type="radio"
								name="themelab_width_mode"
								value="fluid"',
								$width_mode === 'fluid' ? ' checked' : '',
							'>
							', $txt['themelab_layout_fluid'], '
						</label>
					</div>

					<label class="themelab_field_label" for="themelab_max_width">
						', $txt['themelab_layout_max_width'], '
					</label>

					<div class="themelab_number_control">
						<input
							type="number"
							id="themelab_max_width"
							name="themelab_max_width"
							value="', $max_width, '"
							min="800"
							max="2400"
							step="10"
							required
						>
						<span>px</span>
					</div>
				</section>

				<section class="themelab_layout_panel">
					<h4>', $txt['themelab_layout_shape'], '</h4>

					<label class="themelab_field_label" for="themelab_border_radius">
						', $txt['themelab_layout_border_radius'], '
					</label>

					<div class="themelab_number_control">
						<input
							type="number"
							id="themelab_border_radius"
							name="themelab_border_radius"
							value="', $border_radius, '"
							min="0"
							max="40"
							required
						>
						<span>px</span>
					</div>

					<label class="themelab_field_label" for="themelab_shadow_style">
						', $txt['themelab_layout_shadow'], '
					</label>

					<select
						id="themelab_shadow_style"
						name="themelab_shadow_style"
					>
						<option value="none"', $shadow_style === 'none' ? ' selected' : '', '>
							', $txt['themelab_shadow_none'], '
						</option>

						<option value="soft"', $shadow_style === 'soft' ? ' selected' : '', '>
							', $txt['themelab_shadow_soft'], '
						</option>

						<option value="medium"', $shadow_style === 'medium' ? ' selected' : '', '>
							', $txt['themelab_shadow_medium'], '
						</option>

						<option value="strong"', $shadow_style === 'strong' ? ' selected' : '', '>
							', $txt['themelab_shadow_strong'], '
						</option>
					</select>
				</section>

				<section class="themelab_layout_panel">
					<h4>', $txt['themelab_layout_spacing'], '</h4>

					<p class="smalltext">
						', $txt['themelab_layout_spacing_desc'], '
					</p>

					<div class="themelab_choice_cards">
						<label class="themelab_choice_card">
							<input
								type="radio"
								name="themelab_content_spacing"
								value="compact"',
								$content_spacing === 'compact' ? ' checked' : '',
							'>
							<span>
								<strong>', $txt['themelab_spacing_compact'], '</strong>
							</span>
						</label>

						<label class="themelab_choice_card">
							<input
								type="radio"
								name="themelab_content_spacing"
								value="medium"',
								$content_spacing === 'medium' ? ' checked' : '',
							'>
							<span>
								<strong>', $txt['themelab_spacing_medium'], '</strong>
							</span>
						</label>

						<label class="themelab_choice_card">
							<input
								type="radio"
								name="themelab_content_spacing"
								value="comfortable"',
								$content_spacing === 'comfortable' ? ' checked' : '',
							'>
							<span>
								<strong>', $txt['themelab_spacing_comfortable'], '</strong>
							</span>
						</label>
					</div>
				</section>


					</div>
				</div>';

	template_themelab_mini_forum_preview($preview_settings);

	echo '
			</div>

			<div class="themelab_layout_actions">
				<a
					class="button"
					href="', $scripturl, '?action=admin;area=themelab;sa=palette"
				>
					', $txt['themelab_back_palette'], '
				</a>

				<button
					type="submit"
					name="themelab_save_layout"
					value="1"
					class="button"
				 formnovalidate>
					', $txt['themelab_save_layout'], '
				</button>
			</div>
		</div>

		<input
			type="hidden"
			name="', $context['session_var'], '"
			value="', $context['session_id'], '"
		>
	</form>';
}

/**
 * Theme Lab Typography Studio.
 */
function template_themelab_typography()
{
	global $context, $txt, $scripturl;

	$project = !empty($context['themelab_active_project'])
		? $context['themelab_active_project']
		: array();

	$typography =
		!empty($context['themelab_typography'])
		&& is_array($context['themelab_typography'])
			? $context['themelab_typography']
			: array();

	$fonts = !empty($context['themelab_typography_fonts'])
		? $context['themelab_typography_fonts']
		: array();

	$monospace_fonts =
		!empty($context['themelab_monospace_fonts'])
			? $context['themelab_monospace_fonts']
			: array();

	$project_name = !empty($project['project_name'])
		? $project['project_name']
		: '';

	$font_family = !empty($typography['font_family'])
		? $typography['font_family']
		: 'verdana';

	$font_size = isset($typography['font_size'])
		? (int) $typography['font_size']
		: 14;

	$line_spacing = !empty($typography['line_spacing'])
		? $typography['line_spacing']
		: 'comfortable';

	$heading_weight = !empty($typography['heading_weight'])
		? (string) $typography['heading_weight']
		: '700';

	$monospace_font = !empty($typography['monospace_font'])
		? $typography['monospace_font']
		: 'consolas';

	$current_font_stack =
		!empty($fonts[$font_family]['family'])
			? $fonts[$font_family]['family']
			: 'Verdana, Geneva, sans-serif';

	$current_monospace_stack =
		!empty($monospace_fonts[$monospace_font]['family'])
			? $monospace_fonts[$monospace_font]['family']
			: 'Consolas, Monaco, monospace';

	$line_height_values = array(
		'compact' => '1.25',
		'comfortable' => '1.5',
		'relaxed' => '1.75',
	);

	$current_line_height =
		isset($line_height_values[$line_spacing])
			? $line_height_values[$line_spacing]
			: '1.5';

    	$project_data = !empty($project['project_data_array'])
		&& is_array($project['project_data_array'])
			? $project['project_data_array']
			: array();

	$palette_data = !empty($project_data['palette']['colors'])
		&& is_array($project_data['palette']['colors'])
			? $project_data['palette']['colors']
			: array();

	$layout_data = !empty($project_data['layout'])
		&& is_array($project_data['layout'])
			? $project_data['layout']
			: array();

	$preview_settings = themelab_build_preview_settings($project);

        $preview_settings = array_merge(
	       $preview_settings,
	           array(
		          'font_family' => $current_font_stack,
		          'font_size' => $font_size,
		          'line_height' => $current_line_height,
		          'heading_weight' => $heading_weight,
		          'monospace_font' => $current_monospace_stack,
	           )
        );
    
	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['themelab_typography'], '</h3>
	</div>

	<div class="windowbg">
		<p>',
			sprintf(
				$txt['themelab_typography_project'],
				$project_name
			),
		'</p>

		<p>', $txt['themelab_typography_intro'], '</p>
	</div>';

	if (isset($_REQUEST['typography_saved']))
	{
		echo '
	<div class="infobox">
		', $txt['themelab_typography_saved'], '
	</div>';
	}

	if (!empty($context['themelab_typography_errors']))
	{
		echo '
	<div class="errorbox">
		<strong>',
			$txt['themelab_typography_has_errors'],
		'</strong>

		<ul>';

		foreach (
			$context['themelab_typography_errors']
			as $error
		)
		{
			if (!empty($txt[$error]))
				echo '
			<li>', $txt[$error], '</li>';
		}

		echo '
		</ul>
	</div>';
	}

	echo '
	<form
		id="themelab_typography_form"
		action="', $scripturl, '?action=admin;area=themelab;sa=typography"
		method="post"
		accept-charset="UTF-8"
		novalidate
	>
		<input type="hidden" name="themelab_save_typography" value="1">
		<div class="cat_bar">
			<h3 class="catbg">',
				$txt['themelab_typography_settings'],
			'</h3>
		</div>

		<div class="windowbg">
			<div class="themelab_typography_layout">

				<div class="themelab_typography_controls">

					<section class="themelab_typography_panel">
						<h4>',
							$txt['themelab_typography_font_family'],
						'</h4>

						<p class="smalltext">',
							$txt['themelab_typography_font_family_desc'],
						'</p>

						<div class="themelab_font_grid">';

	foreach ($fonts as $font_key => $font)
	{
		$is_selected = $font_family === $font_key;

		echo '
							<label
								class="themelab_font_card',
								$is_selected ? ' is_selected' : '',
								'"
								style="font-family:',
									$font['family'],
								';"
							>
								<input
									type="radio"
									name="themelab_font_family"
									value="', $font_key, '"',
									$is_selected ? ' checked' : '',
								'>

								<span class="themelab_font_sample">
									Aa
								</span>

								<strong>', $font['name'], '</strong>

								<span class="smalltext">
									', $font['type'], '
								</span>
							</label>';
	}

	echo '
						</div>
					</section>

					<section class="themelab_typography_panel">
						<h4>',
							$txt['themelab_typography_readability'],
						'</h4>

						<label
							class="themelab_field_label"
							for="themelab_font_size"
						>
							', $txt['themelab_typography_font_size'], '
						</label>

						<div class="themelab_number_control">
							<input
								type="number"
								id="themelab_font_size"
								name="themelab_font_size"
								value="', $font_size, '"
								min="10"
								max="22"
								step="1"
								required
							>
							<span>px</span>
						</div>

						<label
							class="themelab_field_label"
							for="themelab_line_spacing"
						>
							', $txt['themelab_typography_line_spacing'], '
						</label>

						<select
							id="themelab_line_spacing"
							name="themelab_line_spacing"
						>
							<option value="compact"',
								$line_spacing === 'compact'
									? ' selected'
									: '',
							'>
								', $txt['themelab_line_compact'], '
							</option>

							<option value="comfortable"',
								$line_spacing === 'comfortable'
									? ' selected'
									: '',
							'>
								', $txt['themelab_line_comfortable'], '
							</option>

							<option value="relaxed"',
								$line_spacing === 'relaxed'
									? ' selected'
									: '',
							'>
								', $txt['themelab_line_relaxed'], '
							</option>
						</select>
					</section>

					<section class="themelab_typography_panel">
						<h4>',
							$txt['themelab_typography_headings'],
						'</h4>

						<div class="themelab_heading_weight_grid">
							<label class="themelab_weight_card">
								<input
									type="radio"
									name="themelab_heading_weight"
									value="400"',
									$heading_weight === '400'
										? ' checked'
										: '',
								'>
								<span class="themelab_weight_sample" data-weight="400" style="font-weight:400;">
									', $txt['themelab_weight_normal'], '
								</span>
							</label>

							<label class="themelab_weight_card">
								<input
									type="radio"
									name="themelab_heading_weight"
									value="500"',
									$heading_weight === '500'
										? ' checked'
										: '',
								'>
								<span class="themelab_weight_sample is_medium" data-weight="500" style="font-weight:500;">
									', $txt['themelab_weight_semibold'], '
								</span>
							</label>

							<label class="themelab_weight_card">
								<input
									type="radio"
									name="themelab_heading_weight"
									value="700"',
									$heading_weight === '700'
										? ' checked'
										: '',
								'>
								<span class="themelab_weight_sample" data-weight="700" style="font-weight:700;">
									', $txt['themelab_weight_bold'], '
								</span>
							</label>
						</div>
					</section>

					<section class="themelab_typography_panel">
						<h4>',
							$txt['themelab_typography_code_font'],
						'</h4>

						<label
							class="themelab_field_label"
							for="themelab_monospace_font"
						>
							', $txt['themelab_typography_monospace'], '
						</label>

						<select
							id="themelab_monospace_font"
							name="themelab_monospace_font"
						>';

	foreach ($monospace_fonts as $font_key => $font)
	{
		echo '
							<option
								value="', $font_key, '"
								data-family="', htmlspecialchars($font['family'], ENT_QUOTES, 'UTF-8'), '"',
								$monospace_font === $font_key
									? ' selected'
									: '',
							'>
								', $font['name'], '
							</option>';
	}

	echo '
						</select>
					</section>
				</div>

								';

	template_themelab_mini_forum_preview($preview_settings);

	echo '
			</div>

			<div class="themelab_layout_actions">
				<a
					class="button"
					href="', $scripturl,
						'?action=admin;area=themelab;sa=layout"
				>
					', $txt['themelab_back_layout'], '
				</a>

				<button
					type="submit"
					name="themelab_save_typography"
					value="1"
					form="themelab_typography_form"
					class="button"
				 formnovalidate>
					', $txt['themelab_save_typography'], '
				</button>
			</div>
		</div>

		<input
			type="hidden"
			name="', $context['session_var'], '"
			value="', $context['session_id'], '"
		>
	</form>

	<script>
	window.themeLabTypographyFonts = ',
		json_encode(array_map(
			function ($font) {
				return $font['family'];
			},
			$fonts
		)),
	';

	window.themeLabMonospaceFonts = ',
		json_encode(array_map(
			function ($font) {
				return $font['family'];
			},
			$monospace_fonts
		)),
	';
	</script>';
}

/**
 * Render the shared sticky Theme Lab mini-forum preview.
 *
 * @param array $settings Current project preview settings.
 */
function template_themelab_mini_forum_preview($settings = array())
{
	global $txt;

	$defaults = array(
		'font_family' => 'Verdana, Geneva, sans-serif',
		'font_size' => 14,
		'line_height' => '1.5',
		'heading_weight' => '700',
		'monospace_font' => 'Consolas, Monaco, monospace',

		'background' => '#E9EEF2',
		'surface' => '#FFFFFF',
		'surface_alt' => '#F5F7F8',
		'text' => '#222222',
		'muted_text' => '#666666',
		'primary' => '#3465A4',
		'secondary' => '#5B87B2',
		'accent' => '#204A87',
		'link' => '#3465A4',
		'button' => '#3465A4',
		'border' => '#C7CDD1',
		'header' => '#3465A4',
		'footer' => '#D5DEE5',

		'button_border_radius' => 5,
		'button_border_width' => 0,
		'button_padding_vertical' => 3,
		'button_padding_horizontal' => 16,
		'button_font_weight' => '500',
		'button_text_transform' => 'none',
		'button_depth' => 'soft',
		'button_hover_effect' => 'darken',
		'button_primary_text' => '#FFFFFF',
		'button_secondary_text' => '#222222',
		'button_danger_background' => '#B33A3A',
		'button_danger_text' => '#FFFFFF',
		'button_danger_border' => '#8F2E2E',
		'button_disabled_background' => '#D6DCE2',
		'button_disabled_text' => '#6B7280',
		'button_disabled_border' => '#C3CAD2',

		'forms_field_size' => 'standard',
		'forms_border_radius' => 4,
		'forms_border_width' => 1,
		'forms_padding_horizontal' => 9,
		'forms_focus_style' => 'glow',
		'forms_textarea_height' => 82,
		'forms_textarea_resize' => 'vertical',
		'forms_choice_size' => 16,
		'forms_choice_style' => 'classic',
		'forms_toggle_size' => 22,
		'forms_toggle_style' => 'pill',
		'forms_label_weight' => '500',
		'forms_required_marker' => 'asterisk',
		'forms_validation_style' => 'border',
		'preview_mode' => 'forum',

		'navigation_menu_radius' => 5,
		'navigation_menu_gap' => 6,
		'navigation_menu_padding_vertical' => 6,
		'navigation_menu_padding_horizontal' => 12,
		'navigation_font_weight' => '500',
		'navigation_text_transform' => 'none',
		'navigation_breadcrumb_style' => 'plain',
		'navigation_breadcrumb_separator' => 'chevron',
		'navigation_pagination_style' => 'boxed',
		'navigation_pagination_active_emphasis' => 'filled',
		'navigation_pagination_size' => 'standard',
		'navigation_pagination_gap' => 5,
		'navigation_pagination_radius' => 5,
		'navigation_pagination_active_weight' => 'balanced',
		'navigation_active_emphasis' => 'filled',
		'button_header_background' => '#FFFFFF',
		'button_header_text' => '#243447',
		'button_header_border' => '#C8D0D8',
		'button_header_hover_background' => '#E7EEF5',
		'button_header_hover_text' => '#17324D',
		'button_header_current_background' => '#3465A4',
		'button_header_current_text' => '#FFFFFF',
		'navigation_footer_style' => 'text',
		'button_footer_background' => '#FFFFFF',
		'button_footer_text' => '#3465A4',
		'button_footer_border' => '#C8D0D8',
		'button_footer_hover_background' => '#E7EEF5',
		'button_footer_hover_text' => '#17324D',
		'button_footer_current_background' => '#3465A4',
		'button_footer_current_text' => '#FFFFFF',

		'board_index_container_radius' => 6,
		'board_index_border_width' => 1,
		'board_index_panel_padding' => 12,
		'board_index_shadow_style' => 'soft',
		'board_index_header_height' => 38,
		'board_index_header_alignment' => 'left',
		'board_index_header_weight' => '700',
		'board_index_row_spacing' => 'standard',
		'board_index_stats_position' => 'right',
		'board_index_last_post_layout' => 'standard',
		'board_index_alternating_rows' => 'on',
		'board_index_icon_style' => 'filled',
		'board_index_header_text' => '#FFFFFF',
		'board_index_header_link' => '#FFFFFF',
		'board_index_stats_text' => '#666666',
		'board_index_description_text' => '#666666',
		'board_index_unread_indicator' => '#3465A4',

		'border_radius' => 6,
		'width_mode' => 'fixed',
		'max_width' => 1200,
		'content_spacing' => 'medium',
		'shadow_style' => 'soft',
        
        'header_height' => 130,
        'logo_text' => 'Theme Lab Forum',
        'logo_image' => '',
        'logo_image_url' => '',
        'logo_filename' => '',
        'logo_mime' => '',
        'logo_width' => 0,
        'logo_height' => 0,
        'logo_size' => 100,
        'logo_file_size' => 0,
        'logo_alignment' => 'left',
        'header_background_mode' => 'solid',
        'header_background_image' => '',
        'header_background_image_url' => '',
        'header_background_position' => 'center',
        'header_background_position_x' => 50,
        'header_background_position_y' => 50,
        'header_background_size' => 'cover',
        'header_image_opacity' => 100,
        'header_background_opacity' => 35,
        'header_background_color' => '#3465A4',
        'header_gradient_start' => '#3465A4',
        'header_gradient_end' => '#204A87',
        'header_text_color' => '#FFFFFF',
        'navigation_alignment' => 'left',
        'navigation_style' => 'pills',
        'search_position' => 'right',
        
        'footer_height' => 70,
        'footer_text' => '',
        'footer_text_alignment' => 'center',
        'footer_background_mode' => 'solid',
        'footer_background_color' => '#D5DEE5',
        'footer_gradient_start' => '#D5DEE5',
        'footer_gradient_end' => '#B9C8D3',
        'footer_text_color' => '#222222',
        'footer_link_color' => '#3465A4',
        'footer_show_credit' => 1,
        'footer_credit_text' => themelab_get_footer_credit_text(),
        'header_gradient_css' => '',
        'footer_gradient_css' => '',
        'category_header_gradient_css' => '',
        'card_header_gradient_css' => '',
	);

	$settings = array_merge($defaults, $settings);

	if ((int) $settings['logo_size'] < 25 || (int) $settings['logo_size'] > 100)
		$settings['logo_size'] = 100;

	$logo_safe_height = max(1, (int) floor((int) $settings['header_height'] * 0.82));
	$logo_preview_height = max(1, (int) floor($logo_safe_height * ((int) $settings['logo_size'] / 100)));
	$logo_preview_width_percent = max(1, (int) floor(70 * ((int) $settings['logo_size'] / 100)));

	$font_family = htmlspecialchars(
		$settings['font_family'],
		ENT_QUOTES,
		'UTF-8'
	);

	$monospace_font = htmlspecialchars(
		$settings['monospace_font'],
		ENT_QUOTES,
		'UTF-8'
	);

	echo '
	<aside class="themelab_preview_column">
		<div class="themelab_preview_title">
			', $txt['themelab_mini_preview'], '
		</div>

		<div
			id="themelab_mini_forum"
			data-project-id="', isset($settings['project_id']) ? (int) $settings['project_id'] : 0, '"
			class="themelab_mini_forum"
			data-spacing="', $settings['content_spacing'], '"
			data-shadow="', $settings['shadow_style'], '"
			data-width-mode="', $settings['width_mode'], '"
			data-button-depth="', $settings['button_depth'], '"
			data-button-hover="', $settings['button_hover_effect'], '"
			data-heading-weight="', $settings['heading_weight'], '"
			data-button-weight="', $settings['button_font_weight'], '"
			data-form-size="', $settings['forms_field_size'], '"
			data-form-focus="', $settings['forms_focus_style'], '"
			data-textarea-resize="', $settings['forms_textarea_resize'], '"
			data-choice-style="', $settings['forms_choice_style'], '"
			data-toggle-style="', $settings['forms_toggle_style'], '"
			data-form-label-weight="', $settings['forms_label_weight'], '"
			data-required-marker="', $settings['forms_required_marker'], '"
			data-validation-style="', $settings['forms_validation_style'], '"
			data-preview-mode="', $settings['preview_mode'], '"
			data-navigation-active="', $settings['navigation_active_emphasis'], '"
			data-breadcrumb-style="', $settings['navigation_breadcrumb_style'], '"
			data-breadcrumb-separator="', $settings['navigation_breadcrumb_separator'], '"
			data-pagination-style="', $settings['navigation_pagination_style'], '"
			data-pagination-active="', $settings['navigation_pagination_active_emphasis'], '"
			data-pagination-size="', $settings['navigation_pagination_size'], '"
			data-pagination-active-weight="', $settings['navigation_pagination_active_weight'], '"
			data-footer-navigation-style="', $settings['navigation_footer_style'], '"
			data-board-shadow="', $settings['board_index_shadow_style'], '"
			data-board-header-align="', $settings['board_index_header_alignment'], '"
			data-board-header-weight="', $settings['board_index_header_weight'], '"
			data-board-row-spacing="', $settings['board_index_row_spacing'], '"
			data-board-stats-position="', $settings['board_index_stats_position'], '"
			data-board-last-post="', $settings['board_index_last_post_layout'], '"
			data-board-alternating="', $settings['board_index_alternating_rows'], '"
			data-board-icon-style="', $settings['board_index_icon_style'], '"
			style="
				--themelab-preview-heading-weight:', htmlspecialchars((string) $settings['heading_weight'], ENT_QUOTES, 'UTF-8'), ';
				--themelab-preview-monospace:', $monospace_font, ';
				--themelab-preview-background:', $settings['background'], ';
				--themelab-preview-surface:', $settings['surface'], ';
				--themelab-preview-surface-alt:', $settings['surface_alt'], ';
				--themelab-preview-text:', $settings['text'], ';
				--themelab-preview-muted-text:', $settings['muted_text'], ';
				--themelab-preview-primary:', $settings['primary'], ';
				--themelab-preview-secondary:', $settings['secondary'], ';
				--themelab-preview-accent:', $settings['accent'], ';
				--themelab-preview-link:', $settings['link'], ';
				--themelab-preview-link-hover:', $settings['link_hover'], ';
				--themelab-preview-link-visited:', $settings['link_visited'], ';
				--themelab-preview-button:', $settings['button'], ';
				--themelab-button-radius:', (int) $settings['button_border_radius'], 'px;
				--themelab-button-border-width:', (int) $settings['button_border_width'], 'px;
				--themelab-button-padding-y:', (int) $settings['button_padding_vertical'], 'px;
				--themelab-button-padding-x:', (int) $settings['button_padding_horizontal'], 'px;
				--themelab-button-font-weight:', $settings['button_font_weight'], ';
				--themelab-medium-font-stroke:', themelab_get_medium_font_stroke(), ';
				--themelab-button-text-transform:', $settings['button_text_transform'], ';
				--themelab-button-primary-text:', $settings['button_primary_text'], ';
				--themelab-button-secondary-text:', $settings['button_secondary_text'], ';
				--themelab-button-danger-bg:', $settings['button_danger_background'], ';
				--themelab-button-danger-text:', $settings['button_danger_text'], ';
				--themelab-button-danger-border:', $settings['button_danger_border'], ';
				--themelab-button-disabled-bg:', $settings['button_disabled_background'], ';
				--themelab-button-disabled-text:', $settings['button_disabled_text'], ';
				--themelab-button-disabled-border:', $settings['button_disabled_border'], ';
				--themelab-form-radius:', (int)$settings['forms_border_radius'], 'px;
				--themelab-form-border-width:', (int)$settings['forms_border_width'], 'px;
				--themelab-form-padding-x:', (int)$settings['forms_padding_horizontal'], 'px;
				--themelab-textarea-height:', (int)$settings['forms_textarea_height'], 'px;
				--themelab-choice-size:', (int)$settings['forms_choice_size'], 'px;
				--themelab-toggle-size:', (int)$settings['forms_toggle_size'], 'px;
				--themelab-form-label-weight:', $settings['forms_label_weight'], ';
				--themelab-board-radius:', (int)$settings['board_index_container_radius'], 'px;
				--themelab-board-border-width:', (int)$settings['board_index_border_width'], 'px;
				--themelab-board-padding:', (int)$settings['board_index_panel_padding'], 'px;
				--themelab-board-header-height:', (int)$settings['board_index_header_height'], 'px;
				--themelab-board-header-weight:', $settings['board_index_header_weight'], ';
				--themelab-board-header-text:', $settings['board_index_header_text'], ';
				--themelab-board-header-link:', $settings['board_index_header_link'], ';
				--themelab-board-stats-text:', $settings['board_index_stats_text'], ';
				--themelab-board-description-text:', $settings['board_index_description_text'], ';
				--themelab-board-unread-indicator:', $settings['board_index_unread_indicator'], ';
				--themelab-board-row-background:', $settings['board_index_row_background'], ';
				--themelab-board-row-background-alt:', $settings['board_index_row_background_alt'], ';

				--themelab-navigation-radius:', (int)$settings['navigation_menu_radius'], 'px;
				--themelab-navigation-gap:', (int)$settings['navigation_menu_gap'], 'px;
				--themelab-navigation-padding-y:', (int)$settings['navigation_menu_padding_vertical'], 'px;
				--themelab-navigation-padding-x:', (int)$settings['navigation_menu_padding_horizontal'], 'px;
				--themelab-navigation-font-weight:', $settings['navigation_font_weight'], ';
				--themelab-navigation-text-transform:', $settings['navigation_text_transform'], ';
				--themelab-pagination-gap:', (int)$settings['navigation_pagination_gap'], 'px;
				--themelab-pagination-radius:', (int)$settings['navigation_pagination_radius'], 'px;
				--themelab-header-button-bg:', $settings['button_header_background'], ';
				--themelab-header-button-text:', $settings['button_header_text'], ';
				--themelab-header-button-border:', $settings['button_header_border'], ';
				--themelab-header-button-hover-bg:', $settings['button_header_hover_background'], ';
				--themelab-header-button-hover-text:', $settings['button_header_hover_text'], ';
				--themelab-header-button-current-bg:', $settings['button_header_current_background'], ';
				--themelab-header-button-current-text:', $settings['button_header_current_text'], ';
				--themelab-footer-button-bg:', $settings['button_footer_background'], ';
				--themelab-footer-button-text:', $settings['button_footer_text'], ';
				--themelab-footer-button-border:', $settings['button_footer_border'], ';
				--themelab-footer-button-hover-bg:', $settings['button_footer_hover_background'], ';
				--themelab-footer-button-hover-text:', $settings['button_footer_hover_text'], ';
				--themelab-footer-button-current-bg:', $settings['button_footer_current_background'], ';
				--themelab-footer-button-current-text:', $settings['button_footer_current_text'], ';
				--themelab-preview-border:', $settings['border'], ';
				--themelab-preview-header:', $settings['header'], ';
				--themelab-preview-footer:', $settings['footer'], ';
				--themelab-header-solid:', $settings['header_background_color'], ';
				--themelab-header-base:', !empty($settings['header_gradient_enabled']) && !empty($settings['header_gradient_css']) ? $settings['header_gradient_css'] : $settings['header_background_color'], ';
				--themelab-footer-solid:', $settings['footer_background_color'], ';
				--themelab-suggest-header-a:', !empty($settings['header_gradient_enabled']) ? $settings['header_gradient_start'] : $settings['header_background_color'], ';
				--themelab-suggest-header-b:', !empty($settings['header_gradient_enabled']) ? $settings['header_gradient_end'] : $settings['header_background_color'], ';
				--themelab-suggest-footer-a:', !empty($settings['footer_gradient_enabled']) ? $settings['footer_gradient_start'] : $settings['footer_background_color'], ';
				--themelab-suggest-footer-b:', !empty($settings['footer_gradient_enabled']) ? $settings['footer_gradient_end'] : $settings['footer_background_color'], ';
				--themelab-suggest-primary:', $settings['primary'], ';
				--themelab-suggest-accent:', $settings['accent'], ';
				--themelab-category-header-solid:', $settings['primary'], ';
				--themelab-card-header-solid:', $settings['secondary'], ';
				--themelab-category-header-background:', !empty($settings['category_header_gradient_css']) ? $settings['category_header_gradient_css'] : $settings['primary'], ';
				--themelab-card-header-background:', !empty($settings['card_header_gradient_css']) ? $settings['card_header_gradient_css'] : $settings['secondary'], ';
				--themelab-preview-radius:', (int) $settings['border_radius'], 'px;
				--themelab-preview-width:', (int) $settings['max_width'], 'px;
				--themelab-preview-width-scale:', max(88, min(100, round(((int) $settings['max_width'] - 800) / 1600 * 12 + 88))), '%;
				--themelab-header-height:', (int) $settings['header_height'], 'px;
				--themelab-logo-max-height:', $logo_preview_height, 'px;

				font-family:', $font_family, ';
				font-size:', (int) $settings['font_size'], 'px;
				line-height:', $settings['line_height'], ';
			"
		>
			<header
	           id="themelab_preview_header"
	           class="themelab_preview_header"
	           data-background-mode="', $settings['header_background_mode'], '"
	           data-has-background-image="', !empty($settings['header_background_image_url']) ? '1' : '0', '"
	           data-background-position="', $settings['header_background_position'], '"
	           data-background-position-x="', (int) $settings['header_background_position_x'], '"
	           data-background-position-y="', (int) $settings['header_background_position_y'], '"
	           data-background-size="', $settings['header_background_size'], '"
	           data-image-opacity="', (int) $settings['header_image_opacity'], '"
	           data-overlay-opacity="', (int) $settings['header_background_opacity'], '"
	           data-background-image="', htmlspecialchars($settings['header_background_image_url'], ENT_QUOTES, 'UTF-8'), '"
	           data-logo-alignment="', $settings['logo_alignment'], '"
	           data-smf-logo-visibility="', $settings['smf_logo_visibility'], '"
	           data-smf-logo-position="', $settings['smf_logo_position'], '"
	           data-navigation-alignment="', $settings['navigation_alignment'], '"
	           data-navigation-style="', $settings['navigation_style'], '"
	           data-search-position="', $settings['search_position'], '"
	           data-utility-mode="', $settings['utility_mode'], '"
	           data-utility-position="', $settings['utility_position'], '"
	           style="
		          min-height:', (int) $settings['header_height'], 'px;
		          color:', $settings['header_text_color'], ';
		          background-color:', $settings['header_background_color'], ';
		          background-image:',
	!empty($settings['header_gradient_enabled'])
		? (!empty($settings['header_gradient_css']) ? $settings['header_gradient_css'] : 'linear-gradient(' . themelab_get_gradient_angle($settings['header_gradient_direction']) . ', ' . $settings['header_gradient_start'] . ', ' . $settings['header_gradient_end'] . ')')
		: 'none',
';">
				<span
					id="themelab_preview_header_artwork"
					class="themelab_preview_header_artwork"
					aria-hidden="true"
					style="background-image:', !empty($settings['header_background_image_url']) ? 'url(&quot;' . htmlspecialchars($settings['header_background_image_url'], ENT_QUOTES, 'UTF-8') . '&quot;)' : 'none', ';
						background-position:', (int) $settings['header_background_position_x'], '% ', (int) $settings['header_background_position_y'], '%;
						background-size:', $settings['header_background_size'], ';
						opacity:', max(0, min(100, (int) $settings['header_image_opacity'])) / 100, ';"
				></span>
				<span
					id="themelab_preview_header_overlay"
					class="themelab_preview_header_overlay"
					aria-hidden="true"
					style="opacity:', !empty($settings['header_background_image_url']) ? max(0, min(100, (int) $settings['header_background_opacity'])) / 100 : 0, ';"
				></span>
				<div id="themelab_preview_utility_lane" class="themelab_preview_utility_lane" aria-label="Integrated header utilities preview">
					<span class="themelab_preview_utility_user">Admin</span>
					<span>Messages <b>2</b></span>
					<span>Alerts <b>3</b></span>
				</div>

				<div
					id="themelab_preview_logo"
					class="themelab_preview_logo"
					data-logo-mode="', !empty($settings['logo_image_url']) ? 'image' : 'text', '"
				>
					<img
						id="themelab_preview_logo_image"
						class="themelab_preview_logo_image"',
						!empty($settings['logo_image_url'])
							? ' src="' . htmlspecialchars($settings['logo_image_url'], ENT_QUOTES, 'UTF-8') . '"'
							: '',
						' alt="', htmlspecialchars($settings['logo_text'], ENT_QUOTES, 'UTF-8'), '"',
						' data-logo-natural-width="', (int) ($settings['logo_width'] ?? 0), '"',
						' data-logo-natural-height="', (int) ($settings['logo_height'] ?? 0), '"',
						' style="max-height:', $logo_preview_height, 'px;max-width:', $logo_preview_width_percent, '%;"',
						empty($settings['logo_image_url']) ? ' hidden' : '',
					'>
					<span
						id="themelab_preview_logo_text"
						class="themelab_preview_logo_text"',
						!empty($settings['logo_image_url']) ? ' hidden' : '',
					'>', htmlspecialchars($settings['logo_text'], ENT_QUOTES, 'UTF-8'), '</span>
				</div>

				<div id="themelab_preview_smf_logo" class="themelab_preview_smf_logo">Simple Machines&reg; Forum</div>

				<div class="themelab_preview_header_tools">
                <div
		          id="themelab_preview_search"
		          class="themelab_preview_search"
	            >
		          Search...
	           </div>

	           <nav
		          id="themelab_preview_navigation"
		          class="themelab_preview_navigation"
	           >
		          <a class="is_normal_sample" href="#" onclick="return false;">Normal</a>
		          <a class="is_hover_sample" href="#" onclick="return false;">Hover</a>
		          <a class="is_current_sample is_active" href="#" onclick="return false;">Current</a>
	           </nav>
                </div>
			</header>

			<div class="themelab_preview_link_states" aria-label="Palette link state preview">
				<strong>Links:</strong>
				<a class="is_normal" href="#" onclick="return false;">Normal</a>
				<a class="is_hover" href="#" onclick="return false;">Hover</a>
				<a class="is_visited" href="#" onclick="return false;">Visited</a>
			</div>

			<div class="themelab_preview_breadcrumb">
				<a href="#" onclick="return false;">Forum</a>
				<span>&rsaquo;</span>
				<a href="#" onclick="return false;">General Discussion</a>
			</div>

			<section class="themelab_preview_board">
				<div class="themelab_preview_board_header">
					<h3
						class="themelab_preview_heading"
						style="font-weight:', $settings['heading_weight'], ';"
					>
						General Discussion
					</h3>
				</div>

				<div class="themelab_preview_board_row">
					<div class="themelab_preview_board_icon">
						&#9679;
					</div>

					<div class="themelab_preview_board_content">
						<a
							class="themelab_preview_board_link"
							href="#"
							onclick="return false;"
						>
							Welcome to the Community
						</a>

						<p>
							Share news, ideas and conversation with other members.
						</p>
					</div>

					<div class="themelab_preview_board_stats">
						<strong>24</strong>
						<span>Topics</span>
					</div>
				</div>
			</section>


			<section class="themelab_preview_board_index" aria-label="Board Index preview">
				<div class="themelab_preview_category">
					<div class="themelab_preview_category_header">Community</div>
					<div class="themelab_preview_board_item">
						<span class="themelab_preview_board_badge">●</span>
						<div class="themelab_preview_board_details"><a href="#" onclick="return false;">General Discussion</a><p>Talk about the community, share ideas and meet other members.</p></div>
						<div class="themelab_preview_board_counts">
							<div class="themelab_preview_stat themelab_preview_stat_posts"><span class="themelab_preview_stat_icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4.5 5.5h15v10h-8.2L6 19.2v-3.7H4.5z"></path><path d="M8 9.5h.01M12 9.5h.01M16 9.5h.01"></path></svg></span><span class="themelab_preview_stat_copy"><span class="themelab_preview_stat_label">Posts</span><strong>145</strong></span></div>
							<span class="themelab_preview_stat_divider" aria-hidden="true"></span>
							<div class="themelab_preview_stat themelab_preview_stat_topics"><span class="themelab_preview_stat_icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3.5h8l4 4v13H7z"></path><path d="M15 3.5v4h4M10 11h6M10 14.5h6M10 18h4"></path></svg></span><span class="themelab_preview_stat_copy"><span class="themelab_preview_stat_label">Topics</span><strong>24</strong></span></div>
						</div>
						<div class="themelab_preview_last_post"><strong>Latest:</strong><a href="#" onclick="return false;">Welcome to Theme Lab</a><span>Today by Administrator</span></div>
					</div>
					<div class="themelab_preview_board_item">
						<span class="themelab_preview_board_badge">●</span>
						<div class="themelab_preview_board_details"><a href="#" onclick="return false;">Theme Showcase</a><p>Share completed themes and works in progress.</p></div>
						<div class="themelab_preview_board_counts">
							<div class="themelab_preview_stat themelab_preview_stat_posts"><span class="themelab_preview_stat_icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4.5 5.5h15v10h-8.2L6 19.2v-3.7H4.5z"></path><path d="M8 9.5h.01M12 9.5h.01M16 9.5h.01"></path></svg></span><span class="themelab_preview_stat_copy"><span class="themelab_preview_stat_label">Posts</span><strong>88</strong></span></div>
							<span class="themelab_preview_stat_divider" aria-hidden="true"></span>
							<div class="themelab_preview_stat themelab_preview_stat_topics"><span class="themelab_preview_stat_icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3.5h8l4 4v13H7z"></path><path d="M15 3.5v4h4M10 11h6M10 14.5h6M10 18h4"></path></svg></span><span class="themelab_preview_stat_copy"><span class="themelab_preview_stat_label">Topics</span><strong>12</strong></span></div>
						</div>
						<div class="themelab_preview_last_post"><strong>Latest:</strong><a href="#" onclick="return false;">Party Pink preview</a><span>Yesterday by Theme Designer</span></div>
					</div>
				</div>
				<div class="themelab_preview_category">
					<div class="themelab_preview_category_header">Support</div>
					<div class="themelab_preview_board_item">
						<span class="themelab_preview_board_badge">●</span>
						<div class="themelab_preview_board_details"><a href="#" onclick="return false;">Help &amp; Support</a><p>Questions, documentation and community assistance.</p></div>
						<div class="themelab_preview_board_counts">
							<div class="themelab_preview_stat themelab_preview_stat_posts"><span class="themelab_preview_stat_icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4.5 5.5h15v10h-8.2L6 19.2v-3.7H4.5z"></path><path d="M8 9.5h.01M12 9.5h.01M16 9.5h.01"></path></svg></span><span class="themelab_preview_stat_copy"><span class="themelab_preview_stat_label">Posts</span><strong>53</strong></span></div>
							<span class="themelab_preview_stat_divider" aria-hidden="true"></span>
							<div class="themelab_preview_stat themelab_preview_stat_topics"><span class="themelab_preview_stat_icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3.5h8l4 4v13H7z"></path><path d="M15 3.5v4h4M10 11h6M10 14.5h6M10 18h4"></path></svg></span><span class="themelab_preview_stat_copy"><span class="themelab_preview_stat_label">Topics</span><strong>9</strong></span></div>
						</div>
						<div class="themelab_preview_last_post"><strong>Latest:</strong><a href="#" onclick="return false;">Getting started</a><span>Monday by Support Team</span></div>
					</div>
				</div>
			</section>
			<section class="themelab_preview_topic">
				<div class="themelab_preview_topic_header">
					<h3
						class="themelab_preview_heading"
						style="font-weight:', $settings['heading_weight'], ';"
					>
						Theme Lab Preview
					</h3>
				</div>

				<article class="themelab_preview_post">
					<div class="themelab_preview_member">
						<strong>Administrator</strong>
						<span>Forum Owner</span>
					</div>

					<div class="themelab_preview_message">
						<p>
							This miniature forum shows how the current
							Theme Lab settings work together.
						</p>

						<blockquote>
							Colors, typography and layout will all update here.
						</blockquote>

						<code
							class="themelab_preview_code"
						>ThemeLab::preview(); 0123456789 { } [ ] =&gt; ::</code>


						<div class="themelab_preview_buttons" aria-label="Button preview">
							<button type="button" class="themelab_demo_button is_primary">Primary</button>
							<button type="button" class="themelab_demo_button is_secondary">Secondary</button>
							<button type="button" class="themelab_demo_button is_danger">Danger</button>
							<button type="button" class="themelab_demo_button is_small">Small</button>
							<button type="button" class="themelab_demo_button is_large">Large</button>
							<button type="button" class="themelab_demo_button" disabled>Disabled</button>
						</div>

						<div class="themelab_preview_form" aria-label="Form controls preview">
							<h3 class="themelab_preview_form_heading">Edit Profile</h3>
							<label>Display Name <span class="themelab_required_marker">*</span><input type="text" value="Theme Lab Member" autocomplete="off"></label>
							<label>Search<input type="search" placeholder="Search the forum..." autocomplete="off"></label>
							<label>Password<input type="password" value="themelab" autocomplete="new-password"></label>
							<label>Forum Language<select><option>English</option><option>Spanish</option><option>Dutch</option></select></label>
							<label>Timezone<select><option>UTC -05:00</option><option>UTC</option><option>UTC +01:00</option></select></label>
							<label>Disabled Field<input type="text" value="Unavailable" disabled></label>
							<label>Biography<textarea>Theme Lab makes forum design approachable.</textarea></label>
							<div class="themelab_preview_choices">
								<label><input type="checkbox" checked><span class="themelab_choice_indicator" aria-hidden="true"></span> Receive notifications</label>
								<label><input type="checkbox"><span class="themelab_choice_indicator" aria-hidden="true"></span> Show email address</label>
								<label><input type="radio" name="themelab_preview_visibility"><span class="themelab_choice_indicator" aria-hidden="true"></span> Public</label>
								<label><input type="radio" name="themelab_preview_visibility" checked><span class="themelab_choice_indicator" aria-hidden="true"></span> Members only</label>
							</div>
							<div class="themelab_preview_toggles" aria-label="Toggle switch preview">
								<label><span>Enable quick reply</span><input type="checkbox" checked><span class="themelab_toggle_indicator" aria-hidden="true"></span></label>
								<label><span>Show online status</span><input type="checkbox"><span class="themelab_toggle_indicator" aria-hidden="true"></span></label>
								<label class="is_disabled"><span>Disabled toggle</span><input type="checkbox" checked disabled><span class="themelab_toggle_indicator" aria-hidden="true"></span></label>
							</div>
							<label class="themelab_validation_sample">Email<input type="email" value="not-an-email" autocomplete="off"><small>Please enter a valid email address.</small></label>
							<button type="button" class="themelab_demo_button is_primary themelab_preview_form_submit">Save Profile</button>
						</div>

						<div class="themelab_preview_post_actions">
							<a href="#" onclick="return false;">Reply</a>
							<a href="#" onclick="return false;">Quote</a>
						</div>
					</div>
				</article>
			</section>


			<div class="themelab_preview_pagination" aria-label="Pagination preview">
				<a href="#" onclick="return false;">&laquo;</a>
				<span class="current_page is_active" aria-current="page">1</span>
				<a href="#" onclick="return false;">2</a>
				<a href="#" onclick="return false;">3</a>
				<a href="#" onclick="return false;">&raquo;</a>
			</div>

<footer
	id="themelab_preview_footer"
	class="themelab_preview_footer"
	data-background-mode="', $settings['footer_background_mode'], '"
	data-text-alignment="', $settings['footer_text_alignment'], '"
	style="
		min-height:', (int) $settings['footer_height'], 'px;
		color:', $settings['footer_text_color'], ';
		--themelab-preview-footer-link:', $settings['footer_link_color'], ';
		background:',
	$settings['footer_background_mode'] === 'gradient'
		? (!empty($settings['footer_gradient_css']) ? $settings['footer_gradient_css'] : 'linear-gradient('
			. themelab_get_gradient_angle($settings['footer_gradient_direction'])
			. ', ' . $settings['footer_gradient_start'] . ', ' . $settings['footer_gradient_end'] . ')')
		: $settings['footer_background_color'],
';
	"
>
	<div
		id="themelab_preview_footer_text"
		class="themelab_preview_footer_text"
	>
		', htmlspecialchars($settings['footer_text'], ENT_QUOTES, 'UTF-8'), '
	</div>

';

	if ($settings['preview_mode'] === 'footer')
	{
		echo '
	<div class="themelab_preview_footer_link_sample" aria-label="Footer link color preview">
		<span class="themelab_preview_footer_link_sample_label">Footer links:</span>
		<a href="#" onclick="return false;">Help</a>
		<span aria-hidden="true">&middot;</span>
		<a href="#" onclick="return false;">Terms</a>
		<span aria-hidden="true">&middot;</span>
		<a href="#" onclick="return false;">Rules</a>
		<span aria-hidden="true">&middot;</span>
		<a href="#" onclick="return false;">Go Up</a>
	</div>';
	}
	elseif (in_array($settings['preview_mode'], array('navigation', 'buttons'), true))
	{
		echo '
	<nav class="themelab_preview_footer_navigation" aria-label="Footer navigation preview">
		<a class="is_normal_sample" href="#" onclick="return false;">Help</a>
		<a class="is_hover_sample" href="#" onclick="return false;">Terms</a>
		<a class="is_current_sample is_current" href="#" onclick="return false;">Rules</a>
		<a class="is_normal_sample" href="#" onclick="return false;">Go Up</a>
	</nav>';
	}

	echo '

	<div class="themelab_preview_footer_system_row">
		<div
			id="themelab_preview_footer_credit"
			class="themelab_preview_footer_credit',
				empty($settings['footer_show_credit'])
					? ' is_hidden'
					: '',
			'"
		>
			', htmlspecialchars($settings['footer_credit_text'], ENT_QUOTES, 'UTF-8'), '
		</div>

	</div>

	<div class="themelab_preview_footer_brand">Made with Theme Lab</div>
</footer>
		</div>
	</aside>';
}


/**
 * Theme Lab Button Studio.
 */
function template_themelab_buttons()
{
	global $context, $txt, $scripturl;

	$project = !empty($context['themelab_active_project']) ? $context['themelab_active_project'] : array();
	$buttons = !empty($context['themelab_buttons']) && is_array($context['themelab_buttons'])
		? $context['themelab_buttons'] : array();
	$project_name = !empty($project['project_name']) ? $project['project_name'] : '';
	$buttons = array_merge(themelab_get_default_buttons(), $buttons);

	$render_color = function($key, $label) use ($buttons, $txt)
	{
		$id = 'themelab_button_' . $key;
		$value = htmlspecialchars(strtoupper($buttons[$key]), ENT_QUOTES, 'UTF-8');
		echo '<label for="', $id, '_hex"><span>', $txt[$label], '</span><span class="themelab_navigation_color_control"><input type="color" id="', $id, '" name="', $id, '" value="', $value, '" aria-label="', htmlspecialchars($txt[$label], ENT_QUOTES, 'UTF-8'), '"><input type="text" id="', $id, '_hex" class="themelab_color_hex" value="', $value, '" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" data-color-target="', $id, '" aria-label="', htmlspecialchars($txt[$label], ENT_QUOTES, 'UTF-8'), ' HEX value"></span></label>';
	};

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['themelab_buttons'], '</h3></div>
	<div class="windowbg"><p>', sprintf($txt['themelab_buttons_project'], $project_name), '</p><p>', $txt['themelab_buttons_intro'], '</p></div>';
	if (isset($_REQUEST['buttons_saved'])) echo '<div class="infobox">', $txt['themelab_buttons_saved'], '</div>';
	if (!empty($context['themelab_button_errors']))
	{
		echo '<div class="errorbox"><strong>', $txt['themelab_buttons_has_errors'], '</strong><ul>';
		foreach ($context['themelab_button_errors'] as $error) if (!empty($txt[$error])) echo '<li>', $txt[$error], '</li>';
		echo '</ul></div>';
	}

	echo '<form id="themelab_buttons_form" action="', $scripturl, '?action=admin;area=themelab;sa=buttons" method="post" accept-charset="UTF-8" novalidate>
	<input type="hidden" name="themelab_save_buttons" value="1">
	<div class="cat_bar"><h3 class="catbg">', $txt['themelab_buttons_settings'], '</h3></div>
	<div class="windowbg themelab_workspace_window"><div class="themelab_studio_layout"><div class="themelab_studio_controls"><div class="themelab_button_grid">
	<section class="themelab_layout_panel"><h4>', $txt['themelab_buttons_shape'], '</h4>
	<label class="themelab_field_label" for="themelab_button_border_radius">', $txt['themelab_buttons_border_radius'], '</label><div class="themelab_number_control"><input type="number" id="themelab_button_border_radius" name="themelab_button_border_radius" value="', (int)$buttons['border_radius'], '" min="0" max="40" required><span>px</span></div>
	<label class="themelab_field_label" for="themelab_button_border_width">', $txt['themelab_buttons_border_width'], '</label><div class="themelab_number_control"><input type="number" id="themelab_button_border_width" name="themelab_button_border_width" value="', (int)$buttons['border_width'], '" min="0" max="5" required><span>px</span></div></section>
	<section class="themelab_layout_panel"><h4>', $txt['themelab_buttons_size'], '</h4>
	<label class="themelab_field_label" for="themelab_button_padding_vertical">', $txt['themelab_buttons_vertical_padding'], '</label><div class="themelab_number_control"><input type="number" id="themelab_button_padding_vertical" name="themelab_button_padding_vertical" value="', (int)$buttons['padding_vertical'], '" min="3" max="24" required><span>px</span></div>
	<label class="themelab_field_label" for="themelab_button_padding_horizontal">', $txt['themelab_buttons_horizontal_padding'], '</label><div class="themelab_number_control"><input type="number" id="themelab_button_padding_horizontal" name="themelab_button_padding_horizontal" value="', (int)$buttons['padding_horizontal'], '" min="6" max="48" required><span>px</span></div></section>
	<section class="themelab_layout_panel"><h4>', $txt['themelab_buttons_typography'], '</h4>
	<label class="themelab_field_label" for="themelab_button_font_weight">', $txt['themelab_buttons_font_weight'], '</label><select id="themelab_button_font_weight" name="themelab_button_font_weight"><option value="400"', $buttons['font_weight']==='400'?' selected':'', '>', $txt['themelab_buttons_normal'], '</option><option value="500"', $buttons['font_weight']==='500'?' selected':'', '>', $txt['themelab_buttons_medium'], '</option><option value="700"', $buttons['font_weight']==='700'?' selected':'', '>', $txt['themelab_buttons_bold'], '</option></select>
	<label class="themelab_field_label" for="themelab_button_text_transform">', $txt['themelab_buttons_text_style'], '</label><select id="themelab_button_text_transform" name="themelab_button_text_transform"><option value="none"', $buttons['text_transform']==='none'?' selected':'', '>', $txt['themelab_buttons_normal'], '</option><option value="uppercase"', $buttons['text_transform']==='uppercase'?' selected':'', '>', $txt['themelab_buttons_uppercase'], '</option></select></section>
	<section class="themelab_layout_panel"><h4>', $txt['themelab_buttons_depth'], '</h4><select id="themelab_button_depth" name="themelab_button_depth"><option value="flat"', $buttons['depth']==='flat'?' selected':'', '>', $txt['themelab_buttons_flat'], '</option><option value="soft"', $buttons['depth']==='soft'?' selected':'', '>', $txt['themelab_buttons_soft'], '</option><option value="raised"', $buttons['depth']==='raised'?' selected':'', '>', $txt['themelab_buttons_raised'], '</option></select>
	<label class="themelab_field_label" for="themelab_button_hover_effect">', $txt['themelab_buttons_hover'], '</label><select id="themelab_button_hover_effect" name="themelab_button_hover_effect"><option value="none"', $buttons['hover_effect']==='none'?' selected':'', '>', $txt['themelab_buttons_hover_none'], '</option><option value="darken"', $buttons['hover_effect']==='darken'?' selected':'', '>', $txt['themelab_buttons_hover_darken'], '</option><option value="lift"', $buttons['hover_effect']==='lift'?' selected':'', '>', $txt['themelab_buttons_hover_lift'], '</option><option value="glow"', $buttons['hover_effect']==='glow'?' selected':'', '>', $txt['themelab_buttons_hover_glow'], '</option></select></section>
	<section class="themelab_layout_panel themelab_navigation_color_panel"><h4>', $txt['themelab_buttons_semantic_foregrounds'], '</h4><p class="smalltext">', $txt['themelab_buttons_semantic_foregrounds_desc'], '</p><div class="themelab_navigation_color_grid">';
	foreach (array('primary_text'=>'themelab_buttons_primary_text','secondary_text'=>'themelab_buttons_secondary_text','danger_background'=>'themelab_buttons_danger_background','danger_text'=>'themelab_buttons_danger_text','danger_border'=>'themelab_buttons_danger_border','disabled_background'=>'themelab_buttons_disabled_background','disabled_text'=>'themelab_buttons_disabled_text','disabled_border'=>'themelab_buttons_disabled_border') as $key=>$label) $render_color($key, $label);
	echo '</div></section>
	<section class="themelab_layout_panel themelab_navigation_color_panel"><h4>', $txt['themelab_buttons_header_buttons'], '</h4><p class="smalltext">', $txt['themelab_buttons_header_buttons_desc'], '</p><div class="themelab_button_suggestion_bar"><button type="button" class="button themelab_button_suggest" id="themelab_suggest_header_buttons">', $txt['themelab_buttons_suggest_header'], '</button><span class="smalltext">', $txt['themelab_buttons_suggest_help'], '</span></div><div class="themelab_navigation_color_grid">';
	foreach (array('header_background'=>'themelab_buttons_normal_background','header_text'=>'themelab_buttons_normal_text','header_border'=>'themelab_buttons_border_color','header_hover_background'=>'themelab_buttons_hover_background','header_hover_text'=>'themelab_buttons_hover_text','header_current_background'=>'themelab_buttons_current_background','header_current_text'=>'themelab_buttons_current_text') as $key=>$label) $render_color($key, $label);
	echo '</div></section><section class="themelab_layout_panel themelab_navigation_color_panel"><h4>', $txt['themelab_buttons_footer_buttons'], '</h4><p class="smalltext">', $txt['themelab_buttons_footer_buttons_desc'], '</p><div class="themelab_button_suggestion_bar"><button type="button" class="button themelab_button_suggest" id="themelab_suggest_footer_buttons">', $txt['themelab_buttons_suggest_footer'], '</button><span class="smalltext">', $txt['themelab_buttons_suggest_help'], '</span></div><div class="themelab_navigation_color_grid">';
	foreach (array('footer_background'=>'themelab_buttons_normal_background','footer_text'=>'themelab_buttons_normal_text','footer_border'=>'themelab_buttons_border_color','footer_hover_background'=>'themelab_buttons_hover_background','footer_hover_text'=>'themelab_buttons_hover_text','footer_current_background'=>'themelab_buttons_current_background','footer_current_text'=>'themelab_buttons_current_text') as $key=>$label) $render_color($key, $label);
	echo '</div></section></div><p class="smalltext">', $txt['themelab_buttons_preview_note'], '</p></div>';

	$preview = !empty($context['themelab_preview_settings']) ? $context['themelab_preview_settings'] : array();
	foreach ($buttons as $key => $value) $preview['button_' . $key] = $value;
	$preview['preview_mode'] = 'buttons';
	template_themelab_mini_forum_preview($preview);

	echo '</div><div class="themelab_layout_actions"><a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=typography">', $txt['themelab_back_typography'], '</a><button type="submit" name="themelab_save_buttons" value="1" form="themelab_buttons_form" class="button" formnovalidate>', $txt['themelab_save_buttons'], '</button></div></div><input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '"></form>';
}


/**
 * Theme Lab Menu and Navigation Studio.
 */
function template_themelab_forms()
{
	global $context, $txt, $scripturl;
	$project_name = !empty($context['themelab_active_project']['project_name']) ? htmlspecialchars($context['themelab_active_project']['project_name'], ENT_QUOTES, 'UTF-8') : $txt['themelab_not_set'];
	$forms = array_merge(themelab_get_default_forms(), !empty($context['themelab_forms']) && is_array($context['themelab_forms']) ? $context['themelab_forms'] : array());
	echo '<div class="cat_bar"><h3 class="catbg">', $txt['themelab_forms'], '</h3></div><div class="windowbg"><p>', sprintf($txt['themelab_forms_project'], $project_name), '</p><p>', $txt['themelab_forms_intro'], '</p></div>';
	if (isset($_REQUEST['forms_saved'])) echo '<div class="infobox">', $txt['themelab_forms_saved'], '</div>';
	if (!empty($context['themelab_forms_errors'])) { echo '<div class="errorbox"><strong>', $txt['themelab_forms_has_errors'], '</strong><ul>'; foreach($context['themelab_forms_errors'] as $e) if(!empty($txt[$e])) echo '<li>', $txt[$e], '</li>'; echo '</ul></div>'; }
	echo '<form id="themelab_forms_form" action="', $scripturl, '?action=admin;area=themelab;sa=forms" method="post" novalidate>
		<input type="hidden" name="themelab_save_forms" value="1"><div class="cat_bar"><h3 class="catbg">', $txt['themelab_forms_settings'], '</h3></div><div class="windowbg themelab_workspace_window"><div class="themelab_studio_layout"><div class="themelab_studio_controls"><div class="themelab_button_grid">';
	$select=function($name,$value,$options) use($txt){ echo '<select id="themelab_forms_', $name, '" name="themelab_forms_', $name, '">'; foreach($options as $v=>$label) { $fallback = $v === 'none' ? 'None' : ucwords(str_replace(array('_','-'), ' ', $v)); echo '<option value="',$v,'"', $value===$v?' selected':'','>', isset($txt[$label]) && $txt[$label] !== '' ? $txt[$label] : $fallback, '</option>'; } echo '</select>'; };
	echo '<section class="themelab_layout_panel"><h4>',$txt['themelab_forms_fields'],'</h4><label class="themelab_field_label">',$txt['themelab_forms_field_size'],'</label>'; $select('field_size',$forms['field_size'],array('compact'=>'themelab_forms_compact','standard'=>'themelab_forms_standard','comfortable'=>'themelab_forms_comfortable'));
	foreach(array('border_radius'=>'themelab_forms_border_radius','border_width'=>'themelab_forms_border_width','padding_horizontal'=>'themelab_forms_horizontal_padding') as $k=>$l) echo '<label class="themelab_field_label" for="themelab_forms_',$k,'">',$txt[$l],'</label><div class="themelab_number_control"><input type="number" id="themelab_forms_',$k,'" name="themelab_forms_',$k,'" value="',(int)$forms[$k],'" min="0" max="30"><span>px</span></div>';
	echo '<label class="themelab_field_label">',$txt['themelab_forms_focus_style'],'</label>'; $select('focus_style',$forms['focus_style'],array('none'=>'themelab_forms_focus_none','border'=>'themelab_forms_focus_border','glow'=>'themelab_forms_focus_glow','underline'=>'themelab_forms_focus_underline')); echo '</section>';
	echo '<section class="themelab_layout_panel"><h4>',$txt['themelab_forms_textareas'],'</h4><label class="themelab_field_label">',$txt['themelab_forms_textarea_height'],'</label><div class="themelab_number_control"><input type="number" id="themelab_forms_textarea_height" name="themelab_forms_textarea_height" value="',(int)$forms['textarea_height'],'" min="50" max="240"><span>px</span></div><label class="themelab_field_label">',$txt['themelab_forms_resize'],'</label>'; $select('textarea_resize',$forms['textarea_resize'],array('none'=>'themelab_forms_resize_none','vertical'=>'themelab_forms_resize_vertical','horizontal'=>'themelab_forms_resize_horizontal','both'=>'themelab_forms_resize_both')); echo '</section>';
	echo '<section class="themelab_layout_panel"><h4>',$txt['themelab_forms_choices'],'</h4><label class="themelab_field_label">',$txt['themelab_forms_choice_size'],'</label><div class="themelab_number_control"><input type="number" id="themelab_forms_choice_size" name="themelab_forms_choice_size" value="',(int)$forms['choice_size'],'" min="12" max="28"><span>px</span></div><label class="themelab_field_label">',$txt['themelab_forms_choice_style'],'</label>'; $select('choice_style',$forms['choice_style'],array('classic'=>'themelab_forms_classic','rounded'=>'themelab_forms_rounded','modern'=>'themelab_forms_modern')); echo '<label class="themelab_field_label">',$txt['themelab_forms_toggle_size'],'</label><div class="themelab_number_control"><input type="number" id="themelab_forms_toggle_size" name="themelab_forms_toggle_size" value="',(int)$forms['toggle_size'],'" min="18" max="36"><span>px</span></div><label class="themelab_field_label">',$txt['themelab_forms_toggle_style'],'</label>'; $select('toggle_style',$forms['toggle_style'],array('pill'=>'themelab_forms_toggle_pill','rounded'=>'themelab_forms_toggle_rounded','square'=>'themelab_forms_toggle_square')); echo '</section>';
	echo '<section class="themelab_layout_panel"><h4>',$txt['themelab_forms_labels'],'</h4><label class="themelab_field_label">',$txt['themelab_forms_label_weight'],'</label>'; $select('label_weight',$forms['label_weight'],array('400'=>'themelab_buttons_normal','500'=>'themelab_buttons_medium','700'=>'themelab_buttons_bold')); echo '<label class="themelab_field_label">',$txt['themelab_forms_required_marker'],'</label>'; $select('required_marker',$forms['required_marker'],array('asterisk'=>'themelab_forms_asterisk','bold'=>'themelab_forms_bold_marker','none'=>'themelab_forms_no_marker')); echo '<label class="themelab_field_label">',$txt['themelab_forms_validation_style'],'</label>'; $select('validation_style',$forms['validation_style'],array('border'=>'themelab_forms_validation_border','background'=>'themelab_forms_validation_background','icon'=>'themelab_forms_validation_icon')); echo '</section></div><p class="smalltext">',$txt['themelab_forms_preview_note'],'</p></div>';
	$preview=$context['themelab_preview_settings']??array(); foreach($forms as $k=>$v) $preview['forms_'.$k]=$v; $preview['preview_mode']='forms'; template_themelab_mini_forum_preview($preview);
	echo '</div><div class="themelab_layout_actions"><a class="button" href="',$scripturl,'?action=admin;area=themelab;sa=buttons">',$txt['themelab_back_buttons'],'</a><button class="button" type="submit" name="themelab_save_forms" value="1" form="themelab_forms_form" formnovalidate>',$txt['themelab_save_forms'],'</button></div></div><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"></form>';
}

function template_themelab_navigation()
{
	global $context, $txt, $scripturl;
	$project_name = !empty($context['themelab_active_project']['project_name']) ? htmlspecialchars($context['themelab_active_project']['project_name'], ENT_QUOTES, 'UTF-8') : $txt['themelab_not_set'];
	$navigation = array_merge(themelab_get_default_navigation(), !empty($context['themelab_navigation']) && is_array($context['themelab_navigation']) ? $context['themelab_navigation'] : array());
	$select = function($name, $value, $options) use ($txt) { echo '<select id="themelab_navigation_', $name, '" name="themelab_navigation_', $name, '">'; foreach ($options as $v => $label) echo '<option value="', $v, '"', $value === $v ? ' selected' : '', '>', $txt[$label], '</option>'; echo '</select>'; };

	echo '<div class="cat_bar"><h3 class="catbg">', $txt['themelab_navigation'], '</h3></div><div class="windowbg"><p>', sprintf($txt['themelab_navigation_project'], $project_name), '</p><p>', $txt['themelab_navigation_intro'], '</p></div>';
	if (isset($_REQUEST['navigation_saved'])) echo '<div class="infobox">', $txt['themelab_navigation_saved'], '</div>';
	if (!empty($context['themelab_navigation_errors'])) { echo '<div class="errorbox"><strong>', $txt['themelab_navigation_has_errors'], '</strong><ul>'; foreach ($context['themelab_navigation_errors'] as $e) if (!empty($txt[$e])) echo '<li>', $txt[$e], '</li>'; echo '</ul></div>'; }
	echo '<form id="themelab_navigation_form" action="', $scripturl, '?action=admin;area=themelab;sa=navigation" method="post" novalidate><input type="hidden" name="themelab_save_navigation" value="1"><div class="cat_bar"><h3 class="catbg">', $txt['themelab_navigation_settings'], '</h3></div><div class="windowbg themelab_workspace_window"><div class="themelab_studio_layout"><div class="themelab_studio_controls"><div class="themelab_button_grid">';
	echo '<section class="themelab_layout_panel"><h4>', $txt['themelab_navigation_menu_shape'], '</h4><label class="themelab_field_label">', $txt['themelab_navigation_menu_radius'], '</label><div class="themelab_number_control"><input type="number" id="themelab_navigation_menu_radius" name="themelab_navigation_menu_radius" value="', (int)$navigation['menu_radius'], '" min="0" max="30"><span>px</span></div><label class="themelab_field_label">', $txt['themelab_navigation_menu_gap'], '</label><div class="themelab_number_control"><input type="number" id="themelab_navigation_menu_gap" name="themelab_navigation_menu_gap" value="', (int)$navigation['menu_gap'], '" min="0" max="24"><span>px</span></div></section>';
	echo '<section class="themelab_layout_panel"><h4>', $txt['themelab_navigation_menu_spacing'], '</h4><label class="themelab_field_label">', $txt['themelab_navigation_vertical_padding'], '</label><div class="themelab_number_control"><input type="number" id="themelab_navigation_menu_padding_vertical" name="themelab_navigation_menu_padding_vertical" value="', (int)$navigation['menu_padding_vertical'], '" min="2" max="20"><span>px</span></div><label class="themelab_field_label">', $txt['themelab_navigation_horizontal_padding'], '</label><div class="themelab_number_control"><input type="number" id="themelab_navigation_menu_padding_horizontal" name="themelab_navigation_menu_padding_horizontal" value="', (int)$navigation['menu_padding_horizontal'], '" min="4" max="36"><span>px</span></div></section>';
	echo '<section class="themelab_layout_panel"><h4>', $txt['themelab_navigation_typography'], '</h4><label class="themelab_field_label">', $txt['themelab_navigation_font_weight'], '</label>'; $select('font_weight',$navigation['font_weight'],array('400'=>'themelab_buttons_normal','500'=>'themelab_buttons_medium','700'=>'themelab_buttons_bold')); echo '<label class="themelab_field_label">', $txt['themelab_navigation_text_style'], '</label>'; $select('text_transform',$navigation['text_transform'],array('none'=>'themelab_buttons_normal','uppercase'=>'themelab_buttons_uppercase')); echo '<label class="themelab_field_label">', $txt['themelab_navigation_active_emphasis'], '</label>'; $select('active_emphasis',$navigation['active_emphasis'],array('filled'=>'themelab_navigation_filled','underline'=>'themelab_navigation_underline','outline'=>'themelab_navigation_outline')); echo '<p class="smalltext">', $txt['themelab_navigation_menu_active_desc'], '</p></section>';
	echo '<section class="themelab_layout_panel"><h4>', $txt['themelab_navigation_breadcrumbs'], '</h4><label class="themelab_field_label">', $txt['themelab_navigation_breadcrumb_style'], '</label>'; $select('breadcrumb_style',$navigation['breadcrumb_style'],array('plain'=>'themelab_navigation_plain','bar'=>'themelab_navigation_bar','boxed'=>'themelab_navigation_boxed')); echo '<label class="themelab_field_label">', $txt['themelab_navigation_breadcrumb_separator'], '</label>'; $select('breadcrumb_separator',$navigation['breadcrumb_separator'],array('chevron'=>'themelab_navigation_chevron','slash'=>'themelab_navigation_slash','dot'=>'themelab_navigation_dot')); echo '</section>';
	echo '<section class="themelab_layout_panel themelab_pagination_panel"><h4>', $txt['themelab_navigation_pagination'], '</h4><p class="smalltext">', $txt['themelab_navigation_pagination_desc'], '</p><label class="themelab_field_label">', $txt['themelab_navigation_pagination_style'], '</label>'; $select('pagination_style',$navigation['pagination_style'],array('plain'=>'themelab_navigation_plain','boxed'=>'themelab_navigation_boxed','pills'=>'themelab_navigation_pills')); echo '<label class="themelab_field_label">', $txt['themelab_navigation_pagination_active_style'], '</label>'; $select('pagination_active_emphasis',$navigation['pagination_active_emphasis'],array('filled'=>'themelab_navigation_filled','underline'=>'themelab_navigation_underline','outline'=>'themelab_navigation_outline')); echo '<label class="themelab_field_label">', $txt['themelab_navigation_pagination_size'], '</label>'; $select('pagination_size',$navigation['pagination_size'],array('compact'=>'themelab_navigation_compact','standard'=>'themelab_navigation_standard','large'=>'themelab_navigation_large')); echo '<label class="themelab_field_label">', $txt['themelab_navigation_pagination_gap'], '</label><div class="themelab_number_control"><input type="number" id="themelab_navigation_pagination_gap" name="themelab_navigation_pagination_gap" value="', (int)$navigation['pagination_gap'], '" min="0" max="16"><span>px</span></div><label class="themelab_field_label">', $txt['themelab_navigation_pagination_radius'], '</label><div class="themelab_number_control"><input type="number" id="themelab_navigation_pagination_radius" name="themelab_navigation_pagination_radius" value="', (int)$navigation['pagination_radius'], '" min="0" max="24"><span>px</span></div><label class="themelab_field_label">', $txt['themelab_navigation_pagination_active_weight'], '</label>'; $select('pagination_active_weight',$navigation['pagination_active_weight'],array('balanced'=>'themelab_navigation_balanced','400'=>'themelab_buttons_normal','500'=>'themelab_buttons_medium','600'=>'themelab_navigation_semibold','700'=>'themelab_buttons_bold')); echo '<p class="smalltext">', $txt['themelab_navigation_pagination_weight_desc'], '</p></section>';
	echo '<section class="themelab_layout_panel"><h4>', $txt['themelab_navigation_footer_behavior'], '</h4><label class="themelab_field_label" for="themelab_navigation_footer_style">', $txt['themelab_navigation_footer_style'], '</label>'; $select('footer_style',$navigation['footer_style'],array('text'=>'themelab_navigation_text_links','buttons'=>'themelab_navigation_button_links')); echo '<p class="smalltext">', $txt['themelab_navigation_colors_moved'], '</p><a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=buttons">', $txt['themelab_navigation_edit_button_colors'], '</a></section>';
	echo '</div><p class="smalltext">', $txt['themelab_navigation_preview_note'], '</p></div>';
	$preview = !empty($context['themelab_preview_settings']) ? $context['themelab_preview_settings'] : array(); foreach ($navigation as $key => $value) $preview['navigation_' . $key] = $value; $preview['preview_mode'] = 'navigation'; template_themelab_mini_forum_preview($preview);
	echo '</div><div class="themelab_layout_actions"><a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=forms">', $txt['themelab_back_forms'], '</a><button type="submit" name="themelab_save_navigation" value="1" form="themelab_navigation_form" class="button" formnovalidate>', $txt['themelab_save_navigation'], '</button></div></div><input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '"></form>';
}

/**
 * Theme Lab Header Studio.
 */
function template_themelab_board_index()
{
	global $context, $txt, $scripturl;
	$project_name = !empty($context['themelab_active_project']['project_name']) ? $context['themelab_active_project']['project_name'] : $txt['themelab_not_set'];
	$settings = array_merge(themelab_get_default_board_index(), !empty($context['themelab_board_index']) && is_array($context['themelab_board_index']) ? $context['themelab_board_index'] : array());
	$render_board_color = function($key, $label) use ($settings, $txt) { $id='themelab_board_index_'.$key; $value=htmlspecialchars(strtoupper($settings[$key]), ENT_QUOTES, 'UTF-8'); echo '<label for="'.$id.'_hex"><span>'.$txt[$label].'</span><span class="themelab_navigation_color_control"><input type="color" id="'.$id.'" name="'.$id.'" value="'.$value.'"><input type="text" id="'.$id.'_hex" class="themelab_color_hex" value="'.$value.'" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" data-color-target="'.$id.'"></span></label>'; };
	$select = function($name, $value, $options) use ($txt) { echo '<select id="themelab_board_index_', $name, '" name="themelab_board_index_', $name, '">'; foreach ($options as $v => $label) echo '<option value="', $v, '"', $value === $v ? ' selected' : '', '>', $txt[$label], '</option>'; echo '</select>'; };
	echo '<div class="cat_bar"><h3 class="catbg">', $txt['themelab_board_index'], '</h3></div><div class="windowbg"><p>', sprintf($txt['themelab_board_index_project'], $project_name), '</p><p>', $txt['themelab_board_index_intro'], '</p></div>';
	if (isset($_REQUEST['board_index_saved'])) echo '<div class="infobox">', $txt['themelab_board_index_saved'], '</div>';
	if (!empty($context['themelab_board_index_errors'])) { echo '<div class="errorbox"><strong>', $txt['themelab_board_index_has_errors'], '</strong><ul>'; foreach ($context['themelab_board_index_errors'] as $e) if (!empty($txt[$e])) echo '<li>', $txt[$e], '</li>'; echo '</ul></div>'; }
	echo '<form id="themelab_board_index_form" action="', $scripturl, '?action=admin;area=themelab;sa=board_index" method="post" novalidate>
		<input type="hidden" name="themelab_save_board_index" value="1"><div class="cat_bar"><h3 class="catbg">', $txt['themelab_board_index_settings'], '</h3></div><div class="windowbg themelab_workspace_window"><div class="themelab_studio_layout"><div class="themelab_studio_controls"><div class="themelab_button_grid">';
	echo '<section class="themelab_layout_panel"><h4>', $txt['themelab_board_index_containers'], '</h4>';
	foreach (array('container_radius'=>'themelab_board_index_radius','border_width'=>'themelab_board_index_border','panel_padding'=>'themelab_board_index_padding') as $key=>$label) echo '<label class="themelab_field_label">',$txt[$label],'</label><div class="themelab_number_control"><input type="number" id="themelab_board_index_',$key,'" name="themelab_board_index_',$key,'" value="',(int)$settings[$key],'"><span>px</span></div>';
	echo '<label class="themelab_field_label">',$txt['themelab_board_index_shadow'],'</label>'; $select('shadow_style',$settings['shadow_style'],array('none'=>'themelab_shadow_none','soft'=>'themelab_shadow_soft','medium'=>'themelab_shadow_medium','strong'=>'themelab_shadow_strong')); echo '</section>';
	echo '<section class="themelab_layout_panel"><h4>',$txt['themelab_board_index_headers'],'</h4><label class="themelab_field_label">',$txt['themelab_board_index_header_height'],'</label><div class="themelab_number_control"><input type="number" id="themelab_board_index_header_height" name="themelab_board_index_header_height" value="',(int)$settings['header_height'],'"><span>px</span></div><label class="themelab_field_label">',$txt['themelab_board_index_header_alignment'],'</label>'; $select('header_alignment',$settings['header_alignment'],array('left'=>'themelab_align_left','center'=>'themelab_align_center','right'=>'themelab_align_right')); echo '<label class="themelab_field_label">',$txt['themelab_board_index_header_weight'],'</label>'; $select('header_weight',$settings['header_weight'],array('400'=>'themelab_buttons_normal','500'=>'themelab_buttons_medium','700'=>'themelab_buttons_bold')); echo '</section>';
	echo '<section class="themelab_layout_panel"><h4>',$txt['themelab_board_index_rows'],'</h4><label class="themelab_field_label">',$txt['themelab_board_index_row_spacing'],'</label>'; $select('row_spacing',$settings['row_spacing'],array('tight'=>'themelab_board_index_tight','standard'=>'themelab_board_index_standard','spacious'=>'themelab_board_index_spacious')); echo '<div class="themelab_navigation_color_grid themelab_board_row_color">'; $render_board_color('row_background','themelab_board_index_row_background'); $render_board_color('row_background_alt','themelab_board_index_row_background_alt'); echo '</div><p class="smalltext">',$txt['themelab_board_index_row_background_desc'],'</p><label class="themelab_field_label">',$txt['themelab_board_index_stats_position'],'</label>'; $select('stats_position',$settings['stats_position'],array('right'=>'themelab_align_right','below'=>'themelab_board_index_below')); echo '<label class="themelab_field_label">',$txt['themelab_board_index_alternating'],'</label>'; $select('alternating_rows',$settings['alternating_rows'],array('on'=>'themelab_board_index_on','off'=>'themelab_board_index_off')); echo '</section>';
	echo '<section class="themelab_layout_panel themelab_navigation_color_panel"><h4>',$txt['themelab_board_index_foregrounds'],'</h4><p class="smalltext">',$txt['themelab_board_index_foregrounds_desc'],'</p><div class="themelab_navigation_color_grid">'; foreach (array('header_text'=>'themelab_board_index_header_text','header_link'=>'themelab_board_index_header_link','stats_text'=>'themelab_board_index_stats_text','description_text'=>'themelab_board_index_description_text','unread_indicator'=>'themelab_board_index_unread_indicator') as $key=>$label) $render_board_color($key,$label); echo '</div></section>';
	echo '<section class="themelab_layout_panel"><h4>',$txt['themelab_board_index_details'],'</h4><label class="themelab_field_label">',$txt['themelab_board_index_last_post'],'</label>'; $select('last_post_layout',$settings['last_post_layout'],array('compact'=>'themelab_board_index_compact','standard'=>'themelab_board_index_standard','expanded'=>'themelab_board_index_expanded')); echo '<label class="themelab_field_label">',$txt['themelab_board_index_icon_style'],'</label>'; $select('icon_style',$settings['icon_style'],array('classic'=>'themelab_board_index_classic','filled'=>'themelab_board_index_filled','outline'=>'themelab_board_index_outline','modern'=>'themelab_board_index_modern')); echo '</section></div><p class="smalltext">',$txt['themelab_board_index_preview_note'],'</p></div>';
	$preview = $context['themelab_preview_settings'] ?? array(); foreach ($settings as $k=>$v) $preview['board_index_'.$k]=$v; $preview['preview_mode']='board_index'; template_themelab_mini_forum_preview($preview);
	echo '</div><div class="themelab_layout_actions"><a class="button" href="',$scripturl,'?action=admin;area=themelab;sa=navigation">',$txt['themelab_back_navigation'],'</a><button class="button" type="submit" name="themelab_save_board_index" value="1" form="themelab_board_index_form" formnovalidate>',$txt['themelab_save_board_index'],'</button></div></div><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"></form>';
}

function template_themelab_header()
{
	global $context, $txt, $scripturl;

	$project = !empty($context['themelab_active_project'])
		? $context['themelab_active_project']
		: array();

	$header = !empty($context['themelab_header'])
		&& is_array($context['themelab_header'])
			? $context['themelab_header']
			: array();

	$project_name = $project['project_name'] ?? '';

	$height = isset($header['height'])
		? (int) $header['height']
		: 130;

	$logo_text = $header['logo_text'] ?? 'Theme Lab Forum';
	$logo_image = $header['logo_image'] ?? '';
	$logo_image_url = !empty($logo_image) ? themelab_get_logo_url($logo_image) : '';
	$logo_filename = $header['logo_filename'] ?? '';
	$logo_width = (int) ($header['logo_width'] ?? 0);
	$logo_height = (int) ($header['logo_height'] ?? 0);
	$logo_source_width = (int) ($header['logo_source_width'] ?? $logo_width);
	$logo_source_height = (int) ($header['logo_source_height'] ?? $logo_height);
	$logo_was_normalized = !empty($header['logo_was_normalized']);
	$logo_mime = $header['logo_mime'] ?? '';
	$logo_width = isset($header['logo_width']) ? (int) $header['logo_width'] : 0;
	$logo_height = isset($header['logo_height']) ? (int) $header['logo_height'] : 0;
	$logo_size = isset($header['logo_size']) && (int) $header['logo_size'] >= 25 && (int) $header['logo_size'] <= 100
		? (int) $header['logo_size']
		: 100;
	$logo_alignment = $header['logo_alignment'] ?? 'left';

	// RC6.1.20n: Header Studio branding controls use the canonical header array.
	$smf_logo_visibility = !empty($header['smf_logo_visibility'])
		&& in_array($header['smf_logo_visibility'], array('show', 'hide'), true)
			? $header['smf_logo_visibility']
			: 'show';
	$smf_logo_position = !empty($header['smf_logo_position'])
		&& in_array($header['smf_logo_position'], array('left', 'center', 'right'), true)
			? $header['smf_logo_position']
			: 'right';
	$background_image = $header['background_image'] ?? '';
	$background_image_url = !empty($background_image) ? themelab_get_header_background_url($background_image) : '';
	$background_image_filename = $header['background_image_filename'] ?? '';
	$background_image_mime = $header['background_image_mime'] ?? '';
	$background_image_width = isset($header['background_image_width']) ? (int) $header['background_image_width'] : 0;
	$background_image_height = isset($header['background_image_height']) ? (int) $header['background_image_height'] : 0;
	$background_position = $header['background_image_position'] ?? 'center';
	$position_presets = array('center' => array(50, 50), 'top' => array(50, 0), 'bottom' => array(50, 100), 'left' => array(0, 50), 'right' => array(100, 50));
	$position_fallback = $position_presets[$background_position] ?? array(50, 50);
	$background_position_x = isset($header['background_image_position_x']) ? max(0, min(100, (int) $header['background_image_position_x'])) : $position_fallback[0];
	$background_position_y = isset($header['background_image_position_y']) ? max(0, min(100, (int) $header['background_image_position_y'])) : $position_fallback[1];
	$background_size = $header['background_image_size'] ?? 'cover';
	$image_opacity = isset($header['background_image_opacity']) ? max(0, min(100, (int) $header['background_image_opacity'])) : 100;
	$background_opacity = isset($header['background_overlay_opacity']) ? max(0, min(100, (int) $header['background_overlay_opacity'])) : 35;
	$background_color = $header['background_color'] ?? '#3465A4';
	$text_color = $header['text_color'] ?? '#FFFFFF';
	$navigation_alignment = $header['navigation_alignment'] ?? 'left';
	$navigation_style = $header['navigation_style'] ?? 'pills';
	$search_position = $header['search_position'] ?? 'right';
	$utility_mode = !empty($header['utility_mode']) && $header['utility_mode'] === 'integrated' ? 'integrated' : 'separate';
	$utility_position = !empty($header['utility_position']) && in_array($header['utility_position'], array('auto', 'left', 'right'), true) ? $header['utility_position'] : 'auto';

	$project_data =
		!empty($project['project_data_array'])
		&& is_array($project['project_data_array'])
			? $project['project_data_array']
			: array();

	$palette = !empty($project_data['palette']['colors'])
		? $project_data['palette']['colors']
		: array();

	$layout = !empty($project_data['layout'])
		? $project_data['layout']
		: array();

	$typography = !empty($project_data['typography'])
		? $project_data['typography']
		: array();
        
	$fonts = themelab_get_typography_fonts();
	$mono_fonts = themelab_get_monospace_fonts();

	$font_key = $typography['font_family'] ?? 'verdana';
	$mono_key = $typography['monospace_font'] ?? 'consolas';

	$line_heights = array(
		'compact' => '1.25',
		'comfortable' => '1.5',
		'relaxed' => '1.75',
	);

	$preview_settings = themelab_build_preview_settings($project);

$preview_settings = array_merge(
	$preview_settings,
	array(
		'header_height' => $height,
		'logo_text' => $logo_text,
		'logo_image' => $logo_image,
		'logo_image_url' => $logo_image_url,
		'logo_filename' => $logo_filename,
		'logo_mime' => $logo_mime,
		'logo_width' => $logo_width,
		'logo_height' => $logo_height,
		'logo_size' => $logo_size,
		'logo_alignment' => $logo_alignment,
		'header_background_image' => $background_image,
		'header_background_image_url' => $background_image_url,
		'header_background_mode' => !empty($background_image_url) ? 'image' : ($preview_settings['header_background_mode'] ?? 'solid'),
		'header_background_position' => $background_position,
		'header_background_position_x' => $background_position_x,
		'header_background_position_y' => $background_position_y,
		'header_background_size' => $background_size,
		'header_image_opacity' => $image_opacity,
		'header_background_opacity' => $background_opacity,
		'header_background_color' => $background_color,
		'header_text_color' => $text_color,
		'navigation_alignment' => $navigation_alignment,
		'navigation_style' => $navigation_style,
		'search_position' => $search_position,
		'utility_mode' => $utility_mode,
		'utility_position' => $utility_position,
	)
);

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['themelab_header'], '</h3>
	</div>

	<div class="windowbg">
		<p>',
			sprintf($txt['themelab_header_project'], $project_name),
		'</p>
		<p>', $txt['themelab_header_intro'], '</p>
	</div>';

	if (isset($_REQUEST['header_saved']))
	{
		echo '
	<div class="infobox">
		', $txt['themelab_header_saved'], '
	</div>';
	}

	if (!empty($context['themelab_header_errors']))
	{
		echo '
	<div class="errorbox">
		<strong>', $txt['themelab_header_has_errors'], '</strong>
		<ul>';

		foreach ($context['themelab_header_errors'] as $error)
		{
			if (!empty($txt[$error]))
				echo '<li>', $txt[$error], '</li>';
		}

		echo '
		</ul>
	</div>';
	}

	echo '
	<form
		id="themelab_header_form"
		action="', $scripturl, '?action=admin;area=themelab;sa=header"
		method="post"
		enctype="multipart/form-data"
		accept-charset="UTF-8"
		novalidate
	>
		<input type="hidden" name="themelab_save_header" value="1">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themelab_header_settings'], '</h3>
		</div>

		<div class="windowbg themelab_workspace_window">
			<div class="themelab_studio_layout">
				<div class="themelab_studio_controls">

					<section class="themelab_header_panel">
						<h4>', $txt['themelab_header_identity'], '</h4>

						<div class="themelab_logo_heading">
							<label class="themelab_field_label" for="themelab_logo_text">
								', $txt['themelab_header_logo_text'], '
							</label>
							<span class="themelab_tooltip" tabindex="0" aria-label="', htmlspecialchars($txt['themelab_header_logo_tip'], ENT_QUOTES, 'UTF-8'), '">?</span>
						</div>

						<input
							type="text"
							id="themelab_logo_text"
							name="themelab_logo_text"
							value="', htmlspecialchars($logo_text, ENT_QUOTES, 'UTF-8'), '"
							size="40"
						>
						<p class="smalltext">', $txt['themelab_header_logo_text_desc'], '</p>

						<div
							id="themelab_logo_dropzone"
							class="themelab_logo_dropzone', !empty($logo_image_url) ? ' has_logo' : '', '"
							tabindex="0"
							role="button"
							aria-controls="themelab_logo_image"
						>
							<input
								type="file"
								id="themelab_logo_image"
								name="themelab_logo_image"
								accept="image/png,image/jpeg,image/webp"
							>
							<div class="themelab_logo_dropzone_prompt">
								<strong>', $txt['themelab_header_logo_upload'], '</strong>
								<span>', $txt['themelab_header_logo_drop'], '</span>
								<small>', $txt['themelab_header_logo_formats'], '</small>
							</div>
						</div>

						<div id="themelab_logo_status" class="themelab_logo_status', !empty($logo_image_url) ? '' : ' is_empty', '">
							<img id="themelab_logo_thumbnail" src="', htmlspecialchars($logo_image_url, ENT_QUOTES, 'UTF-8'), '" alt=""', empty($logo_image_url) ? ' hidden' : '', '>
							<div>
								<strong id="themelab_logo_status_title">', !empty($logo_image_url) ? $txt['themelab_header_logo_ready'] : $txt['themelab_header_logo_none'], '</strong>
								<span id="themelab_logo_status_details">', !empty($logo_image_url)
									? htmlspecialchars($logo_filename, ENT_QUOTES, 'UTF-8') . ' — ' . $logo_width . ' × ' . $logo_height . ' — ' . strtoupper(str_replace('image/', '', $logo_mime))
										. ($logo_was_normalized && ($logo_source_width !== $logo_width || $logo_source_height !== $logo_height)
											? ' — ' . sprintf($txt['themelab_header_logo_normalized_from'], $logo_source_width, $logo_source_height)
											: '')
									: $txt['themelab_header_logo_none_desc'], '</span>
							</div>
						</div>

						<div class="themelab_logo_actions">
							<button type="button" class="button" id="themelab_logo_browse">', !empty($logo_image_url) ? $txt['themelab_header_logo_replace'] : $txt['themelab_header_logo_browse'], '</button>
							<label class="themelab_logo_remove', empty($logo_image_url) ? ' is_hidden' : '', '" id="themelab_logo_remove_label">
								<input type="checkbox" id="themelab_remove_logo" name="themelab_remove_logo" value="1">
								', $txt['themelab_header_logo_remove'], '
							</label>
						</div>

						<div class="themelab_logo_size_control">
							<div class="themelab_logo_size_heading">
								<label class="themelab_field_label" for="themelab_logo_size">', $txt['themelab_header_logo_size'], '</label>
								<output id="themelab_logo_size_value" for="themelab_logo_size">', $logo_size, '%</output>
							</div>
							<input
								type="range"
								id="themelab_logo_size"
								name="themelab_logo_size"
								min="25"
								max="100"
								step="5"
								value="', $logo_size, '"
							>
							<div class="themelab_logo_size_limits smalltext"><span>25%</span><span>100% safe maximum</span></div>
							<div id="themelab_logo_rendered_size" class="themelab_logo_rendered_size smalltext" aria-live="polite"></div>
							<p class="smalltext">', $txt['themelab_header_logo_size_desc'], '</p>
							<p class="smalltext themelab_logo_safety_note">', $txt['themelab_header_logo_safety_desc'], '</p>
						</div>

						<label class="themelab_field_label" for="themelab_header_height">
							', $txt['themelab_header_height'], '
						</label>

						<div class="themelab_number_control">
							<input
								type="number"
								id="themelab_header_height"
								name="themelab_header_height"
								value="', $height, '"
								min="70"
								max="350"
								required
							>
							<span>px</span>
						</div>

						<h5>', $txt['themelab_header_logo_alignment'], '</h5>
						<div class="themelab_choice_group">';

	foreach (array('left', 'center', 'right') as $choice)
	{
		echo '
							<label>
								<input
									type="radio"
									name="themelab_logo_alignment"
									value="', $choice, '"',
									$logo_alignment === $choice ? ' checked' : '',
								'>
								', $txt['themelab_align_' . $choice], '
							</label>';
	}

	echo '
						</div>
					</section>

					<section class="themelab_header_panel">
						<h4>', $txt['themelab_header_background'], '</h4>

						<input type="hidden" name="themelab_header_background_mode" value="', !empty($background_image_url) ? 'image' : 'solid', '">
						<p class="smalltext">Header gradients are configured in Gradient Studio. Header Studio owns the solid base color and uploaded artwork.</p>

						<div id="themelab_header_background_image_panel" class="themelab_header_image_panel">
							<label class="themelab_field_label" for="themelab_header_background_image">', $txt['themelab_header_image_upload'], '</label>
							<input type="file" id="themelab_header_background_image" name="themelab_header_background_image" accept="image/png,image/jpeg,image/webp">
							<p class="smalltext">', $txt['themelab_header_image_formats'], '</p>
							<div id="themelab_header_background_status" class="themelab_header_image_status', empty($background_image_url) ? ' is_empty' : '', '">
								<img id="themelab_header_background_thumbnail" src="', htmlspecialchars($background_image_url, ENT_QUOTES, 'UTF-8'), '" alt=""', empty($background_image_url) ? ' hidden' : '', '>
								<span id="themelab_header_background_details">', !empty($background_image_url) ? htmlspecialchars($background_image_filename, ENT_QUOTES, 'UTF-8') . ' — ' . $background_image_width . ' × ' . $background_image_height : $txt['themelab_header_image_none'], '</span>
							</div>
							<label id="themelab_header_background_remove_label" class="themelab_logo_remove', empty($background_image_url) ? ' is_hidden' : '', '"><input type="checkbox" id="themelab_remove_header_background" name="themelab_remove_header_background" value="1"> ', $txt['themelab_header_image_remove'], '</label>
							<div class="themelab_header_image_options">
								<label><strong>', $txt['themelab_header_image_position'], '</strong><select id="themelab_header_background_position" name="themelab_header_background_position">';
	foreach (array('center','top','bottom','left','right','custom') as $choice)
		echo '<option value="', $choice, '"', $background_position === $choice ? ' selected' : '', '>', $txt['themelab_position_' . $choice], '</option>';
	echo '</select></label>
								<label><strong>', $txt['themelab_header_image_size'], '</strong><select id="themelab_header_background_size" name="themelab_header_background_size">';
	foreach (array('cover','contain','auto') as $choice)
		echo '<option value="', $choice, '"', $background_size === $choice ? ' selected' : '', '>', $txt['themelab_size_' . $choice], '</option>';
	echo '</select></label>
							</div>
							<div class="themelab_header_focal_controls">
								<div class="themelab_logo_size_heading"><label class="themelab_field_label" for="themelab_header_background_position_x">', $txt['themelab_header_position_x'], '</label><output id="themelab_header_background_position_x_value">', $background_position_x, '%</output></div>
								<input type="range" id="themelab_header_background_position_x" name="themelab_header_background_position_x" min="0" max="100" step="1" value="', $background_position_x, '">
								<div class="themelab_logo_size_heading"><label class="themelab_field_label" for="themelab_header_background_position_y">', $txt['themelab_header_position_y'], '</label><output id="themelab_header_background_position_y_value">', $background_position_y, '%</output></div>
								<input type="range" id="themelab_header_background_position_y" name="themelab_header_background_position_y" min="0" max="100" step="1" value="', $background_position_y, '">
								<p class="smalltext">', $txt['themelab_header_position_desc'], '</p>
							</div>
							<div class="themelab_logo_size_control">
								<div class="themelab_logo_size_heading"><label class="themelab_field_label" for="themelab_header_image_opacity">', $txt['themelab_header_image_opacity'], '</label><output id="themelab_header_image_opacity_value">', $image_opacity, '%</output></div>
							<input type="range" id="themelab_header_image_opacity" name="themelab_header_image_opacity" min="0" max="100" step="5" value="', $image_opacity, '">
							<p class="smalltext">', $txt['themelab_header_image_opacity_desc'], '</p>

							<div class="themelab_logo_size_heading"><label class="themelab_field_label" for="themelab_header_background_opacity">', $txt['themelab_header_overlay_opacity'], '</label><output id="themelab_header_background_opacity_value">', $background_opacity, '%</output></div>
								<input type="range" id="themelab_header_background_opacity" name="themelab_header_background_opacity" min="0" max="100" step="5" value="', $background_opacity, '">
								<p class="smalltext">', $txt['themelab_header_overlay_opacity_desc'], '</p>
							</div>
						</div>

						<div class="themelab_header_color_grid">
							<label>
								<strong>', $txt['themelab_header_solid_color'], '</strong>
								<input
									type="color"
									id="themelab_header_background_color"
									name="themelab_header_background_color"
									value="', $background_color, '"
								>

								<input
									type="text"
									class="themelab_color_hex themelab_studio_color_hex"
									data-color-target="themelab_header_background_color"
									value="', $background_color, '"
									maxlength="7"
									spellcheck="false"
									aria-label="Hex color"
								>
							</label>



							<label>
								<strong>', $txt['themelab_header_text_color'], '</strong>
								<input
									type="color"
									id="themelab_header_text_color"
									name="themelab_header_text_color"
									value="', $text_color, '"
								>

								<input
									type="text"
									class="themelab_color_hex themelab_studio_color_hex"
									data-color-target="themelab_header_text_color"
									value="', $text_color, '"
									maxlength="7"
									spellcheck="false"
									aria-label="Hex color"
								>
							</label>
						</div>
					</section>


					<section class="themelab_header_panel">
						<h4>', $txt['themelab_header_smf_logo'], '</h4>
						<p class="smalltext">', $txt['themelab_header_smf_logo_desc'], '</p>
						<div class="themelab_choice_group">
							<label><input type="radio" name="themelab_smf_logo_visibility" value="show"', $smf_logo_visibility === 'show' ? ' checked' : '', '> ', $txt['themelab_show'], '</label>
							<label><input type="radio" name="themelab_smf_logo_visibility" value="hide"', $smf_logo_visibility === 'hide' ? ' checked' : '', '> ', $txt['themelab_hide'], '</label>
						</div>
						<h5>', $txt['themelab_header_smf_logo_position'], '</h5>
						<div class="themelab_choice_group">';
	foreach (array('left', 'center', 'right') as $choice)
		echo '<label><input type="radio" name="themelab_smf_logo_position" value="', $choice, '"', $smf_logo_position === $choice ? ' checked' : '', '> ', $txt['themelab_align_' . $choice], '</label>';
	echo '</div>
					</section>

					<section class="themelab_header_panel" id="themelab_header_utilities_panel">
						<h4>', $txt['themelab_header_utilities'], '</h4>
						<p class="smalltext">', $txt['themelab_header_utilities_desc'], '</p>
						<div class="themelab_choice_group">
							<label><input type="radio" name="themelab_utility_mode" value="separate"', $utility_mode === 'separate' ? ' checked' : '', '> ', $txt['themelab_header_utilities_separate'], '</label>
							<label><input type="radio" name="themelab_utility_mode" value="integrated"', $utility_mode === 'integrated' ? ' checked' : '', '> ', $txt['themelab_header_utilities_integrated'], '</label>
						</div>
						<h5>', $txt['themelab_header_utility_position'], '</h5>
						<div class="themelab_choice_group" id="themelab_utility_position_group">
							<label><input type="radio" name="themelab_utility_position" value="auto"', $utility_position === 'auto' ? ' checked' : '', '> ', $txt['themelab_header_utility_auto'], '</label>
							<label><input type="radio" name="themelab_utility_position" value="left"', $utility_position === 'left' ? ' checked' : '', '> ', $txt['themelab_align_left'], '</label>
							<label><input type="radio" name="themelab_utility_position" value="right"', $utility_position === 'right' ? ' checked' : '', '> ', $txt['themelab_align_right'], '</label>
						</div>
						<p class="smalltext" id="themelab_utility_smart_note">', $txt['themelab_header_utility_smart_desc'], '</p>
					</section>

					<section class="themelab_header_panel">
						<h4>', $txt['themelab_header_navigation'], '</h4>

						<label class="themelab_field_label" for="themelab_navigation_style">
							', $txt['themelab_header_navigation_style'], '
						</label>

						<select
							id="themelab_navigation_style"
							name="themelab_navigation_style"
						>
							<option value="classic"', $navigation_style === 'classic' ? ' selected' : '', '>
								', $txt['themelab_nav_classic'], '
							</option>
							<option value="pills"', $navigation_style === 'pills' ? ' selected' : '', '>
								', $txt['themelab_nav_pills'], '
							</option>
							<option value="minimal"', $navigation_style === 'minimal' ? ' selected' : '', '>
								', $txt['themelab_nav_minimal'], '
							</option>
						</select>

						<h5>', $txt['themelab_header_navigation_alignment'], '</h5>
						<div class="themelab_choice_group">';

	foreach (array('left', 'center', 'right') as $choice)
	{
		echo '
							<label>
								<input
									type="radio"
									name="themelab_navigation_alignment"
									value="', $choice, '"',
									$navigation_alignment === $choice ? ' checked' : '',
								'>
								', $txt['themelab_align_' . $choice], '
							</label>';
	}

	echo '
						</div>

						<label class="themelab_field_label" for="themelab_search_position">
							', $txt['themelab_header_search_position'], '
						</label>

						<select
							id="themelab_search_position"
							name="themelab_search_position"
						>
							<option value="hidden"', $search_position === 'hidden' ? ' selected' : '', '>
								', $txt['themelab_search_hidden'], '
							</option>
							<option value="left"', $search_position === 'left' ? ' selected' : '', '>
								', $txt['themelab_align_left'], '
							</option>
							<option value="right"', $search_position === 'right' ? ' selected' : '', '>
								', $txt['themelab_align_right'], '
							</option>
						</select>
					</section>
				</div>';

	template_themelab_mini_forum_preview($preview_settings);

	echo '
			</div>

			<div class="themelab_layout_actions">
				<a
					class="button"
					href="', $scripturl, '?action=admin;area=themelab;sa=navigation"
				>
					', $txt['themelab_back_navigation'], '
				</a>

				<button
					type="submit"
					name="themelab_save_header"
					value="1"
					form="themelab_header_form"
					class="button"
				 formnovalidate>
					', $txt['themelab_save_header'], '
				</button>
			</div>
		</div>

		<input
			type="hidden"
			name="', $context['session_var'], '"
			value="', $context['session_id'], '"
		>
	</form>';
}

/**
 * Theme Lab Footer Studio.
 */
function template_themelab_footer()
{
	global $context, $txt, $scripturl;

	$project = !empty($context['themelab_active_project'])
		? $context['themelab_active_project']
		: array();

	$footer =
		!empty($context['themelab_footer'])
		&& is_array($context['themelab_footer'])
			? $context['themelab_footer']
			: array();

	$project_name = !empty($project['project_name'])
		? $project['project_name']
		: '';

	$height = isset($footer['height'])
		? (int) $footer['height']
		: 70;

	$footer_text = isset($footer['footer_text'])
		? $footer['footer_text']
		: '';

	$text_alignment = !empty($footer['text_alignment'])
		? $footer['text_alignment']
		: 'center';

	$background_color = !empty($footer['background_color'])
		? $footer['background_color']
		: '#D5DEE5';

	$text_color = !empty($footer['text_color'])
		? $footer['text_color']
		: '#222222';

	$link_color = !empty($footer['link_color'])
		? $footer['link_color']
		: '#3465A4';

	$show_credit = !empty($footer['show_credit']);
	$credit_text = themelab_get_footer_credit_text();

	$project_data =
		!empty($project['project_data_array'])
		&& is_array($project['project_data_array'])
			? $project['project_data_array']
			: array();

	$palette = !empty($project_data['palette']['colors'])
		&& is_array($project_data['palette']['colors'])
			? $project_data['palette']['colors']
			: array();

	$layout = !empty($project_data['layout'])
		&& is_array($project_data['layout'])
			? $project_data['layout']
			: array();

	$typography = !empty($project_data['typography'])
		&& is_array($project_data['typography'])
			? $project_data['typography']
			: array();

	$header = !empty($project_data['header'])
		&& is_array($project_data['header'])
			? $project_data['header']
			: array();

	$fonts = themelab_get_typography_fonts();
	$mono_fonts = themelab_get_monospace_fonts();

	$font_key = !empty($typography['font_family'])
		? $typography['font_family']
		: 'verdana';

	$mono_key = !empty($typography['monospace_font'])
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

	$preview_settings = themelab_build_preview_settings($project);

	$preview_settings = array_merge(
		$preview_settings,
		array(
			'footer_height' => $height,
			'footer_text' => $footer_text,
			'footer_text_alignment' => $text_alignment,
			'footer_background_color' => $background_color,
			'footer_text_color' => $text_color,
			'footer_link_color' => $link_color,
			'footer_show_credit' => $show_credit ? 1 : 0,
			'footer_credit_text' => $credit_text,
		)
	);

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['themelab_footer'], '</h3>
	</div>

	<div class="windowbg">
		<p>',
			sprintf(
				$txt['themelab_footer_project'],
				$project_name
			),
		'</p>

		<p>', $txt['themelab_footer_intro'], '</p>
	</div>';

	if (isset($_REQUEST['footer_saved']))
	{
		echo '
	<div class="infobox">
		', $txt['themelab_footer_saved'], '
	</div>';
	}

	if (!empty($context['themelab_footer_errors']))
	{
		echo '
	<div class="errorbox">
		<strong>', $txt['themelab_footer_has_errors'], '</strong>

		<ul>';

		foreach ($context['themelab_footer_errors'] as $error)
		{
			if (!empty($txt[$error]))
				echo '
			<li>', $txt[$error], '</li>';
		}

		echo '
		</ul>
	</div>';
	}

	echo '
	<form
		id="themelab_footer_form"
		action="', $scripturl, '?action=admin;area=themelab;sa=footer"
		method="post"
		accept-charset="UTF-8"
		novalidate
	>
		<input type="hidden" name="themelab_save_footer" value="1">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themelab_footer_settings'], '</h3>
		</div>

		<div class="windowbg themelab_workspace_window">
			<div class="themelab_studio_layout">
				<div class="themelab_studio_controls">

					<section class="themelab_header_panel">
						<h4>', $txt['themelab_footer_content'], '</h4>

						<label
							class="themelab_field_label"
							for="themelab_footer_text"
						>
							', $txt['themelab_footer_custom_text'], '
						</label>

						<input
							type="text"
							id="themelab_footer_text"
							name="themelab_footer_text"
							value="', htmlspecialchars($footer_text, ENT_QUOTES, 'UTF-8'), '"
							size="50"
						>

						<label
							class="themelab_field_label"
							for="themelab_footer_height"
						>
							', $txt['themelab_footer_height'], '
						</label>

						<div class="themelab_number_control">
							<input
								type="number"
								id="themelab_footer_height"
								name="themelab_footer_height"
								value="', $height, '"
								min="40"
								max="300"
								required
							>
							<span>px</span>
						</div>

						<h5>', $txt['themelab_footer_alignment'], '</h5>

						<div class="themelab_choice_group">';

	foreach (array('left', 'center', 'right') as $choice)
	{
		echo '
							<label>
								<input
									type="radio"
									name="themelab_footer_text_alignment"
									value="', $choice, '"',
									$text_alignment === $choice
										? ' checked'
										: '',
								'>
								', $txt['themelab_align_' . $choice], '
							</label>';
	}

	echo '
						</div>

						<label class="themelab_toggle_row">
							<input
								type="checkbox"
								id="themelab_footer_show_credit"
								name="themelab_footer_show_credit"
								value="1"',
								$show_credit ? ' checked' : '',
							'>

							<span>
								<strong>',
									$txt['themelab_footer_show_credit'],
								'</strong>
								<br>
								<span class="smalltext">',
									$txt['themelab_footer_show_credit_desc'],
								'</span>
							</span>
						</label>

						<div class="themelab_footer_credit_sample">
							<span class="smalltext">',
								$txt['themelab_footer_credit_preview'],
							'</span>
							<strong>', htmlspecialchars($credit_text, ENT_QUOTES, 'UTF-8'), '</strong>
						</div>
					</section>

					<section class="themelab_header_panel">
						<h4>', $txt['themelab_footer_background'], '</h4>


						<p class="smalltext themelab_footer_gradient_notice">
							', $txt['themelab_footer_gradient_managed'], '
							<a href="', $scripturl, '?action=admin;area=themelab;sa=gradients">', $txt['themelab_footer_open_gradient_studio'], '</a>
						</p>

						<div class="themelab_header_color_grid">
							<label>
								<strong>', $txt['themelab_footer_solid_color'], '</strong>

								<input
									type="color"
									id="themelab_footer_background_color"
									name="themelab_footer_background_color"
									value="', $background_color, '"
								>

								<input
									type="text"
									class="themelab_color_hex themelab_studio_color_hex"
									data-color-target="themelab_footer_background_color"
									value="', $background_color, '"
									maxlength="7"
									spellcheck="false"
									aria-label="Hex color"
								>
							</label>

							<label>
								<strong>', $txt['themelab_footer_text_color'], '</strong>

								<input
									type="color"
									id="themelab_footer_text_color"
									name="themelab_footer_text_color"
									value="', $text_color, '"
								>

								<input
									type="text"
									class="themelab_color_hex themelab_studio_color_hex"
									data-color-target="themelab_footer_text_color"
									value="', $text_color, '"
									maxlength="7"
									spellcheck="false"
									aria-label="Hex color"
								>
							</label>

							<label>
								<strong>', $txt['themelab_footer_link_color'], '</strong>

								<input
									type="color"
									id="themelab_footer_link_color"
									name="themelab_footer_link_color"
									value="', $link_color, '"
								>

								<input
									type="text"
									class="themelab_color_hex themelab_studio_color_hex"
									data-color-target="themelab_footer_link_color"
									value="', $link_color, '"
									maxlength="7"
									spellcheck="false"
									aria-label="Hex color"
								>
							</label>
						</div>
					</section>
				</div>';

	template_themelab_mini_forum_preview($preview_settings);

	echo '
			</div>

			<div class="themelab_layout_actions">
				<a
					class="button"
					href="', $scripturl,
						'?action=admin;area=themelab;sa=header"
				>
					', $txt['themelab_back_header'], '
				</a>

				<button
					type="submit"
					name="themelab_save_footer"
					value="1"
					form="themelab_footer_form"
					class="button"
				 formnovalidate>
					', $txt['themelab_save_footer'], '
				</button>
			</div>
		</div>

		<input
			type="hidden"
			name="', $context['session_var'], '"
			value="', $context['session_id'], '"
		>
	</form>';
}
/**
 * Theme Review — Stage 1 of Theme Review & Publishing.
 */
function template_themelab_review()
{
	global $context, $txt, $scripturl, $settings;

	$project = $context['themelab_active_project'];
	$review = $context['themelab_review_data'];
	$project_name = htmlspecialchars($project['project_name'], ENT_QUOTES, 'UTF-8');
	$version = htmlspecialchars($project['theme_version'], ENT_QUOTES, 'UTF-8');
	$status_class = $review['ready_for_certification'] ? 'is_ready' : 'needs_attention';
	$status_label = $review['ready_for_certification']
		? $txt['themelab_review_ready']
		: $txt['themelab_review_attention'];

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['themelab_review_title'], '</h3></div>
	<div class="windowbg themelab_review_hero ', $status_class, '">
		<div>
			<span class="themelab_review_eyebrow">', $txt['themelab_review_stage'], '</span>
			<h2>', $project_name, '</h2>
			<p>', sprintf($txt['themelab_review_version_line'], $version), '</p>
		</div>
		<div class="themelab_review_status">
			<strong>', $status_label, '</strong>
			<span>', $review['configured_count'], ' / ', $review['total_studios'], ' ', $txt['themelab_review_studios_configured'], '</span>
		</div>
	</div>

	<div class="information themelab_review_notice">
		<strong>', $txt['themelab_review_stage_one_notice_title'], '</strong><br>
		', $txt['themelab_review_stage_one_notice'], '
	</div>

	<div class="themelab_review_grid">
		<section>
			<div class="cat_bar"><h3 class="catbg">', $txt['themelab_review_project_details'], '</h3></div>
			<div class="windowbg themelab_review_panel">
				<dl class="themelab_review_metadata">';

	foreach ($review['metadata'] as $item)
	{
		$value = $item['complete'] ? $item['value'] : $txt['themelab_review_not_provided'];
		echo '
					<div class="', $item['complete'] ? 'is_complete' : 'is_missing', '">
						<dt>', htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
		if ($item['required'])
			echo ' <span class="themelab_review_required">*</span>';
		echo '</dt>
						<dd>', nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')), '</dd>
						<span class="themelab_review_badge">', $item['complete'] ? $txt['themelab_review_complete'] : ($item['required'] ? $txt['themelab_review_required'] : $txt['themelab_review_optional']), '</span>
					</div>';
	}

	echo '
				</dl>
				<div class="themelab_review_panel_actions">
					<a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=footer">', htmlspecialchars(!empty($context['themelab_registry']['footer']['label']) ? $context['themelab_registry']['footer']['label'] : 'Footer', ENT_QUOTES, 'UTF-8'), '</a>
					<a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=project">', $txt['themelab_review_edit_project'], '</a>
				</div>
			</div>
		</section>

		<section>
			<div class="cat_bar"><h3 class="catbg">', $txt['themelab_review_design_summary'], '</h3></div>
			<div class="windowbg themelab_review_panel">
				<div class="themelab_review_studios">';

	foreach ($review['studios'] as $studio)
	{
		echo '
					<a href="', $scripturl, '?action=admin;area=themelab;sa=', rawurlencode($studio['sa']), '" class="themelab_review_studio ', $studio['configured'] ? 'is_configured' : 'uses_defaults', '">
						<span class="themelab_review_studio_icon">', $studio['configured'] ? '&#10003;' : '&#9675;', '</span>
						<span>
							<strong>', htmlspecialchars($studio['label'], ENT_QUOTES, 'UTF-8'), '</strong>
							<small>', htmlspecialchars($studio['summary'], ENT_QUOTES, 'UTF-8'), '</small>
						</span>
						<em>', $studio['configured'] ? $txt['themelab_review_configured'] : $txt['themelab_review_using_defaults'], '</em>
					</a>';
	}

	echo '
				</div>
				<div class="themelab_review_panel_actions is_right">
					<a class="button" href="', $scripturl, '?action=admin;area=themelab;sa=publish">', htmlspecialchars(!empty($context['themelab_registry']['publish']['label']) ? $context['themelab_registry']['publish']['label'] : 'Publish', ENT_QUOTES, 'UTF-8'), '</a>
				</div>
			</div>
		</section>
	</div>
';
}

/**
 * Publish Studio.
 */
function template_themelab_publish()
{
	global $context, $txt, $scripturl, $user_info;

	$project = $context['themelab_active_project'];
	$validation = $context['themelab_publish_validation'];
	$result = !empty($context['themelab_publish_result']) ? $context['themelab_publish_result'] : array();
	$status_class = $validation['ready'] ? 'is_ready' : 'needs_attention';

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['themelab_publish_title'], '</h3></div>
	<div class="windowbg themelab_publish_hero ', $status_class, '">
		<div>
			<span class="themelab_review_eyebrow">', $txt['themelab_publish_stage'], '</span>
			<h2>', htmlspecialchars($project['project_name'], ENT_QUOTES, 'UTF-8'), '</h2>
			<p>', sprintf($txt['themelab_publish_version_line'], htmlspecialchars(!empty($result['published_version']) ? $result['published_version'] : themelab_publish_increment_version($project['theme_version']), ENT_QUOTES, 'UTF-8')), '</p>
			<p>', sprintf($txt['themelab_publish_publisher_line'], htmlspecialchars(!empty($result['publisher']) ? $result['publisher'] : themelab_publish_current_publisher(), ENT_QUOTES, 'UTF-8')), '</p>
		</div>
		<div class="themelab_publish_score">
			<strong>', $validation['ready'] ? $txt['themelab_publish_certified'] : $txt['themelab_publish_not_ready'], '</strong>
			<span>', $validation['passed'], ' / ', $validation['total'], ' ', $txt['themelab_publish_checks_passed'], '</span>
		</div>
	</div>';

	if (!empty($result) && empty($result['success']))
		echo '<div class="errorbox"><strong>', $txt['themelab_publish_failed'], '</strong><br>', htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8'), '</div>';

	if (!empty($result['pipeline']))
	{
		echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['themelab_publish_pipeline'], '</h3></div>
	<div class="windowbg themelab_publish_pipeline">
		<div class="themelab_publish_pipeline_header">
			<div><span class="themelab_review_eyebrow">', $txt['themelab_publish_pipeline_stage'], '</span><h3>', htmlspecialchars($project['project_name'], ENT_QUOTES, 'UTF-8'), '</h3></div>
			<strong class="themelab_publish_pipeline_result ', !empty($result['success']) ? 'is_pass' : 'is_fail', '">', !empty($result['success']) ? $txt['themelab_publish_build_successful'] : $txt['themelab_publish_build_stopped'], '</strong>
		</div>
		<div class="themelab_publish_pipeline_steps">';
		foreach ($result['pipeline'] as $stage)
		{
			$status = !empty($stage['status']) ? $stage['status'] : 'pending';
			echo '
			<div class="themelab_publish_pipeline_step is_', htmlspecialchars($status, ENT_QUOTES, 'UTF-8'), '">
				<span class="themelab_publish_pipeline_icon">', $status === 'pass' ? '&#10003;' : ($status === 'fail' ? '&#10007;' : '&#8226;'), '</span>
				<div><strong>', htmlspecialchars($stage['label'], ENT_QUOTES, 'UTF-8'), '</strong>';
			if (!empty($stage['details']))
			{
				echo '<ul>';
				foreach ($stage['details'] as $detail)
					echo '<li>', htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'), '</li>';
				echo '</ul>';
			}
			echo '</div></div>';
		}
		echo '
		</div>';
		if (!empty($result['success']))
			echo '<div class="themelab_publish_metrics"><span><strong>', (int) $result['token_total'], '</strong>', $txt['themelab_publish_metric_saved'], '</span><span><strong>', (int) $result['compiler_values'], '</strong>', $txt['themelab_publish_metric_accounted'], '</span><span><strong>', (int) $result['css_values'], '</strong>', $txt['themelab_publish_metric_compiled'], '</span><span><strong>', (int) $result['file_count'], '</strong>', $txt['themelab_publish_metric_files'], '</span><span><strong>', number_format((float) $result['duration'], 2), 's</strong>', $txt['themelab_publish_metric_time'], '</span></div>';
		echo '</div>';
	}

	echo '
	<div class="themelab_publish_grid">
		<section>
			<div class="cat_bar"><h3 class="catbg">', $txt['themelab_publish_validation'], '</h3></div>
			<div class="windowbg themelab_publish_panel">
				<div class="themelab_publish_checks">';

	foreach ($validation['items'] as $check)
	{
		echo '
					<div class="themelab_publish_check ', $check['status'] ? 'is_pass' : 'is_fail', '">
						<span class="themelab_publish_check_icon">', $check['status'] ? '&#10003;' : '&#10007;', '</span>
						<div><strong>', htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8'), '</strong>';
		if (!$check['status'])
			echo '<small>', htmlspecialchars($check['message'], ENT_QUOTES, 'UTF-8'), '</small>';
		echo '</div></div>';
	}

	echo '
				</div>
			</div>
		</section>
		<section>
			<div class="cat_bar"><h3 class="catbg">', $txt['themelab_publish_package_options'], '</h3></div>
			<form action="', $scripturl, '?action=admin;area=themelab;sa=publish" method="post" class="windowbg themelab_publish_panel">
				<label class="themelab_publish_option"><input type="checkbox" name="include_readme" value="1" checked> <span><strong>', $txt['themelab_publish_include_readme'], '</strong><small>', $txt['themelab_publish_include_readme_desc'], '</small></span></label>
				<label class="themelab_publish_option"><input type="checkbox" name="include_license" value="1" checked> <span><strong>', $txt['themelab_publish_include_license'], '</strong><small>', $txt['themelab_publish_include_license_desc'], '</small></span></label>
				<label class="themelab_publish_option"><input type="checkbox" name="include_changelog" value="1" checked> <span><strong>', $txt['themelab_publish_include_changelog'], '</strong><small>', $txt['themelab_publish_include_changelog_desc'], '</small></span></label>';

	if (!empty($context['themelab_publish_color_modes_enabled']))
		echo '<div class="themelab_publish_option themelab_publish_color_modes"><span><strong>', $txt['themelab_publish_color_modes'], '</strong><small>', $txt['themelab_publish_color_modes_palette_desc'], '</small></span></div>';
	else
		echo '<div class="themelab_publish_option themelab_publish_color_modes"><span><strong>', $txt['themelab_publish_single_mode'], '</strong><small>', $txt['themelab_publish_single_mode_desc'], '</small></span></div>';

	echo '
				<div class="themelab_publish_structure"><strong>', $txt['themelab_publish_package_structure'], '</strong><pre>', htmlspecialchars(themeslab_publish_structure_preview($project), ENT_QUOTES, 'UTF-8'), '</pre></div>
				<div class="themelab_publish_action', !empty($result['success']) ? ' is_published' : '', '">';

	if (!empty($result['success']))
	{
		$built_at = !empty($result['built_at']) ? $result['built_at'] : time();
		echo '
					<div class="themelab_publish_success_summary">
						<strong>&#10003; ', $txt['themelab_publish_success'], '</strong>
						<span>', htmlspecialchars($result['filename'], ENT_QUOTES, 'UTF-8'), ' &mdash; ', number_format((int) $result['size'] / 1024, 1), ' KB</span>
						<small>', $txt['themelab_publish_built_at'], ': ', date('M j, Y g:i A', (int) $built_at), ' &mdash; ', $txt['themelab_publish_published_by'], ': ', htmlspecialchars($result['publisher'], ENT_QUOTES, 'UTF-8'), '</small>
					</div>
					<div class="themelab_publish_action_buttons">
						<button type="button" class="button themelab_publish_button is_complete" disabled aria-disabled="true">&#10003; ', $txt['themelab_publish_completed'], '</button>
						<a class="button themelab_publish_button themelab_publish_download_button" href="', $scripturl, '?action=admin;area=themelab;sa=publish;download=', rawurlencode($result['filename']), ';', $context['session_var'], '=', $context['session_id'], '">', $txt['themelab_publish_download'], '</a>
					</div>
					<p class="themelab_publish_action_help">', $txt['themelab_publish_download_ready'], '</p>';
	}
	else
	{
		echo '
					<div class="themelab_publish_action_buttons">
						<button type="submit" name="themelab_publish_theme" value="1" class="button themelab_publish_button"', $validation['ready'] ? '' : ' disabled aria-disabled="true"', '>', $txt['themelab_publish_button'], '</button>
					</div>
					<p class="themelab_publish_action_help">', $validation['ready'] ? $txt['themelab_publish_ready_help'] : $txt['themelab_publish_blocked_help'], '</p>';
	}

	echo '
				</div>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</section>
	</div>';

	template_themelab_studio_navigation();
}

function themeslab_publish_structure_preview($project)
{
	$slug = function_exists('themelab_publish_slug') ? themelab_publish_slug($project['project_name']) : 'theme';
	return $slug . "/\n├── css/\n│   └── themelab-generated.css\n├── images/\n├── languages/\n├── scripts/\n├── index.template.php\n├── theme_info.xml\n├── THEMELAB_CERTIFICATION.txt\n├── README.txt\n├── LICENSE.txt\n└── CHANGELOG.txt";
}
