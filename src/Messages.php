<?php

declare(strict_types=1);

/** Finding texts in English (default) and German. Placeholders: %1$s, %2$s … in argument order. */
final class Messages
{
    private const array TEXTS = [
        'no_library' => [
            'en' => 'No library.json found — not a Symcon module library.',
            'de' => 'Keine library.json gefunden — keine Symcon-Modulbibliothek.',
        ],
        'invalid_json' => [
            'en' => 'File is not valid JSON.',
            'de' => 'Datei ist kein gültiges JSON.',
        ],
        'status_not_declared' => [
            'en' => 'Status %1$s is set but not declared in the "status" list of the configuration form — an AI only sees the number.',
            'de' => 'Status %1$s wird gesetzt, steht aber nicht in der "status"-Liste des Konfigurationsformulars — eine KI sieht nur die Zahl.',
        ],
        'status_without_caption' => [
            'en' => 'Status %1$s has no caption.',
            'de' => 'Status %1$s hat keinen Text.',
        ],
        'status_not_translated' => [
            'en' => 'Caption of status %1$s is missing in locale.json: "%2$s".',
            'de' => 'Text von Status %1$s fehlt in der locale.json: "%2$s".',
        ],
        'secret_not_password_field' => [
            'en' => 'Field "%1$s" looks like a credential but is a %2$s — the console shows it in plain text; use a PasswordTextBox. (The MCP server masks it by name, which is undocumented.)',
            'de' => 'Feld "%1$s" sieht nach Zugangsdaten aus, ist aber eine %2$s — die Konsole zeigt es im Klartext; PasswordTextBox verwenden. (Der MCP-Server verbirgt es am Namen, das ist nicht dokumentiert.)',
        ],
        'no_selftest' => [
            'en' => 'No self test %1$s(): string — the common name lets an AI find a side-effect-free check without docs.',
            'de' => 'Kein Selbsttest %1$s(): string — über den gemeinsamen Namen findet eine KI eine Prüfung ohne Nebenwirkung auch ohne Doku.',
        ],
        'selftest_signature' => [
            'en' => 'RunSelfTest should take no parameters and return string.',
            'de' => 'RunSelfTest sollte keine Parameter haben und string liefern.',
        ],
        'function_without_hint' => [
            'en' => 'Public function %1$s is not mentioned in form.json — add a (hidden) hint: what it does, when to use it, what the parameters expect, what it returns.',
            'de' => 'Öffentliche Funktion %1$s kommt in der form.json nicht vor — (unsichtbaren) Hinweis ergänzen: was sie tut, wann man sie nimmt, was die Parameter erwarten, was zurückkommt.',
        ],
        'generic_parameter' => [
            'en' => '%1$s: parameter %2$s says nothing about what is expected — only parameter names travel with the function.',
            'de' => '%1$s: Parameter %2$s sagt nicht, was erwartet wird — mit der Funktion reisen nur die Parameternamen.',
        ],
        'hint_visible' => [
            'en' => 'Hint "%1$s…" is shown in the console — hints for scripts and AI assistants belong in a label with "visible": false.',
            'de' => 'Hinweis „%1$s…“ erscheint in der Konsole — Hinweise für Skripte und KI-Assistenten gehören in ein Label mit "visible": false.',
        ],
        'hint_stale' => [
            'en' => 'The form names %1$s, but there is no such public function — a hint or button refers to a removed or renamed function.',
            'de' => 'Das Formular nennt %1$s, eine solche öffentliche Funktion gibt es aber nicht — ein Hinweis oder Knopf verweist auf eine entfernte oder umbenannte Funktion.',
        ],
        'requestaction_silent_default' => [
            'en' => 'RequestAction of %1$s: the default branch of the switch reports nothing (no trigger_error/throw) — an unknown ident fails silently for scripts and AI assistants.',
            'de' => 'RequestAction von %1$s: Der default-Zweig des switch meldet nichts (kein trigger_error/throw) — ein unbekannter Ident scheitert für Skripte und KI-Assistenten still.',
        ],
        'status_not_logged' => [
            'en' => 'Module %1$s sets error states but never writes to the log (LogMessage) — an AI only sees the number; log the cause and the next step once per change.',
            'de' => 'Modul %1$s setzt Fehlerstatus, schreibt aber nie ins Log (LogMessage) — eine KI sieht nur die Zahl; Ursache und nächsten Schritt einmal je Wechsel loggen.',
        ],
        'debug_secret' => [
            'en' => 'Debug output contains %1$s — every AI with read access sees the debug through the MCP server; mask credentials (e.g. by overriding SendDebug).',
            'de' => 'Debug-Ausgabe enthält %1$s — jede KI mit Lesezugriff sieht das Debug über den MCP-Server; Zugangsdaten maskieren (z. B. durch Überschreiben von SendDebug).',
        ],
    ];

    public static function format(string $key, array $args, string $language): string
    {
        $text = self::TEXTS[$key][$language] ?? self::TEXTS[$key]['en'] ?? $key;
        return vsprintf($text, array_map('strval', $args));
    }
}
