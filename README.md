# GroveMinder — Availability & Booking Site

A personal booking and availability site with a Firebase-backed admin panel. Built as a reusable template — only one file needs editing to launch a new instance.

## Stack

- Static HTML/CSS/JS — no build step
- Firebase Firestore for config and testimonial submissions
- Firebase Auth (email/password) for admin login
- PHP iCal proxy for calendar sync
- GitHub Actions SFTP deploy

## Setting up a new site

### 1. Create a Firebase project

1. Go to [console.firebase.google.com](https://console.firebase.google.com) and create a project
2. Add a **Web app** and copy the config object
3. Enable **Firestore** (start in production mode)
4. Enable **Authentication → Email/Password**
5. Create an admin user under Authentication → Users
6. Add your domain to **Authentication → Settings → Authorized domains**

### 2. Set Firestore security rules

In Firestore → Rules:

```
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /config/{doc} {
      allow read: if true;
      allow write: if request.auth != null;
    }
    match /testimonials/{doc} {
      allow create: if true;
      allow read, update, delete: if request.auth != null;
    }
    match /bookings/{doc} {
      allow create: if true;
      allow read, update, delete: if request.auth != null;
    }
    match /private/{doc} {
      allow read, write: if request.auth != null;
    }
  }
}
```

### 3. Edit site-config.js

This is the **only file you need to edit** for a new site:

```js
const SITE_CONFIG = {
    firebase: { /* paste your Firebase config here */ },

    siteName: "Your Name",
    navEmoji: "🌳",
    tagline:  "Your tagline here",
    heroDesc: "A short description of what you offer.",

    // Pick one of the named themes (grove, coastal, lavender, slate, terracotta)
    // — or override individual colors in the `colors` block below.
    theme: "grove",
    colors: {},

    defaultServices:     [ /* edit or replace */ ],
    defaultTestimonials: [ /* edit or replace */ ],
    defaultFaq:          [ /* edit or replace */ ],
};
```

Themes can also be switched at runtime from the admin panel — that choice is saved in Firestore and overrides the file-level default.

### 4. Create a GitHub repo and set secrets

Add these secrets under **Settings → Secrets → Actions**:

| Secret | Value |
|---|---|
| `SFTP_USER` | Your hosting username |
| `SFTP_PASSWORD` | Your hosting password |

### 5. Update deploy.yml

In `.github/workflows/deploy.yml`, replace the SFTP host and remote paths with your own domain and web root. The current workflow deploys to `metacrystal.com/groveminder/`.

### 6. Push to deploy

```sh
git push
```

GitHub Actions will SFTP the project files to your server. The admin panel is at `/admin.html`.

## Files

| File | Purpose |
|---|---|
| `site-config.js` | All site-specific config — edit this for each new site |
| `index.html` | Public-facing site |
| `admin.html` | Password-protected admin panel |
| `ical-proxy.php` | Server-side proxy for iCal calendar feeds (CORS) |
| `cal-push.php` | Server-side calendar push: email (.ics), Google Calendar, Apple iCloud/CalDAV |
| `mailer.php` | Server-side mail relay for notification emails |
| `.github/workflows/deploy.yml` | Auto-deploy on push to main |

## Admin features

- **Appearance** — pick a colour theme (Grove, Coastal, Lavender, Slate, Terracotta)
- **Settings** — email, Venmo, Cash App, Zelle, iCal sync URL
- **Availability** — click-to-toggle calendar, import from Google/Apple/Outlook
- **Services** — toggle on/off, edit icon, name, description
- **Rates** — private reference rates (never shown publicly)
- **Testimonials** — approve/reject submitted references, edit approved ones
- **FAQ** — add, edit, delete questions and answers
