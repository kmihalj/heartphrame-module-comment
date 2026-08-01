<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Service;

use AaiEduHr\HeartPhrameModuleNotification\Service\NotificationService;

use function array_keys;
use function array_map;
use function array_values;
use function is_scalar;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * HR: Evidentira prijavu komentara i obavještava vlasnika sadržaja odnosno
 *     autora posljednje objavljene izmjene.
 * EN: Records a comment report and notifies the content owner and the author
 *     of the latest published change.
 */
final readonly class CommentReportService
{
    /**
     * HR: Prima servise komentara, pristupa dokumentu i obavijesti.
     * EN: Receives comment, document-access, and notification services.
     */
    public function __construct(
        private CommentService $comments,
        private CommentDocumentAccess $access,
        private NotificationService $notifications,
    ) {
    }

    /**
     * HR: Prijavljuje komentar jednom po korisniku te novu prijavu šalje
     *     relevantnim moderatorima, osim samom prijavitelju.
     * EN: Reports a comment once per user and sends a new report to relevant
     *     moderators, excluding the reporter.
     *
     * @return array{created:bool}
     */
    public function report(string $commentUuid, string $reason, string $pageUrl): array
    {
        $identity = $this->access->authenticatedIdentity();
        $comment = $this->comments->activeByUuid($commentUuid);
        $document = $this->access->publishedDocument(
            $this->stringValue($comment['document_id'] ?? ''),
            $this->stringValue($comment['language_code'] ?? ''),
        );
        $result = $this->comments->report($commentUuid, $identity['id'], $reason);
        if (!$result['created']) {
            return ['created' => false];
        }

        $recipientIds = [];
        foreach ($this->access->reportRecipientIds($document) as $userId) {
            if ($userId !== $identity['id']) {
                $recipientIds[$userId] = true;
            }
        }

        $safePageUrl = str_starts_with(trim($pageUrl), '/') ? trim($pageUrl) : '';
        $this->notifications->notifyUsers(
            array_values(array_map(intval(...), array_keys($recipientIds))),
            'comment.reported',
            __('Prijavljen je komentar'),
            sprintf(__('Korisnik je prijavio komentar na dokumentu "%s".'), $document->title),
            $safePageUrl !== '' ? $safePageUrl . '#comment-' . $commentUuid : '',
            'comment',
            $commentUuid,
            'comment-report:' . $commentUuid . ':' . $identity['id'],
            [
                'document_id' => $document->id,
                'language' => $this->stringValue($comment['language_code'] ?? ''),
                'comment_uuid' => $commentUuid,
                'reporter_user_id' => $identity['id'],
            ],
        );

        return ['created' => true];
    }

    /**
     * HR: Sigurno pretvara samo skalarne ORM vrijednosti u tekst.
     * EN: Safely converts scalar ORM values to text only.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
