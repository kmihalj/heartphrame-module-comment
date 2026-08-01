# Comment Module Guide

## 1. Responsibility

This module owns comment data and commenting behavior. The HTML Editor owns
documents and versions, Workspace owns inherited permissions and publishing,
Auth owns user identity, ORM owns portable database access, and Notification
owns the inbox. Keeping these boundaries prevents hidden dependencies.

## 2. Data Model

The single initial migration creates four empty tables:

| Table | Purpose |
| --- | --- |
| `document_comment_settings` | Published and shared-draft setting per document and language |
| `document_comments` | Comment text, author snapshot, timestamps, and deletion audit |
| `document_comment_reactions` | One `up` or `down` reaction per user and comment |
| `document_comment_reports` | One moderation report per reporter and comment |

All schema operations use the ORM schema builder. There is no database-specific
SQL and no package seed data.

Comment text is stored as plain text and escaped before rendering. Line breaks
are preserved, but submitted HTML is never executed.

## 3. Access Rules

Every write operation reloads the published document and rechecks access.
Hiding a button is therefore only a user-interface convenience, not security.

- Read comments: anyone who can read the published document.
- Add, react, or report: an authenticated user who can read it.
- Delete: content owner, latest published editor, user with Workspace publish
  permission, or administrator for a standalone Editor document.
- Change “Comments enabled”: a user who may edit the document.
- Finalize the setting: a user who may publish the Workspace draft.

The module does not create its own Workspace grants.

## 4. Draft and Publication Semantics

`published_enabled` is what readers see. `draft_enabled` is the editor's
requested value. `has_draft_setting` distinguishes an explicit draft choice
from inheriting the published value.

1. Saving a Workspace draft updates only `draft_enabled`.
2. Publishing copies it to `published_enabled`.
3. Discarding restores the editor to the published setting.
4. Standalone Editor saves directly to both values.

Existing comments remain visible when new comments are disabled. This avoids
silently hiding an existing discussion.

## 5. Reporting and Notifications

A report is idempotent per reporter and comment. A new report notifies the
content owner and latest published editor, excluding the reporter. The
notification links directly to the comment when a safe local page URL is
available.

Deleting a comment is a soft delete because the moderation audit is operational
data, not legacy document compatibility. Deleted comments disappear from the
page while their author, deletion actor, and timestamp remain available in the
database.

## 6. HTTP Routes

| Method | Route name | Purpose |
| --- | --- | --- |
| `GET` | `comment.assets.css` | Public module styles |
| `GET` | `comment.assets.js` | Public interaction script |
| `GET` | `comment.csrf` | Fresh token for AJAX writes |
| `POST` | `comment.create` | Create comment |
| `POST` | `comment.reaction` | Toggle or switch reaction |
| `POST` | `comment.report` | Report comment |
| `POST` | `comment.delete` | Soft-delete comment |

All state-changing routes require authentication and CSRF validation.

## 7. Integration Contract

The Editor uses its optional `EditorCommentIntegration` bridge. It resolves
`CommentIntegrationService` only when the package is installed, so the Editor
continues to work independently without this module. Static HTML export may
include Comment CSS, but interactive comments are intentionally not embedded
in an offline export.

## 8. Developer Checklist

```bash
composer validate --strict
composer check-platform-reqs
composer on-commit
```

When changing the schema during development, update
`resources/migrations/initial_comment_schema.php` directly and recreate the
test database. Do not add compatibility migrations for this pre-release module.
