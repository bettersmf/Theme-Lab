# Theme Lab v1.0.2

Theme Lab is a visual theme workspace and publisher for SMF 2.1.x. It provides guided design studios, live preview, reusable Workspaces, Theme Lab theme import/recovery, and generation of installable SMF theme packages without editing SMF core files.

## Stable release

Version 1.0.2 promotes the tested 1.0.1-dev14k development line to stable. It includes the completed Workspace workflow, import/recovery support, publishing pipeline, light/dark mode foundation, integrated header/navigation work, mobile popup fixes, and the accumulated Curve2 polish from the 1.0.1 development series.

Uninstalling always detaches Theme Lab files and hooks. Saved Workspaces are preserved unless the administrator explicitly chooses SMF's database/data removal option. Published themes are independent of Theme Lab and are not removed when Theme Lab is uninstalled.

---

## Development history

# Theme Lab 1.0.1-dev14k — Mobile Popup Height Repair

Root cause found:
- dev14j changed `.popup_container` to `position: relative` in order to lift it above the integrated header.
- SMF mobile popup containers are overlay elements. Making them relative pulled the overlay into normal page flow.
- The result was the giant empty cell visible after opening Main Menu or Administration Center Menu.

Fix:
- Mobile popup containers are fixed to the viewport again.
- The popup overlay no longer contributes height to the page.
- Popup windows size to their actual content (`height:auto`, `min-height:0`).
- Popup windows are capped to the viewport and scroll internally only if needed.
- Popup content/menu trees are prevented from inheriting stretched heights.
- dev14j themed popup headers, Admin popup colors, high z-index stacking, envelope icon and bell icon are retained.

Desktop behavior is unchanged.

# Theme Lab 1.0.1-dev14j — Mobile Popup Header + Scroll Stacking Fix

This pass targets the actual remaining mobile defects:

- Main Menu popup header strip falling back to white.
- Administration Center mobile popup/body falling back to white.
- Both mobile menu popups being painted behind the integrated header while scrolling.

## Fix
- Replaces direct-child-only popup selectors with ancestry-independent `.popup_container` authority.
- Styles every mobile popup heading/title bar with Theme Lab's configured header/card-header colors.
- Styles Admin/mobile popup content, generic menu and menu rows with Theme Lab surface/text tokens.
- Raises the popup container to the highest mobile stacking layer.
- Uses `:has(.popup_container)` to lift `#main_menu`, `#adm_submenus`, `.generic_menu`, or `#upper_section` when SMF nests the popup inside one of those stacking contexts.
- Preserves the dev14h/dev14i envelope and bell icons.

Desktop behavior is unchanged.

# Theme Lab 1.0.1-dev14i — Mobile Menu State/Popup Authority

This pass fixes the two mobile menu problems shown after dev14h.

## Root cause
dev14h still forced the original desktop menu trees to `display:block` on mobile.
SMF's mobile UI is supposed to hide those source trees and open a popup copy from
the hamburger controls. Forcing the source tree visible caused duplicated,
half-open and overlapping main/admin menus.

## Fix
- Original `#main_menu` desktop tree is hidden at mobile width.
- Original Administration Center generic menu tree is hidden at mobile width.
- SMF's hamburger/popup menu copy becomes the only visible mobile menu.
- Mobile popup shells now use Theme Lab surface, border and header colors.
- Main and Admin popup rows use readable Theme Lab text colors.
- Active/current rows keep the configured Header Current button colors.
- Nested menu children remain in normal flow inside the popup.
- Close icons remain visible.
- dev14h envelope/bell utility icons are retained.

Desktop navigation is unchanged.

# Theme Lab 1.0.1-dev14h — Mobile Integrated Header/Menu Rebuild

Fixes two mobile regressions shown in the Halloween Theme Lab theme.

## Header utilities
- Removes the incorrect dependency on guessed `#pm_menu_top` / `#alerts_menu_top` IDs.
- Targets the actual PM and Alerts destinations semantically by href.
- Messages always receives an envelope icon on mobile.
- Alerts always receives a bell icon on mobile.
- Unread-count badges remain visible.
- Icons inherit the generated header utility text color.

