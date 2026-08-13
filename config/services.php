<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleComment\Command\HpCommentCommand;
use AaiEduHr\HeartPhrameModuleComment\Controller\CommentController;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentDocumentAccess;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentHtmlRenderer;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentIntegrationService;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentReportService;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentService;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentSettingsService;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorApiActorContext;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorService;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorWorkspaceIntegration;
use AaiEduHr\HeartPhrameModuleNotification\Service\NotificationService;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use HeartPhrame\Authn\AuthnHandlerInterface;
use HeartPhrame\Config\ConfigInterface;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Routing\UrlGenerator;
use HeartPhrame\Session\SessionInterface;
use Psr\Container\ContainerInterface;

$services = [
    CommentSettingsService::class => static fn(ContainerInterface $container): CommentSettingsService =>
        new CommentSettingsService($container->get(Database::class)),

    CommentService::class => static fn(ContainerInterface $container): CommentService =>
        new CommentService($container->get(Database::class)),

    CommentDocumentAccess::class => static fn(ContainerInterface $container): CommentDocumentAccess =>
        new CommentDocumentAccess(
            $container->get(EditorService::class),
            $container->get(AuthnHandlerInterface::class),
            $container->get(EditorWorkspaceIntegration::class),
            $container->get(EditorApiActorContext::class),
        ),

    CommentReportService::class => static fn(ContainerInterface $container): CommentReportService =>
        new CommentReportService(
            $container->get(CommentService::class),
            $container->get(CommentDocumentAccess::class),
            $container->get(NotificationService::class),
        ),

    CommentHtmlRenderer::class => static fn(ContainerInterface $container): CommentHtmlRenderer =>
        new CommentHtmlRenderer(
            $container->get(CommentService::class),
            $container->get(CommentSettingsService::class),
            $container->get(CommentDocumentAccess::class),
            $container->get(UrlGenerator::class),
        ),

    CommentIntegrationService::class => static fn(ContainerInterface $container): CommentIntegrationService =>
        new CommentIntegrationService(
            $container->get(CommentSettingsService::class),
            $container->get(CommentHtmlRenderer::class),
            $container->get(CommentDocumentAccess::class),
        ),

    CommentController::class => static fn(ContainerInterface $container): CommentController =>
        new CommentController(
            $container->get(ResponseFactory::class),
            $container->get(SessionInterface::class),
            $container->get(CommentDocumentAccess::class),
            $container->get(CommentSettingsService::class),
            $container->get(CommentService::class),
            $container->get(CommentReportService::class),
        ),

    HpCommentCommand::class => static fn(ContainerInterface $container): HpCommentCommand =>
        new HpCommentCommand($container->get(ConfigInterface::class)),
];

if (class_exists(\AaiEduHr\HeartPhrameModuleBackup\Service\DatabaseTableBackupProvider::class)) {
    $services['heartphrame.backup.provider.comment'] =
        static fn(ContainerInterface $container): \AaiEduHr\HeartPhrameModuleBackup\Service\DatabaseTableBackupProvider =>
            new \AaiEduHr\HeartPhrameModuleBackup\Service\DatabaseTableBackupProvider(
                $container->get(\AaiEduHr\HeartPhrameModuleOrm\Database\Database::class),
                new \AaiEduHr\HeartPhrameModuleBackup\Value\BackupProviderMetadata(
                    'comment',
                    \AaiEduHr\HeartPhrameModuleComment\ModuleComment::PACKAGE_NAME,
                    2,
                    ['hr' => 'Komentari', 'en' => 'Comments'],
                    ['auth', 'editor-html'],
                    [\AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::SITE, \AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::COMPONENT],
                    true,
                    true,
                ),
                [
                    ['dataset' => 'settings', 'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_SETTINGS, 'primary_key' => 'id', 'conflict_keys' => ['document_id', 'language_code'], 'preserve_primary_key' => false, 'foreign_keys' => [
                        ['column' => 'updated_by_user_id', 'namespace' => 'auth.user', 'nullable' => true],
                    ]],
                    ['dataset' => 'comments', 'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_COMMENTS, 'primary_key' => 'id', 'conflict_keys' => ['uuid'], 'preserve_primary_key' => false, 'identity_namespace' => 'comment.comment', 'foreign_keys' => [
                        ['column' => 'user_id', 'namespace' => 'auth.user'],
                        ['column' => 'deleted_by_user_id', 'namespace' => 'auth.user', 'nullable' => true],
                    ]],
                    ['dataset' => 'reactions', 'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_REACTIONS, 'primary_key' => 'id', 'conflict_keys' => ['comment_id', 'user_id'], 'preserve_primary_key' => false, 'foreign_keys' => [
                        ['column' => 'comment_id', 'namespace' => 'comment.comment'],
                        ['column' => 'user_id', 'namespace' => 'auth.user'],
                    ]],
                    ['dataset' => 'reports', 'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_REPORTS, 'primary_key' => 'id', 'conflict_keys' => ['uuid'], 'preserve_primary_key' => false, 'foreign_keys' => [
                        ['column' => 'comment_id', 'namespace' => 'comment.comment'],
                        ['column' => 'reporter_user_id', 'namespace' => 'auth.user'],
                    ]],
                ],
            );
}

