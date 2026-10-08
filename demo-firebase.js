// ─────────────────────────────────────────────────────────────────────────────
// demo-firebase.js — offline stand-in for Firebase, used only by the demo.
//
// Does nothing unless site-config.js sets `demo: true`. Then it replaces the
// small part of the Firebase compat API the pages use (Firestore get/set/add/
// update/delete, one where + orderBy + limit query, and email sign-in) with a
// version that keeps everything in this browser's localStorage, seeded with
// sample content. Emails and calendar sync are not sent; a short note says
// what would have happened. A banner explains the demo and offers a reset.
// ─────────────────────────────────────────────────────────────────────────────

(function () {
    if (typeof SITE_CONFIG === 'undefined' || !SITE_CONFIG.demo) return;

    const PREFIX = 'groveminder-demo:';
    const SEEDED = PREFIX + 'seeded';

    // ── Storage ──────────────────────────────────────────────────────────────
    function load(collection) {
        try { return JSON.parse(localStorage.getItem(PREFIX + collection)) || {}; }
        catch { return {}; }
    }
    function save(collection, docs) {
        try { localStorage.setItem(PREFIX + collection, JSON.stringify(docs)); } catch { /* storage full or blocked */ }
    }
    function newId() { return Math.random().toString(36).slice(2, 12); }

    // Sample content, relative to today so the calendar is never stale
    function seed() {
        const day = n => {
            const d = new Date();
            d.setDate(d.getDate() + n);
            return d.toISOString().slice(0, 10);
        };
        const now = Date.now();
        const available = [2, 3, 5, 8, 9, 10, 12, 15, 16, 17, 19, 22, 23, 24, 26, 29, 30].map(day);
        save('config', { settings: {
            email: 'hello@example.com',
            venmoHandle: 'wrens-garden-demo',
            cashappHandle: '',
            zelleHandle: 'hello@example.com',
            availableDates: available,
            icalUrl: '',
            theme: SITE_CONFIG.theme || 'grove',
            services: SITE_CONFIG.defaultServices,
            faq: SITE_CONFIG.defaultFaq,
            testimonials: SITE_CONFIG.demoTestimonials || [],
        }});
        save('bookings', {
            b1: { name: 'Priya S.', email: 'priya@example.com', phone: '', service: 'Seasonal Planting', date: available[0], time: '10:00', notes: 'Front beds only, please.', status: 'pending', createdAt: now - 3600e3 },
            b2: { name: 'Tom & Ellie', email: 'tom@example.com', phone: '555-0142', service: 'Plant Sitting', date: available[2], time: '', notes: 'Away for a week — 30 house plants!', status: 'pending', createdAt: now - 7200e3 },
            b3: { name: 'Marcus L.', email: 'marcus@example.com', phone: '', service: 'Garden Tidy-Up', date: available[1], time: '09:00', notes: '', status: 'confirmed', createdAt: now - 86400e3 },
        });
        save('testimonials', {
            t1: { name: 'Jo R.', role: 'Plant sitting · 2 trips', quote: 'Came home to happier plants than when I left. The photo updates were a lovely touch.', stars: 5, approved: false, createdAt: now - 5400e3 },
        });
        save('private', {});
        localStorage.setItem(SEEDED, '1');
    }
    try { if (!localStorage.getItem(SEEDED)) seed(); } catch { /* storage blocked: pages fall back to defaults */ }

    function resetDemo() {
        try {
            Object.keys(localStorage).filter(k => k.startsWith(PREFIX)).forEach(k => localStorage.removeItem(k));
        } catch { /* ignore */ }
        location.reload();
    }

    // ── Firestore stand-in ───────────────────────────────────────────────────
    function docSnap(id, data) {
        return { id, exists: data !== undefined, data: () => (data === undefined ? undefined : JSON.parse(JSON.stringify(data))) };
    }

    function querySnap(entries) {
        const docs = entries.map(([id, data]) => docSnap(id, data));
        return { docs, empty: docs.length === 0, size: docs.length, forEach: fn => docs.forEach(fn) };
    }

    // serverTimestamp() → a marker that becomes "now" when written
    const SERVER_TS = { __serverTimestamp: true };
    function resolve(data) {
        const out = {};
        for (const [k, v] of Object.entries(data)) out[k] = v === SERVER_TS ? Date.now() : v;
        return out;
    }

    function docRef(collection, id) {
        return {
            id,
            async get() { return docSnap(id, load(collection)[id]); },
            async set(data, opts) {
                const docs = load(collection);
                docs[id] = opts && opts.merge ? { ...(docs[id] || {}), ...resolve(data) } : resolve(data);
                save(collection, docs);
            },
            async update(data) {
                const docs = load(collection);
                if (!docs[id]) throw new Error('No document to update');
                docs[id] = { ...docs[id], ...resolve(data) };
                save(collection, docs);
            },
            async delete() {
                const docs = load(collection);
                delete docs[id];
                save(collection, docs);
            },
        };
    }

    function query(collection, filters = [], order = null, max = null) {
        return {
            where(field, op, value) {
                if (op !== '==') throw new Error('Demo supports only == filters');
                return query(collection, [...filters, [field, value]], order, max);
            },
            orderBy(field, dir = 'asc') { return query(collection, filters, [field, dir], max); },
            limit(n) { return query(collection, filters, order, n); },
            async get() {
                let entries = Object.entries(load(collection))
                    .filter(([, d]) => filters.every(([f, v]) => d[f] === v));
                if (order) {
                    const [f, dir] = order;
                    entries.sort(([, a], [, b]) => (a[f] > b[f] ? 1 : a[f] < b[f] ? -1 : 0) * (dir === 'desc' ? -1 : 1));
                }
                if (max != null) entries = entries.slice(0, max);
                return querySnap(entries);
            },
        };
    }

    const db = {
        collection(name) {
            return Object.assign(query(name), {
                doc: id => docRef(name, id || newId()),
                async add(data) {
                    const id = newId();
                    await docRef(name, id).set(data);
                    return docRef(name, id);
                },
            });
        },
    };

    // ── Auth stand-in: the demo admin is always signed in ────────────────────
    const DEMO_USER = { uid: 'demo', email: 'demo@groveminder.example', getIdToken: async () => 'demo' };
    const listeners = [];
    const auth = {
        currentUser: DEMO_USER,
        onAuthStateChanged(fn) { listeners.push(fn); setTimeout(() => fn(auth.currentUser), 0); return () => {}; },
        async signInWithEmailAndPassword() { auth.currentUser = DEMO_USER; listeners.forEach(fn => fn(DEMO_USER)); return { user: DEMO_USER }; },
        async signOut() { auth.currentUser = null; listeners.forEach(fn => fn(null)); },
    };

    const firestore = () => db;
    firestore.FieldValue = { serverTimestamp: () => SERVER_TS };
    window.firebase = { initializeApp() {}, firestore, auth: () => auth };

    // ── Server calls: nothing leaves the browser ─────────────────────────────
    const realFetch = window.fetch.bind(window);
    const json = (body, status = 200) =>
        new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    window.fetch = async (input, init) => {
        const url = typeof input === 'string' ? input : input.url;
        if (/mailer\.php/.test(url)) {
            let form = '';
            try { form = JSON.parse(init.body).form; } catch { /* ignore */ }
            note(`Demo: a real site would now send the "${form.replace(/_/g, ' ')}" email.`);
            return json({ ok: true });
        }
        if (/cal-push\.php/.test(url)) {
            note('Demo: a real site would add this booking to your calendar.');
            return json({ ok: true, skipped: true });
        }
        if (/ical-proxy\.php/.test(url)) {
            return new Response('Calendar sync is turned off in the demo.', { status: 503 });
        }
        return realFetch(input, init);
    };

    // ── Banner and notes ─────────────────────────────────────────────────────
    const style = document.createElement('style');
    style.textContent = `
      .gm-demo-bar{position:sticky;top:0;z-index:9999;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;justify-content:center;
        padding:.55rem 1rem;background:var(--primary-dark,#1A2E1A);color:#fff;font:600 .82rem/1.4 Lato,system-ui,sans-serif;text-align:center}
      .gm-demo-bar a,.gm-demo-bar button{color:var(--accent-light,#E0B070);font:inherit;background:none;border:0;padding:.35rem .2rem;cursor:pointer;text-decoration:underline}
      .gm-demo-bar a:focus-visible,.gm-demo-bar button:focus-visible{outline:2px solid var(--accent-light,#E0B070);outline-offset:2px;border-radius:3px}
      .gm-demo-note{position:fixed;left:50%;bottom:1.25rem;transform:translateX(-50%);z-index:10000;max-width:min(92vw,30rem);
        padding:.7rem 1rem;border-radius:8px;background:var(--primary,#2D4A2B);color:#fff;font:.85rem/1.4 Lato,system-ui,sans-serif;
        box-shadow:0 6px 24px rgba(0,0,0,.25);text-align:center}
    `;
    document.head.appendChild(style);

    let noteTimer;
    function note(text) {
        let el = document.querySelector('.gm-demo-note');
        if (!el) {
            el = document.createElement('div');
            el.className = 'gm-demo-note';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = text;
        el.hidden = false;
        clearTimeout(noteTimer);
        noteTimer = setTimeout(() => { el.hidden = true; }, 4500);
    }

    function addBanner() {
        const isAdmin = /admin\.html$/.test(location.pathname);
        const bar = document.createElement('div');
        bar.className = 'gm-demo-bar';
        bar.setAttribute('role', 'region');
        bar.setAttribute('aria-label', 'Demo notice');
        const msg = document.createElement('span');
        msg.textContent = isAdmin
            ? 'GroveMinder demo admin — no login needed. Changes stay in this browser.'
            : 'GroveMinder demo — a fictional business. Changes stay in this browser.';
        const link = document.createElement('a');
        link.href = isAdmin ? './' : 'admin.html';
        link.textContent = isAdmin ? 'View the public site →' : 'Try the admin panel →';
        const reset = document.createElement('button');
        reset.type = 'button';
        reset.textContent = 'Reset demo';
        reset.addEventListener('click', resetDemo);
        bar.append(msg, link, reset);
        document.body.prepend(bar);
    }
    if (document.body) addBanner();
    else document.addEventListener('DOMContentLoaded', addBanner);
})();
