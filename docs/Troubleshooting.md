# Troubleshooting

## Theme Lab does not appear in Administration

Confirm the package completed installation successfully and that the Theme Lab source, template, language, CSS, and JavaScript files were installed. Revisit Package Manager for installation errors.

## Environment Check reports failures

Use the detail and suggested fix shown by Environment Check. Filesystem write failures must be corrected at the hosting/filesystem level; Theme Lab should not guess or silently broaden permissions.

## Preview does not update

Save or change the relevant Studio control again, then check the browser developer console for JavaScript errors. After upgrading Theme Lab, clear the browser cache so an older preview controller is not reused.

## Published theme does not contain a recent fix

Theme Lab compiler changes affect newly generated themes. Open the Workspace and publish a fresh theme package, install/use that generated version, and clear browser cache if CSS or JavaScript changed.

## Import says the theme was not created by Theme Lab

That message is expected for normal SMF and third-party themes in Theme Lab 1.0. Nothing should be imported or changed. Use a Theme Lab Workspace JSON file or a Theme Lab-generated theme ZIP.

## ZIP import fails

Confirm PHP ZIP support is available, the file is within the 64 MB import limit, and the archive is readable and complete.

## A generated page looks structurally wrong

First confirm the forum is actually using the newest generated theme version. Theme Lab's RC cycle includes generated-template and CSS polish, so an older installed generated theme can make a corrected Theme Lab package appear unchanged.

## Reporting a problem

Record the Theme Lab version, SMF version, PHP version, active generated-theme version, exact Admin/page path, reproduction steps, and any PHP or browser-console error. A screenshot is useful for visual layout defects.
