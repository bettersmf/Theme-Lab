# API Reference

## Status

Theme Lab does not yet promise a stable third-party extension API. This document records important internal extension points so they can be evolved deliberately.

## SMF integration

### `themelab_admin_areas`

Registered through SMF’s `integrate_admin_areas` integration hook. It adds Theme Lab to the administration interface and defines its subsections.

The installer registers this integration using the package’s `Sources/ThemeLab.php` path. The uninstaller removes the integration.

## Core registry

### `themelab_get_studio_registry()`

Returns the ordered Studio definitions used for administration subsections, dispatch, preparation, project requirements, and workflow navigation.

Registry entries are internal contracts. Consumers should not assume undocumented fields are stable.

## Preview controller

The browser-side controller is exposed as `ThemeLabPreview` and operates on `#themelab_mini_forum`.

Current behavior includes methods or internal operations corresponding to:

- `init()`
- Color token updates
- Layout updates
- Font-family, size, line-spacing, heading-weight, and monospace updates
- Header height, text, attributes, and background updates
- Footer height, text, attributes, and background updates

Exact method signatures should be verified against `Themes/default/scripts/ThemeLabPreview.js` before external use. They are not yet a compatibility-guaranteed public API.

## Preview context

### `$context['themelab_preview_settings']`

Contains the shared current preview state prepared for design Studios. Templates and scripts should read this normalized context rather than independently reconstructing the full project state.

## Persistence

The projects table and encoded `project_data` are internal storage. Direct third-party writes are discouraged until a versioned project-data schema and service-level API are introduced.

## Stability labels

- **Stable:** intended to remain compatible within a major release.
- **Internal:** available to Theme Lab modules but subject to change.
- **Planned:** documented design, not implemented.

All extension points in this RC should be considered **internal** unless explicitly marked otherwise in a future release.
