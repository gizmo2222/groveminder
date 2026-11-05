// ─────────────────────────────────────────────────────────────────────────────
// site-config.js — edit this file to set up a new site instance
// Data only. The product logic that reads it lives in site-core.js.
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

    // Optional image for the hero, shown instead of navEmoji. Put the file next
    // to this one and name it here (e.g. "logo.png"). Empty = use the emoji.
    heroImage: "",

    // ── Theme ─────────────────────────────────────────────────────────────────
    // Pick one of the named presets (THEMES in site-core.js), or override individual
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

    // Set to false to hide the Appearance picker in the admin panel and always
    // use the theme above (for a site with a fixed brand).
    themePicker: true,

    // ── Copy ───────────────────────────────────────────────────────────────
    // Optional wording overrides. Uncomment a line to change it; anything left
    // out uses the built-in default (COPY_DEFAULTS in site-core.js).
    copy: {
        // navBook:                "Book",                 // nav link to the booking section
        // navAsk:                 "Ask",                  // nav link to the Q&A section
        // testimonialsEmpty:      "Kind words from the people I work with will be shared here soon.",
        // notesPlaceholder:       "Anything that helps me prepare: timing, access, special requests.",
        // roleExample:            "Client · 2 years",     // example in the review form
        // ratesIntro:             "Rates vary depending on the service, hours, and any special requirements. ...",
        // rateDetailsPlaceholder: "What you need, how often, any special considerations...",
        // rateNotesExample:       "+$10 for weekends",    // example in the admin's rate notes
    },

    // ── Default content ───────────────────────────────────────────────────────
    // Shown before the admin has saved anything to Firestore.

    defaultServices: [
        { id: 'service1', icon: '✨', name: 'Service One',   desc: 'Describe what you offer here. One or two sentences is plenty — focus on the outcome for the client.', active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'service2', icon: '🌿', name: 'Service Two',   desc: 'A second offering, with a short description that makes it clear who this is for and what they get.',  active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'service3', icon: '🤝', name: 'Service Three', desc: 'Add or remove services from the admin panel — these defaults are placeholders to get the page rendering.', active: true, rate: { base: '', min: '', notes: '', public: false } },
    ],

    // Real testimonials are approved in the admin panel. Leave this empty: the
    // public page holds the space with a short note until the first one is published.
    defaultTestimonials: [],

    defaultFaq: [
        { q: 'What services do you offer?',     a: 'Edit this answer in the admin panel under FAQ. Visitors expect a quick, clear summary of what you do.' },
        { q: 'How does scheduling work?',       a: 'Use the calendar to mark days you\'re available. Visitors can request bookings on those days from the public site.' },
        { q: 'How does payment work?',          a: 'Add your Venmo, Cash App, or Zelle handle in the admin Settings tab — the matching buttons will appear in the Pay section.' },
    ],
};
