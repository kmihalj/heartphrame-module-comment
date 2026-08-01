<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Service;

use AaiEduHr\HeartPhrameModuleComment\ModuleComment;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use RuntimeException;

use function array_values;
use function bin2hex;
use function ceil;
use function chr;
use function date;
use function in_array;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function max;
use function mb_strlen;
use function min;
use function ord;
use function random_bytes;
use function sprintf;
use function substr;
use function trim;

/**
 * HR: Provodi persistence pravila komentara, reakcija i prijava kroz prenosivi ORM.
 * EN: Enforces comment, reaction, and report persistence rules through the portable ORM.
 */
final readonly class CommentService
{
    /**
     * HR: Prima ORM bazu podataka.
     * EN: Receives the ORM database.
     */
    public function __construct(private Database $database)
    {
    }

    /**
     * HR: Vraća stranicu aktivnih komentara s agregiranim reakcijama.
     * EN: Returns a page of active comments with aggregated reactions.
     *
     * @return array{items:list<array<string,mixed>>,page:int,pages:int,total:int}
     */
    public function comments(
        string $documentId,
        string $language,
        int $currentUserId,
        bool $canDelete,
        int $page = 1,
        int $pageSize = 30,
    ): array {
        $this->assertTablesReady();
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));

        $count = $this->database->table(ModuleComment::TABLE_COMMENTS)
            ->select(['COUNT(*) AS aggregate'])
            ->where('document_id', '=', $documentId)
            ->where('language_code', '=', $language)
            ->where('is_deleted', '=', false)
            ->first();
        $total = is_array($count) && is_numeric($count['aggregate'] ?? null)
        ? (int)$count['aggregate']
        : 0;
        $pages = max(1, (int)ceil($total / $pageSize));
        $page = min($page, $pages);
        $rows = $this->database->table(ModuleComment::TABLE_COMMENTS)
            ->where('document_id', '=', $documentId)
            ->where('language_code', '=', $language)
            ->where('is_deleted', '=', false)
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get();

        $comments = [];
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!is_numeric($row['id'] ?? null)) {
                continue;
            }

            $id = (int)$row['id'];
            $ids[] = $id;
            $comments[$id] = $this->normalizeComment($this->stringKeyedRow($row), $canDelete);
        }

        if ($ids !== []) {
            $reactions = $this->database->table(ModuleComment::TABLE_REACTIONS)
                ->whereIn('comment_id', $ids)
                ->get();
            foreach ($reactions as $reaction) {
                if (!is_array($reaction)) {
                    continue;
                }

                if (!is_numeric($reaction['comment_id'] ?? null)) {
                    continue;
                }

                if (!isset($comments[(int)$reaction['comment_id']])) {
                    continue;
                }

                $commentId = (int)$reaction['comment_id'];
                $value = $this->stringValue($reaction['reaction'] ?? '');
                if ($value === 'up') {
                    $comments[$commentId]['up_count'] = $this->intValue(
                        $comments[$commentId]['up_count'] ?? 0,
                    ) + 1;
                } elseif ($value === 'down') {
                    $comments[$commentId]['down_count'] = $this->intValue(
                        $comments[$commentId]['down_count'] ?? 0,
                    ) + 1;
                }

                if (
                    $currentUserId > 0
                    && is_numeric($reaction['user_id'] ?? null)
                    && (int)$reaction['user_id'] === $currentUserId
                ) {
                    $comments[$commentId]['current_reaction'] = $value;
                }
            }
        }

        return [
            'items' => array_values($comments),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    /**
     * HR: Sprema novi komentar kao običan tekst i vraća normalizirani zapis.
     * EN: Stores a new plain-text comment and returns the normalized row.
     *
     * @return array<string, mixed>
     */
    public function create(
        string $documentId,
        string $language,
        int $userId,
        string $displayName,
        string $body,
        bool $canDelete,
    ): array {
        $this->assertTablesReady();
        $body = trim($body);
        if ($body === '') {
            throw new RuntimeException(__('Komentar ne smije biti prazan.'));
        }

        if (mb_strlen($body) > 5000) {
            throw new RuntimeException(__('Komentar smije sadržavati najviše 5000 znakova.'));
        }

        $now = date('Y-m-d H:i:s');
        $this->database->table(ModuleComment::TABLE_COMMENTS)->insert([
            'uuid' => $this->uuid(),
            'document_id' => $documentId,
            'language_code' => $language,
            'user_id' => $userId,
            'author_display_name' => $displayName !== '' ? $displayName : __('Korisnik'),
            'body' => $body,
            'is_deleted' => false,
            'deleted_by_user_id' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $row = $this->findById((int)$this->database->lastInsertId());
        if (!is_array($row)) {
            throw new RuntimeException(__('Spremljeni komentar nije moguće učitati.'));
        }

        return $this->normalizeComment($row, $canDelete);
    }

    /**
     * HR: Postavlja reakciju, mijenja joj smjer ili je uklanja ponovnim klikom.
     * EN: Sets a reaction, switches its direction, or removes it on a repeated click.
     *
     * @return array{reaction:string,up_count:int,down_count:int}
     */
    public function react(string $commentUuid, int $userId, string $reaction): array
    {
        $this->assertTablesReady();
        if (!in_array($reaction, ['up', 'down'], true)) {
            throw new RuntimeException(__('Reakcija komentara nije valjana.'));
        }

        $comment = $this->activeByUuid($commentUuid);
        $commentId = $this->intValue($comment['id'] ?? 0);
        $existing = $this->database->table(ModuleComment::TABLE_REACTIONS)
            ->where('comment_id', '=', $commentId)
            ->where('user_id', '=', $userId)
            ->first();
        $now = date('Y-m-d H:i:s');
        $activeReaction = $reaction;
        if (is_array($existing) && is_numeric($existing['id'] ?? null)) {
            if ($this->stringValue($existing['reaction'] ?? '') === $reaction) {
                $this->database->table(ModuleComment::TABLE_REACTIONS)
                    ->where('id', '=', (int)$existing['id'])
                    ->delete();
                $activeReaction = '';
            } else {
                $this->database->table(ModuleComment::TABLE_REACTIONS)
                    ->where('id', '=', (int)$existing['id'])
                    ->update(['reaction' => $reaction, 'updated_at' => $now]);
            }
        } else {
            $this->database->table(ModuleComment::TABLE_REACTIONS)->insert([
                'comment_id' => $commentId,
                'user_id' => $userId,
                'reaction' => $reaction,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $counts = ['up_count' => 0, 'down_count' => 0];
        $rows = $this->database->table(ModuleComment::TABLE_REACTIONS)
            ->where('comment_id', '=', $commentId)
            ->get();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (($row['reaction'] ?? '') === 'up') {
                ++$counts['up_count'];
            } elseif (($row['reaction'] ?? '') === 'down') {
                ++$counts['down_count'];
            }
        }

        return ['reaction' => $activeReaction, ...$counts];
    }

    /**
     * HR: Evidentira jednu prijavu po korisniku i komentaru te javlja je li nova.
     * EN: Records one report per user and comment and indicates whether it is new.
     *
     * @return array{comment:array<string,mixed>,created:bool}
     */
    public function report(string $commentUuid, int $reporterUserId, string $reason = ''): array
    {
        $this->assertTablesReady();
        $comment = $this->activeByUuid($commentUuid);
        $commentId = $this->intValue($comment['id'] ?? 0);
        $existing = $this->database->table(ModuleComment::TABLE_REPORTS)
            ->where('comment_id', '=', $commentId)
            ->where('reporter_user_id', '=', $reporterUserId)
            ->first();
        if (is_array($existing)) {
            return ['comment' => $comment, 'created' => false];
        }

        $now = date('Y-m-d H:i:s');
        $this->database->table(ModuleComment::TABLE_REPORTS)->insert([
            'uuid' => $this->uuid(),
            'comment_id' => $commentId,
            'reporter_user_id' => $reporterUserId,
            'status' => 'open',
            'reason' => trim($reason) !== '' ? trim($reason) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['comment' => $comment, 'created' => true];
    }

    /**
     * HR: Soft-briše aktivni komentar kako bi ostao moderacijski audit trag.
     * EN: Soft-deletes an active comment so the moderation audit trail remains.
     */
    public function delete(string $commentUuid, int $deletedByUserId): void
    {
        $comment = $this->activeByUuid($commentUuid);
        $now = date('Y-m-d H:i:s');
        $this->database->table(ModuleComment::TABLE_COMMENTS)
            ->where('id', '=', $this->intValue($comment['id'] ?? 0))
            ->update([
                'is_deleted' => true,
                'deleted_by_user_id' => $deletedByUserId,
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * HR: Učitava aktivni komentar po javnom UUID-u.
     * EN: Loads an active comment by its public UUID.
     *
     * @return array<string, mixed>
     */
    public function activeByUuid(string $uuid): array
    {
        $row = $this->database->table(ModuleComment::TABLE_COMMENTS)
            ->where('uuid', '=', trim($uuid))
            ->where('is_deleted', '=', false)
            ->first();
        if (!is_array($row)) {
            throw new RuntimeException(__('Komentar nije pronađen.'));
        }

        return $this->stringKeyedRow($row);
    }

    /**
     * HR: Provjerava jesu li sve početne tablice dostupne.
     * EN: Checks whether all initial tables are available.
     */
    public function tablesReady(): bool
    {
        $schema = $this->database->schema();

        return $schema->hasTable(ModuleComment::TABLE_COMMENTS)
        && $schema->hasTable(ModuleComment::TABLE_REACTIONS)
        && $schema->hasTable(ModuleComment::TABLE_REPORTS)
        && $schema->hasTable(ModuleComment::TABLE_SETTINGS);
    }

    /**
     * HR: Učitava komentar po internom ključu.
     * EN: Loads a comment by its internal key.
     *
     * @return array<string, mixed>|null
     */
    private function findById(int $id): ?array
    {
        $row = $this->database->table(ModuleComment::TABLE_COMMENTS)
            ->where('id', '=', $id)
            ->first();

        return is_array($row) ? $this->stringKeyedRow($row) : null;
    }

    /**
     * HR: Pretvara redak komentara u stabilan payload prikaza.
     * EN: Converts a comment row into a stable view payload.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeComment(array $row, bool $canDelete): array
    {
        return [
            'uuid' => $this->stringValue($row['uuid'] ?? ''),
            'user_id' => $this->intValue($row['user_id'] ?? 0),
            'author_display_name' => $this->stringValue($row['author_display_name'] ?? ''),
            'body' => $this->stringValue($row['body'] ?? ''),
            'created_at' => $this->stringValue($row['created_at'] ?? ''),
            'updated_at' => $this->stringValue($row['updated_at'] ?? ''),
            'up_count' => 0,
            'down_count' => 0,
            'current_reaction' => '',
            'can_delete' => $canDelete,
        ];
    }

    /**
     * HR: Zaustavlja rad ako aplikacija nije primijenila početnu migraciju.
     * EN: Stops the operation when the host has not applied the initial migration.
     */
    private function assertTablesReady(): void
    {
        if (!$this->tablesReady()) {
            throw new RuntimeException(__('Tablice Comment modula nisu instalirane.'));
        }
    }

    /**
     * HR: Pretvara skalarnu ORM vrijednost u očišćen tekst.
     * EN: Converts a scalar ORM value into trimmed text.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Pretvara brojivu ORM vrijednost u cijeli broj.
     * EN: Converts a numeric ORM value into an integer.
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * HR: Zadržava samo string ključeve ORM retka za stabilan javni oblik.
     * EN: Keeps only string keys from an ORM row for a stable public shape.
     *
     * @param array<mixed, mixed> $row
     * @return array<string, mixed>
     */
    private function stringKeyedRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * HR: Generira nasumični UUID v4 bez vanjske biblioteke.
     * EN: Generates a random UUID v4 without an external library.
     */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