## Primary navigation
- Adds a dedicated mobile menu authority instead of reusing desktop header-button rules.
- Mobile menu panel uses Theme Lab surface/border tokens.
- Inactive menu rows use readable `--tl-text`.
- Hover/focus uses the Theme Lab alternate surface/link color.
- Current/active menu item retains the configured Header Current button colors.
- Mobile submenus return to normal document flow instead of inheriting desktop absolute dropdown positioning.
- Child icons/text inherit the readable row color instead of getting washed out by desktop rules.

Desktop navigation and header utility behavior remain unchanged.

# Theme Lab 1.0.1-dev14g — Mobile Header Message/Alert Icons

- Fixes blank Messages and Alerts pills when integrated header utilities collapse on mobile.
- Desktop keeps the clean text-pill presentation.
- At <=640px, Messages always gets an envelope icon.
- Alerts always gets a bell icon.
- Icons inherit the generated header utility text color, so they remain readable in any Theme Lab palette.
- Existing unread-count badges remain visible beside the icon.
- Uses semantic generated SVG masks rather than depending on SMF sprite state, so the icons remain reliable even though native `.main_icons` are intentionally hidden in the integrated header.

# Theme Lab 1.0.1-dev14f — Dark Mode Board Row Fix

Root cause:
- Board Index Studio correctly stored custom light-theme row colors.
- A late generated-CSS authority wrote those colors as literal HEX backgrounds.
- Those literal colors overrode Theme Lab's dark-mode surface variables, leaving the board rows white in night mode.

Fix:
- Board Index row colors now use Theme Lab CSS variables.
- Light mode keeps the exact Board Index Studio row colors.
- Dark mode derives coordinated row and alternate-row surfaces from Theme Lab's dark palette.
- Row text follows the dark text token.
- The Board Index final authority no longer hard-codes light-only backgrounds.
- All dev14e smart button suggestion work remains intact.

# Theme Lab 1.0.1-dev14e — Smart Header/Footer Button Suggestions

Buttons Studio now includes:
- Suggest From Header
- Suggest From Footer

The helper:
- reads the current Header/Footer surface colors
- uses gradient endpoints when that surface has an active gradient
- considers the Workspace primary and accent colors
- derives Normal, Hover, Current, Border and Text states
- automatically chooses light/dark text for contrast
- populates the existing editable button controls
- updates the mini-forum preview immediately
- does not save automatically; the user can tweak the suggestion first

No automatic background synchronization is introduced. This is an explicit helper only.

# Theme Lab 1.0.1-dev14d — Live Header Gradient Preview Fix

Root cause:
- Gradient Studio correctly updated `--themelab-header-base` on the shared mini-forum root.
- The preview `<header>` also carried its own inline `--themelab-header-base`.
- That child-local CSS variable shadowed the root value, so the visible header remained frozen at page-load state.

Fix:
- Moves the initial `--themelab-header-base` value to the mini-forum root.
- Removes the duplicate child-local variable from the preview header.
- Gradient Studio can now update the single authoritative live variable and the header redraws immediately.
- Footer, Category Header and Card Header paths were audited; they do not use the same conflicting child-local variable pattern.
- Retains all dev14c palette seeding, preview isolation, live-sync and color-mode work.

# Theme Lab 1.0.1-dev14c — First Palette Specialist Seeding Fix

Root cause:
- New Workspaces pre-create Header/Footer with Theme Lab defaults.
- Palette's original first-use logic only seeded Header/Footer when those arrays were empty.
- Therefore a first choice such as Desert could change the global palette while leaving the default blue header behind.

Fix:
- Detects whether Header/Footer still match their untouched Studio defaults.
- The first palette choice may seed those untouched Studios.
- Once Header or Footer has been customized, future palette selections preserve that Studio.
- Existing customized Workspaces are not globally overwritten.
- Retains dev14b preview isolation, live sync, Palette Color Mode, and pagination fixes.

# Theme Lab 1.0.1-dev14b — Pagination Preview Isolation Fix

- Fixes pagination links in the mini-forum preview inheriting the active forum theme's visited-link color.
- Explicitly owns normal/link/visited/hover/focus/active pagination states.
- Preserves filled/outline/underline current-page styling.
- Retains all dev14/dev14a preview isolation, live-sync, and Palette Color Mode changes.

# Theme Lab 1.0.1-dev14a — Palette-Owned Color Mode

- Moves the Light + Dark choice from Publish Studio to Palette Studio.
- Palette now exposes only:
  - Single Mode
  - Light + Dark Toggle
