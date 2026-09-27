<?php
declare(strict_types=1);
$view = file_get_contents(__DIR__ . '/../../resources/views/liteback/settings/index.blade.php');
$count = 0;
preg_match_all('/<input\b[^>]*name="(cedar_[^"]+)"[^>]*>/', $view, $inputs, PREG_SET_ORDER);
foreach ($inputs as $input) {
    if (!preg_match('/cedar_(crash|plinko|mines|dice|wheel)_(min_bet|max_bet|max_multiplier)$/', $input[1])) continue;
    if (!str_contains($input[0], 'step="any"') || !str_contains($input[0], 'min="') || !str_contains($input[0], 'max="')) {
        throw new RuntimeException('Numeric range/step mismatch: ' . $input[1]);
    }
    $count++;
}
if ($count !== 11) throw new RuntimeException('Expected all eleven Cedar bet/cap inputs.');
echo "PASS: eleven Cedar numeric inputs accept server-supported decimals without step-offset conflicts; min/max retained.\n";
