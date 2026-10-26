# Release Process

## Release objective

Every package should be installable, explainable, testable, and recoverable. A version change alone does not create a release.

## 1. Define the release

- State the exact scope.
- Separate required work from later ideas.
- Identify behavior, schema, installer, data, and compatibility effects.
- Choose the version according to Semantic Versioning where practical.

## 2. Prepare the code and documentation

- Complete only the agreed scope.
- Remove temporary debugging artifacts.
- Update `CHANGELOG.md` thoroughly.
- Update README and technical documentation when behavior or architecture changed.
- Confirm planned functionality is still labeled as planned.

## 3. Validate source files

- Run `php -l` on every PHP and template-language PHP file.
- Check JavaScript in the supported administration browser and inspect the console.
- Check CSS and interface behavior in the supported SMF theme environment.
- Validate XML manifests.
- Confirm all manifest-referenced files exist with exact case-sensitive paths.
- Check text files for unintended byte-order marks or encoding damage.

## 4. Validate package lifecycle

Test, as applicable:

- Fresh installation
- Upgrade from the last stable package
- Existing project selection and data preservation
- Every administration subsection
- Every affected save and redirect path
- Preview initialization and updates
- Uninstall behavior
- Reinstallation
- Emergency rollback

Workspace database removal must remain optional and clearly disclosed; required uninstall logic must not destroy saved Workspace data.

## 5. Compare against the previous release

Review the complete file diff. Confirm that every changed runtime file was intended. Documentation-only releases should produce no runtime-code differences beyond manifest versioning or package metadata explicitly required for the release.

## 6. Build the archive

- Place `package-info.xml` at the ZIP root.
- Preserve exact relative paths.
- Do not wrap the package in an unnecessary outer directory.
- Use a descriptive filename containing the project and version.
- Open and list the final ZIP after creation.

## 7. Record validation

The release changelog should state:

- What changed
- Why it changed
- How it was tested
- Compatibility and migration effects
- Known limitations

## 8. Acceptance

The maintainer installs the package on a representative forum, verifies expected behavior, and decides whether to publish or revise it. Keep the previous known-good ZIP available until the new release has passed acceptance testing.
