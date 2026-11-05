// ─────────────────────────────────────────────────────────────────────────────
// site-config.demo.js — the public GroveMinder demo (a fictional business).
// The deploy uploads this file as site-config.js to metacrystal.com/groveminder/.
// `demo: true` switches on demo-firebase.js: no backend, data stays in the
// visitor's browser, no emails sent. For a real site, edit site-config.js.
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

    // ── Demo mode ─────────────────────────────────────────────────────────────
    demo: true,

    // ── Identity ──────────────────────────────────────────────────────────────
    siteName: "Wren's Garden Care",
    navEmoji: "🌿",                              // shown in nav + hero + footer
    tagline:  "Gardens tended like they're my own",
    heroDesc: "Garden upkeep, seasonal planting and plant-sitting for busy households — one careful gardener, booked when it suits you.",

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

    // ── Default content ───────────────────────────────────────────────────────
    // Shown before the admin has saved anything to Firestore.

    defaultServices: [
        { id: 'tidy',     icon: '🌿', name: 'Garden Tidy-Up',     desc: 'Weeding, edging, pruning and a proper clear-up, so the garden looks cared for again.', active: true, rate: { base: '45', min: '2', notes: 'Green waste taken away.', public: true } },
        { id: 'planting', icon: '🌷', name: 'Seasonal Planting',  desc: 'Bulbs, bedding and pots planned for the season, planted and watered in.',               active: true, rate: { base: '45', min: '2', notes: 'Plants billed at cost.', public: true } },
        { id: 'sitting',  icon: '🪴', name: 'Plant Sitting',      desc: 'Indoor and outdoor plants watered and checked while you are away, with photo updates.', active: true, rate: { base: '25', min: '1', notes: 'Per visit.', public: true } },
        { id: 'lawn',     icon: '🌱', name: 'Lawn Care',          desc: 'Mowing, feeding and patch repair on a schedule that fits your lawn.',                  active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'design',   icon: '📐', name: 'Garden Planning',    desc: 'A walk-round and a simple plan for what to grow where, and when.',                       active: true, rate: { base: '', min: '', notes: '', public: false } },
    ],

    defaultTestimonials: [],

    // Seeded into the demo's settings on first visit (fictional)
    demoTestimonials: [
        { name: 'The Okafor family', role: 'Garden tidy-up · monthly', quote: 'Our garden went from embarrassing to lovely in one morning, and it has stayed that way.', stars: 5 },
        { name: 'Sam P.',            role: 'Plant sitting',            quote: 'Two weeks away and every plant was thriving when we got back. The photos were a nice touch.', stars: 5 },
        { name: 'Linda & Ray',       role: 'Seasonal planting',        quote: 'Thoughtful about what would actually grow in our shady corner. Spring was a picture.', stars: 5 },
    ],

    defaultFaq: [
        { q: 'Which areas do you cover?',      a: 'Anywhere within about 20 minutes of town. Further afield is possible for regular work — just ask.' },
        { q: 'Do I need to be home?',          a: 'No. As long as I can reach the garden (or have a key for plant sitting), you are free to be out.' },
        { q: 'What do you bring?',             a: 'All tools, compost and bags for green waste. Plants and bulbs are bought for you and billed at cost.' },
        { q: 'How does payment work?',         a: 'Pay by Venmo or Zelle after each visit — the buttons are in the Pay section.' },
    ],
};
