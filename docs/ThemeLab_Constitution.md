# Theme Lab Constitution

This document records durable rules for project decisions. It is guidance, not a legal instrument.

## 1. The forum must be protected

No Theme Lab feature is worth corrupting an SMF installation, losing project data, or overwriting a working theme without recovery safeguards.

## 2. Shipped behavior must be described honestly

Implemented, experimental, deprecated, and planned functionality must be clearly distinguished.

## 3. The Core owns shared workflow

Routing, Studio registration, active-project requirements, navigation context, and common preview preparation belong in shared architecture rather than duplicated Studio code.

## 4. Studios own their domains

Each Studio defines its defaults, validation, persistence keys, interface, and preview mapping while preserving data owned by other Studios.

## 5. Server validation is authoritative

Client-side controls and preview behavior improve usability but do not replace permission checks, request validation, normalization, or safe persistence.

## 6. Compatibility changes are explicit

Changes to package identity, schema, project-data keys, integration hooks, public interfaces, or generated output require documentation, testing, and a migration or recovery story.

## 7. Releases are evidence-based

A package is ready when its changes, reasons, tests, and compatibility effects are known—not merely when a version number has been changed.

## 8. The changelog is the project history

Notable shipped work belongs in `CHANGELOG.md`. A separate milestone history is intentionally unnecessary.

## 9. Simplicity is preferred

New abstractions must remove real duplication or risk. Cleverness without a maintenance benefit should be avoided.

## 10. Project ownership is clear

Dean Shofner retains project ownership and release authority. Collaboration and contribution credit should be recorded accurately.
