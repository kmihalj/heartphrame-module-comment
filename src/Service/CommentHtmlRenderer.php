<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Service;

use HeartPhrame\Routing\UrlGenerator;

use function file_get_contents;
use function htmlspecialchars;
use function http_build_query;
use function is_file;
use function is_numeric;
use function is_scalar;
use function is_string;
use function nl2br;
use function rtrim;
use function str_contains;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * HR: Renderira semantički, tematski neutralan HTML komentara koji koristi
 *     Bootstrap i Theme varijable kada su dostupne.
 * EN: Renders semantic, theme-neutral comment HTML that uses Bootstrap and
 *     Theme variables when available.
 */
final readonly class CommentHtmlRenderer
{
    /**
     * HR: Prima servise sadržaja, postavki, pristupa i imenovanih ruta.
     * EN: Receives content, settings, access, and named-route services.
     */
    public function __construct(
        private CommentService $comments,
        private CommentSettingsService $settings,
        private CommentDocumentAccess $access,
        private UrlGenerator $urlGenerator,
    ) {
    }

    /**
     * HR: Gradi cijelu sekciju komentara za aktualnu objavu dokumenta.
     * EN: Builds the complete comments section for the current document publication.
     */
    public function render(
        string $documentId,
        string $language,
        int $page,
        string $pageUrl,
    ): string {
        $document = $this->access->publishedDocument($documentId, $language);
        $enabled = $this->settings->publishedEnabled($document->id, $language);
        $identity = null;
        if ($this->access->isAuthenticated()) {
            $identity = $this->access->authenticatedIdentity();
        }

        $canDelete = $this->access->canModerate($document);
        $result = $this->comments->comments(
            $document->id,
            $language,
            $identity['id'] ?? 0,
            $canDelete,
            $page,
        );
        $createUrl = $this->pathFor('comment.create', '/comments');
        $csrfUrl = $this->pathFor('comment.csrf', '/comments/csrf-token');
        $reactionUrl = $this->pathFor('comment.reaction', '/comments/reaction');
        $reportUrl = $this->pathFor('comment.report', '/comments/report');
        $deleteUrl = $this->pathFor('comment.delete', '/comments/delete');

        $html = '<section class="document-comments" aria-labelledby="document-comments-title"'
        . ' data-comment-csrf-url="' . $this->escape($csrfUrl) . '"'
        . ' data-comment-reaction-url="' . $this->escape($reactionUrl) . '"'
        . ' data-comment-report-url="' . $this->escape($reportUrl) . '"'
        . ' data-comment-delete-url="' . $this->escape($deleteUrl) . '"'
        . ' data-comment-language="' . $this->escape($language) . '"'
        . ' data-comment-error="' . $this->escape(__('Radnju s komentarom nije moguće spremiti.')) . '"'
        . ' data-comment-close-label="' . $this->escape(__('Zatvori')) . '">';
        $html .= '<div class="document-comments-heading">';
        $html .= '<h2 id="document-comments-title">' . $this->escape(__('Komentari')) . '</h2>';
        $html .= '<span class="badge text-bg-secondary">' . (int)$result['total'] . '</span>';
        $html .= '</div>';

        if ($result['items'] === []) {
            $html .= '<p class="document-comments-empty">' . $this->escape(__('Još nema komentara.')) . '</p>';
        } else {
            $html .= '<div class="document-comments-list">';
            foreach ($result['items'] as $comment) {
                $html .= $this->comment($comment, $identity !== null);
            }

            $html .= '</div>';
        }

        if (!$enabled) {
            $html .= '<p class="document-comments-notice">'
            . $this->escape(__('Novi komentari na ovom dokumentu nisu omogućeni.'))
            . '</p>';
        } elseif ($identity === null) {
            $html .= '<p class="document-comments-notice">'
            . $this->escape(__('Za dodavanje komentara potrebna je prijava.'))
            . '</p>';
        } else {
            $html .= '<form class="document-comment-form" action="' . $this->escape($createUrl) . '" method="post">';
            $html .= '<input type="hidden" name="document_id" value="' . $this->escape($document->id) . '">';
            $html .= '<input type="hidden" name="language" value="' . $this->escape($language) . '">';
            $html .= '<input type="hidden" name="page_url" value="' . $this->escape($pageUrl) . '">';
            $html .= '<label for="document-comment-body">' . $this->escape(__('Novi komentar')) . '</label>';
            $html .= '<textarea id="document-comment-body" name="body" class="form-control" rows="3"'
            . ' maxlength="5000" required></textarea>';
            $html .= '<div class="document-comment-form-actions">';
            $html .= '<button type="submit" class="btn btn-primary btn-sm">'
            . $this->escape(__('Objavi komentar')) . '</button>';
            $html .= '</div></form>';
        }

        if ($result['pages'] > 1) {
            $html .= '<nav class="document-comments-pagination" aria-label="'
            . $this->escape(__('Stranice komentara'))
            . '">';
            for ($number = 1; $number <= $result['pages']; ++$number) {
                $query = http_build_query(['comments_page' => $number]);
                $active = $number === $result['page'] ? ' active' : '';
                $html .= '<a class="btn btn-sm btn-outline-secondary' . $active . '" href="'
                . $this->escape($pageUrl . (str_contains($pageUrl, '?') ? '&' : '?') . $query)
                . '#document-comments-title">' . $number . '</a>';
            }

            $html .= '</nav>';
        }

        return $html . '</section>';
    }

    /**
     * HR: Vraća CSS sadržaj za filesystem i ZIP export bez interaktivnih radnji.
     * EN: Returns CSS content for filesystem and ZIP exports without interactive actions.
     */
    public function standaloneCss(): string
    {
        $path = dirname(__DIR__, 2) . '/resources/assets/comments.css';
        $css = is_file($path) ? file_get_contents($path) : false;

        return is_string($css) ? $css : '';
    }

    /**
     * HR: Renderira jedan sigurno escapani komentar i njegove dostupne akcije.
     * EN: Renders one safely escaped comment and its available actions.
     *
     * @param array<string, mixed> $comment
     */
    private function comment(array $comment, bool $authenticated): string
    {
        $uuid = $this->stringValue($comment['uuid'] ?? '');
        $reaction = $this->stringValue($comment['current_reaction'] ?? '');
        $disabled = $authenticated ? '' : ' disabled';
        $html = '<article class="document-comment" id="comment-' . $this->escape($uuid) . '"'
        . ' data-comment-uuid="' . $this->escape($uuid) . '">';
        $html .= '<header><strong>'
        . $this->escape($this->stringValue($comment['author_display_name'] ?? ''))
        . '</strong>';
        $html .= '<time class="document-comment-time" datetime="'
        . $this->escape($this->stringValue($comment['created_at'] ?? '')) . '">'
        . $this->escape($this->stringValue($comment['created_at'] ?? '')) . '</time></header>';
        $html .= '<div class="document-comment-body">'
        . nl2br($this->escape($this->stringValue($comment['body'] ?? '')), false)
        . '</div><footer>';
        $html .= $this->reactionButton(
            'up',
            $this->intValue($comment['up_count'] ?? 0),
            $reaction === 'up',
            $disabled,
        );
        $html .= $this->reactionButton(
            'down',
            $this->intValue($comment['down_count'] ?? 0),
            $reaction === 'down',
            $disabled,
        );
        if ($authenticated) {
            $html .= '<button type="button" class="document-comment-action" data-comment-report'
            . ' title="' . $this->escape(__('Prijavi neprimjeren komentar')) . '"'
            . ' aria-label="' . $this->escape(__('Prijavi neprimjeren komentar')) . '">'
            . $this->icon('flag') . '</button>';
        }

        if ((bool)($comment['can_delete'] ?? false)) {
            $html .= '<button type="button" class="document-comment-action document-comment-delete"'
            . ' data-comment-delete title="' . $this->escape(__('Obriši komentar')) . '"'
            . ' aria-label="' . $this->escape(__('Obriši komentar')) . '">'
            . $this->icon('trash') . '</button>';
        }

        return $html . '</footer></article>';
    }

    /**
     * HR: Gradi jedan dostupni gumb reakcije s brojačem.
     * EN: Builds one accessible reaction button with its count.
     */
    private function reactionButton(string $reaction, int $count, bool $active, string $disabled): string
    {
        $label = $reaction === 'up' ? __('Sviđa mi se') : __('Ne sviđa mi se');
        $activeClass = $active ? ' is-active' : '';

        return '<button type="button" class="document-comment-action' . $activeClass . '"'
        . ' data-comment-reaction="' . $reaction . '" title="' . $this->escape($label) . '"'
        . ' aria-label="' . $this->escape($label) . '"' . $disabled . '>'
        . $this->icon($reaction) . '<span data-comment-reaction-count>' . $count . '</span></button>';
    }

    /**
     * HR: Vraća mali SVG koji nasljeđuje boju teksta aktualne teme.
     * EN: Returns a compact SVG inheriting the current theme text color.
     */
    private function icon(string $name): string
    {
        $paths = [
            'up' => '<path d="M7 10v11H3V10h4Zm2 11V9l4-7 2 1v5h5a2 2 0 0 1 2 2l-2 9a2 2 0 0 1-2 2H9Z"/>',
            'down' => '<path d="M7 14V3H3v11h4Zm2-11v12l4 7 2-1v-5h5a2 2 0 0 0 2-2l-2-9a2 2 0 0 0-2-2H9Z"/>',
            'flag' => '<path d="M5 3v18M5 4h11l-2 4 2 4H5"/>',
            'trash' => '<path d="M4 7h16M9 7V4h6v3m-9 0 1 14h10l1-14M10 11v6m4-6v6"/>',
        ];

        return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . ($paths[$name] ?? '') . '</svg>';
    }

    /**
     * HR: Generira named rutu ili sigurni fallback ispod base patha.
     * EN: Generates a named route or a safe fallback below the base path.
     */
    private function pathFor(string $name, string $fallback): string
    {
        return $this->urlGenerator->namedRouteExists($name)
        ? $this->urlGenerator->getPathFor($name)
        : rtrim($this->urlGenerator->getBasePath(), '/') . $fallback;
    }

    /**
     * HR: Escapa vrijednost za siguran HTML prikaz.
     * EN: Escapes a value for safe HTML output.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * HR: Sigurno pretvara samo skalarne vrijednosti prikaza u tekst.
     * EN: Safely converts scalar view values to text only.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Sigurno pretvara brojive vrijednosti prikaza u cijeli broj.
     * EN: Safely converts numeric view values to an integer.
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
