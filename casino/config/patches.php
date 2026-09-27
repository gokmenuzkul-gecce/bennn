<?php

return [
    'demo' => env('PROMEX_PATCH_DEMO', false),
    'demo_public_key' => env('PROMEX_PATCH_DEMO_PUBLIC_KEY_FILE', ''),
    'mysqldump' => env('PROMEX_MYSQLDUMP_BINARY', 'mysqldump'),
    'php_binary' => env('PROMEX_PHP_BINARY', in_array(PHP_SAPI, ['cli', 'cli-server'], true) ? PHP_BINARY : 'php'),
];
