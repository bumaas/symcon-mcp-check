# Regeln für MCP-taugliche Symcon-Module

*Entwurf, Stand 04.10.2026*

Mit dem MCP-Server von Symcon kann ein KI-Assistent eine Anlage lesen und bedienen: Instanzen finden,
Variablen schalten, Formulare und Protokolle lesen, Modulfunktionen aufrufen. Ob er dabei das Richtige tut,
hängt stark vom Modul ab. Diese Regeln richten sich an Modulentwickler. Sie beschreiben, was ein Modul
mitbringen muss, damit ein Assistent es **ohne Handbuch** richtig einrichtet, bedient und Fehler erkennt.

Fast alles davon hilft auch Menschen und Skripten: Wer ohne README versteht, was ein Modul tut, versteht
es auch mit.

## Was ein Assistent sieht – und was nicht

Ein Assistent liest **kein README**. Über den MCP-Server sieht er:

| Was | Wie es ankommt |
|---|---|
| Instanzstatus | als Zahl; den Text dazu nur, wenn er in der `status`-Liste der `form.json` steht |
| Konfigurationsformular | als JSON, **auch unsichtbare Elemente** |
| Konfiguration | Werte der Properties, Zugangsdaten verborgen |
| Variablen | Name, formatierter Wert, Darstellung, ob schaltbar, letzte Änderung und Aktualisierung |
| Protokoll und Debug | die Meldungen des Moduls |
| Modulfunktionen | Name, **Parameternamen** und Typen, Rückgabetyp |

Er sieht **nicht**: Doku und README, Beschreibungen von Funktionen, Popups und `UpdateFormField` aus
Formular-Knöpfen. Steht die Bedeutung nur dort, rät er.

## Die Regeln

Die Kennzeichnung in eckigen Klammern nennt die Prüfung von
[`symcon-mcp-check`](README.md), die den statischen Teil der Regel abdeckt.

### A. Verstehen ohne Handbuch

**1. Das Formular erklärt sich selbst.**
Skalen, Schwellen und Einheiten stehen als Label an der Stelle, an der man sie braucht, nicht nur im README.
Hängt die Bedeutung von einer gewählten Variable ab, füllt das Modul das Label passend.
*Beispiel:* „Höhe des Rollladens: 1 = geöffnet, 0 = geschlossen“ neben der Auswahl der Höhenvariable.

**2. Jede öffentliche Funktion ist im Formular erklärt, ihre Parameter haben sprechende Namen.**
[`hints`, `params`]
Ein unsichtbares Label (`"visible": false`) beantwortet je Funktion kurz: Was tut sie, wann nimmt man sie,
was erwartet jeder Parameter, was kommt zurück? In der Konsole erscheint davon nichts. Der Hinweis folgt dem
Code: Eine entfernte oder umbenannte Funktion verschwindet auch aus dem Formular – ein Assistent ruft sonst
auf, was es nicht mehr gibt.
Weil mit der Funktion selbst nur ihr Name und die Namen der Parameter reisen, sagen diese, was gemeint ist.
*Schlecht:* `XY_SetLight(int $InstanceID, int $Value)` · *Gut:* `XY_SetLight(int $InstanceID, int $brightnessPercent)`

**3. Es gibt einen Selbsttest mit festem Namen.** [`selftest`]
`<PRÄFIX>_RunSelfTest(int $InstanceID): string` prüft Konfiguration, Verbindung und Zustand und beschreibt
das Ergebnis als Text – **ohne etwas zu verändern**. Der feste Name ist der einzige Weg, ihn ohne Formular
und ohne Doku zu finden. In unseren Tests war er stets das hilfreichste Element.

