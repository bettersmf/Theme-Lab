# Studio Development

## Definition

A Studio is a focused editor for one coherent group of theme settings. The current design Studios are Palette, Layout, Typography, Header, and Footer.

## Required characteristics

Every Studio should have:

- A stable workflow key
- A clear ownership boundary
- Canonical defaults
- Server-side validation and normalization
- Persistent project-data keys
- A preparation callback
- Template controls with labels and help text
- Immediate preview bindings where practical
- Save feedback and post-save redirect behavior
- Changelog and documentation coverage

## Server-side lifecycle

1. Load the active project through shared Core behavior.
2. Merge stored values with canonical defaults.
3. On submission, validate the session and request.
4. Normalize each setting to a documented type and range.
5. Preserve unrelated project-data sections.
6. Store the updated project data and modification time.
7. Redirect back to a stable administration URL.
8. Render validation errors without discarding valid submitted values.

## Naming

Use predictable names across layers. A setting such as `border_radius` should have an understandable relationship among its form name, project-data key, PHP context key, JavaScript binding, and preview CSS custom property.

Do not rename persisted keys casually. When renaming is necessary, provide migration or backward-compatible reading and document the change.

## Preview behavior

Studio controls should call public Preview Controller methods rather than manipulating unrelated preview internals. Binding code must tolerate missing optional elements and must not initialize the controller multiple times.

## Adding a Studio

A complete addition normally touches:

- Core registry
- New or existing source module
- Main template file or a dedicated template module
- English language file
- Theme Lab stylesheet
- Preview Controller or server preview preparation
- Package manifest when new runtime files are introduced
- README or roadmap when scope changes
- Technical documentation
- Changelog

## Review checklist

- Does the Studio have one clear responsibility?
- Are defaults and accepted values documented?
- Is server validation authoritative?
- Are unrelated settings preserved?
- Does the full preview remain correct?
- Does navigation derive from the registry?
- Are upgrade and stored-data effects understood?
