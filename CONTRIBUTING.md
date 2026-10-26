# Contributing to Theme Lab

Thank you for helping improve Theme Lab. Contributions should protect the project’s stability, clarity, and long-term maintainability.

## Before starting

- Confirm the change belongs in Theme Lab rather than in a generated theme.
- Search existing discussions and changelog entries for related work.
- For architectural or data-model changes, describe the proposed approach before writing a large patch.
- Keep one contribution focused on one coherent problem.

## A complete change answers four questions

1. What changed?
2. Why was it changed?
3. How was it tested?
4. Does it affect compatibility?

## Development expectations

- Target the currently supported SMF 2.1 range.
- Follow established SMF patterns where doing so improves compatibility.
- Preserve the `ThemeLab` naming convention and existing public identifiers.
- Keep Studio-specific behavior in the appropriate Studio module.
- Use the Core for shared workflow concerns rather than recreating routing or project guards.
- Avoid unrelated cleanup in a functional patch.
- Do not describe planned functionality as implemented.
- Do not silently change stored project-data keys.

## Testing

At minimum, test the affected installation and runtime paths. Relevant checks may include:

- Fresh package installation
- Upgrade over the previous stable development package
- Package uninstall behavior
- PHP syntax validation
- Administration routing and permissions
- Project creation, selection, update, and persistence
- Every affected Studio’s load, save, validation, and redirect flow
- Live preview initialization and updates
- Browser-console errors
- Database preservation or migration behavior
- Rollback to the previous working package

Document what was and was not tested.

## Changelog requirement

Every user-visible, compatibility-relevant, architectural, security, schema, installer, or documentation change must update `CHANGELOG.md` under the intended release. Entries should be specific and should note compatibility or migration effects.

## Pull requests and patch reviews

A reviewable change should contain:

- A concise problem statement
- The chosen solution and important alternatives considered
- Exact files and behavior affected
- Testing evidence
- Compatibility notes
- Changelog updates
- Screenshots for meaningful interface changes

Maintainers may request that a large change be divided into smaller, independently testable steps.

## Security issues

Do not open a public issue for a suspected vulnerability. Follow `SECURITY.md`.

## License

By contributing, you agree that your contribution may be distributed under the project’s MIT License.
