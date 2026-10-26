# Coding Style Guide

## File naming

Theme Lab runtime files use the `ThemeLab` prefix for discoverability. Keep related source files in SMF’s existing `Sources/` and default-theme paths unless an architectural change explicitly justifies another structure.

## PHP formatting

- Use tabs consistently with the existing codebase for indentation.
- Place braces according to the surrounding SMF-style code.
- Use descriptive snake_case for Theme Lab functions and local variables.
- Use strict comparisons when type matters.
- Avoid deeply nested conditionals; validate and return or redirect early where appropriate.
- Keep callbacks focused and give helpers names that explain their domain action.

## JavaScript formatting

- Keep the Preview Controller inside its intentional wrapper.
- Use camelCase for JavaScript functions and variables.
- Use readable DOM identifiers prefixed with `themelab_`.
- Keep event bindings grouped by Studio or feature.
- Prefer small setter methods over repeated direct DOM manipulation.

## CSS naming

- Prefix project-specific classes with `themelab_`.
- Prefix preview custom properties with `--themelab-preview-`.
- Use `data-*` attributes for finite visual modes such as spacing, shadow, alignment, or navigation style.
- Keep selectors no more specific than necessary.

## Language strings

- User-facing text belongs in language files.
- Keys should be stable, descriptive, and prefixed for Theme Lab.
- Help text should explain consequences, not merely repeat labels.

## Comments

Comments should explain intent, constraints, or non-obvious compatibility behavior. Do not narrate code that is already self-explanatory.

## File headers

New source files should include a concise header naming Theme Lab, the file’s responsibility, package identifier, copyright holder, and MIT license. Version numbers in individual file headers should be used only when the project can maintain them reliably; stale per-file versions are worse than no per-file version.
