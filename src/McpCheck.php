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
        // aus dem Roborock-Umbau (04.10.2026): dort in 21 Funktionen umbenannt
        'y', 'state', 'mode', 'power', 'number', 'part', 'time', 'direction', 'token',
    ];

    /** Wording that marks a label as a hint for scripts and AI assistants (Roborock, ebusdMQTT, SonyTV, BlindControl). */
    private const string HINT_MARKER = '/for scripts|from a script|in scripts|ai assistant|für skripte|ki-assistent/i';

    /** @var array<string, true> public function names of the whole library (lower case), incl. base classes and traits */
    private array $libraryFunctions = [];

    /** @var array<string, true> traits of the library that override SendDebug (lower case) */
    private array $maskingTraits = [];

    /** @var array<string, true> traits of the library that write to the log (lower case) */
    private array $loggingTraits = [];

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
        $this->scanLibrary();
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
        $classSource = $this->moduleClassSource($source);
        // Form built in code (GetConfigurationForm in module.php, e.g. Roborock): read status list,
        // fields and captions from the array literals of the module class as well.
        $codeForm = preg_match('/function\s+GetConfigurationForm\s*\(/', $classSource) ? $this->parseCodeForm($classSource) : null;

        $this->checkStatusCodes($directory, $source, $form, $locale, $codeForm);
        $this->checkSecrets($directory, $form, $codeForm);
        $publicFunctions = $this->findPublicFunctions($classSource, substr_count(substr($source, 0, (int)strpos($source, $classSource)), "\n"));
        $this->checkSelfTest($directory, $moduleJson, $publicFunctions, $prefix);
        $this->checkFunctionHints($directory, $publicFunctions, $form, $prefix, $source, $codeForm);
        $this->checkParameterNames($directory, $publicFunctions, $prefix);
        $this->checkHintVisibility($directory, $form, $codeForm);
        $this->checkStaleNames($directory, $form, $codeForm, $prefix);
        $this->checkRequestAction($directory, $classSource, $prefix);
        $this->checkStatusLogged($directory, $source, $classSource);
        $this->checkDebugSecrets($directory, $classSource);
    }

    /** Public functions and SendDebug-overriding traits of all PHP files of the library (not tests, .style, vendor). */
    private function scanLibrary(): void
    {
        $this->libraryFunctions = [];
        $this->maskingTraits    = [];
        $this->loggingTraits    = [];
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            static fn(SplFileInfo $f): bool => !($f->isDir() && in_array($f->getFilename(), ['.git', '.style', 'tests', 'vendor', 'stubs', 'node_modules'], true))
        ));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string)file_get_contents($file->getPathname());
            if (preg_match_all('/^[ \t]*public\s+(?:static\s+)?function\s+(\w+)\s*\(/m', $code, $m)) {
                foreach ($m[1] as $name) {
                    $this->libraryFunctions[strtolower($name)] = true;
                }
            }
            if (preg_match_all('/\btrait\s+(\w+)\s*\{/', $code, $t)) {
                foreach ($t[1] as $trait) {
                    if (preg_match('/function\s+SendDebug\s*\(/', $code)) {
                        $this->maskingTraits[strtolower($trait)] = true;
                    }
                    if (preg_match('/LogMessage\s*\(/', $code)) {
                        $this->loggingTraits[strtolower($trait)] = true;
                    }
                }
            }
        }
    }

    // ---- Rule 6: hints stay hidden and current --------------------------------------------------------

    private function checkHintVisibility(string $directory, ?array $form, ?array $codeForm): void
    {
        if ($form !== null) {
            $visit = function (array $element) use ($directory): void {
                $caption = (string)($element['caption'] ?? '');
                if (($element['type'] ?? '') === 'Label' && preg_match(self::HINT_MARKER, $caption) && ($element['visible'] ?? true) !== false) {
                    $this->add('hints', 'error', $directory . '/form.json', 0, 'hint_visible', [$this->hintStart($caption)]);
                }
            };
            $this->walkFormElements($form['elements'] ?? [], $visit);
            $this->walkFormElements($form['actions'] ?? [], $visit);
        }
        foreach (($codeForm['labels'] ?? []) as $label) {
            if (preg_match(self::HINT_MARKER, $label['caption']) && !$label['hidden']) {
                $this->add('hints', 'error', $directory . '/module.php', $label['line'], 'hint_visible', [$this->hintStart($label['caption'])]);
            }
        }
    }

    private function hintStart(string $caption): string
    {
        $start = strstr($caption, ':', true);
        return mb_substr($start === false ? $caption : $start, 0, 40);
    }

    private function checkStaleNames(string $directory, ?array $form, ?array $codeForm, string $prefix): void
    {
        if ($prefix === '') {
            return;
        }
        // A name counts as a function when it is called or shown with its signature (PREFIX_Name( …) anywhere
        // in the form, or when a marked hint lists it. Element names like "PREFIX_url" are properties.
        $sources = [];
        if ($form !== null) {
            $hints = [];
            $collect = function (array $element) use (&$hints): void {
                $caption = (string)($element['caption'] ?? '');
                if (($element['type'] ?? '') === 'Label' && preg_match(self::HINT_MARKER, $caption)) {
                    $hints[] = $caption;
                }
            };
            $this->walkFormElements($form['elements'] ?? [], $collect);
            $this->walkFormElements($form['actions'] ?? [], $collect);
            $sources[$directory . '/form.json'] = [(string)json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), implode("\n", $hints)];
        }
        if ($codeForm !== null) {
            $hints = array_column(array_filter($codeForm['labels'], fn(array $l): bool => (bool)preg_match(self::HINT_MARKER, $l['caption'])), 'caption');
            $sources[$directory . '/module.php'] = [$codeForm['text'], implode("\n", $hints)];
        }
        $quoted = preg_quote($prefix, '/');
        foreach ($sources as $file => [$text, $hintText]) {
            $names = [];
            if (preg_match_all('/\b' . $quoted . '_(\w+)\s*\(/', $text, $m)) {
                $names = $m[1];
            }
            if (preg_match_all('/\b' . $quoted . '_(\w+)\b/', $hintText, $m)) {
                $names = array_merge($names, $m[1]);
            }
            foreach (array_unique($names) as $name) {
                if (!isset($this->libraryFunctions[strtolower($name)])) {
                    $this->add('hints', 'error', $file, 0, 'hint_stale', [$prefix . '_' . $name]);
                }
            }
        }
    }

    // ---- Rule 8: RequestAction reports every failure ---------------------------------------------------

    private function checkRequestAction(string $directory, string $classSource, string $prefix): void
    {
        $body = $this->functionBody($classSource, 'RequestAction');
        if ($body === null || !preg_match('/\bswitch\s*\(/', $body)) {
            return; // no switch (delegates or uses match) — nothing to judge statically
        }
        $default = strrpos($body, 'default:');
        $silent  = $default === false || !preg_match('/\b(trigger_error|throw)\b/', substr($body, $default));
        if ($silent) {
            $this->add('action', 'warning', $directory . '/module.php', 0, 'requestaction_silent_default', [$prefix !== '' ? $prefix : $directory]);
        }
    }

    /** Body of a method in the module class (from its opening brace to the matching closing brace), or null. */
    private function functionBody(string $classSource, string $name): ?string
    {
        if (!preg_match('/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)[^{]*\{/', $classSource, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $len   = strlen($classSource);
        for ($i = $start; $i < $len; $i++) {
            if ($classSource[$i] === '{') {
                $depth++;
            } elseif ($classSource[$i] === '}' && --$depth === 0) {
                return substr($classSource, $start, $i - $start);
            }
        }
        return null;
    }

    /** Does the module class use (use X, Y;) one of the given traits? */
    private function usesTraitFrom(string $classSource, array $traits): bool
    {
        if ($traits === [] || !preg_match_all('/^[ \t]*use\s+([\w\\\\, ]+);/m', $classSource, $uses)) {
            return false;
        }
        foreach ($uses[1] as $list) {
            foreach (array_map('trim', explode(',', $list)) as $trait) {
                if (isset($traits[strtolower(basename(str_replace('\\', '/', $trait)))])) {
                    return true;
                }
            }
        }
        return false;
    }

    // ---- Rule 3: error states are logged ---------------------------------------------------------------

    private function checkStatusLogged(string $directory, string $source, string $classSource): void
    {
        if ($this->findUsedStatusCodes($source) !== [] && !preg_match('/LogMessage\s*\(/', $source)
            && !$this->usesTraitFrom($classSource, $this->loggingTraits)) {
            $this->add('status', 'warning', $directory . '/module.php', 0, 'status_not_logged', [$directory]);
        }
    }

    // ---- Rules 10/13: no credentials in the debug output -----------------------------------------------

    private function checkDebugSecrets(string $directory, string $classSource): void
    {
        if (preg_match('/function\s+SendDebug\s*\(/', $classSource)) {
            return; // the module masks its own debug output
        }
        if ($this->usesTraitFrom($classSource, $this->maskingTraits)) {
            return;
        }
        foreach (preg_split('/\R/', $classSource) ?: [] as $index => $line) {
            if (!preg_match('/\b(SendDebug|_debug|logDebug)\s*\(/', $line) || preg_match('/^\s*(\*|\/\/|#)/', $line)) {
                continue;
            }
            if (preg_match('/\$\w*(token|passw|secret)\w*\b|[\'"](token|password|ssecurity|serviceToken|passToken)[\'"]/i', $line, $hit)) {
                $this->add('secrets', 'warning', $directory . '/module.php', $index + 1, 'debug_secret', [$hit[0]]);
            }
        }
    }

    /**
     * The module class only (class … extends IPSModule/IPSModuleStrict up to the next class, trait,
     * interface or enum) — helper classes in the same file are not module functions.
     */
    private function moduleClassSource(string $source): string
    {
        if (!preg_match('/^[ 	]*(?:final\s+|abstract\s+)?class\s+\w+\s+extends\s+IPSModule(?:Strict)?\b/m', $source, $m, PREG_OFFSET_CAPTURE)) {
            return $source;
        }
        $start = $m[0][1];
        $rest  = substr($source, $start + strlen($m[0][0]));
        if (preg_match('/^[ 	]*(?:final\s+|abstract\s+|readonly\s+)*(?:class|trait|interface|enum)\s+\w+/m', $rest, $n, PREG_OFFSET_CAPTURE)) {
            return substr($source, $start, strlen($m[0][0]) + $n[0][1]);
        }
        return substr($source, $start);
    }

    /**
     * Status entries, fields and captions of a form built in PHP code. Reads the innermost array
     * literals: an array with 'code' and 'caption' is a status entry ('code' as number or self::CONST),
     * an array with 'name' and 'type' is a field; every 'caption'/'label' literal counts as form text.
     *
     * @return array{status: array<int, array{caption:string, line:int}>, fields: list<array{name:string, type:string, line:int}>, text: string}
     */
    private function parseCodeForm(string $classSource): array
    {
        $constants = [];
        if (preg_match_all('/const\s+(?:int\s+)?(\w+)\s*=\s*(\d+)\s*;/', $classSource, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $constants[$hit[1]] = (int)$hit[2];
            }
        }
        $literal = '\'((?:[^\'\\\\]|\\\\.)*)\'';
        $result  = ['status' => [], 'fields' => [], 'labels' => [], 'text' => ''];
        preg_match_all('/\[[^\[\]]*\]/', $classSource, $arrays, PREG_OFFSET_CAPTURE);
        foreach ($arrays[0] as [$array, $offset]) {
            $line = substr_count(substr($classSource, 0, $offset), "\n") + 1;
            $caption = preg_match("/'caption'\s*=>\s*$literal/", $array, $c) ? stripcslashes($c[1]) : null;
            if ($caption !== null && preg_match('/\'code\'\s*=>\s*(?:(\d+)|(?:self|static)::(\w+))/', $array, $code)) {
                $value = $code[1] !== '' ? (int)$code[1] : ($constants[$code[2]] ?? null);
                if ($value !== null) {
                    $result['status'][$value] = ['caption' => $caption, 'line' => $line];
                }
            }
            if (preg_match("/'name'\s*=>\s*$literal/", $array, $name) && preg_match("/'type'\s*=>\s*$literal/", $array, $type)) {
                $result['fields'][] = ['name' => $name[1], 'type' => $type[1], 'line' => $line];
            }
            if ($caption !== null && preg_match("/'type'\s*=>\s*'Label'/", $array)) {
                $result['labels'][] = ['caption' => $caption, 'hidden' => (bool)preg_match("/'visible'\s*=>\s*false/", $array), 'line' => $line];
            }
        }
        if (preg_match_all("/'(?:caption|label|onClick)'\s*=>\s*$literal/", $classSource, $texts)) {
            $result['text'] = implode("\n", array_map('stripcslashes', $texts[1]));
        }
        return $result;
    }

    // ---- Rule 3/16: every status code the module sets is declared in form.json with a text ----------

    private function checkStatusCodes(string $directory, string $source, ?array $form, ?array $locale, ?array $codeForm): void
    {
        $declared = [];
        foreach (($codeForm['status'] ?? []) as $code => $status) {
            $declared[$code] = $status['caption'];
            if ($code >= 200 && trim($status['caption']) === '') {
                $this->add('status', 'error', $directory . '/module.php', $status['line'], 'status_without_caption', [$code]);
            }
            if ($code >= 200 && trim($status['caption']) !== '' && is_array($locale) && !$this->isTranslated($status['caption'], $locale)) {
                $this->add('status', 'warning', $directory . '/locale.json', 0, 'status_not_translated', [$code, $status['caption']]);
            }
        }
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

    private function checkSecrets(string $directory, ?array $form, ?array $codeForm): void
    {
        foreach (($codeForm['fields'] ?? []) as $field) {
            if (preg_match(self::SECRET_NAME_PATTERN, $field['name']) && in_array($field['type'], ['ValidationTextBox', 'TextBox', 'NumberSpinner'], true)) {
                $this->add('secrets', 'warning', $directory . '/module.php', $field['line'], 'secret_not_password_field', [$field['name'], $field['type']]);
            }
        }
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

    private function checkFunctionHints(string $directory, array $publicFunctions, ?array $form, string $prefix, string $source, ?array $codeForm): void
    {
        $formText = $form === null ? '' : (string)json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Captions in code: only the full name counts — every caption of the module is collected there,
        // a plain word like "Power" or "Play" would otherwise hide a missing hint.
        $codeText = $codeForm['text'] ?? '';
        foreach ($publicFunctions as $name => $function) {
            // RunSelfTest needs no hint: its fixed name is what makes it discoverable (rule 15).
            if (in_array($name, self::KERNEL_METHODS, true) || $name === 'RunSelfTest' || $this->isTimerTarget($source, $prefix, $name)) {
                continue;
            }
            $mentioned = str_contains($formText, $prefix . '_' . $name) || preg_match('/\b' . preg_quote($name, '/') . '\b/', $formText)
                || ($prefix !== '' && preg_match('/\b' . preg_quote($prefix . '_' . $name, '/') . '\b/', $codeText));
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
    private function findPublicFunctions(string $source, int $lineOffset = 0): array
    {
        $result = [];
        if (!preg_match_all('/^[ 	]*public\s+(?:static\s+)?function\s+(\w+)\s*\(([^)]*)\)\s*(?::\s*([?\w|]+))?/m', $source, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $result;
        }
        foreach ($m as $hit) {
            if (str_starts_with($hit[1][0], '__')) {
                continue; // magic methods (__construct, __destruct …) are PHP, not module functions
            }
            $params = [];
            if (preg_match_all('/\$(\w+)/', $hit[2][0], $p)) {
                $params = $p[1];
            }
            $result[$hit[1][0]] = [
                'params' => $params,
                'return' => isset($hit[3]) ? (string)$hit[3][0] : '',
                'line'   => $lineOffset + substr_count(substr($source, 0, $hit[0][1]), "\n") + 1,
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