- Dark companion colors remain automatic and hidden.
- Publish Studio is now informational and respects the Workspace choice.
- Existing Workspaces without the new field safely resolve to Single Mode.
- New/imported Workspaces default to Single Mode.
- Dark Workspaces still do not generate a redundant second dark companion.

# Theme Lab 1.0.1-dev14 — Forum Preview Isolation & Live-Sync Polish

This pass hardens the shared mini-forum preview.

## Host-theme isolation
- Preview content links now explicitly own normal/link/visited/hover/focus/active colors.
- The active forum theme can no longer leak its link or visited-link color into a new Workspace preview.
- Palette link-state samples are deterministic and no longer depend on browser history.
- Header navigation, footer navigation, breadcrumb-bar mode and other specialist samples retain their own semantic colors.

## Live preview synchronization
- Keeps all existing Studio-specific preview binders.
- Adds a final delegated live-sync safety net for Palette, Layout, Typography, Buttons, Navigation, Board Index, Header and Footer controls.
- Controls dynamically replaced or rendered after initial binding still update the shared mini-forum.
- This is intentionally a fallback; it does not replace the existing specialized preview logic.

# Theme Lab 1.0.1-dev13f — Universal Button Readability Authority

- Fixes anchor-based buttons becoming unreadable after the underlying link has been visited.
- Adds a final generated-CSS authority after all normal link styling.
- Covers normal, link, visited, hover, focus and active states.
- Preserves separate readable foregrounds for:
  - secondary/default buttons
  - primary/current/active buttons
  - danger/delete buttons
  - disabled controls
- Disabled controls remain subdued but use opacity 1 so their configured text color stays legible.
- Child spans/icons inherit the protected button foreground.
- Normal content links still retain Theme Lab's visited-link styling.

# Theme Lab 1.0.1-dev13e — Unified Header / Navigation Stacking Fix

Root cause found:
- dev12 deliberately raised `#upper_section` / `#main_menu` to z-index 6000-6040 so Admin/Forum dropdowns stayed above secondary admin navigation.
- Profile, Messages and Alerts were still only at roughly z-index 2300.
- Their lower portions were not actually clipped; they were being painted underneath `#upper_section` and looked cut off.

Fix:
- Removes the dev13d profile-menu portal experiment completely.
- Restores native SMF profile-menu DOM behavior.
- Keeps primary navigation at 6000-6040.
- Gives the integrated top utility/header layer its own final 7000-7040 stack.
- Profile, Messages and Alerts now outrank the main navigation while the main navigation still outranks page/admin content.
- Light/Dark Mode foundation remains intact.

# Theme Lab 1.0.1-dev13d — Profile Menu Portal Fix

- Stops relying on header overflow/stacking rules for the top account/profile menu.
- After SMF initializes, Theme Lab moves `#profile_menu` directly under `<body>`.
- The popup is positioned from the original account/admin trigger coordinates.
- Repositions on open, resize, and scroll.
- Keeps SMF's existing menu element and show/hide behavior; no clone or duplicate menu is created.
- Uses a dedicated high-z-index portal class so the header image, wrappers, or stacking contexts cannot clip the popup.
- Light/Dark Mode foundation remains intact.

# Theme Lab 1.0.1-dev13c — Profile Menu Clipping Fix

- Fixes the top account/profile menu being cut off at the bottom inside integrated Theme Lab headers.
- Removes `overflow:hidden` from `#profile_menu`.
- Forces the profile menu to `height:auto` with no max-height cap.
- Keeps PM and Alerts menus clipped to their own scrollable viewports.
- Retains the existing high z-index/overflow-visible header stacking fix.
- No changes to the Light/Dark Mode architecture in this pass.

# Theme Lab 1.0.1-dev13b — Header Runtime Timing Regression Fix

- Fixes the top profile/account popup regression introduced by dev13's color-mode bootstrap timing.
- Restores `themelab-generated.js` to the same deferred, late execution order used by dev12.
- Adds a separate tiny `themelab-color-mode-init.js` bootstrap when Light + Dark Mode is published.
- The tiny bootstrap does one thing only: apply the saved/system light-dark mode to `<html>`.
- Header relocation, SMF utility handling, profile menus, scroll-to-top, and the visible sun/moon control stay in the normal deferred runtime.
- Prevents the color-mode feature from racing SMF's own top-menu/profile-menu initialization.

