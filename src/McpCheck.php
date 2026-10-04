<?php

declare(strict_types=1);

/**
 * Static MCP-readiness checks for a Symcon module library (library.json + module folders).
 *
 * The rules mirror what an AI assistant can see through the Symcon MCP server: status codes and their
 * texts, the configuration form, function names and parameter names. Everything here is checked
 * without a running Symcon installation; behaviour (invalid values, reload, presentations of created
 * variables) belongs into the module's own runtime tests.
 *
 * Each finding: rule id, severity (error|warning), file, line, message key and arguments.
 */
final class McpCheck
{
    /** Kernel callbacks and standard overrides — not part of the module's own public API. */
    private const array KERNEL_METHODS = [
        '__construct', 'Create', 'ApplyChanges', 'Destroy', 'RequestAction', 'ReceiveData', 'ForwardData',
        'MessageSink', 'GetConfigurationForm', 'GetConfigurationForParent', 'GetCompatibleParents',
        'Translate', 'GetVisualizationTile', 'ProcessHookData', 'ProcessOAuthData', 'Migrate',
    ];

    /** Parameter names that say nothing about what is expected. */
    private const array GENERIC_PARAMETER_NAMES = [
        'value', 'status', 'data', 'param', 'params', 'parameter', 'wert', 'text', 'string', 'arg', 'args',
        'input', 'val', 'x', 'p', 'v',
    ];

    /** Form element names that hold credentials. */
    private const string SECRET_NAME_PATTERN = '/(passw(or)?(d|t)|token|secret|api_?key|apikey|^pin$|credential)/i';

    /** Module types that talk to a device or service and should offer a self test (module.json "type"). */
    private const array SELFTEST_MODULE_TYPES = [1, 2, 3];

    /** @var list<array{rule:string, severity:string, file:string, line:int, key:string, args:list<string|int>}> */
    private array $findings = [];

    public function __construct(private readonly string $root)
    {
    }

    /** @return list<array{rule:string, severity:string, file:string, line:int, key:string, args:list<string|int>}> */
    public function run(): array
    {
        $this->findings = [];
        if (!is_file($this->root . '/library.json')) {
            $this->add('setup', 'error', 'library.json', 0, 'no_library', []);
            return $this->findings;
        }
        foreach ($this->findModuleDirectories() as $directory) {
            $this->checkModule($directory);
        }
        return $this->findings;
    }

    /** @return list<string> module directories (relative), each with module.json */
    private function findModuleDirectories(): array
    {
        $result = [];
        foreach (scandir($this->root) ?: [] as $entry) {
            if ($entry[0] === '.' || !is_dir($this->root . '/' . $entry)) {
                continue;
            }
            if (is_file($this->root . '/' . $entry . '/module.json')) {
                $result[] = $entry;
            }
        }
        sort($result);
        return $result;
    }

    private function checkModule(string $directory): void
    {
        $moduleJson = $this->readJson($directory . '/module.json');
        $form = is_file($this->root . '/' . $directory . '/form.json') ? $this->readJson($directory . '/form.json') : null;
        $locale = is_file($this->root . '/' . $directory . '/locale.json') ? $this->readJson($directory . '/locale.json') : null;
        $source = is_file($this->root . '/' . $directory . '/module.php') ? (string)file_get_contents($this->root . '/' . $directory . '/module.php') : '';
        $prefix = (string)($moduleJson['prefix'] ?? '');

        $this->checkStatusCodes($directory, $source, $form, $locale);
        $this->checkSecrets($directory, $form);
        $publicFunctions = $this->findPublicFunctions($source);
        $this->checkSelfTest($directory, $moduleJson, $publicFunctions, $prefix);
        $this->checkFunctionHints($directory, $publicFunctions, $form, $prefix, $source);
        $this->checkParameterNames($directory, $publicFunctions, $prefix);
    }

    // ---- Rule 3/16: every status code the module sets is declared in form.json with a text ----------