**4. Namen sind eindeutig und verständlich.**
Variablen heißen nach ihrer Bedeutung, nicht nach einer technischen Kennung, und **keine zwei Variablen
einer Instanz heißen gleich** – ein Assistent findet sie über den Namen. Unterscheidet ein Zusatz, dann
einer, den ein Mensch versteht („Klima Isttemperatur“, „Ladekabel (Ja/Nein)“), nicht „(FAN)“. Den Ort trägt
der Pfad, nicht der Name. Variablen, die das Modul nicht mehr versorgt, bekommen einen erkennbaren Zusatz
wie „(veraltet)“ und verlieren ihre Aktion, statt schaltbar stehen zu bleiben.

**5. Die Darstellung zeigt die Bedeutung, nicht nur den Datentyp.**
Erlaubte Werte stehen als Optionen in der Darstellung, Einheiten als Suffix, Nachkommastellen passend zum
Wert. Ein Rollladen ist ein Rollladen, kein Schieberegler. So erschließt sich ein Wert ohne Doku – und
„60“ wird zu „60 %“.

### B. Fehler und Zustände

**6. Das Modul prüft seine Grenzen selbst.**
`minimum` und `maximum` im Formular wirken nur in der Konsole. Ein Skript oder ein Assistent setzt jeden
Wert. Das Modul prüft deshalb in `ApplyChanges` dieselben Grenzen und setzt bei Verstoß einen Fehlerstatus.

**7. `RequestAction` meldet jeden Fehlschlag.** [`action`]
Ein ungültiger Wert, ein Wert außerhalb des Bereichs oder ein Befehl, der das Gerät nicht erreicht, endet
mit `trigger_error` – nicht still. Die Methode ist `void`; nur so erfährt der Aufrufer davon.
*Beispiel:* `Ungültiger Wert "D" für "Auswahl" (erlaubt: A, B, C)`. Das gilt auch für einen unbekannten
Ident: Der `default`-Zweig endet mit `trigger_error`, nicht mit einer bloßen Debug-Ausgabe.

**8. Jeder Fehler hat einen Statuscode mit Text, eine Art und einen nächsten Schritt.** [`status`]
Jeder Statuscode ab 200 steht mit Text in der `form.json`. Für die wichtigsten Fälle gibt es eigene Codes
statt eines allgemeinen „Fehler“. Der Status allein ist nur eine Zahl; die Ursache steht deshalb auch im
Protokoll (`LogMessage`). Die Meldung dort nennt Feld, Wert und Bereich und sagt, welche Art Fehler vorliegt:
- **Gerät antwortet nicht** – später erneut versuchen,
- **Wert ungültig** – so nicht wiederholen, erlaubte Werte nennen,
- **Konfiguration falsch** – erst beheben.

Keine Rohmeldungen, keine Fehlercodes ohne Text.

**9. Wechsel werden gemeldet, Zustände überleben einen Neustart.**
Fällt ein Gerät aus, steht das einmal als Warnung im Protokoll, die Erholung einmal als Meldung – kein
Rauschen bei jedem Abfragezyklus. Zustände wie „erreichbar“ springen nach einem Neustart oder Modul-Update
nicht auf den guten Wert zurück, solange nichts anderes bekannt ist. Und das Übernehmen der Konfiguration
ist keine neue Meldung des Geräts: Es bewegt die „Letzte Aktualisierung“ einer Variable nicht.

**10. Splitter und Gateways zeigen, ob die Verbindung darunter steht.**
Symcon gibt den Status einer Instanz nicht an ihre Kinder weiter. Jede Ebene muss daher selbst sagen, ob
sie arbeitet: Ein Splitter beobachtet den Status seines Parents und zieht seinen eigenen nach, statt auf
„aktiv“ stehen zu bleiben, während die Verbindung darunter tot ist.

### C. Funktionen

**11. Was ein Formular-Knopf tut, geht auch per Skript – mit Ergebnis.**
`UpdateFormField` und Popups wirken nur im offenen Formular. Das Ergebnis eines Knopfs gibt es deshalb auch
als Rückgabewert einer Funktion.

