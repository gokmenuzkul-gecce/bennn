<?php

declare(strict_types=1);

$provider = file_get_contents(__DIR__ . '/../../app/Extension/CustomSessionServiceProvider.php');
if ($provider === false) {
    fwrite(STDERR, "Unable to read the custom session provider.\n");
    exit(1);
}

$checks = [
    'custom session creator uses the callback application container' => str_contains(
        $provider,
        '$app[\'db\']->connection($connection)'
    ),
    'custom session creator does not access the rebound manager as a provider' => !str_contains(
        $provider,
        '$this->app[\'db\']->connection($connection)'
    ),
];

$failures = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

foreach (array_keys($checks) as $check) {
    echo "PASS: {$check}\n";
}
