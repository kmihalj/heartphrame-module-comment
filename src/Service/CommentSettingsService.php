<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Service;

use AaiEduHr\HeartPhrameModuleComment\ModuleComment;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;

use function date;
use function is_array;
use function is_numeric;
use function is_string;

/**
 * HR: Čuva odvojenu postavku komentiranja za trenutačnu objavu i jedini
 *     zajednički nacrt dokumenta.
 * EN: Stores separate commenting settings for the current publication and the
 *     document's single shared draft.
 */
final readonly class CommentSettingsService
{
    /**
     * HR: Prima prenosivi ORM pristup bazi.
     * EN: Receives the portable ORM database access.
     */
    public function __construct(private Database $database)
    {
    }

    /**
     * HR: Vraća postavku koju vide čitatelji; novi dokumenti dopuštaju komentare.
     * EN: Returns the setting readers see; new documents allow comments.
     */
    public function publishedEnabled(string $documentId, string $language): bool
    {
        $row = $this->find($documentId, $language);

        return !is_array($row) || (bool)($row['published_enabled'] ?? true);
    }

    /**
     * HR: Vraća nacrtnu postavku ako postoji, inače aktualnu objavljenu vrijednost.
     * EN: Returns the draft setting when present, otherwise the current published value.
     */
    public function editorEnabled(string $documentId, string $language): bool
    {
        $row = $this->find($documentId, $language);
        if (!is_array($row)) {
            return true;
        }

        return (bool)($row['has_draft_setting'] ?? false)
        ? (bool)($row['draft_enabled'] ?? true)
        : (bool)($row['published_enabled'] ?? true);
    }

    /**
     * HR: Sprema postavku samostalnog Editora izravno kao objavljenu.
     * EN: Saves the standalone Editor setting directly as published.
     */
    public function savePublished(
        string $documentId,
        string $language,
        bool $enabled,
        int $userId,
    ): void {
        $this->upsert($documentId, $language, [
            'published_enabled' => $enabled,
            'draft_enabled' => $enabled,
            'has_draft_setting' => false,
            'updated_by_user_id' => $userId > 0 ? $userId : null,
        ]);
    }

    /**
     * HR: Sprema zahtjev uređivača uz nacrt bez promjene javne stranice.
     * EN: Saves the editor's request with the draft without changing the public page.
     */
    public function saveDraft(
        string $documentId,
        string $language,
        bool $enabled,
        int $userId,
    ): void {
        $this->upsert($documentId, $language, [
            'draft_enabled' => $enabled,
            'has_draft_setting' => true,
            'updated_by_user_id' => $userId > 0 ? $userId : null,
        ]);
    }

    /**
     * HR: Objavljuje nacrtnu postavku i uklanja oznaku privremene vrijednosti.
     * EN: Publishes the draft setting and clears the temporary-value marker.
     */
    public function publishDraft(string $documentId, string $language, int $userId): void
    {
        $row = $this->find($documentId, $language);
        if (!is_array($row) || !(bool)($row['has_draft_setting'] ?? false)) {
            return;
        }

        $enabled = (bool)($row['draft_enabled'] ?? true);
        $this->upsert($documentId, $language, [
            'published_enabled' => $enabled,
            'draft_enabled' => $enabled,
            'has_draft_setting' => false,
            'updated_by_user_id' => $userId > 0 ? $userId : null,
        ]);
    }

    /**
     * HR: Odbacuje samo nacrtnu postavku i zadržava ponašanje zadnje objave.
     * EN: Discards only the draft setting and preserves the last publication behavior.
     */
    public function discardDraft(string $documentId, string $language, int $userId): void
    {
        $row = $this->find($documentId, $language);
        if (!is_array($row)) {
            return;
        }

        $published = (bool)($row['published_enabled'] ?? true);
        $this->upsert($documentId, $language, [
            'draft_enabled' => $published,
            'has_draft_setting' => false,
            'updated_by_user_id' => $userId > 0 ? $userId : null,
        ]);
    }

    /**
     * HR: Pronalazi jedan jezični zapis postavke.
     * EN: Finds one locale-specific setting row.
     *
     * @return array<string, mixed>|null
     */
    private function find(string $documentId, string $language): ?array
    {
        if (!$this->database->schema()->hasTable(ModuleComment::TABLE_SETTINGS)) {
            return null;
        }

        $row = $this->database->table(ModuleComment::TABLE_SETTINGS)
            ->where('document_id', '=', $documentId)
            ->where('language_code', '=', $language)
            ->first();

        return is_array($row) ? $this->stringKeyedRow($row) : null;
    }

    /**
     * HR: Umeće ili ažurira postavku bez SQL-a specifičnog za pojedinu bazu.
     * EN: Inserts or updates the setting without database-specific SQL.
     *
     * @param array<string, mixed> $values
     */
    private function upsert(string $documentId, string $language, array $values): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->find($documentId, $language);
        if (is_array($existing) && is_numeric($existing['id'] ?? null)) {
            $this->database->table(ModuleComment::TABLE_SETTINGS)
                ->where('id', '=', (int)$existing['id'])
                ->update([...$values, 'updated_at' => $now]);

            return;
        }

        $this->database->table(ModuleComment::TABLE_SETTINGS)->insert([
            'document_id' => $documentId,
            'language_code' => $language,
            'published_enabled' => true,
            'draft_enabled' => true,
            'has_draft_setting' => false,
            'updated_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            ...$values,
        ]);
    }

    /**
     * HR: Zadržava samo string ključeve ORM retka za stabilan oblik postavke.
     * EN: Keeps only string keys from an ORM row for a stable setting shape.
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
}