# Theme Lab 1.0.1-dev13a — Language Output Hotfix

- Fixes the Light/Dark Mode language strings printing above every page.
- Moves the four new language entries inside PHP before the closing tag.
- Verifies that no PHP file has non-whitespace content after a real standalone closing PHP tag.

# Theme Lab 1.0.1-dev13 — Light + Dark Mode Foundation

- Adds a Publish Studio option for Light + Dark Mode on light Theme Lab workspaces.
- Automatically detects already-dark palettes and suppresses the redundant dark companion option.
- Generates a coordinated dark surface/text/border/link/form palette while retaining theme brand colors.
- Published themes get a compact sun/moon visitor toggle in SMF's existing top utility area.
- First visit follows `prefers-color-scheme`.
- Manual choice persists in `localStorage` per published theme name.
- When color modes are enabled, generated mode JavaScript is registered early so the saved preference can be applied before normal DOM behavior.
- No SMF core files are edited.

This is the foundation pass. A later Studio pass can expose the generated dark companion colors for manual tuning.

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

# Theme Lab 1.0.1-dev9 — Package Manager Button Containment

- Restores compact Package Manager operation buttons after the global button polish.
- Keeps Uninstall, List Files, and Delete on one row where normal admin width allows.
- Scopes the correction only to `packages_lists_*` operation cells.
- Does not shrink buttons globally or affect forum/posting controls.
- Preserves the dev8 composer popup/Better Tags stacking fix.

# Theme Lab 1.0.1

Theme Lab is an administration modification for Simple Machines Forum (SMF) 2.1.x that lets an administrator design, preview, save, import, edit, and publish installable Curve2-based themes from inside the SMF Administration Center.

## Highlights

- Workspace-based theme projects with save, reopen, import, and delete workflows.
- Palette, Gradients, Layout, Typography, Buttons, Form Controls, Menus & Navigation, Board Index, Header, and Footer Studios.
- Shared live preview with persistent Studio-to-Studio settings.
- Header background/logo uploads with validation and safe sizing.
- Theme Review and Environment Check screens before publishing.
- Builds independent, installable SMF theme ZIP packages.
- Round-trip `themelab-workspace.json` metadata for reopening Theme Lab-generated themes.
- Generated themes include build/certification metadata and preserve original author/publisher information.
- No SMF core-file edits are required by Theme Lab itself.

## Requirements

- SMF 2.1.x (package compatibility: 2.1 through 2.1.99).
- PHP 7.0 or newer.
- Writable SMF `Themes` directory so Theme Lab can build theme workspaces/packages.
- PHP Zip support for publishing ZIP packages.
- GD is optional. When available, Theme Lab can normalize oversized uploaded logos. Without GD, validated source images are preserved and publishing remains available.

## Installation

1. Upload `theme_lab_1.0.1.zip` in **Administration Center → Package Manager**.
2. Install the package.
3. Open **Administration Center → Configuration → Theme Lab**.
4. Run **Environment Check**.
5. Create a new Workspace or import a Theme Lab workspace/theme.
6. Work through the Studios, then use **Review** and **Publish** to build the finished theme ZIP.

## Upgrade

Theme Lab 1.0.1 includes direct Package Manager upgrade support from the 1.0.0 release, supported RC builds, and the 1.0.1 development builds through `1.0.1-dev3h`. Existing Workspace data is retained during upgrade.

## Uninstall and Workspace data

Theme Lab always removes its installed files, administration integration hook, and runtime settings when uninstalled.

SMF's Package Manager offers an optional database-removal choice during uninstall:

- **Leave database removal unchecked** to preserve the `themelab_projects` table and all saved Workspaces for a later reinstall.
- **Enable database removal** only when you deliberately want to delete all saved Theme Lab Workspace data.

Published themes are independent SMF themes and are not removed when Theme Lab is uninstalled. Keeping exported Workspace/theme ZIPs as normal backups is still recommended.

## Security and validation

Theme Lab administration screens require forum-administrator permission. Mutating actions use SMF session validation. Imported ZIPs and uploaded images are validated before use, and publishing validates the output package before presenting it for download.

## License

MIT License. See `LICENSE.md`.

## Support / development

See the `docs/` directory for architecture, data model, Studio development, import/recovery, troubleshooting, and release-process documentation.
