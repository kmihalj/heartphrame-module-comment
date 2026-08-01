<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Service;

/**
 * HR: Stabilno integracijsko pročelje koje HTML Editor poziva bez poznavanja
 *     unutarnjih tablica ili UI implementacije Comment modula.
 * EN: Stable integration facade used by the HTML Editor without knowing the
 *     Comment module's internal tables or UI implementation.
 */
final readonly class CommentIntegrationService
{
    /**
     * HR: Prima servise postavki, prikaza i identiteta.
     * EN: Receives setting, rendering, and identity services.
     */
    public function __construct(
        private CommentSettingsService $settings,
        private CommentHtmlRenderer $renderer,
        private CommentDocumentAccess $access,
    ) {
    }

    /**
     * HR: Vraća vrijednost koja se prikazuje u editoru.
     * EN: Returns the value displayed in the editor.
     */
    public function editorEnabled(string $documentId, string $language): bool
    {
        return $this->settings->editorEnabled($documentId, $language);
    }

    /**
     * HR: Sprema postavku u nacrt Workspacea ili izravno u samostalnu objavu.
     * EN: Saves the setting to a Workspace draft or directly to a standalone publication.
     */
    public function saveEditorSetting(
        string $documentId,
        string $language,
        bool $enabled,
        bool $workspaceDraft,
    ): void {
        $identity = $this->access->authenticatedIdentity();
        if ($workspaceDraft) {
            $this->settings->saveDraft($documentId, $language, $enabled, $identity['id']);

            return;
        }

        $this->settings->savePublished($documentId, $language, $enabled, $identity['id']);
    }

    /**
     * HR: Objavljuje nacrtnu postavku zajedno s HTML dokumentom.
     * EN: Publishes the draft setting together with the HTML document.
     */
    public function publishDraft(string $documentId, string $language): void
    {
        $identity = $this->access->authenticatedIdentity();
        $this->settings->publishDraft($documentId, $language, $identity['id']);
    }

    /**
     * HR: Odbacuje nacrtnu postavku zajedno s HTML nacrtom.
     * EN: Discards the draft setting together with the HTML draft.
     */
    public function discardDraft(string $documentId, string $language): void
    {
        $identity = $this->access->authenticatedIdentity();
        $this->settings->discardDraft($documentId, $language, $identity['id']);
    }

    /**
     * HR: Renderira komentare aktualne objave.
     * EN: Renders comments for the current publication.
     */
    public function render(
        string $documentId,
        string $language,
        int $page,
        string $pageUrl,
    ): string {
        return $this->renderer->render($documentId, $language, $page, $pageUrl);
    }

    /**
     * HR: Vraća CSS za statični export.
     * EN: Returns CSS for static exports.
     */
    public function standaloneCss(): string
    {
        return $this->renderer->standaloneCss();
    }
}
