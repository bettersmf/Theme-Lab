# Theme Lab 1.0 Release Checklist

> **1.0.0-RC1 candidate note:** This archive was produced from the
> live-green `1.0.0-RC6.1.21dl` baseline after three reported end-to-end
> passes. Use this checklist for the final package-lifecycle acceptance before
> promoting RC1 to 1.0.0.

Use this checklist for final acceptance. Static package checks do not replace testing inside SMF.

## Package

- [ ] Package installs cleanly on a representative SMF 2.1.x forum.
- [ ] Upgrade from the previous known-good Theme Lab RC succeeds.
- [ ] Package Manager shows the expected Theme Lab version.
- [ ] Environment Check completes without unexpected failures.

## Workspace and import

- [ ] Create and save a new Workspace.
- [ ] Reopen an existing Workspace and confirm settings persist.
- [ ] Delete a disposable Workspace.
- [ ] Import a Theme Lab Workspace JSON file.
- [ ] Import a current Theme Lab-generated theme ZIP.
- [ ] Import an older supported Theme Lab-generated ZIP.
- [ ] Attempt a third-party theme ZIP and confirm graceful rejection with no Workspace changes.

## Studios and preview

- [ ] Save every Studio at least once.
- [ ] Verify live preview updates for each Studio.
- [ ] Verify Form Controls None, Border, Glow, and Underline focus modes.
- [ ] Verify Navigation active emphasis in the preview/generated theme.
- [ ] Verify Header SMF logo show/hide and position controls.
- [ ] Verify Footer custom text, entity rendering, attribution visibility, and left/center/right Theme Lab text stacking.

## Review and publish

- [ ] Theme Review accurately summarizes Workspace details and Studio configuration.
- [ ] Publish certification reports failures clearly and blocks an invalid build.
- [ ] A valid Workspace builds successfully.
- [ ] Generated ZIP downloads and installs as an SMF theme.
- [ ] Generated package contains the expected Theme Lab Workspace metadata.

## Generated-theme regression

- [ ] Board Index and topic view.
- [ ] Posting/composer and Quick Reply.
- [ ] Profile summary, posts, attachments, and notifications.
- [ ] Main forum navigation active state.
- [ ] Admin main navigation active state.
- [ ] Theme Lab Studio active state.
- [ ] Package Manager, permissions, membergroups, members, attachments, smileys, languages, ban list, and mail queue surfaces previously polished during RC6.1.21.
- [ ] Footer fixed SMF/system content and movable Theme Lab/custom text.
- [ ] Desktop and narrower browser widths show no new overflow or escaped controls.

## Lifecycle

- [ ] Uninstall behavior is understood and backed up before testing.
- [ ] Reinstall succeeds.
- [ ] Previous known-good package remains available for rollback until final acceptance.
