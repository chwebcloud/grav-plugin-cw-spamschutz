# v1.3.0
## 2026-11-09

1. [](#improved)
    * **The foreign-script rule is off by default, and language-aware when on.** It used to be on and always counted Cyrillic, CJK, Arabic and Thai as foreign. That is right for a German site and wrong for a Russian, Japanese or Arabic one, which would have rejected its own visitors straight after installing. It now only counts scripts the installation does not itself list under `system.languages.supported` (or, on a single-language site, its active language).
    * **Forms outside the `form` plugin are opt-in.** The honeypot and the timestamp used to be injected into any form whose action ended in `/subscribe` — which also hit other newsletter plugins and embedded third-party forms, and posted two unknown fields to services that did not expect them. The new `fremde_formulare` setting names the addresses explicitly; empty means none. Each entry is matched against the end of the action attribute, so `/newsletter/subscribe` covers both the relative and the absolute form of the same address.
    * `compatibility` now lists `1.7` and `2.0`, matching the dependency and the README. It said `2.0` only.
    * The admin notice and the honeypot label ("Please leave this field empty") were still hardcoded German; both come from the language files now, in German, English, French and Italian.
    * The README documented settings that do not exist (`hoechstzeit`, `limit_anzahl`, `limit_fenster`). It now shows the real keys, explains the foreign-script rule, and warns that a CDN or full-page cache freezes the timestamp — cached forms start failing once the cached HTML is older than `mindestzeit.max_stunden`.
    * This changelog is in English with ISO dates.

    All of the above comes from Andy Miller's review of the directory submission, [getgrav/grav#4395](https://github.com/getgrav/grav/issues/4395).

# v1.2.0
## 2026-11-09

1. [](#improved)
    * **The admin labels speak four languages.** 25 labels, help texts, placeholders and option labels were hardcoded German in the blueprints; they are language keys in `languages/de|en|fr|it.yaml` now. Nothing visible changes on a German installation.
    * The header fields (name, description, author) stay plain text on purpose: the Grav directory reads them literally, and a language key would end up in the catalogue.

# v1.1.1
## 2026-11-09

1. [](#improved)
    * **Prepared for the getgrav.org directory.** Description, keywords and README in English — the directory is read internationally. The German README sits beside it as `README.de.md`. `bugs` and `docs` point at the public repository.

# v1.1.0
## 2026-11-04

1. [](#new)
    * **Newsletter sign-up protected.** The honeypot and the timestamp are now also added to forms whose action ends in `/subscribe` (cw-newsletter), without touching the theme. cw-newsletter ≥ 1.25.0 checks honeypot, minimum time and rate limit through `CwSpamschutzPlugin::pruefeEinsendung`. Reason: a flood of invented @bundestag.de addresses on regio-huttwil.ch, 2–4 November 2026 — each one triggered a confirmation mail and a bounce. (Made opt-in in 1.3.0.)

# v1.0.1
## 2026-10-07

1. [](#bugfix)
    * Messages appeared in English when the site lists no languages under `languages.supported`. The site's default language applies now, German as the last fallback.

# v1.0.0
## 2026-10-06

1. [](#new)
    * First release. Reason: the contact form of regio-huttwil.ch took around 30 bot messages in 30 minutes (Russian text, a link to a .top domain).
    * Protects every form of the `form` plugin automatically, without touching the form pages: an invisible honeypot, a signed timestamp with a minimum fill time (4 s) and a maximum age (24 h), content rules (more than two links, a message in a foreign script, a foreign script together with a link, blocked words, spam domain endings), and a rate limit per sender (5 in 10 minutes, the IP address kept only as a daily-rotating hash in the cache).
    * Rejection with a neutral message and a way out for real people; counting per day and reason in `user-data://cw-spamschutz/statistik.json`, without content and without IP addresses.
