<?php

declare(strict_types=1);

/**
 * symcon-mcp-check — static MCP-readiness checks for Symcon module libraries.
 *
 * Usage: php check_mcp.php [<library path>] [--lang=en|de] [--fail-on=error|warning]
 * Exit:  0 = passed, 1 = findings at or above --fail-on, 2 = usage error
 */

require_once __DIR__ . '/src/McpCheck.php';
require_once __DIR__ . '/src/Messages.php';

$path = '.';
$language = 'en';
$failOn = 'error';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--lang=')) {
        $language = substr($argument, 7);
    } elseif (str_starts_with($argument, '--fail-on=')) {
        $failOn = substr($argument, 10);
    } elseif (!str_starts_with($argument, '--')) {
        $path = $argument;
    } else {
        fwrite(STDERR, "Unknown option: $argument\n");
        exit(2);
    }
}
if (!in_array($language, ['en', 'de'], true) || !in_array($failOn, ['error', 'warning'], true) || !is_dir($path)) {
    fwrite(STDERR, "Usage: php check_mcp.php [<library path>] [--lang=en|de] [--fail-on=error|warning]\n");
    exit(2);
}

$findings = (new McpCheck(rtrim($path, '/\\')))->run();
usort($findings, static fn(array $a, array $b): int => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

$counts = ['error' => 0, 'warning' => 0];
foreach ($findings as $f) {
    $counts[$f['severity']]++;
    $where = $f['file'] . ($f['line'] > 0 ? ':' . $f['line'] : '');
    $label = $f['severity'] === 'error' ? 'ERROR  ' : 'WARNING';
    echo sprintf("%s [%s] %s — %s\n", $label, $f['rule'], $where, Messages::format($f['key'], $f['args'], $language));
    if (getenv('GITHUB_ACTIONS') === 'true') {
        echo sprintf("::%s file=%s,line=%d,title=MCP %s::%s\n", $f['severity'], $f['file'], max(1, $f['line']), $f['rule'],
            Messages::format($f['key'], $f['args'], $language));
    }
}
echo $language === 'de'
    ? sprintf("\n%d Fehler, %d Warnungen\n", $counts['error'], $counts['warning'])
    : sprintf("\n%d errors, %d warnings\n", $counts['error'], $counts['warning']);

$failed = $counts['error'] > 0 || ($failOn === 'warning' && $counts['warning'] > 0);
exit($failed ? 1 : 0);
