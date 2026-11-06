# Security review — 2026-11-06

Scope: `firestore.rules`, `admin.html`, `index.html`, `site-core.js`, `site-config.js`, the PHP endpoints
(`hp-lib.php`, `mailer.php`, `ical-proxy.php`, `cal-push.php`), `email-templates.json`, the deploy files
(`.github/workflows/deploy.yml`, `theme-template/deploy.yml`, `tools/deploy.sh`, `.github/known_hosts`) and
`holly-poppins/site-config.js`.

Method: a code read, plus local tests only. No live site, no live Firebase project, and nothing was pushed.

- Rules: `tests/rules.test.js` runs against the Firestore emulator with a pass and a fail case for each rule (36 tests).
- PHP: `tests/php/run.sh` runs the endpoints under `php -S`, with Firestore pointed at a closed local port and
  mail going to a local SMTP sink (25 checks).
- Admin: the iCal migration was checked by hand in demo mode in a local browser.

How to run the tests is in the README, under "Tests".

## Summary

| Severity | Count | Fixed on this branch |
|---|---|---|
| Critical | 0 | — |
| High | 1 | 1 |
| Medium | 4 | 0 (your call) |
| Low | 12 | 0 (your call) |

What held up well:
- Visitor text is escaped wherever it's rendered (`esc()` in `index.html`, `escHtml()` in `admin.html`). No XSS from visitor input was found.
- Bookings and testimonials are field-whitelisted and size-limited, and visitors cannot read them.
- `admins` cannot be written from a client.
- Mail headers cannot be injected. Every field is stripped of control characters, the subject is base64-encoded and the
  recipient is checked with `filter_var`.
- The PHP admin check is sound, because Firestore verifies the token's signature.
- The deploy pins the host key (`StrictHostKeyChecking=yes`), and the private key file is mode 600 and deleted on exit.

## Findings

Line numbers are as of commit `994ff29`, before this branch.

