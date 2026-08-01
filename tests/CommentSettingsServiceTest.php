<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Tests;

use AaiEduHr\HeartPhrameModuleComment\Service\CommentSettingsService;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommentSettingsService::class)]
final class CommentSettingsServiceTest extends TestCase
{
    private CommentSettingsService $settings;

    /**
     * HR: Priprema praznu SQLite bazu za svaki izolirani test postavki.
     * EN: Prepares an empty SQLite database for each isolated settings test.
     */
    protected function setUp(): void
    {
        $helper = new Helper();
        $config = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]);
        $database = new Database($config, $helper);
        $migration = require dirname(__DIR__) . '/resources/migrations/initial_comment_schema.php';
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($database);
        $this->settings = new CommentSettingsService($database);
    }

    /**
     * HR: Novi dokument prema zadanim postavkama dopušta komentare.
     * EN: A new document allows comments by default.
     */
    public function testNewDocumentEnablesCommentsByDefault(): void
    {
        $this->assertTrue($this->settings->publishedEnabled('page-a', 'hr'));
        $this->assertTrue($this->settings->editorEnabled('page-a', 'hr'));
    }

    /**
     * HR: Workspace nacrt ne mijenja javno ponašanje prije objave.
     * EN: A Workspace draft does not change public behavior before publication.
     */
    public function testDraftSettingChangesOnlyWhenPublished(): void
    {
        $this->settings->saveDraft('page-a', 'hr', false, 7);

        $this->assertTrue($this->settings->publishedEnabled('page-a', 'hr'));
        $this->assertFalse($this->settings->editorEnabled('page-a', 'hr'));

        $this->settings->publishDraft('page-a', 'hr', 8);

        $this->assertFalse($this->settings->publishedEnabled('page-a', 'hr'));
        $this->assertFalse($this->settings->editorEnabled('page-a', 'hr'));
    }

    /**
     * HR: Odbacivanje nacrta vraća postavku zadnje objavljene stranice.
     * EN: Discarding a draft restores the setting of the last published page.
     */
    public function testDiscardDraftKeepsPublishedSetting(): void
    {
        $this->settings->savePublished('page-a', 'hr', false, 7);
        $this->settings->saveDraft('page-a', 'hr', true, 8);

        $this->settings->discardDraft('page-a', 'hr', 9);

        $this->assertFalse($this->settings->publishedEnabled('page-a', 'hr'));
        $this->assertFalse($this->settings->editorEnabled('page-a', 'hr'));
    }
}
