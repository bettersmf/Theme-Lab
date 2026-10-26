# Theme Compiler

## Status

The Theme Compiler is implemented and used by the Publish Studio in Theme Lab 1.0.

## Responsibility

The compiler translates the active Workspace into deterministic generated-theme output. It starts from SMF Curve2, applies the design values owned by Theme Lab Studios, applies Theme Lab's generated-theme compatibility and structural-polish rules, writes Theme Lab metadata, and prepares the theme package for download.

## Inputs

Compiler inputs come from normalized Workspace settings rather than directly from request data. The Workspace is the durable source of design intent.

Major input groups include palette, gradients, layout, typography, buttons, form controls, navigation, Board Index, header, footer, and theme identity/package settings.

## Certification

Publishing is preceded by validation/certification. A build should not proceed when required Workspace or filesystem conditions fail. The Publish interface reports passed/failed checks and build-pipeline information.

## Round-trip metadata

Current generated themes include `themelab-workspace.json`. This lets Theme Lab reconstruct the Workspace without attempting to reverse-engineer compiled CSS. Older Theme Lab output can be imported only when Theme Lab can positively identify and safely reconstruct supported settings.

## Output safety

The compiler should:

- Validate output paths and theme identity.
- Avoid modifying the source Curve2 theme.
- Avoid overwriting unrelated themes through guessed identity.
- Produce predictable package paths and metadata.
- Report build failures rather than silently producing a partial package.

## Generated-theme polish

Theme Lab 1.0 also applies targeted styling/template transformations needed to make common SMF surfaces visually coherent with the generated design. These transformations should preserve SMF behavior while changing presentation and component ownership.
