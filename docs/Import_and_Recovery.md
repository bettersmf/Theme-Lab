# Import and Recovery

## Supported imports in Theme Lab 1.0

Theme Lab accepts:

- Native Theme Lab Workspace JSON files.
- Theme Lab-generated theme ZIPs containing `themelab-workspace.json`.
- Older Theme Lab-generated ZIPs that can be safely recognized and reconstructed from Theme Lab's generated CSS manifest.

## Third-party themes

Theme Lab 1.0 does **not** convert arbitrary SMF or third-party themes. If the uploaded file does not contain valid Theme Lab identity/metadata, Theme Lab rejects it before creating or changing a Workspace and displays a friendly error.

This is intentional. Guessing at an unrelated theme's design model could create an incomplete or misleading Workspace. Third-party conversion is planned as a separate post-1.0 feature with explicit analysis and reporting.

## Import safety

The import path validates upload status, size, supported type, ZIP readability, Theme Lab identity, and required Workspace data before saving the imported Workspace.

The maximum import size is 64 MB in the current 1.0 implementation.

## Legacy Theme Lab packages

Legacy Theme Lab ZIP reconstruction may not be able to recover original uploaded source images. When an image path cannot be safely reconstructed, select the source image again in the appropriate Studio.

## Recovery practice

Keep copies of important Workspace exports and published theme ZIPs outside the live forum. Before an upgrade or uninstall, verify that the material needed to rebuild an important theme is retained.
