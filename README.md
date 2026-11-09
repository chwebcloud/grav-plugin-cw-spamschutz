# CHWEB Spam Protection (cw-spamschutz)

Spam protection for **every form of the Grav `form` plugin** – without a
captcha, without cookies and without a third-party service. Real visitors
never notice it, and you do not have to touch a single form page.

The usual answer to form spam is a captcha, which puts a hurdle in front of
the people who did nothing wrong: distorted letters cost everyone time,
especially on a phone and with impaired sight, and modern bots solve them
anyway. On top of that, the well-known captcha services report every visit to
your site to a large company – something you then have to declare in your
privacy policy. This plugin judges behaviour instead of people, and nothing
leaves your server.

## The four layers

1. **Honeypot** – an input field that is invisible in the browser and only
   filled in by programs that read a form instead of looking at it.
2. **Minimum fill time** – every form is handed a signed timestamp when it is
   rendered. A submission that comes back faster than four seconds, later than
   24 hours, or without the form ever being loaded is rejected.
3. **Content rules** – more than two links, a message written mostly in a
   foreign script (Cyrillic, CJK, Arabic, Thai), a foreign script together with
   a link, blocklisted words, and links to top-level domains that are almost
   only used for spam.
4. **Rate limit** – at most five submissions from the same sender within ten
   minutes.

No single layer stops everything. Together they do – and a visitor never sees
that they exist.

## What it does not do

- It does not store IP addresses. For the rate limit the address is kept as a
  hash that changes daily, in the cache only. There is no list from which you
  could later read who submitted what, and when.
- It does not phone home. No request to any service, no cookie, no tracking
  pixel, nothing to add to your privacy policy.
- It does not cover forms that bring their own processing (shop checkouts,
  booking systems, newsletter sign-ups of other plugins). Everything that goes
  through the standard `form` plugin is covered automatically, including forms
  that already exist.

## Installation

```bash
bin/gpm install cw-spamschutz
```

Or copy the folder to `user/plugins/cw-spamschutz` and run
`bin/grav clear-cache`. There is nothing else to set up: the two fields are
added while the page is rendered (`onOutputGenerated`, recognised by the
`__form-name__` field), and the check runs in `onFormValidationProcessed` with
priority 100 – before any mail is sent or anything is stored.

## Settings

All thresholds live in `user/config/plugins/cw-spamschutz.yaml` and in the
admin. The defaults are the values above; the ones worth knowing:

```yaml
enabled: true
mindestzeit: 4          # seconds, minimum time to fill in a form
hoechstzeit: 86400      # seconds, after which a form is considered stale
max_links: 2            # links allowed in a message
limit_anzahl: 5         # submissions per sender …
limit_fenster: 600      # … within this many seconds
sperrwoerter: []        # additional blocked words
```

## Counting, not logging

`user-data://cw-spamschutz/statistik.json` keeps, per day, how many submissions
were rejected, for which reason and on which form – for 60 days. No content, no
addresses. Every rejection also writes one line to the Grav log, so you can tell
a quiet form from a form that is too strict.

## Requirements

Grav 1.7 or newer with the `form` plugin, PHP 8.1 or newer.

## Licence

MIT – see [LICENSE](LICENSE). Built and maintained by
[CHWEB GmbH](https://chweb.ch), Huttwil, Switzerland.
