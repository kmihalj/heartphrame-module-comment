<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment\Tests;

use AaiEduHr\HeartPhrameModuleComment\Event\CommentChanged;
use AaiEduHr\HeartPhrameModuleComment\ModuleComment;
use AaiEduHr\HeartPhrameModuleComment\Service\CommentService;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommentService::class)]
#[UsesClass(CommentChanged::class)]
final class CommentServiceTest extends TestCase
{
    private Database $database;

    private CommentService $comments;

    /**
     * HR: Priprema praznu SQLite bazu istom početnom migracijom koju koristi host.
     * EN: Prepares an empty SQLite database with the same initial migration used by the host.
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
        $this->database = new Database($config, $helper);
        $migration = require dirname(__DIR__) . '/resources/migrations/initial_comment_schema.php';
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($this->database);
        $this->comments = new CommentService($this->database);
    }

    /**
     * HR: Potvrđuje da početna migracija stvara potpune i prazne tablice modula.
     * EN: Confirms that the initial migration creates complete and empty module tables.
     */
    public function testInitialSchemaIsCompleteAndEmpty(): void
    {
        $this->assertTrue($this->database->schema()->hasColumns(ModuleComment::TABLE_SETTINGS, [
            'document_id',
            'language_code',
            'published_enabled',
            'draft_enabled',
            'has_draft_setting',
        ]));
        $this->assertTrue($this->database->schema()->hasColumns(ModuleComment::TABLE_COMMENTS, [
            'uuid',
            'document_id',
            'language_code',
            'user_id',
            'body',
            'is_deleted',
        ]));
        $this->assertTrue($this->database->schema()->hasColumns(ModuleComment::TABLE_REACTIONS, [
            'comment_id',
            'user_id',
            'reaction',
        ]));
        $this->assertTrue($this->database->schema()->hasColumns(ModuleComment::TABLE_REPORTS, [
            'uuid',
            'comment_id',
            'reporter_user_id',
            'status',
        ]));
        $this->assertSame([], $this->database->table(ModuleComment::TABLE_COMMENTS)->get());
    }

    /**
     * HR: Provjerava unos, paginaciju i trajno auditirano soft-brisanje komentara.
     * EN: Verifies creation, pagination, and permanently audited soft deletion of comments.
     */
    public function testCommentsCanBeCreatedListedAndSoftDeleted(): void
    {
        $first = $this->comments->create('page-a', 'hr', 11, 'Ana Horvat', 'Prvi komentar', true);
        $second = $this->comments->create('page-a', 'hr', 12, 'Ivo Ivić', 'Drugi komentar', true);

        $page = $this->comments->comments('page-a', 'hr', 11, true, 1, 1);

        $this->assertSame(2, $page['total']);
        $this->assertSame(2, $page['pages']);
        $this->assertSame('Prvi komentar', $page['items'][0]['body']);

        $this->comments->delete((string)$first['uuid'], 99);
        $afterDelete = $this->comments->comments('page-a', 'hr', 11, true);
        $stored = $this->database->table(ModuleComment::TABLE_COMMENTS)
            ->where('uuid', '=', (string)$first['uuid'])
            ->first();

        $this->assertSame(1, $afterDelete['total']);
        $this->assertSame($second['uuid'], $afterDelete['items'][0]['uuid']);
        $this->assertIsArray($stored);
        $this->assertTrue((bool)($stored['is_deleted'] ?? false));
        $this->assertSame(99, (int)($stored['deleted_by_user_id'] ?? 0));
    }

    /**
     * HR: Reakcija se ponovnim klikom uklanja, a promjenom smjera ostaje jedinstvena.
     * EN: A repeated reaction is removed, while switching direction remains unique.
     */
    public function testReactionToggleAndDirectionAreDeterministic(): void
    {
        $comment = $this->comments->create('page-a', 'hr', 11, 'Ana Horvat', 'Komentar', false);
        $uuid = (string)$comment['uuid'];

        $up = $this->comments->react($uuid, 22, 'up');
        $removed = $this->comments->react($uuid, 22, 'up');
        $down = $this->comments->react($uuid, 22, 'down');

        $this->assertSame(['reaction' => 'up', 'up_count' => 1, 'down_count' => 0], $up);
        $this->assertSame(['reaction' => '', 'up_count' => 0, 'down_count' => 0], $removed);
        $this->assertSame(['reaction' => 'down', 'up_count' => 0, 'down_count' => 1], $down);
        $this->assertCount(1, $this->database->table(ModuleComment::TABLE_REACTIONS)->get());
    }

    /**
     * HR: Isti korisnik može isti komentar prijaviti samo jednom.
     * EN: The same user can report the same comment only once.
     */
    public function testReportIsIdempotentPerUserAndComment(): void
    {
        $comment = $this->comments->create('page-a', 'hr', 11, 'Ana Horvat', 'Komentar', false);
        $uuid = (string)$comment['uuid'];

        $first = $this->comments->report($uuid, 22, 'Neprimjeren sadržaj');
        $second = $this->comments->report($uuid, 22, 'Ponovljena prijava');

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertCount(1, $this->database->table(ModuleComment::TABLE_REPORTS)->get());
    }
}