if (
    class_exists(\AaiEduHr\HeartPhrameModuleBackup\Service\DocumentScopedDatabaseBackupProvider::class)
    && class_exists(\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::class)
) {
    $services['heartphrame.backup.provider.comment-workspace'] =
        static function (ContainerInterface $container): \AaiEduHr\HeartPhrameModuleBackup\Service\DocumentScopedDatabaseBackupProvider {
            $database = $container->get(Database::class);
            $identities = $container->get(\AaiEduHr\HeartPhrameModuleAuth\Backup\AuthBackupIdentityResolver::class);

            // HR: Poslovni modul poznaje Workspace shemu samo kao opcionalnu
            // integraciju. Backup jezgra nikada ne traži Workspace servis.
            // EN: The business module knows the Workspace schema only as an
            // optional integration. Backup core never locates Workspace services.
            $documentKeys = static function (string $identifier) use ($database): array {
                $workspace = $database->table(\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::TABLE_WORKSPACES)
                    ->where('slug', '=', $identifier)
                    ->first()
                    ?? $database->table(\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::TABLE_WORKSPACES)
                        ->where('uuid', '=', $identifier)
                        ->first();
                if (!is_array($workspace) || !is_numeric($workspace['id'] ?? null)) {
                    throw new \AaiEduHr\HeartPhrameModuleBackup\Exception\BackupException(
                        'Workspace does not exist: ' . $identifier,
                    );
                }
                $rows = $database->table(\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::TABLE_WORKSPACE_NODES)
                    ->select(['document_key'])
                    ->where('workspace_id', '=', (int)$workspace['id'])
                    ->get();
                return array_values(array_unique(array_filter(array_map(
                    static fn(array $row): string => trim((string)($row['document_key'] ?? '')),
                    $rows,
                ))));
            };

            return new \AaiEduHr\HeartPhrameModuleBackup\Service\DocumentScopedDatabaseBackupProvider(
                $database,
                new \AaiEduHr\HeartPhrameModuleBackup\Value\BackupProviderMetadata(
                    'comment-workspace',
                    \AaiEduHr\HeartPhrameModuleComment\ModuleComment::PACKAGE_NAME,
                    1,
                    ['hr' => 'Komentari područja', 'en' => 'Workspace comments'],
                    ['editor-html-workspace'],
                    [\AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::WORKSPACE],
                    true,
                    true,
                    [\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::PACKAGE_NAME],
                ),
                [
                    [
                        'dataset' => 'settings',
                        'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_SETTINGS,
                        'primary_key' => 'id',
                        'conflict_keys' => ['document_id', 'language_code'],
                        'preserve_primary_key' => false,
                        'scope_document_column' => 'document_id',
                        'portable_document_columns' => ['document_id'],
                        'portable_user_columns' => [['column' => 'updated_by_user_id', 'nullable' => true]],
                    ],
                    [
                        'dataset' => 'comments',
                        'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_COMMENTS,
                        'primary_key' => 'id',
                        'conflict_keys' => ['uuid'],
                        'preserve_primary_key' => false,
                        'identity_namespace' => 'comment-workspace.comment',
                        'scope_document_column' => 'document_id',
                        'portable_document_columns' => ['document_id'],
                        'portable_user_columns' => [
                            'user_id',
                            ['column' => 'deleted_by_user_id', 'nullable' => true],
                        ],
                        'copy_uuid_columns' => ['uuid'],
                    ],
                    [
                        'dataset' => 'reactions',
                        'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_REACTIONS,
                        'primary_key' => 'id',
                        'conflict_keys' => ['comment_id', 'user_id'],
                        'preserve_primary_key' => false,
                        'scope_parent' => [
                            'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_COMMENTS,
                            'primary_key' => 'id',
                            'document_column' => 'document_id',
                            'foreign_column' => 'comment_id',
                        ],
                        'portable_user_columns' => ['user_id'],
                        'foreign_keys' => [[
                            'column' => 'comment_id',
                            'namespace' => 'comment-workspace.comment',
                        ]],
                    ],
                    [
                        'dataset' => 'reports',
                        'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_REPORTS,
                        'primary_key' => 'id',
                        'conflict_keys' => ['uuid'],
                        'preserve_primary_key' => false,
                        'scope_parent' => [
                            'table' => \AaiEduHr\HeartPhrameModuleComment\ModuleComment::TABLE_COMMENTS,
                            'primary_key' => 'id',
                            'document_column' => 'document_id',
                            'foreign_column' => 'comment_id',
                        ],
                        'portable_user_columns' => ['reporter_user_id'],
                        'copy_uuid_columns' => ['uuid'],
                        'foreign_keys' => [[
                            'column' => 'comment_id',
                            'namespace' => 'comment-workspace.comment',
                        ]],
                    ],
                ],
                $documentKeys,
                static fn(mixed $id): ?string => $identities->userKeyForId($id),
                static fn(?string $key): ?int => $identities->userIdForKey($key),
            );
        };
}

return $services;
