# Theme Lab v1.0.2

- Promoted the tested `1.0.1-dev14k` line to stable.
- Added direct Package Manager upgrade support from `1.0.1-dev14k`.
- Normalized package, source, language, compiler, and documentation version identity to 1.0.2.
- Corrected Workspace import documentation to match the actual 64 MB application-level limit.
- Verified Workspace import reads ZIP contents without extracting arbitrary archive paths.
- Verified admin-only access and SMF session checks for Workspace mutations and published-package downloads.
- Verified uninstall detaches integration hooks and runtime settings while preserving Workspace data unless database removal is explicitly selected.
- Verified PHP syntax, JavaScript syntax, language-key coverage, package manifest file coverage, and archive integrity.
- No database schema changes.

## 1.0.1-dev12 — True Final Main Navigation Stacking Authority
- Removes #upper_section from the late integrated-header z-index:30 rule that was trapping primary navigation dropdowns beneath Administration secondary navigation.
- Adds final #upper_section/main-menu stacking authority immediately before the generated CSS is returned, so no later Theme Lab rule can demote it.
- Keeps primary dropdown panels fully opaque.
- Republish the Theme Lab theme after upgrading.

## 1.0.1-dev11 — Ancestor-Aware Main Navigation Stacking Fix
- Raises the full primary-header ancestor stacking chain so Admin and other main-nav dropdowns cannot be covered by Administration/Profile secondary menus.
- Fixes the case where #top_section/#upper_section remained at z-index 30 and trapped a higher-z-index submenu underneath #genericmenu.
- Forces main-nav dropdown panels and rows to an opaque Theme Lab surface with no inherited background image.
- Republish the Theme Lab theme after upgrading.

## 1.0.1-dev10 — Main Navigation Stacking Authority
- Prevents Theme Lab's generated CSS from demoting the primary navigation to z-index 20-23 later in the stylesheet.
- Gives the header/main-menu stacking chain final authority over normal page content so Admin and other dropdowns remain on top.
- Requires republishing the Theme Lab theme after upgrading so the generated theme CSS receives the fix.

# Theme Lab 1.0.1-dev8 — Final Composer Popup Authority

- Adds a final generated-CSS authority for posting-field autocomplete and popup layers.
- Keeps the New Topic/Reply content chain overflow-visible where popup controls need to escape their rows.
- Places `#post_header` above SCEditor and keeps focused posting fields above the composer.
- Adds direct Better Tags suggestion-surface compatibility without changing Better Tags itself.
- Preserves all dev7 composer, PM dropdown, and Delete-icon fixes.

# Theme Lab 1.0.1-dev7 — Composer Popup Stacking

- Stops the SMF posting header from clipping autocomplete/suggestion popups.
- Keeps `#post_header` above SCEditor while preserving the editor's own stacking layer.
- Fixes Better Tags autocomplete appearing behind the composer in Theme Lab-generated themes.
- No theme settings, publishing behavior, PM dropdown fix, or Delete-icon behavior changed.

## 1.0.1-dev6

- Permanently fixed the native SMF Delete-icon red-square regression in Personal Messages and other quickbutton surfaces.
- Corrected Theme Lab's selector ownership: `remove_button` is an SMF icon class, not a destructive-button class.
- Native remove glyphs now keep a transparent background while real moderation Delete/Remove buttons retain danger styling.
- No SMF core files or delete behavior changed; republish generated themes after upgrading.

## 1.0.1-dev5

- Fixed PM inbox secondary dropdowns still painting behind the Pages pagination row.
- Raised the `#genericmenu` wrapper above `#admin_content` so child dropdowns can actually clear PM pagination.
- Kept the fix limited to generated theme CSS; no SMF core changes.

## 1.0.1-dev4

- Fixed secondary `.generic_menu` dropdown stacking in Personal Messages and other SMF secondary navigation areas.
- PM inbox dropdowns now paint above nearby pagination rows such as `Pages: 1`, matching Curve2 behavior.
- Kept the fix scoped to generated-theme navigation CSS; no SMF core files are modified.
- Added direct Package Manager upgrade support from public Theme Lab 1.0.1.

## 1.0.1 — Public Release

- Promoted the tested 1.0.1 development line to the public 1.0.1 package.
- Added direct upgrade support from `1.0.1-dev3h`.
- Removed the unused empty BoardMod modification action; Theme Lab installs through files, installer code, and SMF integration hooks without core edits.
- Added native optional Workspace database removal on uninstall: files/hooks always uninstall, while saved Workspaces can be preserved when database removal is left unchecked.
- Made GD optional in Environment Check; missing GD no longer blocks Theme Lab, while validated uploaded images remain supported.
- Normalized generated-theme compiler/build metadata to Theme Lab 1.0.1.
- Refreshed public README, certification, and uninstall/data-preservation documentation.

## 1.0.1-dev3h

- Fixed desktop primary-menu dropdown stacking so long Home/menu submenus stay above topic action buttons such as Reply and Add Poll.
- Raised the generated integrated header stacking context instead of relying only on a child submenu z-index.
- Added direct package upgrade support from 1.0.1-dev3f and 1.0.1-dev3g.

## 1.0.1-dev3f

- Fixed mobile User actions / Moderation popup z-index so topic quickbuttons cannot overlap the modal.
- Added proper spacing between wrapped mobile popup action buttons.
- Added direct package upgrade support from 1.0.1-dev3e.

## 1.0.1-dev3e
- Removed remaining child-board vertical divider lines.
- Added soft shadow to the rounded Posts/Topics stats card.
- Hardened border cleanup against legacy/theme inherited rules.

## 1.0.1-dev3e
- Premium polish for child-board rows.
- Added compact themed Posts/Topics card.
- Removed hard/double separator appearance.
- Rebalanced child-board grid and mobile stack.

## 1.0.1-dev3e
- Fixed native SMF child-board `.up_contain` layout in generated themes.
- Added dedicated child-board stats and last-post columns.
- Removed legacy float/divider geometry that cramped Posts/Topics.
- Added mobile child-board stacking rules.
- Applies equally in normal forum mode and Better Portal embeds.

# Theme Lab Changelog

## 1.0.0 — Initial Stable Release

- Theme Workspace creation, persistence, import, editing, and deletion.
- Palette, Gradient, Layout, Typography, Header, Buttons, Forms, Navigation, Board Index, and Footer Studios.
- Shared live preview with save/reload and Studio-to-Studio persistence.
- Curve2-based theme publishing with preview-to-production parity.
- Import/edit support for Theme Lab-generated themes and workspace data.
- Integrated Header Utilities with native SMF profile, messages, alerts, search, guest/login behavior, and responsive placement.
- Configurable navigation and pagination styling.
- Palette-aware Board Index presentation, including Posts/Topics statistics cards.
- Theme-aware forms, buttons, popups, login overlay, admin surfaces, posting/topic surfaces, and common SMF components.
- Environment checks, Review checks, and publish validation.
- Install, upgrade, uninstall, reinstall, fresh-theme creation, and imported-theme editing verified before release.
