<?php
declare(strict_types=1);

namespace Grav\Plugin\CwSpamschutz;

use Grav\Common\Grav;

/**
 * Die Regeln des Spamschutzes, ohne Grav-Formularlogik.
 *
 * Jede Pruefung liefert einen GRUND (kurzes Kennwort) oder null. Der Grund
 * landet im Grav-Log und in der Tageszaehlung -- nie der Inhalt der Nachricht,
 * nie eine IP-Adresse im Klartext (CHWEB-Regel: keine Personendaten auf dem
 * Server).
 */
class Pruefung
{
    /** Name des Koederfelds. Bewusst kein «website»/«url»: Browser fuellen solche Felder selbst aus. */
    public const KOEDER = 'cw_hp_feld';
    /** Name des Zeitstempel-Felds (signiert). */
    public const ZEIT = 'cw_hp_zeit';

    private array $cfg;
    private string $salz;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $grav = Grav::instance();
        $this->salz = (string) ($grav['config']->get('security.salt') ?: $grav['config']->get('system.security.salt') ?: __FILE__);
    }

    // ------------------------------------------------------------------
    // Zeitstempel: beim Anzeigen des Formulars ausgegeben, beim Absenden
    // geprueft. Signiert, damit ein Bot ihn nicht selbst bauen kann.
    // ------------------------------------------------------------------

    public function zeitstempel(): string
    {
        $t = (string) time();

        return $t . '.' . substr(hash_hmac('sha256', $t, $this->salz . '|cw-spamschutz'), 0, 20);
    }

    /** null = in Ordnung, sonst Grund. */
    public function pruefeZeit(?string $wert): ?string
    {
        if (empty($this->cfg['mindestzeit']['aktiv'] ?? true)) {
            return null;
        }
        if (!is_string($wert) || !preg_match('/^(\d{9,11})\.([a-f0-9]{20})$/', $wert, $m)) {
            return 'zeitstempel_fehlt';
        }
        if (!hash_equals(substr(hash_hmac('sha256', $m[1], $this->salz . '|cw-spamschutz'), 0, 20), $m[2])) {
            return 'zeitstempel_gefaelscht';
        }
        $alter = time() - (int) $m[1];
        $min = max(1, (int) ($this->cfg['mindestzeit']['sekunden'] ?? 4));
        $max = max(1, (int) ($this->cfg['mindestzeit']['max_stunden'] ?? 24)) * 3600;
        if ($alter < $min) {
            return 'zu_schnell';
        }
        if ($alter > $max) {
            return 'zu_alt';
        }

        return null;
    }

    public function pruefeKoeder(?string $wert): ?string
    {
        if (empty($this->cfg['koederfeld'] ?? true)) {
            return null;
        }

        return (is_string($wert) && trim($wert) !== '') ? 'koederfeld' : null;
    }

    // ------------------------------------------------------------------
    // Inhalt: Links, fremde Schriften, Sperrwoerter.
    // ------------------------------------------------------------------

    /**
     * Die Schriften, die auf DIESER Website fremd sind -- als Zeichenklasse
     * fuer einen regulaeren Ausdruck, oder leer, wenn nichts uebrig bleibt.
     *
     * Gelesen werden die Sprachen der Installation (system.languages.supported,
     * sonst die aktive Sprache). Fuehrt die Website eine Sprache, die eine
     * dieser Schriften benutzt, zaehlt diese Schrift nicht mehr als fremd.
     */
    private static function fremdeSchriften(): string
    {
        static $zwischenspeicher = null;
        if ($zwischenspeicher !== null) {
            return $zwischenspeicher;
        }

        $schriften = [
            'Cyrillic' => ['ru', 'uk', 'be', 'bg', 'sr', 'mk', 'kk', 'ky', 'mn', 'tg'],
            'Han'      => ['zh', 'ja', 'ko'],
            'Hiragana' => ['ja'],
            'Katakana' => ['ja'],
            'Hangul'   => ['ko'],
            'Arabic'   => ['ar', 'fa', 'ur', 'ps', 'ku', 'sd'],
            'Thai'     => ['th'],
        ];

        $grav = Grav::instance();
        $sprachen = (array) ($grav['config']->get('system.languages.supported') ?: []);
        if (!$sprachen && isset($grav['language'])) {
            $sprachen = array_filter([
                (string) $grav['language']->getActive(),
                (string) $grav['language']->getDefault(),
            ]);
        }
        $sprachen = array_map(
            static fn ($s) => strtolower(substr((string) $s, 0, 2)),
            $sprachen
        );

        $klasse = '';
        foreach ($schriften as $schrift => $codes) {
            if (array_intersect($codes, $sprachen)) {
                continue;
            }
            $klasse .= '\p{' . $schrift . '}';
        }

        return $zwischenspeicher = $klasse;
    }

    /** @param string[] $texte alle eingereichten Textwerte */
    public function pruefeInhalt(array $texte): ?string
    {
        $c = (array) ($this->cfg['inhalt'] ?? []);
        if (empty($c['aktiv'] ?? true)) {
            return null;
        }
        $alles = implode("\n", array_map(static fn ($t) => (string) $t, $texte));

        $links = preg_match_all('~(https?://|www\.)\S+~iu', $alles);
        $maxLinks = (int) ($c['max_links'] ?? 2);
        if ($links > $maxLinks) {
            return 'zu_viele_links';
        }

        // Fremde Schriften. AUS als Vorgabe, und wenn eingeschaltet, dann nur
        // gegen Schriften, die diese Website selbst nicht benutzt.
        //
        // Warum: Die Regel war bis 1.3.0 an und zaehlte Kyrillisch, CJK,
        // Arabisch und Thai immer als fremd. Fuer eine deutschsprachige Seite
        // ist das richtig; eine russische, japanische oder arabische Website
        // haette damit ihre eigenen Besucher abgewiesen, gleich nach der
        // Installation. Angemerkt von Andy Miller beim Verzeichnis-Antrag
        // (getgrav/grav#4395, 09.11.2026).
        if (!empty($c['fremde_schrift'] ?? false)) {
            $schriften = self::fremdeSchriften();
            if ($schriften !== '') {
                $fremd = preg_match_all('/[' . $schriften . ']/u', $alles);
                $buchstaben = max(1, preg_match_all('/\p{L}/u', $alles));
                if ($fremd >= 6 && $fremd / $buchstaben > 0.2) {
                    return 'fremde_schrift';
                }
                if ($fremd >= 3 && $links > 0) {
                    return 'fremde_schrift_mit_link';
                }
            }
        }

        foreach ((array) ($c['sperrwoerter'] ?? []) as $wort) {
            $wort = trim((string) $wort);
            if ($wort !== '' && mb_stripos($alles, $wort) !== false) {
                return 'sperrwort';
            }
        }

        // Links zu Endungen, die fast nur Spam fuehren.
        foreach ((array) ($c['spam_endungen'] ?? []) as $tld) {
            $tld = ltrim(trim((string) $tld), '.');
            if ($tld !== '' && preg_match('~https?://[^\s/]+\.' . preg_quote($tld, '~') . '(?:[/\s:?#]|$)~iu', $alles)) {
                return 'spam_endung';
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Begrenzung je Absender: hoechstens N Einsendungen in M Minuten.
    // Gespeichert wird ein Hash aus IP und Tagessalz (nicht umkehrbar,
    // taeglich neu), dazu Zeitpunkte. Datei im Cache-Ordner.
    // ------------------------------------------------------------------

    public function pruefeTakt(string $ip): ?string
    {
        $c = (array) ($this->cfg['begrenzung'] ?? []);
        if (empty($c['aktiv'] ?? true) || $ip === '') {
            return null;
        }
        $max = max(1, (int) ($c['anzahl'] ?? 5));
        $fenster = max(1, (int) ($c['minuten'] ?? 10)) * 60;
        $datei = $this->taktDatei();
        if ($datei === null) {
            return null;
        }
        $schluessel = substr(hash('sha256', $ip . '|' . date('Y-m-d') . '|' . $this->salz), 0, 24);
        $jetzt = time();

        $fh = @fopen($datei, 'c+');
        if (!$fh) {
            return null;
        }
        try {
            flock($fh, LOCK_EX);
            $roh = stream_get_contents($fh);
            $daten = json_decode($roh ?: '{}', true) ?: [];
            foreach ($daten as $k => $zeiten) {
                $daten[$k] = array_values(array_filter((array) $zeiten, static fn ($t) => $t > $jetzt - $fenster));
                if (!$daten[$k]) {
                    unset($daten[$k]);
                }
            }
            $eigene = $daten[$schluessel] ?? [];
            $zuViel = count($eigene) >= $max;
            $eigene[] = $jetzt;
            $daten[$schluessel] = $eigene;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($daten));
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return $zuViel ? 'zu_viele_einsendungen' : null;
    }

    private function taktDatei(): ?string
    {
        $ordner = Grav::instance()['locator']->findResource('cache://cw-spamschutz', true, true);
        if (!is_string($ordner)) {
            return null;
        }
        if (!is_dir($ordner) && !@mkdir($ordner, 0775, true) && !is_dir($ordner)) {
            return null;
        }

        return $ordner . '/takt.json';
    }

    // ------------------------------------------------------------------
    // Zaehlung: abgewiesene Einsendungen je Tag und Grund (keine Inhalte).
    // ------------------------------------------------------------------

    public static function zaehle(string $grund, string $formular): void
    {
        $basis = Grav::instance()['locator']->findResource('user-data://cw-spamschutz', true, true);
        if (!is_string($basis)) {
            return;
        }
        if (!is_dir($basis) && !@mkdir($basis, 0775, true) && !is_dir($basis)) {
            return;
        }
        $datei = $basis . '/statistik.json';
        $fh = @fopen($datei, 'c+');
        if (!$fh) {
            return;
        }
        try {
            flock($fh, LOCK_EX);
            $d = json_decode(stream_get_contents($fh) ?: '{}', true) ?: [];
            $tag = date('Y-m-d');
            $d[$tag][$grund] = ($d[$tag][$grund] ?? 0) + 1;
            $d[$tag]['formular:' . $formular] = ($d[$tag]['formular:' . $formular] ?? 0) + 1;
            // Nur die letzten 60 Tage behalten.
            krsort($d);
            $d = array_slice($d, 0, 60, true);
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($d, JSON_PRETTY_PRINT));
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
