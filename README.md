# HeartPhrame Comment Module

[Hrvatska verzija](README_hr.md)

The Comment module adds authenticated comments, reactions, reporting, and
moderation to published HTML Editor documents. It reuses the Editor and
Workspace access rules instead of creating a second, competing ACL system.

## Dependencies

Required, in enable order:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-auth` (`dev-main`)
4. `aaieduhr/heartphrame-module-notification` (`dev-main`)
5. `aaieduhr/heartphrame-module-editor-html` (`dev-main`)
6. `aaieduhr/heartphrame-module-comment` (`dev-main`)

Optional integrations:

- Workspace supplies inherited read/publish permissions.
- Theme supplies configured light and dark visual tokens.

```bash
composer require aaieduhr/heartphrame-module-comment:dev-main
vendor/bin/hph comment:install-migration
vendor/bin/hph orm-migrate:up
```

## Features

- Comments on published documents for authenticated users who can read them.
- Per-document and per-language “Comments enabled” setting.
- Separate Workspace draft and published settings.
- Like and dislike reactions with one active reaction per user and comment.
- One inappropriate-content report per user and comment.
- Notifications to the content owner and latest published editor.
- Deletion by the content owner, latest editor, publisher, or standalone
  Editor administrator.
- Theme-aware Bootstrap UI that remains usable without the Theme module.
- Portable ORM schema for SQLite, PostgreSQL, MySQL, and MariaDB.
- No external frontend or mail dependency.

Disabling comments blocks new comments but intentionally keeps existing
comments visible.

## Requirements

- PHP 8.2 or newer with `mbstring`.
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`
- `aaieduhr/heartphrame-module-editor-html`
- `aaieduhr/heartphrame-module-notification`

Optional integrations:

- `aaieduhr/heartphrame-module-workspace` supplies inherited read and publish
  permissions.
- `aaieduhr/heartphrame-module-theme` supplies configured light/dark tokens.

## Installation

Enable dependencies before the Comment module in `app.modules.enabled`, then
copy and run its single initial migration:

```bash
vendor/bin/hph comment:install-migration
vendor/bin/hph orm-migrate:up
```

The package contains no users, documents, comments, or test seed data.

## Editor Workflow

The HTML Editor shows a “Comments enabled” switch only when this module is
installed.

- Standalone Editor: saving applies the setting to the current publication.
- Workspace: saving stores the requested setting with the shared draft.
- Publishing promotes the draft setting to readers.
- Discarding the draft discards its comment setting.

Comments themselves are not document versions. They remain attached to the
document and language while HTML versions change.

## Documentation

- [English guide](docs/index_en.md)
- [Hrvatske upute](docs/index_hr.md)

## Quality Checks

```bash
composer on-commit
```

The suite runs PHPCS, Rector dry-run, PHPStan for production and test code,
and PHPUnit.

## Dependency policy

The Framework and internal HeartPhrame modules are required from the moving
`dev-main` branch. This module does not commit `composer.lock`; GitHub CI
resolves the latest development heads on PHP 8.2-8.5 and runs the complete
`composer on-commit` suite.
