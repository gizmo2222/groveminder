// Firestore rules tests. Run from tests\ with: npm run test:rules
// Uses the local emulator and a demo- project id, so no live project is touched.
import { readFileSync } from 'node:fs';
import { test, before, after, beforeEach } from 'node:test';
import {
  initializeTestEnvironment, assertSucceeds, assertFails,
} from '@firebase/rules-unit-testing';
import {
  doc, getDoc, setDoc, updateDoc, deleteDoc, addDoc, collection,
  serverTimestamp, Timestamp,
} from 'firebase/firestore';

let env;

before(async () => {
  env = await initializeTestEnvironment({
    projectId: 'demo-groveminder',
    firestore: {
      rules: readFileSync(new URL('../firestore.rules', import.meta.url), 'utf8'),
      host: '127.0.0.1',
      port: 8085,
    },
  });
});
after(async () => { await env.cleanup(); });

beforeEach(async () => {
  await env.clearFirestore();
  await env.withSecurityRulesDisabled(async (ctx) => {
    const db = ctx.firestore();
    await setDoc(doc(db, 'admins/boss'), { note: 'operator' });
    await setDoc(doc(db, 'config/settings'), { siteName: 'Test' });
    await setDoc(doc(db, 'private/calendar'), { icalUrl: 'https://example.test/secret.ics' });
    await setDoc(doc(db, 'bookings/b1'), { name: 'A', status: 'pending' });
    await setDoc(doc(db, 'testimonials/t1'), { name: 'A', approved: false });
  });
});

const anon = () => env.unauthenticatedContext().firestore();
const user = () => env.authenticatedContext('stranger').firestore();
const admin = () => env.authenticatedContext('boss').firestore();

const booking = (over = {}) => ({
  name: 'Ada', email: 'ada@example.test', phone: '0123', service: 'Garden visit',
  date: '2026-12-01', time: 'Morning', notes: 'Back gate', status: 'pending',
  createdAt: serverTimestamp(), ...over,
});
const testimonial = (over = {}) => ({
  name: 'Ada', role: 'Neighbour', quote: 'Lovely work.', stars: 5,
  approved: false, createdAt: serverTimestamp(), ...over,
});
const without = (obj, ...keys) => {
  const o = { ...obj };
  for (const k of keys) delete o[k];
  return o;
};
const addBooking = (data) => addDoc(collection(anon(), 'bookings'), data);
const addTestimonial = (data) => addDoc(collection(anon(), 'testimonials'), data);

// ── admins ──
test('admins: a user reads their own entry', () =>
  assertSucceeds(getDoc(doc(admin(), 'admins/boss'))));
test('admins: a user cannot read another entry', () =>
  assertFails(getDoc(doc(user(), 'admins/boss'))));
test('admins: nobody can write, not even an admin', () =>
  assertFails(setDoc(doc(admin(), 'admins/stranger'), { note: 'x' })));
test('admins: a user cannot make themselves an admin', () =>
  assertFails(setDoc(doc(user(), 'admins/stranger'), { note: 'x' })));

// ── config ──
test('config: anyone reads settings', () =>
  assertSucceeds(getDoc(doc(anon(), 'config/settings'))));
test('config: an admin writes settings', () =>
  assertSucceeds(setDoc(doc(admin(), 'config/settings'), { siteName: 'New' })));
test('config: a signed-in non-admin cannot write', () =>
  assertFails(setDoc(doc(user(), 'config/settings'), { siteName: 'Hacked' })));
test('config: an anonymous visitor cannot write', () =>
  assertFails(setDoc(doc(anon(), 'config/settings'), { siteName: 'Hacked' })));
test('config: the secret iCal address cannot be stored in public settings', () =>
  assertFails(setDoc(doc(admin(), 'config/settings'),
    { siteName: 'New', icalUrl: 'https://example.test/s.ics' })));

// ── private ──
test('private: an admin reads and writes', async () => {
  await assertSucceeds(getDoc(doc(admin(), 'private/calendar')));
  await assertSucceeds(setDoc(doc(admin(), 'private/calendar'),
    { icalUrl: 'https://example.test/new.ics' }));
});
test('private: a signed-in non-admin cannot read', () =>
  assertFails(getDoc(doc(user(), 'private/calendar'))));
test('private: an anonymous visitor cannot read', () =>
  assertFails(getDoc(doc(anon(), 'private/calendar'))));
test('private: a signed-in non-admin cannot write', () =>
  assertFails(setDoc(doc(user(), 'private/calendar'), { icalUrl: 'x' })));

// ── testimonials ──
test('testimonials: a visitor submits a well-formed entry', () =>
  assertSucceeds(addTestimonial(testimonial())));
test('testimonials: role is optional', () =>
  assertSucceeds(addTestimonial(without(testimonial(), 'role'))));
test('testimonials: a visitor cannot self-approve', () =>
  assertFails(addTestimonial(testimonial({ approved: true }))));
