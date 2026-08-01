<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleComment;

/**
 * HR: Sadrži stabilne identifikatore paketa i tablica Comment modula.
 * EN: Contains stable Comment module package and table identifiers.
 */
final class ModuleComment
{
    public const PACKAGE_NAME = 'aaieduhr/heartphrame-module-comment';

    public const TABLE_COMMENTS = 'document_comments';

    public const TABLE_REACTIONS = 'document_comment_reactions';

    public const TABLE_REPORTS = 'document_comment_reports';

    public const TABLE_SETTINGS = 'document_comment_settings';

    /**
     * HR: Sprječava instanciranje registra konstanti.
     * EN: Prevents instantiation of the constants registry.
     */
    private function __construct()
    {
    }
}
