# Theme Lab 1.0.1 Certification

Theme Lab 1.0.1 is the public-release package promoted from the tested 1.0.1 development line through `1.0.1-dev3h`.

Release-hardening changes:
- Normalized the public package/compiler version to 1.0.1.
- Added direct upgrade support from 1.0.1-dev3h.
- Removed the empty BoardMod modification action; Theme Lab requires no SMF core-file edits.
- Split Workspace table removal into SMF's optional uninstall database action so saved Workspaces can be preserved.
- Made GD an optional Environment Check capability rather than a publishing blocker.
- Updated public installation, upgrade, uninstall, and data-preservation documentation.

Static package validation for this build:
- PHP syntax: passed.
- XML parsing: passed.
- JavaScript syntax: passed.
- Package required-file inventory: passed.
- ZIP integrity: passed.

Final runtime acceptance should be completed on a clean SMF 2.1.x installation before public submission.