    private function checkStatusCodes(string $directory, string $source, ?array $form, ?array $locale): void
    {
        $declared = [];
        foreach (($form['status'] ?? []) as $status) {
            $code = (int)($status['code'] ?? 0);
            $caption = trim((string)($status['caption'] ?? ''));
            $declared[$code] = $caption;
            if ($code >= 200 && $caption === '') {
                $this->add('status', 'error', $directory . '/form.json', 0, 'status_without_caption', [$code]);
            }
            if ($code >= 200 && $caption !== '' && is_array($locale) && !$this->isTranslated($caption, $locale)) {
                $this->add('status', 'warning', $directory . '/locale.json', 0, 'status_not_translated', [$code, $caption]);
            }
        }

        foreach ($this->findUsedStatusCodes($source) as $code => $line) {
            if (!array_key_exists($code, $declared)) {
                $this->add('status', 'error', $directory . '/module.php', $line, 'status_not_declared', [$code]);
            }
        }
    }

    /**
     * Status codes ≥ 200 the module refers to: numeric SetStatus() arguments and every class constant
     * with STATUS in its name that the module uses (also via a variable: $ret = self::STATUS_X).
     *
     * @return array<int, int> code => first line
     */
    private function findUsedStatusCodes(string $source): array
    {
        $constants = [];
        if (preg_match_all('/const\s+(?:int\s+)?([A-Z0-9_]*STATUS[A-Z0-9_]*)\s*=\s*(\d+)\s*;/', $source, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $constants[$hit[1]] = (int)$hit[2];
            }
        }

        $used = [];
        $lines = preg_split('/\R/', $source) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(\*|\/\/|#)/', $line) || preg_match('/\bconst\s/', $line)) {
                continue;
            }
            if (preg_match_all('/SetStatus\(\s*(\d+)/', $line, $numbers)) {
                foreach ($numbers[1] as $number) {
                    $used[(int)$number] ??= $index + 1;
                }
            }
            if (preg_match_all('/(?:self|static)::([A-Z0-9_]*STATUS[A-Z0-9_]*)\b/', $line, $names)) {
                foreach ($names[1] as $name) {
                    if (isset($constants[$name])) {
                        $used[$constants[$name]] ??= $index + 1;
                    }
                }
            }
        }
        return array_filter($used, static fn(int $line, int $code): bool => $code >= 200, ARRAY_FILTER_USE_BOTH);
    }

    // ---- Rule 13: credentials are entered in password fields ----------------------------------------

    private function checkSecrets(string $directory, ?array $form): void
    {
        if ($form === null) {
            return;
        }
        $this->walkFormElements($form['elements'] ?? [], function (array $element) use ($directory): void {
            $name = (string)($element['name'] ?? '');
            $type = (string)($element['type'] ?? '');
            if ($name !== '' && preg_match(self::SECRET_NAME_PATTERN, $name) && $type !== 'PasswordTextBox'
                && in_array($type, ['ValidationTextBox', 'TextBox', 'NumberSpinner'], true)) {
                // Warning, not error: the MCP server already masks such values by name ("[hidden]"), but the
                // console shows them in plain text and the masking rule is undocumented.
                $this->add('secrets', 'warning', $directory . '/form.json', 0, 'secret_not_password_field', [$name, $type]);
            }
        });
    }

    private function walkFormElements(array $elements, callable $visit): void
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            $visit($element);
            foreach (['items', 'elements', 'form'] as $key) {
                if (is_array($element[$key] ?? null)) {
                    $this->walkFormElements($element[$key], $visit);
                }
            }
        }
    }

    // ---- Rule 15: a self test with the common name ----------------------------------------------------

    private function checkSelfTest(string $directory, ?array $moduleJson, array $publicFunctions, string $prefix): void
    {
        $type = (int)($moduleJson['type'] ?? -1);
        if (!in_array($type, self::SELFTEST_MODULE_TYPES, true)) {
            return;
        }
        $selfTest = $publicFunctions['RunSelfTest'] ?? null;
        if ($selfTest === null) {
            $this->add('selftest', 'warning', $directory . '/module.php', 0, 'no_selftest', [$prefix !== '' ? $prefix . '_RunSelfTest' : 'RunSelfTest']);
            return;
        }
        if ($selfTest['return'] !== 'string' || $selfTest['params'] !== []) {
            $this->add('selftest', 'warning', $directory . '/module.php', $selfTest['line'], 'selftest_signature', []);
        }
    }

    // ---- Rule 6: hints for every public function, meaningful parameter names ------------------------

    private function checkFunctionHints(string $directory, array $publicFunctions, ?array $form, string $prefix, string $source): void
    {
        $formText = $form === null ? '' : (string)json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($publicFunctions as $name => $function) {
            // RunSelfTest needs no hint: its fixed name is what makes it discoverable (rule 15).
            if (in_array($name, self::KERNEL_METHODS, true) || $name === 'RunSelfTest' || $this->isTimerTarget($source, $prefix, $name)) {
                continue;
            }
            $mentioned = str_contains($formText, $prefix . '_' . $name) || preg_match('/\b' . preg_quote($name, '/') . '\b/', $formText);
            if (!$mentioned) {
                $this->add('hints', 'warning', $directory . '/form.json', 0, 'function_without_hint', [$prefix !== '' ? $prefix . '_' . $name : $name]);
            }
        }
    }

    private function checkParameterNames(string $directory, array $publicFunctions, string $prefix): void
    {
        foreach ($publicFunctions as $name => $function) {
            if (in_array($name, self::KERNEL_METHODS, true)) {
                continue;
            }
            foreach ($function['params'] as $parameter) {
                if (in_array(strtolower($parameter), self::GENERIC_PARAMETER_NAMES, true)) {
                    $this->add('params', 'warning', $directory . '/module.php', $function['line'], 'generic_parameter',
                        [($prefix !== '' ? $prefix . '_' : '') . $name, '$' . $parameter]);
                }
            }
        }
    }

    // Public only because a timer calls it (RegisterTimer(…, 'PREFIX_Name(…)')): not meant for users or AIs.
    private function isTimerTarget(string $source, string $prefix, string $name): bool
    {
        if ($prefix === '') {
            return false;
        }
        return (bool)preg_match('/RegisterTimer\s*\([^;]*' . preg_quote($prefix . '_' . $name, '/') . '\s*\(/', $source);
    }

    /** @return array<string, array{params:list<string>, return:string, line:int}> */
    private function findPublicFunctions(string $source): array
    {
        $result = [];
        if (!preg_match_all('/^\s*public\s+(?:static\s+)?function\s+(\w+)\s*\(([^)]*)\)\s*(?::\s*([?\w|]+))?/m', $source, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $result;
        }
        foreach ($m as $hit) {
            $params = [];
            if (preg_match_all('/\$(\w+)/', $hit[2][0], $p)) {
                $params = $p[1];
            }
            $result[$hit[1][0]] = [
                'params' => $params,
                'return' => isset($hit[3]) ? (string)$hit[3][0] : '',
                'line'   => substr_count(substr($source, 0, $hit[0][1]), "\n") + 1,
            ];
        }
        return $result;
    }

    // ---- helpers ------------------------------------------------------------------------------------

    private function isTranslated(string $text, array $locale): bool
    {
        foreach (($locale['translations'] ?? []) as $translations) {
            if (is_array($translations) && array_key_exists($text, $translations)) {
                return true;
            }
        }
        return false;
    }

    private function readJson(string $relative): ?array
    {
        $decoded = json_decode((string)@file_get_contents($this->root . '/' . $relative), true);
        if (!is_array($decoded)) {
            $this->add('setup', 'error', $relative, 0, 'invalid_json', []);
            return null;
        }
        return $decoded;
    }

    private function add(string $rule, string $severity, string $file, int $line, string $key, array $args): void
    {
        $this->findings[] = ['rule' => $rule, 'severity' => $severity, 'file' => $file, 'line' => $line, 'key' => $key, 'args' => $args];
    }
}
