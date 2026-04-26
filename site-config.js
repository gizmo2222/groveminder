// ─────────────────────────────────────────────────────────────────────────────
// site-config.js — edit this file to set up a new site instance
// ─────────────────────────────────────────────────────────────────────────────

const SITE_CONFIG = {

    // ── Firebase ──────────────────────────────────────────────────────────────
    // Create a project at https://console.firebase.google.com and paste your
    // web app config object here.
    firebase: {
        apiKey:            "YOUR_API_KEY",
        authDomain:        "your-project.firebaseapp.com",
        projectId:         "your-project",
        storageBucket:     "your-project.firebasestorage.app",
        messagingSenderId: "000000000000",
        appId:             "1:000000000000:web:0000000000000000000000"
    },

    // ── Identity ──────────────────────────────────────────────────────────────
    siteName: "GroveMinder",
    navEmoji: "🌳",                              // shown in nav + hero + footer
    tagline:  "Your tagline here",
    heroDesc: "A short description of what you offer — the kind of work, who it's for, and why someone should reach out.",

    // ── Theme ─────────────────────────────────────────────────────────────────
    // Pick one of the named presets in THEMES below, or override individual
    // colors with the `colors` block. The admin panel can switch themes at
    // runtime; that choice is saved in Firestore and overrides this default.
    theme: "grove",

    // Optional per-color override. Leave empty to use the named theme above.
    // Any keys present here will override the corresponding theme value.
    colors: {
        // primary:       "#2D4A2B",
        // primaryLight:  "#3F6240",
        // primaryDark:   "#1A2E1A",
        // accent:        "#C8924A",
        // accentLight:   "#E0B070",
        // bg:            "#F5EFE3",
    },

    // ── Default content ───────────────────────────────────────────────────────
    // Shown before the admin has saved anything to Firestore.

    defaultServices: [
        { id: 'service1', icon: '✨', name: 'Service One',   desc: 'Describe what you offer here. One or two sentences is plenty — focus on the outcome for the client.', active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'service2', icon: '🌿', name: 'Service Two',   desc: 'A second offering, with a short description that makes it clear who this is for and what they get.',  active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'service3', icon: '🤝', name: 'Service Three', desc: 'Add or remove services from the admin panel — these defaults are placeholders to get the page rendering.', active: true, rate: { base: '', min: '', notes: '', public: false } },
    ],

    defaultTestimonials: [
        { name: 'Sample Client',   role: 'Long-time customer', quote: 'Replace these with real testimonials from the admin panel — visitors can also submit their own from the public site.', stars: 5 },
        { name: 'Another Client',  role: '',                   quote: 'Approved testimonials show up here on the public site. Manage them under Testimonials in the admin panel.',          stars: 5 },
    ],

    defaultFaq: [
        { q: 'What services do you offer?',     a: 'Edit this answer in the admin panel under FAQ. Visitors expect a quick, clear summary of what you do.' },
        { q: 'How does scheduling work?',       a: 'Use the calendar to mark days you\'re available. Visitors can request bookings on those days from the public site.' },
        { q: 'How does payment work?',          a: 'Add your Venmo, Cash App, or Zelle handle in the admin Settings tab — the matching buttons will appear in the Pay section.' },
    ],
};

// ── Theme presets ─────────────────────────────────────────────────────────────
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

// ── Apply theme ───────────────────────────────────────────────────────────────
// Runs before the page renders so there's no flash of the default palette.
// Public pages overwrite this again after Firestore loads if the admin saved a
// different theme.
function applyTheme(themeKey, overrides) {
    const theme = THEMES[themeKey] || THEMES.grove;
    const merged = { ...theme, ...(overrides || {}) };
    const s = document.documentElement.style;
    s.setProperty('--primary',       merged.primary);
    s.setProperty('--primary-light', merged.primaryLight);
    s.setProperty('--primary-dark',  merged.primaryDark);
    s.setProperty('--accent',        merged.accent);
    s.setProperty('--accent-light',  merged.accentLight);
    s.setProperty('--bg',            merged.bg);
}
applyTheme(SITE_CONFIG.theme, SITE_CONFIG.colors);

// ── Favicon (generated from navEmoji + colors, no static file needed) ─────────
(function () {
    const size = 64;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    const theme = THEMES[SITE_CONFIG.theme] || THEMES.grove;
    ctx.fillStyle = (SITE_CONFIG.colors && SITE_CONFIG.colors.primary) || theme.primary;
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
