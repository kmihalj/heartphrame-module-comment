<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleAuth\Middleware\RequireAuthenticatedUserMiddleware;
use AaiEduHr\HeartPhrameModuleAuth\ModuleAuth;
use AaiEduHr\HeartPhrameModuleComment\Command\HpCommentCommand;
use AaiEduHr\HeartPhrameModuleComment\Controller\CommentController;
use AaiEduHr\HeartPhrameModuleComment\ModuleComment;
use AaiEduHr\HeartPhrameModuleEditorHtml\ModuleEditorHtml;
use AaiEduHr\HeartPhrameModuleNotification\ModuleNotification;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use HeartPhrame\Bridge\ComposerBridge;
use HeartPhrame\Command\CommandDefinition;
use HeartPhrame\Config\ConfigInterface;
use Psr\Container\ContainerInterface;

return new class extends \HeartPhrame\Module\AbstractModuleManifest {
    private const AUTH_PACKAGE = 'aaieduhr/heartphrame-module-auth';

    private const ORM_PACKAGE = 'aaieduhr/heartphrame-module-orm';

    private const EDITOR_PACKAGE = 'aaieduhr/heartphrame-module-editor-html';

    private const NOTIFICATION_PACKAGE = 'aaieduhr/heartphrame-module-notification';

    /**
     * HR: Zaustavlja učitavanje ako obavezni Auth, ORM, Editor i Notification
     *     moduli nisu instalirani i poredani prije Comment modula.
     * EN: Stops loading unless required Auth, ORM, Editor, and Notification
     *     modules are installed and ordered before the Comment module.
     */
    public function canLoad(ContainerInterface $container): bool
    {
        $composer = $container->get(ComposerBridge::class);
        if (!($composer instanceof ComposerBridge)) {
            throw new RuntimeException('Comment module requires ComposerBridge.');
        }

        $requiredClasses = [
            self::AUTH_PACKAGE => ModuleAuth::class,
            self::ORM_PACKAGE => Database::class,
            self::EDITOR_PACKAGE => ModuleEditorHtml::class,
            self::NOTIFICATION_PACKAGE => ModuleNotification::class,
        ];
        foreach ($requiredClasses as $package => $className) {
            if (!$composer->isInstalled($package) || !class_exists($className)) {
                throw new RuntimeException('Comment module requires installed package "' . $package . '".');
            }
        }

        $config = $container->get(ConfigInterface::class);
        if (!($config instanceof ConfigInterface)) {
            throw new RuntimeException('Comment module requires ConfigInterface.');
        }

        $enabled = $config->getAsArrayWithValuesAsNonEmptyStrings('app.modules.enabled') ?? [];
        $commentPosition = array_search(ModuleComment::PACKAGE_NAME, $enabled, true);
        foreach (array_keys($requiredClasses) as $package) {
            $position = array_search($package, $enabled, true);
            if ($position === false || ($commentPosition !== false && $position > $commentPosition)) {
                throw new RuntimeException(
                    'Comment module requires enabled module "' . $package . '" before "'
                    . ModuleComment::PACKAGE_NAME . '".',
                );
            }
        }

        return true;
    }

    /**
     * HR: Odgađa registraciju dok obavezni moduli ne izlože svoje servise.
     * EN: Defers registration until required modules expose their services.
     */
    public function requiresDeferredLoading(): bool
    {
        return true;
    }

    /**
     * HR: Učitava servisne definicije modula.
     * EN: Loads module service definitions.
     */
    public function getServices(): array
    {
        $services = require __DIR__ . '/config/services.php';
        if (!is_array($services)) {
            throw new RuntimeException('Comment config/services.php must return an array.');
        }

        return $services;
    }

    /**
     * HR: Registrira javne assete i autentificirane operacije komentara.
     * EN: Registers public assets and authenticated comment operations.
     */
    public function getBaseRoutes(): array
    {
        $authenticated = [RequireAuthenticatedUserMiddleware::class];

        return [
            ['GET', '/comments/assets.css', CommentController::class . '@css', 'comment.assets.css'],
            ['GET', '/comments/assets.js', CommentController::class . '@js', 'comment.assets.js'],
            ['GET', '/comments/csrf-token', CommentController::class . '@csrf', 'comment.csrf', $authenticated],
            ['POST', '/comments', CommentController::class . '@create', 'comment.create', $authenticated],
            ['POST', '/comments/reaction', CommentController::class . '@reaction', 'comment.reaction', $authenticated],
            ['POST', '/comments/report', CommentController::class . '@report', 'comment.report', $authenticated],
            ['POST', '/comments/delete', CommentController::class . '@delete', 'comment.delete', $authenticated],
        ];
    }

    /**
     * HR: Registrira instalacijsku CLI naredbu za početnu migraciju.
     * EN: Registers the installation CLI command for the initial migration.
     */
    public function getCommands(): array
    {
        return [
            new CommandDefinition('comment', 'Comment module helper command.', [HpCommentCommand::class, 'run']),
            new CommandDefinition(
                'comment:install-migration',
                'Copy initial Comment migration into the host application.',
                [HpCommentCommand::class, 'installMigration'],
            ),
        ];
    }
};