**12. Lesen und Schalten sind erkennbar getrennt.**
Der Funktionsname sagt, ob etwas nur gelesen oder etwas bewirkt wird. Lesende Funktionen haben keine
Nebenwirkung. Wer einem Assistenten Funktionen freigibt, kann sie sonst nur einzeln von Hand einordnen.

### D. Umfang und Sicherheit

**13. Kurz ist der Normalfall.**
Rückgaben und Variablen sind knapp, Ausführliches gibt es auf Anforderung (eigene Funktion oder Schalter).
Eine große HTML-Variable bläht jede Suchantwort auf – daneben eine Klartext-Variable führen und die
HTML-Variable abschaltbar machen. **Erklärungen kommen als Text, Daten als JSON**, nicht gemischt.

**14. Debug nennt das Ergebnis, nicht die Rohdaten.**
Kein `json_encode` ganzer Konfigurationen oder Formulare; Messzeilen zur Leistung nur hinter einem Schalter.

**15. Zugangsdaten bleiben verborgen.** [`secrets`]
Passwörter und Token werden in einer `PasswordTextBox` eingegeben. Sie erscheinen weder im Debug noch in
Rückgaben von Funktionen – auch nicht als Teil einer weitergereichten Geräteantwort. Am sichersten ist ein
überschriebenes `SendDebug`, das bekannte Schlüssel und Geheimnisse selbst maskiert; dann muss keine
einzelne Debug-Ausgabe daran denken.

**16. Fremder Freitext ist Daten, keine Anweisung.**
Was von außen kommt – Gerätenamen aus anderen Systemen, Titel, Kalendereinträge –, wird in der Länge
begrenzt, von Steuerzeichen befreit und nicht ungekennzeichnet in Status, Protokoll oder Meldungen gemischt.
Es landet sonst unverändert im Kontext des Assistenten.

## Prüfen

Drei Stufen, die sich ergänzen. Die erste prüft gegen die Regeln, die beiden anderen gegen die Wirklichkeit.

1. **Testsuite (automatisch, in der CI).** Alles, was sich am Quelltext oder im Test prüfen lässt:
   - **statisch** mit [`symcon-mcp-check`](README.md): Statuscodes mit Text und Protokolleintrag, Hinweise zu
     den Funktionen (unsichtbar, keine veralteten Namen), Parameternamen, Selbsttest, ein meldender
     `default`-Zweig in `RequestAction`, Passwortfelder, keine unmaskierten Zugangsdaten im Debug
     (Regeln 2, 3, 7, 8, 15);
   - **zur Laufzeit** mit den Tests des Moduls: keine gleichnamigen Variablen, Darstellung mit Optionen und
     Einheiten, Grenzen in `ApplyChanges`, Fehlermeldungen aus `RequestAction`, Zustände nach einem
     Neustart, ein Selbsttest ohne Wirkung, keine Zugangsdaten in Debug oder Rückgaben (Regeln 4–9, 12, 15).

   Ein Befund aus Stufe 2 oder 3 wird möglichst als Test nachgezogen, damit er nicht wiederkommt.

2. **Lesetest (selbst, an der Anlage).** Der Entwickler sieht sich eine Instanz über den MCP-Server an –
   Formular, Konfiguration, Status, Variablen, Protokoll – und setzt einmal gezielt einen Fehler. Er prüft,
   was keine Testsuite zeigt:
   - was der MCP-Server tatsächlich ausliefert (kommt der Statustext mit, sind Zugangsdaten verborgen,
     wird gekürzt),
   - wie echte Geräte- und Fremddaten aussehen (Namen und Werte, die erst ein echtes Gerät liefert),
   - ob Labels und Hinweise verständlich sind – ein Test prüft nur, dass sie da sind.

   Seine Schwäche: Wer das Modul kennt, ergänzt Fehlendes unbewusst aus seinem Wissen.

