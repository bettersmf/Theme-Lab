# Architecture

## Architectural shape

Theme Lab uses a modular administration architecture built around five layers:

1. **SMF integration** registers the administration area and package lifecycle behavior.
2. **Main controller** receives the selected administration subsection and delegates workflow handling.
3. **Core registry and dispatcher** define available Studios, workflow order, shared project requirements, and preparation callbacks.
4. **Studio modules** validate, load, save, and normalize settings for one design domain.
5. **Presentation and preview** render templates, styles, language strings, and client-side preview updates.

## Runtime files

- `Sources/ThemeLab.php` — administration entry point and primary controller.
- `Sources/ThemeLabCore.php` — Studio registry, dispatch, project guard, navigation context, and shared preparation.
- `Sources/ThemeLabProjects.php` — project persistence and Project Manager behavior.
- `Sources/ThemeLabPalette.php` — palette workflow.
- `Sources/ThemeLabPalettes.php` — palette presets or palette definitions.
- `Sources/ThemeLabColors.php` — color-related helpers or definitions.
- `Sources/ThemeLabLayout.php` — layout settings.
- `Sources/ThemeLabTypography.php` — typography settings.
- `Sources/ThemeLabHeader.php` — header settings.
- `Sources/ThemeLabFooter.php` — footer settings.
- `Sources/ThemeLabPreview.php` — server-side preparation of shared preview state.
- `Themes/default/ThemeLab.template.php` — administration and preview markup.
- `Themes/default/scripts/ThemeLabPreview.js` — client-side Preview Controller.
- `Themes/default/css/ThemeLab.css` — Theme Lab administration and preview styling.
- `Themes/default/languages/ThemeLab.english.php` — English interface strings.

## Design boundaries

The Core owns workflow mechanics, not Studio-specific design rules. A Studio owns its validation, defaults, normalization, persistence keys, and preview mapping. Templates own markup, not database access. JavaScript may provide immediate visual feedback but must not become the sole source of persisted truth.

## Data flow

1. SMF invokes the Theme Lab administration controller.
2. The requested subsection is resolved through the Studio registry.
3. The Core loads or verifies the active project when required.
4. The Studio preparation callback loads defaults and stored values.
5. The template renders form controls and the shared preview.
6. JavaScript applies control changes to the preview immediately.
7. On save, the server validates and stores canonical project data.
8. A redirect prevents duplicate submission and reloads canonical state.

## Stability rule

Architectural refactoring should preserve externally observable behavior unless the release explicitly documents a behavior change. Large rewrites should be divided into reversible, independently testable changes.


## Header Studio contract (RC6.1.20n)

Header Studio settings are loaded from and saved to `project_data_array['header']`.
The keys `smf_logo_visibility` and `smf_logo_position` are canonical across
validation, storage, template rendering, preview settings, JavaScript bindings,
and the theme compiler. Templates must not rely on uninitialized loose variables.