| # | Sev | File and line | Attack | Impact | Fix | Status |
|---|---|---|---|---|---|---|
| H1 | **High** | `admin.html:1058,1138,1152`; `firestore.rules:33-36` | `config/settings` is readable by anyone (`allow read: if true`), and the admin saved the iCal feed URL there. Anyone can open the site, take the project id from `site-config.js`, and read the document over the Firestore REST API. | The iCal URL is Google's *secret address*. Whoever has it can read the operator's whole private calendar (appointments, names, places) indefinitely. | Store `icalUrl` in `private/calendar` (admin only). The admin page moves an existing value there on the next save, and the rules now refuse `icalUrl` in `config`. Since the address has already been public, the operator must reset it in Google Calendar. | **Fixed** (`8d01bb2`). Live steps are in the README. |
| M1 | Medium | `admin.html:1155` (`config.services`); `index.html:1211` | Rates the operator marks "not public" (`rate.public:false`) are only hidden by the page. They're still stored in the world-readable `config/settings`, so a single REST read shows them. | Pricing the operator meant to give only on request is public. | Store hidden rates in `private/` (or leave them out of `config`) and serve them by email through the `rates` form. | **Fixed**. Hidden rates are saved to `private/rates`; the admin moves existing ones on the next Save (README v1.2.0 step 3). |
| M2 | Medium | `mailer.php:39,76-78`; `email-templates.json` | Anyone can POST `form=booking` (or `question`, `rates` or `reference`) with any victim's address as `email`. Each request sends that address a "thanks for your booking" message from the operator's domain, containing the attacker's `name` and `service` text (100 characters each, links included). The limit is 5 an hour per IP and 100 a day in total. | Spam or phishing sent under the operator's name and domain, which hurts the domain's reputation. Rotating IPs also uses up the site-wide 100-a-day cap, and once it's spent the operator stops getting real booking notifications. | Send the visitor copy without free text the visitor typed, or drop it for unverified addresses. Give admin notifications a separate quota from visitor copies. Add a per-recipient limit as well. | **Fixed** (`c0dbfa2`). Live steps are in the README (v1.2.0). |
| M3 | Medium | `firestore.rules:46-71` | A script calls `addDoc` on `bookings` or `testimonials` in a loop with the public API key. The rules check shape, not rate. | The admin's inbox and lists fill with junk. On the free Spark plan, 20k writes a day can be used up, which stops the site taking real bookings. | Turn on Firebase App Check (reCAPTCHA v3 or Enterprise) and enforce it for Firestore, or send bookings through PHP with `hp_rate_limit`. | **Fixed** in code: optional App Check (`appCheckSiteKey`). Each site must register a key and enforce it in the console (README v1.2.0 step 4). |
| M4 | Medium | `hp-lib.php:57-61` | `hp_mail_from()` builds the sender from `$_SERVER['SERVER_NAME']`. With Apache's default `UseCanonicalName Off`, that value comes from the request's `Host` header. Where the site is the default vhost, an attacker sends `Host: evil.example`. | Mail goes out as `noreply@<attacker domain>`. That fails SPF and DMARC, so the operator's mail lands in spam, or the message seems to come from someone else. | Take the domain from the config (for example a `mailDomain` in `site-config.js`, read in `hp_site_config()`) rather than from the request. | **Fixed** (`17cbec7`). Live steps are in the README (v1.2.0). |
| L1 | Low | `index.html:1259-1264,1274`; `mailer.php:20` | The honeypot (`website`) and the 3-second check run only in the browser. `notify()` never sends `website`, so the server-side check in `mailer.php` never fires. A script that POSTs directly skips both. | No bot filtering on the server beyond the rate limit. | Send `website`, plus a page-load timestamp, from `notify()`, and have `mailer.php` check both. | **Fixed** (`c0dbfa2`). Live steps are in the README (v1.2.0). |
| L2 | Low | `ical-proxy.php:19-33` | An admin, or someone holding the admin's session, can ask the proxy to fetch `https://` URLs on loopback or private addresses, including through redirects. A local test confirmed that `https://127.0.0.1` is tried. | Blind SSRF from the web host to internal HTTPS services. Only admins can reach it, and the body is returned only if it contains `BEGIN:VCALENDAR`. | Resolve the host and refuse private, loopback and link-local ranges (pin the address with `CURLOPT_RESOLVE`), or allow only known calendar hosts. | Fixed |
| L3 | Low | `ical-proxy.php:32` | `CURLOPT_MAXFILESIZE` isn't enforced when the size isn't known up front (chunked responses) on curl older than 8.4. | Any admin-supplied feed can push a very large body into PHP memory. | Abort past 5 MB in a `CURLOPT_WRITEFUNCTION`. | Fixed |
| L4 | Low | `cal-push.php:120,139,146,164` | CalDAV discovery follows `href`s that the server returns through `absoluteUrl()` and sends Basic credentials to each one, even when the host is different. | A malicious or compromised CalDAV server can redirect the operator's CalDAV password to another host. | Refuse discovered URLs whose host differs from the configured CalDAV host. | Fixed |
| L5 | Low | `admin.html:1067-1071` | `onAuthStateChanged` shows the admin panel to any signed-in user, not only admins. If public sign-up is on, anyone can sign up and see the panel. | The UI and the public settings are exposed, but no data is. Rules block every private read and write. | Check `admins/{uid}` before showing the panel, and sign out otherwise. Make sure Email/Password sign-up is disabled (README setup step 1.5). | **Fixed**. The admin signs out any account without an `admins/{uid}` entry. |
| L6 | Low | `index.html:1151-1152`; `admin.html:1039` | There's no Subresource Integrity on the gstatic Firebase scripts, and no Content-Security-Policy. | If the CDN were compromised, scripts would run with the admin's session. XSS would also have no second line of defence. | Add `integrity` hashes (the URLs are versioned) and a CSP `<meta>` that allows only self, gstatic, googleapis and fonts. | **Fixed**. SRI on all Firebase scripts and a CSP `<meta>` on both pages. Inline scripts are allowed by hash (`tools/csp-hashes.mjs`); styles still need `'unsafe-inline'`. |
| L7 | Low | `.github/workflows/deploy.yml:13`; `theme-template/deploy.yml:22,28,31` | Actions are pinned by tag (`@v5`), and themes check out `groveminder` at the mutable tag `v1.0.0`. Neither workflow has a `permissions:` block. | If the action or the tag were moved, the deploy job would run new code that holds the SFTP secrets. | Pin actions and the theme `ref` by commit SHA, and add `permissions: contents: read`. | **Fixed**. Theme repos need README upgrade step 2. |
| L8 | Low | `index.html:1138` | `heroTitle` is set with `innerHTML` from `SITE_CONFIG.siteName`. | Only the operator controls this value, so a visitor can't exploit it. A theme author could break the markup. | Build the title with DOM nodes and `textContent`. | **Fixed**. Built with `textContent`. |
| L9 | Low | `hp-lib.php:141-160` | Rate-limit files sit in the shared `sys_get_temp_dir()` and the check fails open. On shared hosting, other users can delete them. Behind a proxy or CDN, every visitor has one `REMOTE_ADDR`. | The rate limit can be reset or bypassed, or it can lock everyone out at once. | Keep the files in a private directory outside the web root, and fail closed for the site-wide bucket. | **Fixed** (`17cbec7`). Live steps are in the README (v1.2.0). |
| L10 | Low | `admin.html:1429-1439` | A feed event whose DTSTART and DTEND are centuries apart makes the day-by-day `while` loop run millions of times. | The admin's tab hangs on "Sync from Calendar". Only the admin's own feed can trigger it. | Cap each event's span (for example 366 days), or clamp it to the months being shown. | **Fixed**. Each event is capped at 366 days and starts no earlier than today. |
| L11 | Low | `holly-poppins/site-config.js:14` | The web `apiKey` is public by design. Unless it's restricted, anyone can use it against the project's Auth and Firestore from anywhere. | It makes M3 and L5 easier to exploit. | In Google Cloud → APIs and Services → Credentials, restrict the key to HTTP referrers `https://hollypoppins.com/*`. Confirm that sign-up is disabled in Firebase Auth. | Live setting: README upgrade step 6. |
| L12 | Low | `admin.html:1651,1833-1840`; `cal-push.php` | The Google service-account private key and the CalDAV password are kept in Firestore (`private/calendar`) and sent from the browser on every push. | If the admin's account is taken over, or an XSS lands in the admin, the calendar credentials leak too. | Keep calendar secrets in a PHP config file outside the web root, so the browser never sees them. | Fixed |

## Not findings (checked)

- **Header injection:** CR and LF in every field are stripped, and an address containing CRLF is refused. Tested in `tests/php/run.sh`.
- **Open relay:** recipients come only from the template map: the visitor's own validated address and the operator's address in settings. A request cannot name an arbitrary recipient beyond its own `email`; see M2.
- **Forged admin token:** the uid must be alphanumeric, and the token is checked by Firestore itself. Tested with a junk token, a path uid and an unsigned token.
- **XXE in `cal-push.php`:** `DOMDocument::loadXML` does not load external entities on PHP 8.
- **`email-templates.json`:** plain text with no secrets. Placeholders are filled from sanitised values.
- **`site-config.js` and `site-core.js`:** no secrets. Demo mode turns on only when `SITE_CONFIG.demo` is set.
- **`tools/deploy.sh`:** the host key is pinned, the key file is mode 600 and removed on exit, `sshpass -e` keeps the password out of `ps`, and theme file names are checked.
- **`holly-poppins/site-config.js`:** besides the public web config (L11), it exposes nothing. Its default rates are empty.
