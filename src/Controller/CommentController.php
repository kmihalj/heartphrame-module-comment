<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Controller;

use AaiEduHr\HeartPhrameModuleComment\Service\CommentDocumentAccess;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentReportService;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentService;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentSettingsService;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

use function is_array;
use function is_scalar;
use function trim;

/**
 * HR: Poslužuje CSRF-zaštićene JSON radnje za komentare objavljenih dokumenata.
 * EN: Serves CSRF-protected JSON actions for comments on published documents.
 */
final readonly class CommentController
{
    /**
     * HR: Prima HTTP, session, pristupne i poslovne servise komentara.
     * EN: Receives HTTP, session, access, and comment business services.
     */
    public function __construct(
        private ResponseFactory $responseFactory,
        private SessionInterface $session,
        private CommentDocumentAccess $access,
        private CommentSettingsService $settings,
        private CommentService $comments,
        private CommentReportService $reports,
    ) {
    }

    /**
     * HR: Dodaje komentar samo prijavljenom čitatelju objavljenog dokumenta.
     * EN: Adds a comment only for an authenticated reader of the published document.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $documentId = $this->stringValue($body['document_id'] ?? '');
        $language = $this->stringValue($body['language'] ?? '');

        try {
            $document = $this->access->publishedDocument($documentId, $language);
            $identity = $this->access->authenticatedIdentity();
            if (!$this->settings->publishedEnabled($document->id, $language)) {
                throw new \RuntimeException(__('Novi komentari na ovom dokumentu nisu omogućeni.'));
            }

            $comment = $this->comments->create(
                $document->id,
                $language,
                $identity['id'],
                $identity['display_name'],
                $this->stringValue($body['body'] ?? ''),
                $this->access->canModerate($document),
            );
        } catch (Throwable $throwable) {
            return $this->failure($throwable);
        }

        return $this->responseFactory->json([
            'ok' => true,
            'comment' => $comment,
            'csrf' => $this->csrfPayload(),
        ]);
    }

    /**
     * HR: Postavlja, mijenja ili uklanja reakciju prijavljenog čitatelja.
     * EN: Sets, switches, or removes an authenticated reader's reaction.
     */
    public function reaction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);

        try {
            $identity = $this->access->authenticatedIdentity();
            $comment = $this->comments->activeByUuid($this->stringValue($body['comment_uuid'] ?? ''));
            $this->access->publishedDocument(
                $this->stringValue($comment['document_id'] ?? ''),
                $this->stringValue($comment['language_code'] ?? ''),
            );
            $state = $this->comments->react(
                $this->stringValue($comment['uuid'] ?? ''),
                $identity['id'],
                $this->stringValue($body['reaction'] ?? ''),
            );
        } catch (Throwable $throwable) {
            return $this->failure($throwable);
        }

        return $this->responseFactory->json([
            'ok' => true,
            'state' => $state,
            'csrf' => $this->csrfPayload(),
        ]);
    }

    /**
     * HR: Evidentira prijavu neprimjerenog komentara i šalje obavijesti.
     * EN: Records an inappropriate-comment report and sends notifications.
     */
    public function report(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);

        try {
            $result = $this->reports->report(
                $this->stringValue($body['comment_uuid'] ?? ''),
                $this->stringValue($body['reason'] ?? ''),
                $this->stringValue($body['page_url'] ?? ''),
            );
        } catch (Throwable $throwable) {
            return $this->failure($throwable);
        }

        return $this->responseFactory->json([
            'ok' => true,
            'created' => $result['created'],
            'message' => $result['created']
                ? __('Komentar je prijavljen.')
                : __('Ovaj komentar već ste prijavili.'),
            'csrf' => $this->csrfPayload(),
        ]);
    }

    /**
     * HR: Briše komentar samo nakon ponovne provjere moderatorskog prava.
     * EN: Deletes a comment only after rechecking moderation permission.
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);

        try {
            $identity = $this->access->authenticatedIdentity();
            $comment = $this->comments->activeByUuid($this->stringValue($body['comment_uuid'] ?? ''));
            $document = $this->access->publishedDocument(
                $this->stringValue($comment['document_id'] ?? ''),
                $this->stringValue($comment['language_code'] ?? ''),
            );
            if (!$this->access->canModerate($document)) {
                throw new RuntimeException(__('Nemate pravo brisanja komentara na ovom dokumentu.'));
            }

            $this->comments->delete($this->stringValue($comment['uuid'] ?? ''), $identity['id']);
        } catch (Throwable $throwable) {
            return $this->failure($throwable);
        }

        return $this->responseFactory->json([
            'ok' => true,
            'message' => __('Komentar je obrisan.'),
            'csrf' => $this->csrfPayload(),
        ]);
    }

    /**
     * HR: Isporučuje svježi CSRF token za uzastopne AJAX radnje.
     * EN: Supplies a fresh CSRF token for consecutive AJAX actions.
     */
    public function csrf(): ResponseInterface
    {
        return $this->responseFactory->json(['ok' => true, 'csrf' => $this->csrfPayload()]);
    }

    /**
     * HR: Poslužuje CSS Comment modula.
     * EN: Serves the Comment module CSS.
     */
    public function css(): ResponseInterface
    {
        return $this->responseFactory->file(
            dirname(__DIR__, 2) . '/resources/assets/comments.css',
            'text/css; charset=utf-8',
        );
    }

    /**
     * HR: Poslužuje JavaScript Comment modula bez vanjskih ovisnosti.
     * EN: Serves the dependency-free Comment module JavaScript.
     */
    public function js(): ResponseInterface
    {
        return $this->responseFactory->file(
            dirname(__DIR__, 2) . '/resources/assets/comments.js',
            'application/javascript; charset=utf-8',
        );
    }

    /**
     * HR: Pretvara parsed body u siguran niz.
     * EN: Converts the parsed body into a safe array.
     *
     * @return array<mixed, mixed>
     */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /**
     * HR: Vraća konzistentan JSON odgovor pogreške s novim CSRF tokenom.
     * EN: Returns a consistent JSON error response with a fresh CSRF token.
     */
    private function failure(Throwable $throwable): ResponseInterface
    {
        return $this->responseFactory->json([
            'ok' => false,
            'error' => $throwable->getMessage(),
            'csrf' => $this->csrfPayload(),
        ], 403);
    }

    /**
     * HR: Vraća token u obliku koji koriste ostali interaktivni moduli.
     * EN: Returns the token shape used by other interactive modules.
     *
     * @return array{name:string,token:string}
     */
    private function csrfPayload(): array
    {
        return [
            'name' => $this->session->getCsrfTokenName(),
            'token' => $this->session->getOrGenerateCsrfToken(),
        ];
    }

    /**
     * HR: Sigurno čita samo skalarne ulazne vrijednosti.
     * EN: Safely reads scalar input values only.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
