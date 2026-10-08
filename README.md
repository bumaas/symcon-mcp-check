# symcon-mcp-check

Static checks whether a [Symcon](https://www.symcon.de) module library can be used well by AI assistants
through the Symcon MCP server. No running Symcon installation is needed.

*Deutsche Fassung weiter unten.*

## Why

An AI assistant working through the Symcon MCP server does not read a module's README. It sees status codes
(as numbers), the configuration form, variable names and presentations, function names and the **names** of
their parameters. If a module's meaning lives only in its documentation, the assistant guesses. These checks
catch the gaps that can be found in the source.

The full set of rules for MCP-ready modules, with reasons, examples and sources, is in
[RULES.de.md](RULES.de.md) (German). This action covers the part that can be checked statically.

## Rules

| Rule | Severity | Checks |
|---|---|---|
| `status` | error / warning | Every status code ≥ 200 the module sets (`SetStatus(201)`, `self::STATUS_…`, also via a variable) is declared in `form.json` → `status` with a caption; the caption is translated in `locale.json` (warning). A module that sets such codes also writes the cause to the log (`LogMessage`, directly or in a trait; warning). |
| `secrets` | warning | Form fields that look like credentials (`password`, `token`, `secret`, `apikey` …) are `PasswordTextBox`, not plain text boxes. `SendDebug` does not print variables like `$password` or `$token` unless the module overrides `SendDebug` (directly or in a trait) to mask them. |
| `action` | warning | The `default` branch of the `switch` in `RequestAction` reports an unknown ident (`trigger_error`/`throw`) instead of failing silently. |
| `selftest` | warning | Device, splitter and I/O modules offer `<PREFIX>_RunSelfTest(): string`, a side-effect-free check an assistant can find by its fixed name. |
| `hints` | error / warning | Every public function (except kernel callbacks, `RunSelfTest` and timer targets) is mentioned in `form.json` by its full name `PREFIX_Name`, e.g. in a hidden label saying what it does, when to use it, what the parameters expect and what it returns (warning). A caption that merely contains the plain name does not count. A hint for scripts or AI assistants (“For scripts …”, “AI assistant”) is invisible (`"visible": false`, error). The form calls or names no `PREFIX_Function` that the library no longer has (error). A property named with the prefix does not count here, only `PREFIX_Name(` or a name inside such a hint. |
| `params` | warning | Public functions have meaningful parameter names (`$brightnessPercent`, not `$Value`), because only the names travel with the function. |

Forms built in PHP code (an own `GetConfigurationForm()` in `module.php`) are read as well: status entries, fields and captions come from the array literals of the module class. Only the module class (`extends IPSModule`/`IPSModuleStrict`) counts for public functions, helper classes in the same file do not.

Behaviour (invalid values rejected with an error, state surviving a reload, variables named and presented
unambiguously) cannot be checked statically; test it in the module's own runtime tests.

## Getting started

There is nothing to install. The check is a GitHub Action that your module repository calls on every push.

1. In your module repository, create the file `.github/workflows/mcp-check.yml` with this content:

   ```yaml
   name: MCP check

   on: [push, pull_request]

   jobs:
     mcp-check:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v7
         - uses: shivammathur/setup-php@v2
           with:
             php-version: '8.4'
         - uses: bumaas/symcon-mcp-check@v1
   ```

2. Commit and push. On GitHub, the **Actions** tab of your repository now shows a run named “MCP check”.
3. Open the run. Every finding is listed in the log and as an annotation, with file and line. Annotations
   also show up in the “Files changed” view of a pull request.

The run fails on errors and passes with warnings. Fix the errors first, then work through the warnings.
If you already have a workflow that sets up PHP (8.3 or newer), add only the last step to it.

Options (all optional):

```yaml
- uses: bumaas/symcon-mcp-check@v1
  with:
    path: .          # folder with library.json, if it is not the repository root
    language: de     # findings in German (default en)
    fail-on: warning # fail on warnings too (default error)
```

`@v1` always points to the latest 1.x release, so new rules arrive without changing your workflow. Pin a
version (`@v1.3.0`) if you want to decide yourself when that happens.

### Running it locally

With PHP 8.3 or newer on your machine:

```
git clone https://github.com/bumaas/symcon-mcp-check.git
php symcon-mcp-check/check_mcp.php <path to your library> --lang=en
```

Exit code 0 = passed, 1 = findings at or above `--fail-on`, 2 = usage error.

## Badge

Show the result of your workflow, not a claim:

```markdown
[![MCP check](https://github.com/<owner>/<repo>/actions/workflows/<workflow>.yml/badge.svg)](https://github.com/<owner>/<repo>/actions/workflows/<workflow>.yml)
```

“MCP check” is not an official Symcon label. It says that the static rules above pass. Whether an assistant
really copes with the module is a different question. Document manual tests (e.g. a blind test with a fresh assistant) with date
and version in the module README.

## License

MIT, see [LICENSE](LICENSE).

---

## Deutsch

Statische Prüfung, ob eine Symcon-Modulbibliothek für KI-Assistenten über den Symcon-MCP-Server gut
bedienbar ist. Eine laufende Symcon-Installation braucht es dafür nicht.

Ein KI-Assistent liest über den MCP-Server kein README. Er sieht Statuscodes als Zahl, das
Konfigurationsformular, Variablennamen und Darstellungen, Funktionsnamen und die **Namen** ihrer Parameter.
Steht die Bedeutung nur in der Doku, rät er. Das vollständige Regelwerk mit Begründungen, Beispielen und
Quellen steht in [RULES.de.md](RULES.de.md), die Prüfung hier deckt davon den statischen Teil ab:

- **status:** Jeder gesetzte Statuscode ≥ 200 steht mit Text in der `status`-Liste der `form.json`, der Text
  ist in der `locale.json` übersetzt.
  Wer solche Codes setzt, schreibt die Ursache auch ins Protokoll (`LogMessage`).
- **secrets:** Zugangsdaten werden in einer `PasswordTextBox` eingegeben und erscheinen nicht unmaskiert im Debug.
- **action:** Der `default`-Zweig in `RequestAction` meldet einen unbekannten Ident, statt still zu scheitern.
- **selftest:** Geräte-, Splitter- und I/O-Module bieten `<PRÄFIX>_RunSelfTest(): string` ohne Nebenwirkung.
- **hints:** Jede öffentliche Funktion kommt mit vollem Namen `PRÄFIX_Name` in der `form.json` vor (z. B. als
  unsichtbarer Hinweis); ein Knopf, dessen Text nur den bloßen Namen enthält, zählt nicht. Hinweise
  für Skripte und Assistenten sind unsichtbar, und das Formular nennt keine Funktion, die es nicht mehr gibt.
- **params:** Parameternamen sagen, was erwartet wird.

Formulare, die im PHP-Code entstehen (eigenes `GetConfigurationForm()` in der `module.php`), werden ebenfalls gelesen: Statuseinträge, Felder und Texte stammen dann aus den Array-Literalen der Modulklasse. Als öffentliche Funktionen zählen nur die der Modulklasse, nicht die von Hilfsklassen in derselben Datei.

### Einbinden

Installiert wird nichts. Die Prüfung ist eine GitHub Action, die dein Modul-Repo bei jedem Push aufruft.

1. Im Modul-Repo die Datei `.github/workflows/mcp-check.yml` anlegen, mit dem Inhalt aus „Getting started“
   oben. Für deutsche Befundtexte unter den letzten Schritt noch `with:` und `language: de` setzen.
2. Committen und pushen. Im Reiter **Actions** des Repos erscheint ein Lauf „MCP check“.
3. Im Lauf stehen alle Befunde mit Datei und Zeile, im Log und als Annotation. In einem Pull Request
   erscheinen sie auch unter „Files changed“.

Fehler lassen den Lauf scheitern, Warnungen nicht. Wer schon einen Workflow mit PHP ab 8.3 hat, hängt dort
nur den letzten Schritt an. Lokal geht es mit `php check_mcp.php <Bibliothek> --lang=de` aus einem Klon
dieses Repos.

Das Badge zeigt das Ergebnis des Workflows; „MCP check“ ist kein offizielles Symcon-Siegel.
Händische Tests (Blindtest mit frischem Assistenten) mit Datum und Version im Modul-README dokumentieren.
