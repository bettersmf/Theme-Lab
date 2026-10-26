# Data Model

## Projects table

Theme Lab stores projects in `{db_prefix}themelab_projects`.

Current columns:

| Column | Purpose |
|---|---|
| `id_project` | Primary numeric project identifier |
| `project_name` | Human-readable project name |
| `author` | Theme author or project author |
| `theme_version` | Version intended for the generated theme |
| `description` | Project or theme description |
| `base_theme` | Base SMF theme identifier, currently defaulting to `default` |
| `project_data` | Serialized or encoded structured design data |
| `date_created` | Creation timestamp |
| `date_modified` | Last-modified timestamp |

An index on `date_modified` supports ordering or retrieval by recent activity.

## Project data

`project_data` contains the evolving settings for the design Studios. It should be treated as a structured document with domain-specific sections rather than as an ungoverned collection of keys.

Recommended principles:

- Each Studio owns a clearly named section.
- Defaults exist in server-side code.
- Reads tolerate missing keys from older projects.
- Writes preserve unknown or unrelated sections.
- Values are normalized before persistence.
- Stored values use stable primitive types.
- Schema-affecting changes are documented and migrated when necessary.

## Compatibility

Adding an optional key with a default is generally backward-compatible. Renaming or removing a key, changing its type, or changing its meaning requires compatibility handling.

## Future versioning

Before import/export or third-party extensions are introduced, project data should gain an explicit schema version. The version should describe the data format, not the Theme Lab package version, so data migrations can be applied deterministically.

## Deletion behavior

The required uninstaller removes Theme Lab files/hooks/runtime settings but leaves the Workspace table intact. SMF Package Manager handles `uninstall-database.php` as the optional database-removal action: leaving database removal unchecked preserves `themelab_projects`; enabling it deliberately deletes all saved Workspaces.
