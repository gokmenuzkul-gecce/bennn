<?php

return [
    // Clean installs start with compatibility disabled. Existing operators can
    // enable it explicitly after confirming their rights to local game files.
    'enabled_by_default' => filter_var(env('PROMEX_LEGACY_COMPATIBILITY', false), FILTER_VALIDATE_BOOL),
];
