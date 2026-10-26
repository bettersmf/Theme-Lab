# Theme Lab Core Services

## Rule

A Studio owns its unique controls. Core owns reusable design rules.

## Typography Core

Public functions:

- `themelab_get_font_weights()` — canonical Regular 400, Medium 500, Bold 700 scale.
- `themelab_normalize_font_weight()` — validates weights and migrates legacy 600 to 500.
- `themelab_is_font_weight()` — tests membership in the canonical scale.
- `themelab_get_medium_font_stroke()` — returns the shared synthetic Medium emphasis for two-face web-safe fonts.
- `themelab_is_medium_font_weight()` — identifies the semantic Medium state.
- `themelab_get_typography_fonts()` — supported interface font families.
- `themelab_get_monospace_fonts()` — supported code font families.

Consumers in RC5.4.5:

- Typography Studio
- Buttons Studio
- Forms Studio
- Navigation Studio
- Board Index Studio
- Shared Preview Engine
- Theme Lab templates

Future consumers:

- Theme Compiler
- Theme Health Engine
- Pre-Publish Analysis

## Certified Studio Baseline

Core refactors must preserve preview, save, reload, project persistence, and cross-Studio isolation for every certified Studio. Shared logic is promoted only when at least two components genuinely consume it.
