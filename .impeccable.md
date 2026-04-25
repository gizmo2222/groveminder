## Design Context

### Project
Holly Poppins — a personal booking and availability site for a nanny, pet sitter, and childcare provider. Deployed at hollypoppins.com. Built as a reusable template (single `site-config.js` to customise per instance).

### Users
**Visitors (public site):** Families and pet owners seeking trusted in-home care. They are emotionally invested — these are their children and pets. The primary job is to feel confident enough to reach out and book. Trust, warmth, and credibility are the deciding factors.

**Admin (Holly):** A solo operator managing her own small business. She needs a calm, efficient panel for handling availability, bookings, testimonials, and settings — not a complex dashboard.

### Brand Personality
**Three words: Warm, magical, trustworthy.**

The tagline is "Perfectly Imperfect and That's OK" — warm, self-aware, and disarming. The brand leans into the personal over the polished: enchanting but dependable, honest but warm. It should feel like receiving a handwritten note from someone exceptional, not a form from a platform.

### Aesthetic Direction
- **Palette:** Navy (`#1B2A4A`) as the grounding foundation; gold (`#D4AF37`) as precious accent, used sparingly; cream (`#FAF6ED`) as the warm background. These are already locked in — treat them as immovable.
- **Typography:** Playfair Display for display, headings, and anything that should feel considered or elegant. Lato for all UI, body copy, and functional text. Italic Playfair for taglines and pull quotes.
- **Spatial rhythm:** Generous. Wide section padding (5.5rem), breathing room inside cards. Never pack elements tightly.
- **Motion:** Subtle and purposeful — the floating umbrella animation is the brand's personality in code. Hover effects lift cards and buttons slightly (`translateY(-2px)` to `-3px`). Nothing bouncy, nothing aggressive.
- **No references** — the current direction is the reference. Evolve from within the system.

### What This Should NOT Look Like
- **Corporate or cold** — never feel like a staffing agency, HR platform, or marketplace. No hard edges, no clinical white, no grid-heavy data layouts.
- **Cluttered or busy** — one focal point per section. Let silence do work.
- **Generic or template-y** — every detail should feel chosen, not defaulted. The serif headlines, the gold dividers, the cream background — these are signals of care.

### Design Tokens (established, do not change without strong reason)
```
--navy:      #1B2A4A   (primary dark, backgrounds, text on light)
--navy-light:#2C3E6B   (hover states, gradient partner)
--gold:      #D4AF37   (accent, CTAs, active states, dividers)
--gold-light:#F0D060   (hover on gold elements)
--cream:     #FAF6ED   (page background, card backgrounds)
--white:     #FFFFFF   (card surfaces on cream backgrounds)
--text:      #2D2D2D   (body copy)
--text-light:#666666   (secondary text, hints, metadata)
--danger:    #e74c3c
--success:   #27ae60

Font stack: 'Playfair Display' (headings), 'Lato' (body/UI)
Border radius: 4px (inputs/buttons), 8–10px (cards), 12px (prominent cards)
Content max-width: 1100px (public), 860px (admin)
Card shadow: 0 4px 20px rgba(27,42,74,0.08)
```

### Design Principles

1. **Trust through restraint.** Visitors are deciding whether to invite this person into their home. Clutter creates doubt; space creates confidence. When in doubt, remove rather than add.

2. **Personal over institutional.** Serif type, warm backgrounds, handcrafted details. Every touch point should feel like it came from a person, not a product team.

3. **Gold is precious — spend it carefully.** Gold signals "this matters." CTAs, active states, dividers, hover highlights. The moment it appears everywhere, it means nothing.

4. **Magical detail, practical function.** The charm lives in small decisions — italic taglines, a floating umbrella, a warm success message. Not in complexity, decoration, or animation for its own sake.

5. **Warmth at every touch point.** Copy, spacing, and interaction design should feel like the experience of meeting Holly in person: warm, unhurried, and quietly excellent.
