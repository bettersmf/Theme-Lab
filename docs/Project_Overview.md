# Project Overview

## Purpose

Theme Lab is an SMF 2.1 administration modification for designing and publishing Curve2-based themes through structured controls instead of direct CSS editing.

## Core model

A Theme Lab **Workspace** stores the design decisions for one theme. A registry of focused **Studios** edits portions of that Workspace. The shared **Preview Engine** renders accumulated settings while the **Publisher** certifies the Workspace, clones the Curve2 base, applies Theme Lab output, and builds an installable theme ZIP.

## Main areas

- Environment Check
- Workspace
- Palette
- Gradient Studio
- Layout
- Typography
- Buttons
- Form Controls
- Navigation
- Board Index
- Header
- Footer
- Theme Review
- Publish

## Workspace persistence

Workspace records preserve theme identity and Studio settings so a design can be reopened and republished. Internal code retains some historical `project` naming for compatibility even though the administrator-facing term is Workspace.

## Import

Theme Lab 1.0 imports Theme Lab Workspace JSON and Theme Lab-generated theme ZIPs. Generated themes carry a Workspace manifest for round-trip editing. Recognized legacy Theme Lab output can be reconstructed when enough Theme Lab metadata is present.

Arbitrary third-party SMF themes are not converted in 1.0. They are rejected before Workspace mutation.

## Publishing

The Publish Studio performs certification and package construction. Generated output is based on Curve2 plus the active Workspace's compiled design and Theme Lab's compatibility/polish transformations. The resulting ZIP is intended to be installed as an SMF theme.

## Design principles

- Keep Studio ownership explicit.
- Keep preview and compiler interpretation aligned.
- Preserve existing Workspace data across normal upgrades.
- Prefer deterministic output over guessing.
- Fail safely when import or publishing prerequisites are not met.
- Keep generated-theme fixes scoped enough to avoid unnecessary changes to SMF behavior.
