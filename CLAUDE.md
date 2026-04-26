## Design Context

### Project
GroveMinder — a reusable, themable template for a personal booking and availability site. A single `site-config.js` customises identity, copy, default content, and theme; the admin panel (`/admin.html`) lets the operator manage the site at runtime, including switching between named colour themes saved to Firestore.

### Users
**Visitors (public site):** People considering booking a personal service from the operator. The primary job is to feel confident enough to reach out. Trust, warmth, and credibility are the deciding factors.

**Admin (the operator):** A solo operator managing a small business or practice. Needs a calm, efficient panel for handling availability, bookings, testimonials, services, FAQ, and appearance — not a complex dashboard.

### Brand Personality
**Three words: Warm, considered, trustworthy.**

The template should feel personal rather than corporate, considered rather than templated. Each detail (serif headlines, gold-style accents, soft backgrounds, a single emoji as identity mark) is meant to read as "chosen", not "defaulted".

### Aesthetic Direction
- **Themable palette.** The system uses six theme variables: `--primary`, `--primary-light`, `--primary-dark`, `--accent`, `--accent-light`, `--bg`. Five named presets ship in `site-config.js`: **Grove** (forest green + amber), **Coastal** (navy + gold), **Lavender** (plum + dusty rose), **Slate** (charcoal + emerald), **Terracotta** (rust + sun gold). New presets go in the `THEMES` object in `site-config.js`.
- **Typography:** Playfair Display for display, headings, and anything that should feel considered or elegant. Lato for all UI, body copy, and functional text. Italic Playfair for taglines and pull quotes.
- **Spatial rhythm:** Generous. Wide section padding (5.5rem), breathing room inside cards. Never pack elements tightly.
- **Motion:** Subtle and purposeful — the floating hero emoji is the personality in code. Hover effects lift cards and buttons slightly (`translateY(-2px)` to `-3px`). Nothing bouncy, nothing aggressive.
- **Theme-aware tints.** Hardcoded `rgba()` values are avoided — translucent shades use `color-mix(in srgb, var(--accent) X%, transparent)` so they re-tint correctly when the theme changes.

### What This Should NOT Look Like
- **Corporate or cold** — never feel like a staffing agency, HR platform, or marketplace. No hard edges, no clinical white, no grid-heavy data layouts.
- **Cluttered or busy** — one focal point per section. Let silence do work.
- **Generic or template-y** — even though this *is* a template, every detail should feel chosen.

### Design Tokens
```
--primary:       theme primary dark (text on light, dark backgrounds)
--primary-light: hover/gradient partner for --primary
--primary-dark:  darker variant for hero gradient and footer
--accent:        accent colour (CTAs, active states, dividers, hover highlights)
--accent-light:  hover on accent elements
--bg:            soft warm page/card background
--white:         #FFFFFF (card surfaces on bg)
--text:          #2D2D2D (body copy)
--text-light:    #666666 (secondary text, hints, metadata)
--danger:        #e74c3c
--success:       #27ae60

Font stack: 'Playfair Display' (headings), 'Lato' (body/UI)
Border radius: 4px (inputs/buttons), 8–10px (cards), 12px (prominent cards)
Content max-width: 1100px (public), 860px (admin)
Card shadow: 0 4px 20px color-mix(in srgb, var(--primary) 8%, transparent)
```

### Design Principles

1. **Trust through restraint.** Visitors are deciding whether to invite this operator into their space. Clutter creates doubt; space creates confidence. When in doubt, remove rather than add.

2. **Personal over institutional.** Serif type, warm backgrounds, handcrafted details. Every touch point should feel like it came from a person, not a product team.

3. **Accent is precious — spend it carefully.** The accent colour signals "this matters." CTAs, active states, dividers, hover highlights. The moment it appears everywhere, it means nothing.

4. **Magical detail, practical function.** The charm lives in small decisions — italic taglines, a floating emoji, a warm success message. Not in complexity, decoration, or animation for its own sake.

5. **Theme without rewrites.** Anything visual should respond to the theme variables. Avoid hardcoded hex/rgba in component CSS — use `var(--*)` or `color-mix()` so changing themes from the admin panel actually changes the site.