test('testimonials: extra fields are rejected', () =>
  assertFails(addTestimonial(testimonial({ html: '<b>x</b>' }))));
test('testimonials: a required field is missing', () =>
  assertFails(addTestimonial(without(testimonial(), 'quote'))));
test('testimonials: stars outside 1 to 5, or not whole', async () => {
  await assertFails(addTestimonial(testimonial({ stars: 6 })));
  await assertFails(addTestimonial(testimonial({ stars: 0 })));
  await assertFails(addTestimonial(testimonial({ stars: 4.5 })));
});
test('testimonials: empty name or oversized text', async () => {
  await assertFails(addTestimonial(testimonial({ name: '' })));
  await assertFails(addTestimonial(testimonial({ name: 'x'.repeat(101) })));
  await assertFails(addTestimonial(testimonial({ role: 'x'.repeat(101) })));
  await assertFails(addTestimonial(testimonial({ quote: 'x'.repeat(2001) })));
});
test('testimonials: a quote at the size limit is accepted', () =>
  assertSucceeds(addTestimonial(testimonial({ quote: 'x'.repeat(2000) }))));
test('testimonials: a client-chosen timestamp is rejected', () =>
  assertFails(addTestimonial(testimonial({ createdAt: Timestamp.fromMillis(0) }))));
test('testimonials: visitors cannot read, update or delete', async () => {
  await assertFails(getDoc(doc(anon(), 'testimonials/t1')));
  await assertFails(updateDoc(doc(anon(), 'testimonials/t1'), { approved: true }));
  await assertFails(updateDoc(doc(user(), 'testimonials/t1'), { approved: true }));
  await assertFails(deleteDoc(doc(user(), 'testimonials/t1')));
});
test('testimonials: an admin reads, approves and deletes', async () => {
  await assertSucceeds(getDoc(doc(admin(), 'testimonials/t1')));
  await assertSucceeds(updateDoc(doc(admin(), 'testimonials/t1'), { approved: true }));
  await assertSucceeds(deleteDoc(doc(admin(), 'testimonials/t1')));
});

// ── bookings ──
test('bookings: a visitor submits a well-formed request', () =>
  assertSucceeds(addBooking(booking())));
test('bookings: optional fields may be left out', () =>
  assertSucceeds(addBooking(without(booking(), 'phone', 'time', 'notes'))));
test('bookings: a visitor cannot pre-confirm', () =>
  assertFails(addBooking(booking({ status: 'confirmed' }))));
test('bookings: extra fields are rejected', () =>
  assertFails(addBooking(booking({ price: 0 }))));
test('bookings: a required field is missing', () =>
  assertFails(addBooking(without(booking(), 'email'))));
test('bookings: malformed email or date', async () => {
  await assertFails(addBooking(booking({ email: 'not-an-email' })));
  await assertFails(addBooking(booking({ email: 'a b@c.d' })));
  await assertFails(addBooking(booking({ date: 'tomorrow' })));
  await assertFails(addBooking(booking({ date: 20261201 })));
});
test('bookings: oversized fields', async () => {
  await assertFails(addBooking(booking({ name: 'x'.repeat(101) })));
  await assertFails(addBooking(booking({ email: 'a@b.' + 'c'.repeat(200) })));
  await assertFails(addBooking(booking({ phone: '1'.repeat(41) })));
  await assertFails(addBooking(booking({ service: 'x'.repeat(101) })));
  await assertFails(addBooking(booking({ time: 'x'.repeat(21) })));
  await assertFails(addBooking(booking({ notes: 'x'.repeat(2001) })));
});
test('bookings: a client-chosen timestamp is rejected', () =>
  assertFails(addBooking(booking({ createdAt: Timestamp.fromMillis(0) }))));
test('bookings: visitors cannot read, update or delete', async () => {
  await assertFails(getDoc(doc(anon(), 'bookings/b1')));
  await assertFails(getDoc(doc(user(), 'bookings/b1')));
  await assertFails(updateDoc(doc(user(), 'bookings/b1'), { status: 'confirmed' }));
  await assertFails(deleteDoc(doc(anon(), 'bookings/b1')));
});
test('bookings: an admin reads, confirms and deletes', async () => {
  await assertSucceeds(getDoc(doc(admin(), 'bookings/b1')));
  await assertSucceeds(updateDoc(doc(admin(), 'bookings/b1'), { status: 'confirmed' }));
  await assertSucceeds(deleteDoc(doc(admin(), 'bookings/b1')));
});

// ── everything else ──
test('catch-all: unknown collections are closed, even to an admin', async () => {
  await assertFails(getDoc(doc(admin(), 'other/x')));
  await assertFails(setDoc(doc(admin(), 'other/x'), { a: 1 }));
  await assertFails(setDoc(doc(anon(), 'other/x'), { a: 1 }));
});
test('catch-all: a known collection still works for an admin (control)', () =>
  assertSucceeds(getDoc(doc(admin(), 'config/settings'))));
