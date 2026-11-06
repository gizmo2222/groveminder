// ────────────────────────────────────────────────────────────────────────────
// site-core.js — GroveMinder product logic shared by index.html and admin.html.
// Loaded in <head> right after site-config.js. A site (or a theme built on
// GroveMinder) never edits this file: everything site-specific is data in
// site-config.js.
// ────────────────────────────────────────────────────────────────────────────

// ── Theme presets ──────────────────────────────────────────────────────────
// Each preset defines six colors. The admin theme picker shows a swatch for
// each. Add or remove themes here — the admin will pick them up automatically.
const THEMES = {
    grove: {
        label: "Grove",
        primary:      "#2D4A2B",
        primaryLight: "#3F6240",
        primaryDark:  "#1A2E1A",
        accent:       "#C8924A",
        accentLight:  "#E0B070",
        bg:           "#F5EFE3",
    },
    coastal: {
        label: "Coastal",
        primary:      "#1B2A4A",
        primaryLight: "#2C3E6B",
        primaryDark:  "#111A2E",
        accent:       "#D4AF37",
        accentLight:  "#F0D060",
        bg:           "#FAF6ED",
    },
    lavender: {
        label: "Lavender",
        primary:      "#3D2B4F",
        primaryLight: "#553D6B",
        primaryDark:  "#2A1D38",
        accent:       "#C68FB0",
        accentLight:  "#E0AEC8",
        bg:           "#FAF5EE",
    },
    slate: {
        label: "Slate",
        primary:      "#2C3338",
        primaryLight: "#444C52",
        primaryDark:  "#1A2024",
        accent:       "#3FA577",
        accentLight:  "#5BC093",
        bg:           "#F2EFEA",
    },
    terracotta: {
        label: "Terracotta",
        primary:      "#5C3527",
        primaryLight: "#7A4938",
        primaryDark:  "#3D2218",
        accent:       "#D4933A",
        accentLight:  "#E8AE5A",
        bg:           "#F6EBDD",
    },
};

// ── Apply theme ────────────────────────────────────────────────────────────
// Sets the six theme variables on <html>. `overrides` defaults to the `colors`
// block in site-config.js, so those survive a theme saved in the admin panel.
function applyTheme(themeKey, overrides) {
    const theme = THEMES[themeKey] || THEMES[SITE_CONFIG.theme] || THEMES.grove;
    const merged = { ...theme, ...(overrides || SITE_CONFIG.colors || {}) };
    const s = document.documentElement.style;
    s.setProperty('--primary',       merged.primary);
    s.setProperty('--primary-light', merged.primaryLight);
    s.setProperty('--primary-dark',  merged.primaryDark);
    s.setProperty('--accent',        merged.accent);
    s.setProperty('--accent-light',  merged.accentLight);
    s.setProperty('--bg',            merged.bg);
    return merged;
}

// The theme to show: the admin's saved choice, unless site-config.js sets
// `themePicker: false`, in which case the file-level theme always wins.
function siteTheme(savedKey) {
    if (SITE_CONFIG.themePicker === false) return SITE_CONFIG.theme;
    return savedKey || SITE_CONFIG.theme;
}

// Runs in <head>, before the page renders, so there is no flash of another
// palette or name. Public pages apply the theme again once Firestore loads.
const SITE_COLORS = applyTheme(SITE_CONFIG.theme);
document.title = SITE_CONFIG.siteName || '';

// ── Favicon (generated from navEmoji + colors, no static file needed) ──────
(function () {
    const size = 64;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = SITE_COLORS.primary;
    ctx.fillRect(0, 0, size, size);
    ctx.font = '44px serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(SITE_CONFIG.navEmoji || '🌳', size / 2, size / 2 + 2);
    const link = document.createElement('link');
    link.rel = 'icon';
    link.href = canvas.toDataURL();
    document.head.appendChild(link);
})();

// ── Firebase App Check (optional) ─────────────────────────────────────────
// When site-config.js sets appCheckSiteKey (a reCAPTCHA v3 site key), load the
// App Check SDK and activate it. Call right after firebase.initializeApp(), and
// wait for the promise before the first Firestore request. Resolves either way,
// so a blocked script leaves the site working where App Check isn't enforced.
// The PHP endpoints read Firestore on the page's behalf, so same-origin .php
// requests carry the page's App Check token (hp_fs_get() passes it on).
// Keep the version and hash in step with the Firebase <script> tags.
function startAppCheck() {
    const key = SITE_CONFIG.appCheckSiteKey;
    if (!key || SITE_CONFIG.demo) return Promise.resolve();
    return new Promise(resolve => {
        const s = document.createElement('script');
        s.src = 'https://www.gstatic.com/firebasejs/10.12.0/firebase-app-check-compat.js';
        s.integrity = 'sha384-E3vxFxXu8FhW5kvqdQJ5Yf8ugrV7iCJP8jgnItRH2Rk8Q08cJzJGTi/oMC3FEp3j';
        s.crossOrigin = 'anonymous';
        s.onload = () => {
            try {
                const appCheck = firebase.appCheck();
                appCheck.activate(key, true);
                const send = window.fetch.bind(window);
                window.fetch = async (input, init = {}) => {
                    const url = new URL(input instanceof Request ? input.url : input, location.href);
                    if (url.origin === location.origin && url.pathname.endsWith('.php')) {
                        try {
                            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
                            headers.set('X-Firebase-AppCheck', (await appCheck.getToken()).token);
                            init = { ...init, headers };
                        } catch (e) { /* send without it; fine until App Check is enforced */ }
                    }
                    return send(input, init);
                };
            } catch (e) { console.warn('App Check did not start:', e); }
            resolve();
        };
        s.onerror = () => { console.warn('App Check script failed to load.'); resolve(); };
        document.head.appendChild(s);
    });
}

// ── Copy ───────────────────────────────────────────────────────────────────
// Wording a site may want to change. site-config.js overrides any of these in
// its optional `copy` block; whatever it leaves out falls back to the text here.
const COPY_DEFAULTS = {
    navBook:                "Book",
    navAsk:                 "Ask",
    testimonialsEmpty:      "Kind words from the people I work with will be shared here soon.",
    notesPlaceholder:       "Anything that helps me prepare: timing, access, special requests.",
    roleExample:            "Client · 2 years",
    ratesIntro:             "Rates vary depending on the service, hours, and any special requirements. I prefer to discuss pricing personally so I can give you an accurate, fair quote tailored to your situation.",
    rateDetailsPlaceholder: "What you need, how often, any special considerations...",
    rateNotesExample:       "+$10 for weekends",
};

function siteCopy(key) {
    const own = (SITE_CONFIG.copy || {})[key];
    return (typeof own === 'string' && own) ? own : COPY_DEFAULTS[key];
}
