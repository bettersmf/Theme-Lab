# Security Policy

## Supported releases

During pre-release development, security fixes are normally made against the newest available Theme Lab package. Older development packages may be used for diagnosis but should not be assumed to receive separate patches.

## Reporting a vulnerability

Report suspected vulnerabilities privately to the project maintainer. Include:

- A clear description of the issue
- Affected Theme Lab and SMF versions
- Preconditions and required permissions
- Reproduction steps or a minimal proof of concept
- Expected and observed behavior
- Potential impact
- Suggested mitigation, when known

Do not post exploit details, private forum data, credentials, server paths, or unredacted logs in a public issue.

## Response process

The maintainer will attempt to:

1. Acknowledge the report.
2. Reproduce and assess the issue.
3. Determine affected versions and practical impact.
4. Develop and validate a correction.
5. Coordinate disclosure and release notes.

Response times are best-effort because Theme Lab is independently maintained.

## Security boundaries

Theme Lab runs in the SMF administration environment and generates theme files and packages. Security-sensitive areas include:

- Administrative authorization and session validation
- Input validation and output escaping
- Database reads and writes
- Project serialization and import
- File and directory paths
- Generated theme names and package metadata
- Uploads and archives
- Installer and uninstaller behavior
- Cross-site scripting and request forgery protections

## Disclosure

Please allow a reasonable period for investigation and correction before public disclosure. Confirmed security changes will be recorded in `CHANGELOG.md` without publishing unnecessary exploit instructions.
