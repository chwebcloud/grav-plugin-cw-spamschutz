<?php
namespace Grav\Plugin;

use Grav\Common\Data\ValidationException;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Plugin\CwSpamschutz\Pruefung;
use RocketTheme\Toolbox\Event\Event;

/**
 * CHWEB-Spamschutz fuer ALLE Formulare des form-Plugins einer Site.
 *
 * Anlass: Kontaktformular von regio-huttwil.ch, 06.10.2026 -- rund 30
 * Bot-Nachrichten in 30 Minuten (russischer Text, Link auf .top-Domain).
 *
 * Ohne Drittdienst (kein reCAPTCHA, keine Cookies, nichts fuer die
 * Besucherin zu tun) und ohne Aenderung an den Formularseiten:
 *
 *   1. Koederfeld: unsichtbares Eingabefeld, das nur Bots ausfuellen.
 *   2. Mindestzeit: signierter Zeitstempel beim Anzeigen; wer schneller
 *      absendet als ein Mensch tippen kann (Vorgabe 4 s), ist ein Bot.
 *      Fehlt der Stempel ganz, wurde das Formular gar nicht geladen.
 *   3. Inhaltsregeln: zu viele Links, Nachrichten in fremder Schrift,
 *      fremde Schrift mit Link, Sperrwoerter, Spam-Domainendungen.
 *   4. Begrenzung je Absender: hoechstens N Einsendungen in M Minuten
 *      (IP nur als taeglich wechselnder Hash im Cache).
 *   5. Newsletter-Anmeldung (cw-newsletter, seit 1.1.0): Koederfeld und
 *      Zeitstempel kommen auch in jedes Formular, dessen Aktion auf
 *      /subscribe endet; cw-newsletter prueft ueber pruefeEinsendung()
 *      Koederfeld, Zeitstempel und Takt (keine Inhaltsregeln: eine
 *      Anmeldung hat keinen Text). Anlass: Masseneintragungen erfundener
 *      Adressen auf regio-huttwil.ch, 2.-4.11.2026.
 *
 * Beide Felder werden beim Ausgeben der Seite in jedes form-Plugin-Formular
 * geschrieben (onOutputGenerated, erkannt am versteckten Feld
 * `__form-name__`). Geprueft wird in onFormValidationProcessed mit hoher
 * Prioritaet -- vor jedem Versand- oder Speicherschritt.
 *
 * Abgewiesen wird mit einer neutralen Meldung, die echten Menschen den
 * Ausweg nennt (Seite neu laden, E-Mail). Gezaehlt wird je Tag und Grund
 * in user-data://cw-spamschutz/statistik.json, ohne Inhalte.
 */
class CwSpamschutzPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }
        require_once __DIR__ . '/classes/Pruefung.php';
        $this->enable([
            'onOutputGenerated'         => ['onOutputGenerated', 0],
            // Vor form (0) und vor allen Verarbeitungsschritten.
            'onFormValidationProcessed' => ['onFormValidationProcessed', 100],
        ]);
    }

    private function pruefung(): Pruefung
    {
        return new Pruefung((array) $this->config->get('plugins.cw-spamschutz', []));
    }

    /**
     * Koederfeld und Zeitstempel in jedes Formular des form-Plugins setzen.
     * Erkannt wird ein solches Formular am versteckten Feld `__form-name__`,
     * das das form-Plugin immer ausgibt.
     */
    public function onOutputGenerated(): void
    {
        $out = (string) $this->grav->output;
        if ($out === '') {
            return;
        }
        $hatForm = stripos($out, '__form-name__') !== false;
        // Newsletter-Anmeldung von cw-newsletter: Formular-Aktion endet auf /subscribe.
        $hatNewsletter = preg_match(self::NEWSLETTER_FORM, $out) === 1;
        if (!$hatForm && !$hatNewsletter) {
            return;
        }
        $p = $this->pruefung();
        $felder = '<div class="cw-hp" aria-hidden="true" style="position:absolute!important;left:-10000px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important">'
            . '<label>Dieses Feld bitte leer lassen<input type="text" name="' . Pruefung::KOEDER . '" value="" tabindex="-1" autocomplete="off"></label>'
            . '</div>'
            . '<input type="hidden" name="' . Pruefung::ZEIT . '" value="' . htmlspecialchars($p->zeitstempel(), ENT_QUOTES) . '">';

        // Direkt vor dem versteckten Formularnamen einfuegen -- sitzt sicher
        // innerhalb des <form>, unabhaengig vom Aufbau der Felder.
        $neu = $out;
        if ($hatForm) {
            $neu = preg_replace(
                '~(<input[^>]*name=["\']__form-name__["\'][^>]*>)~i',
                $felder . '$1',
                $neu
            ) ?? $neu;
        }
        // Newsletter-Formular: gleich hinter dem <form>-Anfang.
        if ($hatNewsletter) {
            $neu = preg_replace(self::NEWSLETTER_FORM, '$1' . $felder, $neu) ?? $neu;
        }
        if ($neu !== $out) {
            $this->grav->output = $neu;
        }
    }

    /** <form ... action="…/subscribe"> von cw-newsletter (Route konfigurierbar, Endung fest). */
    private const NEWSLETTER_FORM = '~(<form\b[^>]*\baction=["\'][^"\']*/subscribe["\'][^>]*>)~i';

    /**
     * Pruefung fuer Formulare ausserhalb des form-Plugins (cw-newsletter):
     * Koederfeld, Zeitstempel, Takt je Absender -- keine Inhaltsregeln.
     * Zaehlt und protokolliert die Abweisung. null = in Ordnung, sonst Grund.
     *
     * @param array $roh die eingereichten Felder (POST)
     */
    public static function pruefeEinsendung(array $roh, string $formular): ?string
    {
        $grav = Grav::instance();
        if (!$grav['config']->get('plugins.cw-spamschutz.enabled')) {
            return null;
        }
        if (!class_exists(Pruefung::class, false)) {
            require_once __DIR__ . '/classes/Pruefung.php';
        }
        $p = new Pruefung((array) $grav['config']->get('plugins.cw-spamschutz', []));
        $grund = $p->pruefeKoeder($roh[Pruefung::KOEDER] ?? null)
            ?? $p->pruefeZeit($roh[Pruefung::ZEIT] ?? null)
            ?? $p->pruefeTakt((string) ($grav['uri']->ip() ?? ''));
        if ($grund !== null) {
            Pruefung::zaehle($grund, $formular);
            $grav['log']->info(sprintf('cw-spamschutz: Einsendung abgewiesen (%s), Formular %s', $grund, $formular));
        }

        return $grund;
    }

    public function onFormValidationProcessed(Event $event): void
    {
        $form = $event['form'] ?? null;
        if (!$form) {
            return;
        }
        $roh = $this->grav['request']->getParsedBody();
        $roh = is_array($roh) ? $roh : $_POST;
        $daten = (array) ($roh['data'] ?? []);

        $p = $this->pruefung();
        $grund = $p->pruefeKoeder($roh[Pruefung::KOEDER] ?? null)
            ?? $p->pruefeZeit($roh[Pruefung::ZEIT] ?? null)
            ?? $p->pruefeInhalt(self::texte($daten))
            ?? $p->pruefeTakt((string) ($this->grav['uri']->ip() ?? ''));

        if ($grund === null) {
            return;
        }
        $name = method_exists($form, 'getName') ? (string) $form->getName() : (string) ($form->name ?? '');
        Pruefung::zaehle($grund, $name);
        $this->grav['log']->info(sprintf('cw-spamschutz: Einsendung abgewiesen (%s), Formular %s', $grund, $name));

        $text = in_array($grund, ['zu_alt', 'zeitstempel_fehlt'], true)
            ? $this->text('SEITE_NEU_LADEN')
            : (trim((string) $this->config->get('plugins.cw-spamschutz.meldung', '')) ?: $this->text('ABGEWIESEN'));
        throw new ValidationException($text);
    }

    /**
     * Meldung in der Sprache der Website. Ohne `languages.supported` (einsprachige
     * Websites wie chweb.ch) ist keine Sprache aktiv, und Grav fiele auf Englisch
     * zurueck -- dann gilt die Standardsprache der Website, zuletzt Deutsch.
     */
    private function text(string $schluessel): string
    {
        $sprache = $this->grav['language'];
        $code = $sprache->getActive()
            ?: $this->config->get('system.languages.default_lang')
            ?: $this->config->get('site.default_lang')
            ?: 'de';

        return (string) $sprache->translate('PLUGIN_CW_SPAMSCHUTZ.' . $schluessel, array_unique([$code, 'de']));
    }

    /** Alle Textwerte eines Formulars, auch verschachtelt. */
    private static function texte(array $daten): array
    {
        $out = [];
        array_walk_recursive($daten, static function ($v) use (&$out) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        });

        return $out;
    }
}
