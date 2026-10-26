# Coding Standards

## General

- Optimize first for correctness, clarity, SMF compatibility, and safe maintenance.
- Prefer small, reviewable changes over broad rewrites.
- Keep responsibilities separated among integration, Core, Studios, presentation, and preview layers.
- Document non-obvious decisions and compatibility constraints.
- Remove dead experiments before packaging.

## PHP

- Guard runtime entry points against direct access where appropriate.
- Follow the conventions already used by SMF 2.1 and the surrounding Theme Lab code.
- Validate sessions and administrative permissions for state-changing actions.
- Treat request data as untrusted.
- Normalize values before storage or output.
- Escape output in the correct context.
- Use SMF database abstractions and placeholders.
- Avoid changing global state outside a clearly defined integration need.
- Include required source files explicitly and predictably.

## JavaScript

- Avoid polluting the global namespace beyond the intentional Theme Lab controller surface.
- Bind only after elements exist.
- Check optional elements before use.
- Do not bind the same event repeatedly.
- Keep persistence on the server; preview state is not saved state.
- Avoid inline JavaScript when the shared controller can own behavior.
- Report actionable errors during development without exposing sensitive data.

## CSS

- Scope Theme Lab administration styles to Theme Lab containers.
- Prefer descriptive project-prefixed class names.
- Use CSS custom properties for preview tokens that change dynamically.
- Avoid selectors that unintentionally alter the surrounding SMF administration theme.
- Preserve keyboard focus visibility and readable contrast.

## Database and stored data

- Schema changes require upgrade and rollback analysis.
- New fields need explicit types, defaults, and purpose.
- Stored project-data keys are compatibility-sensitive.
- Never discard unrelated project sections during a Studio save.

## Documentation

- Update the changelog for all notable shipped changes.
- Describe planned systems as planned.
- Keep examples synchronized with current identifiers and paths.
