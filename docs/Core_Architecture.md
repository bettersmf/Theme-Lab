# Core Architecture

## Responsibility

`Sources/ThemeLabCore.php` centralizes behavior shared by all Theme Lab screens. Its purpose is to prevent each Studio from reimplementing routing, project guards, navigation, and common preview preparation.

## Studio registry

The registry is the authoritative workflow definition. Each entry should identify, as applicable:

- Stable subsection key
- Display label or language key
- Template name
- Preparation callback
- Whether an active project is required
- Workflow position or neighboring entries
- Optional descriptive metadata for administration tabs

Registry keys should remain stable after release because URLs, saved state, documentation, and integrations may depend on them.

## Dispatcher

The dispatcher resolves the requested Studio, rejects unknown entries safely, invokes shared preparation, and then invokes the Studio-specific callback. It should not contain the detailed validation or persistence rules of individual Studios.

## Active-project guard

Design Studios require an active project. The Core should load the selected project once, expose it consistently through context, and redirect to Project Manager when no valid project is selected. A Studio should not invent a separate selection mechanism.

## Workflow context

The Core provides previous and next Studio metadata so templates do not hard-code workflow order. Adding or reordering a Studio should therefore be primarily a registry change plus the new module and template behavior.

## Shared preview context

The Core and preview preparation layer expose the complete current design state needed by the mini-forum preview. A Studio may focus its controls on one domain, but the preview should represent the whole project rather than resetting unrelated settings.

## Extension rules

A new Studio should:

1. Define a stable registry key.
2. Add a dedicated source module when logic is substantial.
3. Provide a preparation callback.
4. Define canonical defaults and project-data keys.
5. Add template controls and language strings.
6. Bind preview behavior without duplicating controller initialization.
7. Add save validation and redirect behavior.
8. Update documentation and the changelog.
9. Be tested both with and without an active project as appropriate.
