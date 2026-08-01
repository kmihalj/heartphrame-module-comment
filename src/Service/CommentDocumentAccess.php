<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Service;

use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorApiActorContext;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorDocument;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorDocumentVersion;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorService;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorWorkspaceIntegration;
use HeartPhrame\Authn\AuthnHandlerInterface;
use RuntimeException;

use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function trim;

/**
 * HR: Ponovno koristi Editorov i Workspaceov ACL za čitanje, komentiranje i
 *     moderiranje bez skrivenih vlastitih pravila pristupa.
 * EN: Reuses Editor and Workspace ACL rules for reading, commenting, and
 *     moderation without hidden access rules of its own.
 */
final readonly class CommentDocumentAccess
{
    /**
     * HR: Prima Editor, autentikaciju i opcionalni API identitet.
     * EN: Receives the Editor, authentication, and optional API identity.
     */
    public function __construct(
        private EditorService $editor,
        private AuthnHandlerInterface $authnHandler,
        private EditorWorkspaceIntegration $workspace,
        private EditorApiActorContext $apiActors,
    ) {
    }

    /**
     * HR: Učitava dokument koji aktualni korisnik smije čitati i za Workspace
     *     vraća upravo objavljenu verziju.
     * EN: Loads a document readable by the current user and returns the exact
     *     published version for Workspace content.
     */
    public function publishedDocument(string $documentId, string $language): EditorDocument
    {
        $documentId = $this->editor->normalizeDocumentId($documentId);
        $language = $this->editor->normalizeLanguage($language);
        $document = $documentId !== '' ? $this->editor->loadPublic($documentId, $language) : null;
        if (!$document instanceof EditorDocument) {
            throw new RuntimeException(__('Dokument nije pronađen ili mu ne možete pristupiti.'));
        }

        if (!$this->workspace->ownsDocument($documentId)) {
            return $document;
        }

        $versionNumber = $this->workspace->publicationVersion($documentId, $language);
        if ($versionNumber === null || $versionNumber <= 0) {
            throw new RuntimeException(__('Dokument još nije objavljen.'));
        }

        $version = $this->editor->loadVersion($documentId, $language, $versionNumber);
        if (!$version instanceof EditorDocumentVersion) {
            throw new RuntimeException(__('Objavljena verzija dokumenta nije pronađena.'));
        }

        return new EditorDocument(
            $version->documentId,
            $version->title,
            $version->html,
            $version->createdAt,
            [
                ...$document->metadata,
                'language' => $version->language,
                'version_number' => $version->versionNumber,
                'updated_at' => $version->createdAt,
                'updated_by_user_id' => $version->createdByUserId,
                'updated_by_display_name' => $version->createdByDisplayName,
            ],
        );
    }

    /**
     * HR: Vraća identitet prijavljenog korisnika ili prekida operaciju.
     * EN: Returns the authenticated user's identity or rejects the operation.
     *
     * @return array{id:int,display_name:string,is_admin:bool}
     */
    public function authenticatedIdentity(): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            throw new RuntimeException(__('Za ovu radnju potrebna je prijava.'));
        }

        $displayName = '';
        foreach (['display_name', 'name', 'login_identifier', 'email'] as $key) {
            if (is_scalar($user[$key] ?? null) && trim((string)$user[$key]) !== '') {
                $displayName = trim((string)$user[$key]);
                break;
            }
        }

        return [
            'id' => $this->intValue($user['id'] ?? 0),
            'display_name' => $displayName,
            'is_admin' => (bool)($user['is_admin'] ?? false),
        ];
    }

    /**
     * HR: Provjerava postoji li prijavljeni korisnik.
     * EN: Checks whether an authenticated user exists.
     */
    public function isAuthenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    /**
     * HR: Provjerava pravo brisanja: autor ili zadnji urednik sadržaja, osoba s
     *     pravom objave, odnosno administrator samostalnog Editora.
     * EN: Checks delete permission: content owner or last editor, a publisher,
     *     or a standalone Editor administrator.
     */
    public function canModerate(EditorDocument $document): bool
    {
        $user = $this->currentUser();
        if ($user === null) {
            return false;
        }

        $userId = $this->intValue($user['id'] ?? 0);
        foreach (['owner_user_id', 'updated_by_user_id'] as $key) {
            if (is_numeric($document->metadata[$key] ?? null) && (int)$document->metadata[$key] === $userId) {
                return true;
            }
        }

        if ($this->workspace->ownsDocument($document->id)) {
            return $this->workspace->canPublishDocument($document->id);
        }

        return (bool)($user['is_admin'] ?? false);
    }

    /**
     * HR: Vraća jedinstvene primatelje prijave: vlasnika sadržaja i autora
     *     posljednje objavljene izmjene.
     * EN: Returns unique report recipients: the content owner and the author of
     *     the latest published change.
     *
     * @return list<int>
     */
    public function reportRecipientIds(EditorDocument $document): array
    {
        $ids = [];
        foreach (['owner_user_id', 'updated_by_user_id'] as $key) {
            if (is_numeric($document->metadata[$key] ?? null) && (int)$document->metadata[$key] > 0) {
                $ids[(int)$document->metadata[$key]] = true;
            }
        }

        return array_values(array_map(intval(...), array_keys($ids)));
    }

    /**
     * HR: Pretvara brojivu vrijednost identiteta u cijeli broj.
     * EN: Converts a numeric identity value into an integer.
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * HR: Normalizira web ili API auth payload i odbacuje nevaljani identitet.
     * EN: Normalizes the web or API auth payload and rejects an invalid identity.
     *
     * @return array<string, mixed>|null
     */
    private function currentUser(): ?array
    {
        $user = $this->apiActors->actor() ?? $this->authnHandler->userData();
        if (!is_array($user) || !is_numeric($user['id'] ?? null) || (int)$user['id'] <= 0) {
            return null;
        }

        $normalized = [];
        foreach ($user as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
