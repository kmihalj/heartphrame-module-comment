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

return [
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
