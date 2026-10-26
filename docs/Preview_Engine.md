# Preview Engine

The shared preview component is rendered in every design Studio: Palette, Layout, Typography, Buttons, Forms, Navigation, Board Index, Header, and Footer. On desktop layouts the complete preview column uses CSS sticky positioning so both the title bar and mini-forum remain visible while Studio controls scroll. Below 900px the preview returns to normal stacked flow.

# Preview Engine

## Purpose

The Preview Engine gives immediate visual feedback while a project is edited. Its current presentation is a compact mini-forum rendered inside Theme Lab’s administration interface.

## Components

- `Sources/ThemeLabPreview.php` prepares server-side preview settings.
- `Themes/default/ThemeLab.template.php` renders preview markup and initial values.
- `Themes/default/scripts/ThemeLabPreview.js` applies client-side changes.
- `Themes/default/css/ThemeLab.css` defines preview layout and state selectors.

## Source of truth

Persisted project data and server-side normalization are authoritative. JavaScript preview state is temporary and must not be assumed to be saved until the server accepts the form.

## Current public behavior

The Preview Controller updates areas including:

- Palette colors through Theme Lab CSS custom properties
- Border radius, maximum width, shadow style, and spacing
- Font family, font size, line spacing, heading weight, and monospace font
- Header height, logo text, alignment, background, text color, navigation, and search presentation
- Footer height, text, alignment, and background

The controller uses the preview root `#themelab_mini_forum` and targeted child elements where necessary.

## Design rules

- Initialize once after required markup exists.
- Bind controls defensively.
- Keep public update methods narrow and predictable.
- Prefer CSS custom properties and data attributes for state that CSS can render.
- Avoid rebuilding the full preview for a single setting change.
- Preserve settings from other Studios.
- Ensure empty or invalid control values fail safely.
- Keep preview-only markup isolated from the surrounding SMF administration page.

## Future direction

Planned work includes making the Preview Engine a more explicit shared component, keeping it visible while scrolling, adding responsive viewport simulation, improving event/state synchronization, and providing clearer failure diagnostics.
