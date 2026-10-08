<?php

declare(strict_types=1);

/** Finding texts in English (default) and German. Placeholders: %1$s, %2$s … in argument order. */
final class Messages
{
    private const array TEXTS = [
        'no_library' => [
            'en' => 'No library.json found, this is not a Symcon module library.',
            'de' => 'Keine library.json gefunden, das ist keine Symcon-Modulbibliothek.',
        ],
        'invalid_json' => [
            'en' => 'File is not valid JSON.',
            'de' => 'Datei ist kein gültiges JSON.',
        ],
        'status_not_declared' => [
            'en' => 'Status %1$s is set but not declared in the "status" list of the configuration form. An AI only sees the number.',
            'de' => 'Status %1$s wird gesetzt, steht aber nicht in der "status"-Liste des Konfigurationsformulars. Eine KI sieht nur die Zahl.',
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
            'en' => 'Field "%1$s" looks like a credential but is a %2$s, so the console shows it in plain text. Use a PasswordTextBox. (The MCP server hides it by its name, which is undocumented.)',
            'de' => 'Feld "%1$s" sieht nach Zugangsdaten aus, ist aber eine %2$s, die Konsole zeigt es also im Klartext. Besser eine PasswordTextBox verwenden. (Der MCP-Server verbirgt es am Namen, das ist nicht dokumentiert.)',
        ],
        'no_selftest' => [
            'en' => 'No self test %1$s(): string. By this common name an AI finds a side-effect-free check without any docs.',
            'de' => 'Kein Selbsttest %1$s(): string. Über diesen gemeinsamen Namen findet eine KI eine Prüfung ohne Nebenwirkung auch ohne Doku.',
        ],
        'selftest_signature' => [
            'en' => 'RunSelfTest should take no parameters and return string.',
            'de' => 'RunSelfTest sollte keine Parameter haben und string liefern.',
        ],
        'function_without_hint' => [
            'en' => 'Public function %1$s is not mentioned in form.json. Add a (hidden) hint saying what it does, when to use it, what the parameters expect and what it returns.',
            'de' => 'Öffentliche Funktion %1$s kommt in der form.json nicht vor. Ein (unsichtbarer) Hinweis sollte sagen, was sie tut, wann man sie nimmt, was die Parameter erwarten und was zurückkommt.',
        ],
        'generic_parameter' => [
            'en' => '%1$s: parameter %2$s says nothing about what is expected. Only the parameter names travel with the function.',
            'de' => '%1$s: Parameter %2$s sagt nicht, was erwartet wird. Mit der Funktion reisen nur die Parameternamen.',
        ],
        'hint_visible' => [
            'en' => 'Hint "%1$s…" is shown in the console. Hints for scripts and AI assistants belong in a label with "visible": false.',
            'de' => 'Hinweis „%1$s…“ erscheint in der Konsole. Hinweise für Skripte und KI-Assistenten gehören in ein Label mit "visible": false.',
        ],
        'hint_stale' => [
            'en' => 'The form names %1$s, but there is no such public function. A hint or button refers to a removed or renamed function.',
            'de' => 'Das Formular nennt %1$s, eine solche öffentliche Funktion gibt es aber nicht. Ein Hinweis oder Knopf verweist auf eine entfernte oder umbenannte Funktion.',
        ],
        'requestaction_silent_default' => [
            'en' => 'RequestAction of %1$s: the default branch of the switch reports nothing (no trigger_error or throw), so an unknown ident fails silently for scripts and AI assistants.',
            'de' => 'RequestAction von %1$s: Der default-Zweig des switch meldet nichts (kein trigger_error oder throw), ein unbekannter Ident scheitert also still für Skripte und KI-Assistenten.',
        ],
        'status_not_logged' => [
            'en' => 'Module %1$s sets error states but never writes to the log (LogMessage), so an AI only sees the number. Log the cause and the next step once per change.',
            'de' => 'Modul %1$s setzt Fehlerstatus, schreibt aber nie ins Log (LogMessage), eine KI sieht also nur die Zahl. Ursache und nächsten Schritt einmal je Wechsel loggen.',
        ],
        'debug_secret' => [
            'en' => 'Debug output contains %1$s. Every AI with read access sees the debug output through the MCP server, so mask credentials (e.g. by overriding SendDebug).',
            'de' => 'Debug-Ausgabe enthält %1$s. Jede KI mit Lesezugriff sieht das Debug über den MCP-Server, Zugangsdaten deshalb maskieren (z. B. durch ein überschriebenes SendDebug).',
        ],
    ];

    public static function format(string $key, array $args, string $language): string
    {
        $text = self::TEXTS[$key][$language] ?? self::TEXTS[$key]['en'] ?? $key;
        return vsprintf($text, array_map('strval', $args));
    }
}
