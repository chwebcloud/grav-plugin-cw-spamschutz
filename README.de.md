# CHWEB-Spamschutz (cw-spamschutz)

Spamschutz für **alle Formulare des form-Plugins** einer Grav-Site. Kein
Captcha, keine Cookies, kein Drittdienst – echte Besucherinnen und Besucher
merken nichts, und an den Formularseiten muss nichts geändert werden.

## Schichten

1. **Köderfeld** – unsichtbares Eingabefeld (`cw_hp_feld`), das nur Bots ausfüllen.
2. **Mindestzeit** – signierter Zeitstempel (`cw_hp_zeit`) beim Anzeigen; Absenden
   schneller als 4 s oder nach mehr als 24 h wird abgewiesen, ebenso ein Formular,
   das gar nicht geladen wurde.
3. **Inhaltsregeln** – mehr als 2 Links, Nachricht überwiegend in fremder Schrift
   (Kyrillisch, CJK, Arabisch, Thai), fremde Schrift zusammen mit einem Link,
   Sperrwörter, Links auf Spam-Endungen (`top`, `xyz` …).
4. **Begrenzung je Absender** – höchstens 5 Einsendungen in 10 Minuten. Die IP wird
   nur als täglich wechselnder Hash im Cache gehalten.

Beide Felder schreibt das Plugin beim Ausgeben jeder Seite in jedes Formular
(`onOutputGenerated`, erkannt am Feld `__form-name__`); geprüft wird in
`onFormValidationProcessed` mit Priorität 100, also vor jedem Mail- oder
Speicherschritt.

## Zählung

`user-data://cw-spamschutz/statistik.json`: je Tag die Zahl der Abweisungen nach
Grund und Formular, 60 Tage. Keine Inhalte, keine Adressen. Zusätzlich je
Abweisung eine Zeile im Grav-Log (`cw-spamschutz: Einsendung abgewiesen (grund)`).

## Grenzen

Gilt für Formulare des form-Plugins. Formulare mit eigener Verarbeitung
(cw-market, cw-reservation, Newsletter-Anmeldung von cw-newsletter) sind nicht
erfasst.
