# v1.2.0
## 09.11.2026

1. [](#improved)
    * **Die Beschriftungen im Admin sprechen vier Sprachen.** 25 Beschriftungen, Hilfetexte, Platzhalter und Auswahlwerte standen fest verdrahtet auf Deutsch in den Blueprints; jetzt stehen sie als Sprachmarken in `languages/de|en|fr|it.yaml`. Auf einer deutschen Installation aendert sich nichts Sichtbares.
    * Die Angaben am Dateianfang (Name, Beschreibung, Autor) bleiben bewusst Text: Das Grav-Verzeichnis liest sie woertlich, eine Sprachmarke stuende dort im Katalog.

# v1.1.1
## 09.11.2026

1. [](#improved)
    * **Fuer die Anmeldung bei getgrav.org vorbereitet.** Beschreibung, Schlagworte und README auf Englisch -- das Verzeichnis wird international gelesen. Die deutsche Fassung steht als `README.de.md` daneben. `bugs` und `docs` zeigen auf das kuenftige oeffentliche Repository.

# v1.1.0
## 04.11.2026

1. [](#new)
    * **Newsletter-Anmeldung geschützt.** Köderfeld und Zeitstempel kommen jetzt auch in jedes Formular, dessen Aktion auf `/subscribe` endet (cw-newsletter), ohne Änderung am Theme. cw-newsletter ≥ 1.25.0 prüft darüber Köderfeld, Mindestzeit und Takt je Absender (`CwSpamschutzPlugin::pruefeEinsendung`). Anlass: Masseneintragungen erfundener Adressen @bundestag.de auf regio-huttwil.ch, 2.–4.11.2026 – jede löste eine Bestätigungsmail und einen Rückläufer aus.

# v1.0.1
## 07.10.2026

1. [](#bugfix)
    * Meldungen erschienen auf Englisch, wenn die Website keine Sprachen unter `languages.supported` führt (chweb.ch). Jetzt gilt dann die Standardsprache der Website, zuletzt Deutsch.

# v1.0.0
## 06.10.2026

1. [](#new)
    * Erste Fassung. Anlass: Kontaktformular von regio-huttwil.ch, rund 30 Bot-Nachrichten in 30 Minuten (russischer Text, Link auf eine .top-Domain).
    * Schützt alle Formulare des form-Plugins automatisch, ohne Änderung an den Formularseiten: unsichtbares Köderfeld, signierter Zeitstempel mit Mindestzeit (4 s) und Höchstalter (24 h), Inhaltsregeln (mehr als 2 Links, Nachricht in fremder Schrift, fremde Schrift mit Link, Sperrwörter, Spam-Domainendungen), Begrenzung je Absender (5 in 10 Minuten, IP nur als täglich wechselnder Hash im Cache).
    * Abweisung mit neutraler Meldung und Ausweg für Menschen; Zählung je Tag und Grund in `user-data://cw-spamschutz/statistik.json`, ohne Inhalte und ohne IP-Adressen.
