<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleComment\ModuleComment;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;

return new class implements ReversibleMigrationInterface {
    /**
     * HR: Kreira prenosive tablice komentara, reakcija, prijava i postavki
     *     objavljenog odnosno nacrtnog dokumenta bez SQL-a vezanog uz bazu.
     * EN: Creates portable comment, reaction, report, and published/draft
     *     document-setting tables without database-specific SQL.
     */
    public function up(Database $db): void
    {
        $schema = $db->schema();

        if (!$schema->hasTable(ModuleComment::TABLE_SETTINGS)) {
            $schema->create(ModuleComment::TABLE_SETTINGS, static function (Blueprint $table): void {
                $table->id();
                $table->string('document_id', 190)->index();
                $table->string('language_code', 16)->index();
                $table->boolean('published_enabled')->default(true)->index();
                $table->boolean('draft_enabled')->default(true);
                $table->boolean('has_draft_setting')->default(false)->index();
                $table->bigInteger('updated_by_user_id')->unsigned()->nullable()->index();
                $table->timestamps();

                $table->unique(
                    ['document_id', 'language_code'],
                    'comment_setting_document_language_uq',
                );
            });
        }

        if (!$schema->hasTable(ModuleComment::TABLE_COMMENTS)) {
            $schema->create(ModuleComment::TABLE_COMMENTS, static function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->string('document_id', 190)->index();
                $table->string('language_code', 16)->index();
                $table->bigInteger('user_id')->unsigned()->index();
                $table->string('author_display_name', 255);
                $table->longText('body');
                $table->boolean('is_deleted')->default(false)->index();
                $table->bigInteger('deleted_by_user_id')->unsigned()->nullable()->index();
                $table->timestamp('deleted_at')->nullable()->index();
                $table->timestamps();

                $table->index(
                    ['document_id', 'language_code', 'is_deleted', 'created_at'],
                    'comment_document_language_state_created_idx',
                );
            });
        }

        if (!$schema->hasTable(ModuleComment::TABLE_REACTIONS)) {
            $schema->create(ModuleComment::TABLE_REACTIONS, static function (Blueprint $table): void {
                $table->id();
                $table->bigInteger('comment_id')->unsigned()->index();
                $table->bigInteger('user_id')->unsigned()->index();
                $table->string('reaction', 8)->index();
                $table->timestamps();

                $table->unique(
                    ['comment_id', 'user_id'],
                    'comment_reaction_comment_user_uq',
                );
            });
        }

        if (!$schema->hasTable(ModuleComment::TABLE_REPORTS)) {
            $schema->create(ModuleComment::TABLE_REPORTS, static function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->bigInteger('comment_id')->unsigned()->index();
                $table->bigInteger('reporter_user_id')->unsigned()->index();
                $table->string('status', 16)->default('open')->index();
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->unique(
                    ['comment_id', 'reporter_user_id'],
                    'comment_report_comment_reporter_uq',
                );
            });
        }
    }

    /**
     * HR: Uklanja samo tablice u vlasništvu Comment modula, obrnutim redom.
     * EN: Drops only Comment-owned tables in reverse dependency order.
     */
    public function down(Database $db): void
    {
        $db->schema()->dropIfExists(ModuleComment::TABLE_REPORTS);
        $db->schema()->dropIfExists(ModuleComment::TABLE_REACTIONS);
        $db->schema()->dropIfExists(ModuleComment::TABLE_COMMENTS);
        $db->schema()->dropIfExists(ModuleComment::TABLE_SETTINGS);
    }
};
