# Theme Lab User Guide

## The workflow

Theme Lab uses one active **Workspace** and a sequence of design **Studios**. A Workspace stores the design decisions used by the preview and publisher. You can move between Studios without creating separate themes for each section.

## Environment Check

Run this first. It reports whether the installation and filesystem conditions needed by Theme Lab are ready. Publishing should wait until required checks pass.

## Workspace

Create a Workspace for each theme design. Workspace details include the theme identity used during review and publishing. Saved Workspaces can be reopened or deleted. Import accepts Theme Lab workspace JSON files and Theme Lab-generated theme ZIPs.

## Design Studios

- **Palette** — core theme colors and palette presets.
- **Gradient Studio** — gradient surfaces and direction/softness controls.
- **Layout** — overall width, spacing, radius, shadow, and related layout choices.
- **Typography** — font families, sizes, line spacing, heading weight, and monospace choices.
- **Buttons** — button appearance and semantic button colors.
- **Form Controls** — input/textarea styling and focus treatment: None, Border, Glow, or Underline.
- **Navigation** — navigation appearance, active-state emphasis, and related menu styling.
- **Board Index** — board-list presentation and Board Index-specific visual controls.
- **Header** — header size/background, logo text, alignment, SMF logo visibility/position, navigation, and search placement.
- **Footer** — footer size/background, personalized text, Theme Lab attribution, and alignment of Theme Lab-controlled text. SMF/system footer content remains independently positioned.

## Live preview

The shared mini-forum preview follows the accumulated Workspace settings. It is a design preview, not a complete substitute for testing a published theme against real SMF pages. After publishing, test the generated theme on representative forum, profile, posting, and administration screens.

## Theme Review

Theme Review is read-only. It summarizes Workspace identity and Studio configuration so missing details can be corrected before publishing.

## Publish

Publish runs Theme Lab's certification checks and builds the generated theme package. Resolve failed required checks first. Build options can include generated documentation files such as README, LICENSE, and CHANGELOG material for the theme package.

The generated theme includes Theme Lab metadata that allows a later Theme Lab installation to recognize and reconstruct the Workspace.

## Footer custom text

Footer custom text supports HTML entities such as `&copy;`, `&reg;`, `&trade;`, `&amp;`, and numeric entities. Arbitrary HTML markup is not treated as trusted footer markup.

## Active navigation

Generated themes can visibly identify the current main forum destination, Admin section, and Theme Lab Studio. Navigation Studio controls the active emphasis style supported by the generated theme.
