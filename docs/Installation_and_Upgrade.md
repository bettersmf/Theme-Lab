# Installation and Upgrade

## Before installing

Theme Lab is an administration modification for SMF 2.1.x. Back up the forum database and files before installing or upgrading any SMF package.

Theme publishing also requires the forum to be able to write to the Themes directory. ZIP support is required for ZIP import and package-building workflows.

## Fresh installation

1. Sign in as an administrator.
2. Open **Administration Center → Main → Package Manager**.
3. Upload the Theme Lab package ZIP.
4. Review SMF's package actions and install the package.
5. Open **Administration Center → Configuration → Theme Lab → Environment Check**.
6. Resolve any failed environment checks before publishing a theme.

## Upgrade

Theme Lab keeps the package identifier `better-smf:themelab`. Install the newer package through Package Manager using SMF's normal upgrade/replacement workflow. Existing Theme Lab workspace/project records are intended to remain available after an upgrade.

After upgrading:

1. Clear the browser cache when interface JavaScript or CSS changed.
2. Open Theme Lab and run **Environment Check**.
3. Open an existing Workspace and verify its saved settings.
4. Visit several Studios and confirm the preview initializes and updates.
5. Publish a test theme before using the new package on a production theme.

## Generated themes

A published Theme Lab ZIP is a normal generated SMF theme package. When a Theme Lab update changes compiler output, republish the workspace to place those changes into a fresh generated theme.

## Uninstall

Theme Lab supports non-destructive uninstall of Workspace data. Leave SMF Package Manager's database-removal option unchecked to preserve saved Workspaces. As normal backup practice, exported Workspace/theme ZIPs should still be kept separately when important.


### Complete removal behavior (RC6.1.21av+)

A normal SMF Package Manager uninstall always removes Theme Lab's Admin integration, runtime settings, and installed files. Workspace database data is optional: leave database removal unchecked to keep the `themelab_projects` table for reinstall, or enable database removal to intentionally delete it. Published themes created by Theme Lab are independent SMF themes and are not deleted by uninstalling Theme Lab.
