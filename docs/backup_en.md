# Backup and restore

Comment registers `comment` for complete site/component data and `comment-workspace` for comments belonging to documents in one workspace. The archive contains document comment settings, comments, reactions, and abuse reports, including stable user and comment identities.

Workspace scope follows Editor document ownership; comments for documents outside the selected tree are excluded. This keeps page ACL and comment visibility aligned after restore.

Runtime anti-spam counters, sessions, and notification delivery queues are not comment content and are not archived. After restore, verify a public page, a restricted page, and moderation access before allowing new comments.
