<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Event;

/**
 * HR: Neutralno objavljuje stvaranje, odgovor ili uklanjanje komentara bez tijela komentara.
 * EN: Neutrally publishes comment creation, reply, or removal without the comment body.
 */
final readonly class CommentChanged
{
    /** HR: Sprema samo identifikatore potrebne integracijama. EN: Stores only identifiers required by integrations. */
    public function __construct(
        public string $action,
        public string $commentUuid,
        public string $documentId,
        public string $language,
        public int $actorUserId,
        public ?string $parentCommentUuid = null,
    ) {
    }
}
