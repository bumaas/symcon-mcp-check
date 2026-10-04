<?php

declare(strict_types=1);

/**
 * Tests for symcon-mcp-check: every rule must fire on the flawed library and stay silent on the clean one.
 * Run: php tests/run.php — last line "N checks, M failures", exit 0/1.
 */

require_once dirname(__DIR__) . '/src/McpCheck.php';
require_once dirname(__DIR__) . '/src/Messages.php';

$checks = 0;
$failures = 0;
function check(bool $condition, string $label, string $detail = ''): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures++;
    }
    echo ($condition ? '  ok   ' : '  FAIL ') . $label . ($condition || $detail === '' ? '' : ' - ' . $detail) . "\n";
}

/** @return list<string> "severity rule key args" per finding */
function summary(array $findings): array
{
    return array_map(static fn(array $f): string => $f['severity'] . ' ' . $f['rule'] . ' ' . $f['key'] . ' ' . implode(',', $f['args']), $findings);
}

// ---- clean library: no findings ----
$clean = (new McpCheck(__DIR__ . '/fixtures/clean'))->run();
check($clean === [], 'clean library: no findings', implode('; ', summary($clean)));

// ---- flawed library: each rule fires exactly where expected ----
$flawed = summary((new McpCheck(__DIR__ . '/fixtures/flawed'))->run());
$expected = [
    'error status status_not_declared 205'        => 'status via variable ($ret = self::STATUS_BUSY) not declared',
    'error status status_not_declared 204'        => 'numeric SetStatus(204) not declared',
    'error status status_without_caption 203'     => 'declared status without caption',
    'warning status status_not_translated 201,Not translated status' => 'status caption missing in locale.json',
    'warning secrets secret_not_password_field Password,ValidationTextBox' => 'credential in a nested ValidationTextBox',
    'warning selftest selftest_signature '        => 'RunSelfTest with parameter and bool return',
    'warning hints function_without_hint FLD_SetMode' => 'public function without hint',
    'warning params generic_parameter FLD_SetMode,$Value' => 'generic parameter name',
    'error status status_not_declared 206'        => 'code-built form: status set but not in the status list',
    'warning status status_not_translated 203,Not translated in code.' => 'code-built form: status caption missing in locale.json',
    'warning secrets secret_not_password_field ApiToken,ValidationTextBox' => 'code-built form: credential in a ValidationTextBox',
    'warning hints function_without_hint FCD_SwitchOutput' => 'code-built form: public function without hint',
    'warning params generic_parameter FCD_SwitchOutput,$Value' => 'code-built form: generic parameter name',
    'error hints hint_visible For scripts and AI assistants' => 'hint for scripts/AI shown in the console',
    'error hints hint_stale FCD_OldFunction' => 'form names a function that does not exist',
    'warning action requestaction_silent_default FCD' => 'RequestAction default branch does not report the unknown ident',
    'warning status status_not_logged Code Device' => 'error status set, cause never logged (code form)',
    'warning status status_not_logged Example Device' => 'error status set, cause never logged (form.json)',
    'warning secrets debug_secret $token' => 'credential written to the debug output',
    'warning hints function_without_hint FCD_SetPower' => 'second public function without hint',
    'warning params generic_parameter FCD_SetPower,$power' => 'generic parameter name $power',
];
foreach ($expected as $line => $label) {
    check(in_array($line, $flawed, true), 'flawed library: ' . $label, implode('; ', $flawed));
}
check(count($flawed) === count($expected), 'flawed library: no further findings', implode('; ', array_diff($flawed, array_keys($expected))));
check(!array_any($flawed, static fn(string $f): bool => str_contains($f, 'FLC')), 'configurator (type 4): no self test demanded');
check(!array_any($flawed, static fn(string $f): bool => str_contains($f, 'parse')), 'helper class in module.php: its public methods are not module functions');

// ---- line numbers refer to the file, also when only the module class is searched ----
$raw = (new McpCheck(__DIR__ . '/fixtures/flawed'))->run();
$hit = array_values(array_filter($raw, static fn(array $f): bool => $f['key'] === 'generic_parameter' && $f['args'][0] === 'FCD_SwitchOutput'));
$expectedLine = 1 + (int)array_search(true, array_map(static fn(string $l): bool => str_contains($l, 'function SwitchOutput'), file(__DIR__ . '/fixtures/flawed/Code Device/module.php')), true);
check(($hit[0]['line'] ?? 0) === $expectedLine, 'line number of a finding in the module class', 'expected ' . $expectedLine . ', got ' . ($hit[0]['line'] ?? 0));

// ---- no library ----
$none = summary((new McpCheck(__DIR__))->run());
check($none === ['error setup no_library '], 'directory without library.json: setup error', implode('; ', $none));

// ---- messages exist in both languages ----
foreach (['status_not_declared', 'secret_not_password_field', 'no_selftest', 'function_without_hint', 'generic_parameter', 'hint_visible', 'hint_stale', 'requestaction_silent_default', 'status_not_logged', 'debug_secret'] as $key) {
    check(Messages::format($key, ['A', 'B'], 'de') !== Messages::format($key, ['A', 'B'], 'en'), "message $key translated");
}

echo "\n$checks checks, $failures failures\n";
exit($failures > 0 ? 1 : 0);
