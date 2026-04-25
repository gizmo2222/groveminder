// ─────────────────────────────────────────────────────────────────────────────
// site-config.js — edit this file to set up a new site instance
// ─────────────────────────────────────────────────────────────────────────────

const SITE_CONFIG = {

    // ── Firebase ──────────────────────────────────────────────────────────────
    // Create a project at https://console.firebase.google.com and paste your
    // web app config object here.
    firebase: {
        apiKey:            "AIzaSyB5izE8G_gXRRW-RRt54bT6eyqxaxP-14U",
        authDomain:        "holly-poppins.firebaseapp.com",
        projectId:         "holly-poppins",
        storageBucket:     "holly-poppins.firebasestorage.app",
        messagingSenderId: "642721769952",
        appId:             "1:642721769952:web:602735a6e48903612e2385"
    },

    // ── Identity ──────────────────────────────────────────────────────────────
    siteName: "Holly Poppins",
    navEmoji: "☂",                              // shown in nav + hero + footer
    tagline:  "Perfectly Imperfect and That's OK",
    heroDesc: "Trusted, caring, and dependable — available for nannying, pet sitting, and more. Let's find a time that works perfectly for your family.",

    // ── Theme colors ──────────────────────────────────────────────────────────
    // These override the CSS custom properties, so the stylesheet never needs editing.
    colors: {
        navy:      "#1B2A4A",
        navyLight: "#2C3E6B",
        gold:      "#D4AF37",
        goldLight: "#F0D060",
        cream:     "#FAF6ED",
    },

    // ── Default content ───────────────────────────────────────────────────────
    // Shown before the admin has saved anything to Firestore.

    defaultServices: [
        { id: 'nanny',      icon: '👶', name: 'Nanny & Childcare',       desc: 'Attentive, nurturing care for children of all ages — meals, activities, homework help, and bedtime routines.', active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'petsitting', icon: '🐾', name: 'Pet Sitting',             desc: "Loving, attentive care for your pets while you're away — feeding, walks, playtime, and plenty of cuddles.",    active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'activities', icon: '🎨', name: 'Activities & Enrichment', desc: 'Creative play, arts & crafts, outdoor adventures, and age-appropriate educational experiences.',                active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'household',  icon: '🏠', name: 'Household Support',       desc: 'Light tidying, meal prep, school pickups, and errands to keep the family day running smoothly.',                 active: true, rate: { base: '', min: '', notes: '', public: false } },
        { id: 'tutoring',   icon: '📚', name: 'Tutoring',                desc: 'Patient, encouraging academic support across subjects — homework help, test prep, and building study skills.',   active: true, rate: { base: '', min: '', notes: '', public: false } },
    ],

    defaultTestimonials: [
        { name: 'The Harrison Family', role: 'Nanny Client · 3 years', quote: 'Holly has been an absolute blessing for our family. Reliable, warm, and wonderful with our children — we could not recommend her more highly.', stars: 5 },
        { name: 'Amanda & Tom R.',     role: 'Pet Sitting Client',      quote: 'Our dog absolutely adores her. We never worry when Holly is on the job — she sends updates and treats our pup like her own.',                    stars: 5 },
        { name: 'Jessica M.',          role: 'Childcare Client',        quote: 'Dependable, caring, and great with kids of all ages. We felt completely at ease leaving our little ones in her care.',                          stars: 5 },
    ],

    defaultFaq: [
        { q: 'What ages do you work with?',              a: 'I work with children from infants through high school age. I also offer pet sitting for dogs, cats, and other small animals, and have experience with a wide range of personalities and needs.' },
        { q: 'What are your typical hours?',             a: "My availability varies week to week — the calendar on this page always reflects my current open dates. I'm generally available mornings and afternoons, with some evenings. Weekend availability is limited but possible." },
        { q: 'Do you do overnights or travel nannying?', a: 'Yes, on a case-by-case basis. Please include details in your booking request or reach out directly so we can discuss arrangements and rates.' },
        { q: 'Are you CPR / First Aid certified?',       a: "Yes — I hold current CPR and First Aid certification. Safety is always the top priority, and I'm happy to share documentation upon request." },
        { q: 'How does payment work?',                   a: 'Payment is accepted via Venmo or Cash App, typically at the end of each session. Recurring clients may arrange weekly invoicing. See the Pay section below for links.' },
        { q: 'How do I cancel or reschedule?',           a: "Life happens! Please give at least 24 hours notice when possible. Just reply to your confirmation email or use the Ask a Question form to reach me." },
    ],
};

// ── Apply theme colors immediately ────────────────────────────────────────────
// Runs before the page renders so there's no flash of the default palette.
(function () {
    const s = document.documentElement.style;
    const c = SITE_CONFIG.colors;
    if (c.navy)      s.setProperty('--navy',       c.navy);
    if (c.navyLight) s.setProperty('--navy-light', c.navyLight);
    if (c.gold)      s.setProperty('--gold',       c.gold);
    if (c.goldLight) s.setProperty('--gold-light', c.goldLight);
    if (c.cream)     s.setProperty('--cream',      c.cream);
})();

// ── Favicon (generated from navEmoji + colors, no static file needed) ─────────
(function () {
    const size = 64;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = SITE_CONFIG.colors.navy || '#1B2A4A';
    ctx.fillRect(0, 0, size, size);
    ctx.font = '44px serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(SITE_CONFIG.navEmoji || '☂', size / 2, size / 2 + 2);
    const link = document.createElement('link');
    link.rel = 'icon';
    link.href = canvas.toDataURL();
    document.head.appendChild(link);
})();