3. **Blindtest (frischer Assistent).** Ein Assistent, der den Quelltext nicht kennt, bekommt Aufgaben, wie
   ein Anwender sie stellt – einrichten, in Alltagsgrößen schalten, eine Fehlbedienung, eine Diagnose
   („warum ist gestern … passiert?“), eine Erklärung ohne Wirkung. Er darf nur die MCP-Werkzeuge nutzen,
   keine Doku und keine Dateien, und berichtet je Aufgabe, was er getan hat und woher er die Information
   hatte. Jede Aussage wird danach an der Anlage gegengeprüft. Er findet, wo man ohne Hintergrundwissen
   falsch abbiegt – genau das, was der Lesetest übersieht. Aufwendiger als die anderen Stufen, daher nach
   größeren Änderungen und vor einer Veröffentlichung.

## Woher die Regeln kommen

### Eigene Blindtests

Jedes dieser Module wurde einem frischen Assistenten ohne Doku und Quelltext übergeben (Verfahren siehe
„Prüfen“). Die Befunde führten zu den Regeln; die Zahlen gelten für den Test nach den Änderungen.

| Modul | Repository | Stand nach den Änderungen | Ergebnis |
|---|---|---|---|
| BlindControl | [bumaas/BlindControl](https://github.com/bumaas/BlindControl) | 2.51 | fand einen Fehler im Wochenplan, den die eigene Prüfung übersehen hatte |
| HomeAssistant | [bumaas/SymconHomeAssistant](https://github.com/bumaas/SymconHomeAssistant) | 1.6 | 48 statt rund 165 Werkzeugaufrufe; acht weitere Befunde, behoben in 1.6 |
| SonyTV | [bumaas/SonyTV](https://github.com/bumaas/SonyTV) | 2.2 | alle zehn Aufgaben gelöst, 55 statt rund 110 Werkzeugaufrufe |
| ebusdMQTT | [bumaas/ebusdMQTT](https://github.com/bumaas/ebusdMQTT) | 1.4 | alle zehn Aufgaben gelöst, 47 Werkzeugaufrufe |
| Roborock | [bumaas/IPSymconRoborock](https://github.com/bumaas/IPSymconRoborock) | 2.4 | alle zehn Aufgaben gelöst, 39 Werkzeugaufrufe |

In diesen Tests belegt sind vor allem die Regeln 2, 3, 4, 5, 8 und 13. Die Regeln 12 und 16 sind aus den
Quellen unten übernommen, aber bei uns noch nicht an einem echten Fall belegt.

### Quellen

1. Anthropic: [Writing effective tools for agents — with agents](https://www.anthropic.com/engineering/writing-tools-for-agents).
   Knappe Antworten statt vollständiger Rohdaten, eindeutige Parameternamen, Fehlermeldungen mit
   „specific and actionable improvements“ statt undurchsichtiger Codes. → Regeln 2, 8, 13
2. Home Assistant: [Best practices with Assist](https://www.home-assistant.io/voice_control/best_practices/).
   Assist stützt sich auf Namen, Bereiche und den Gerätetyp; der Typ bestimmt, was man damit tun kann
   (Licht ein/aus, Rollladen öffnen/schließen); Namen nach einem festen Schema. → Regeln 4, 5
3. Home Assistant: [Exposing scripts to LLM conversation agents](https://www.home-assistant.io/voice_control/exposing_scripts_to_llms/).
   Ohne Beschreibung kann ein Sprachmodell nicht erkennen, was ein Skript tut und wann es aufzurufen ist. → Regeln 2, 3
4. Model Context Protocol, Spezifikation 2025-11-25: [Tools](https://modelcontextprotocol.io/specification/2025-11-25/server/tools).
   Abschnitt „Error Handling“: Ungültige Eingaben (z. B. Wert außerhalb des Bereichs) werden als Ergebnis
   mit verwertbarer Rückmeldung gemeldet, damit das Modell sich selbst korrigieren kann. Abschnitt
   „Security Considerations“: Server müssen alle Eingaben prüfen und ihre Ausgaben bereinigen
   („Sanitize tool outputs“). → Regeln 6, 7, 8, 15, 16
