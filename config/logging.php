<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stderr'),
    'deprecations' => ['channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'), 'trace' => false],
    'channels' => [
        'stderr' => ['driver' => 'monolog', 'handler' => StreamHandler::class, 'with' => ['stream' => 'php://stderr'], 'level' => env('LOG_LEVEL', 'info')],
        'null' => ['driver' => 'monolog', 'handler' => NullHandler::class],
        'stack' => ['driver' => 'stack', 'channels' => ['stderr'], 'ignore_exceptions' => false],
    ],
];
