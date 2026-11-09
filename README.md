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
3. **Content rules** – more than two links, blocklisted words, and links to
   top-level domains that are almost only used for spam. A fourth rule,
   rejecting messages written in a script the site does not itself use, is
   **off by default**; see below.
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
- It does not touch forms that bring their own processing (shop checkouts,
  booking systems, newsletter sign-ups of other plugins) unless you name them
  yourself under `fremde_formulare`. Everything that goes through the standard
  `form` plugin is covered automatically, including forms that already exist.

## The foreign-script rule

A message written mostly in a script your site does not use is almost always
spam – on a German site, a wall of Cyrillic is not a customer. The rule is
**off by default**, because the opposite is just as true: on a Russian,
Japanese or Arabic site it would reject the site's own visitors.

When you switch it on, it only counts scripts your installation does not
itself list under `system.languages.supported` (or, on a single-language site,
its active language). A site running German and Russian never treats Cyrillic
as foreign. A single name or place in another script is always allowed; the
rule needs either a clear majority of foreign characters or a few of them
together with a link.

## Caching and the timestamp

The timestamp is written into the HTML when the page is rendered. If a CDN or
a full-page cache serves the same HTML to everybody, every visitor gets the
timestamp of the moment the page was cached – and once that is older than
`mindestzeit.max_stunden` (24 hours by default), every submission is rejected
with "please reload the page".

Three ways out, in order of preference: exclude pages with forms from the
full-page cache; raise `max_stunden` above the cache lifetime; or switch the
minimum-time layer off and rely on the other three.

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
koederfeld: true              # the invisible honeypot field

mindestzeit:
  aktiv: true
  sekunden: 4                 # a submission faster than this is rejected
  max_stunden: 24             # … and one from a form older than this

inhalt:
  aktiv: true
  max_links: 2                # links allowed in a message
  fremde_schrift: false       # see "The foreign-script rule" above
  sperrwoerter: []            # additional blocked words
  spam_endungen: [top, xyz, click, icu, cfd, sbs, rest]

begrenzung:
  aktiv: true
  anzahl: 5                   # submissions per sender …
  minuten: 10                 # … within this many minutes

# Forms that do not come from the form plugin. Each entry is matched against
# the end of the form's action attribute, so "/newsletter/subscribe" covers
# both the relative and the absolute form of the same address. Empty means
# none: no other plugin's form is touched unless you say so.
fremde_formulare: []

meldung: ''                   # your own rejection message, empty = built-in
```

## Counting, not logging

`user-data://cw-spamschutz/statistik.json` keeps, per day, how many submissions
were rejected, for which reason and on which form – for 60 days. No content, no
addresses. Every rejection also writes one line to the Grav log, so you can tell
a quiet form from a form that is too strict.

## Requirements

Grav 1.7 or 2.0 with the `form` plugin, PHP 8.1 or newer.

## Licence

MIT – see [LICENSE](LICENSE). Built and maintained by
[CHWEB GmbH](https://chweb.ch), Huttwil, Switzerland.
