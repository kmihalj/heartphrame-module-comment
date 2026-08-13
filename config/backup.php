<?php

declare(strict_types=1);

return ['providers' => [
    'heartphrame.backup.provider.comment',
    [
        'service' => 'heartphrame.backup.provider.comment-workspace',
        'requires' => ['aaieduhr/heartphrame-module-workspace'],
    ],
]];
