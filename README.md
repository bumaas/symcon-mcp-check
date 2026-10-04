# symcon-mcp-check

Static checks whether a [Symcon](https://www.symcon.de) module library can be used well by AI assistants
through the Symcon MCP server — without a running Symcon installation.

*Deutsche Fassung weiter unten.*

## Why

An AI assistant working through the Symcon MCP server does not read a module's README. It sees status codes
(as numbers), the configuration form, variable names and presentations, function names and the **names** of
their parameters. If a module's meaning lives only in its documentation, the assistant guesses. These checks
catch the gaps that can be found in the source.

## Rules

| Rule | Severity | Checks |
|---|---|---|
| `status` | error / warning | Every status code ≥ 200 the module sets (`SetStatus(201)`, `self::STATUS_…`, also via a variable) is declared in `form.json` → `status` with a caption; the caption is translated in `locale.json` (warning). A module that sets such codes also writes the cause to the log (`LogMessage`, directly or in a trait; warning). |
| `secrets` | warning | Form fields that look like credentials (`password`, `token`, `secret`, `apikey` …) are `PasswordTextBox`, not plain text boxes. `SendDebug` does not print variables like `$password` or `$token` unless the module overrides `SendDebug` (directly or in a trait) to mask them. |
| `action` | warning | The `default` branch of the `switch` in `RequestAction` reports an unknown ident (`trigger_error`/`throw`) instead of failing silently. |
| `selftest` | warning | Device, splitter and I/O modules offer `<PREFIX>_RunSelfTest(): string` — a side-effect-free check an assistant can find by its fixed name. |
| `hints` | error / warning | Every public function (except kernel callbacks, `RunSelfTest` and timer targets) is mentioned in `form.json`, e.g. in a hidden label: what it does, when to use it, what the parameters expect, what it returns (warning). A hint for scripts or AI assistants (“For scripts …”, “AI assistant”) is invisible (`"visible": false`, error). The form calls or names no `PREFIX_Function` that the library no longer has (error) — a property named with the prefix does not count, only `PREFIX_Name(` or a name inside such a hint. |
| `params` | warning | Public functions have meaningful parameter names (`$brightnessPercent`, not `$Value`) — only the names travel with the function. |

Forms built in PHP code (an own `GetConfigurationForm()` in `module.php`) are read as well: status entries, fields and captions come from the array literals of the module class. Only the module class (`extends IPSModule`/`IPSModuleStrict`) counts for public functions — helper classes in the same file do not.

Behaviour (invalid values rejected with an error, state surviving a reload, variables named and presented
unambiguously) cannot be checked statically; test it in the module's own runtime tests.

## Usage

GitHub Actions (PHP ≥ 8.3 must be available, e.g. via `shivammathur/setup-php`):

```yaml
- uses: bumaas/symcon-mcp-check@v1
  with:
    path: .          # folder with library.json
    language: en     # or de
    fail-on: error   # or warning
```

Locally:

```
php check_mcp.php <library path> [--lang=en|de] [--fail-on=error|warning]
```

Exit code 0 = passed, 1 = findings at or above `--fail-on`, 2 = usage error. In GitHub Actions the findings
also appear as annotations.

## Badge

Show the result of your workflow, not a claim:

```markdown
[![MCP check](https://github.com/<owner>/<repo>/actions/workflows/<workflow>.yml/badge.svg)](https://github.com/<owner>/<repo>/actions/workflows/<workflow>.yml)
```

“MCP check” is not an official Symcon label. It says that the static rules above pass — not that an assistant
has been tested against the module. Document manual tests (e.g. a blind test with a fresh assistant) with date
and version in the module README.

## License

MIT — see [LICENSE](LICENSE).

---

## Deutsch

Statische Prüfung, ob eine Symcon-Modulbibliothek für KI-Assistenten über den Symcon-MCP-Server gut
bedienbar ist — ohne laufende Symcon-Installation.

Ein KI-Assistent liest über den MCP-Server kein README. Er sieht Statuscodes als Zahl, das
Konfigurationsformular, Variablennamen und Darstellungen, Funktionsnamen und die **Namen** ihrer Parameter.
Steht die Bedeutung nur in der Doku, rät er. Die Regeln (siehe Tabelle oben):

- **status:** Jeder gesetzte Statuscode ≥ 200 steht mit Text in der `status`-Liste der `form.json`, der Text
  ist in der `locale.json` übersetzt.
  Wer solche Codes setzt, schreibt die Ursache auch ins Protokoll (`LogMessage`).
- **secrets:** Zugangsdaten werden in einer `PasswordTextBox` eingegeben und erscheinen nicht unmaskiert im Debug.
- **action:** Der `default`-Zweig in `RequestAction` meldet einen unbekannten Ident, statt still zu scheitern.
- **selftest:** Geräte-, Splitter- und I/O-Module bieten `<PRÄFIX>_RunSelfTest(): string` ohne Nebenwirkung.
- **hints:** Jede öffentliche Funktion kommt in der `form.json` vor (z. B. als unsichtbarer Hinweis). Hinweise
  für Skripte und Assistenten sind unsichtbar, und das Formular nennt keine Funktion, die es nicht mehr gibt.
- **params:** Parameternamen sagen, was erwartet wird.

Formulare, die im PHP-Code entstehen (eigenes `GetConfigurationForm()` in der `module.php`), werden ebenfalls gelesen: Statuseinträge, Felder und Texte stammen dann aus den Array-Literalen der Modulklasse. Als öffentliche Funktionen zählen nur die der Modulklasse, nicht die von Hilfsklassen in derselben Datei.

Aufruf lokal: `php check_mcp.php <Bibliothek> --lang=de`. In GitHub Actions: `uses: bumaas/symcon-mcp-check@v1`
mit `language: de`. Das Badge zeigt das Ergebnis des Workflows; „MCP check" ist kein offizielles Symcon-Siegel.
Händische Tests (Blindtest mit frischem Assistenten) mit Datum und Version im Modul-README dokumentieren.
