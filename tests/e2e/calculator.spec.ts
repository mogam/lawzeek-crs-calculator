import { expect, test, type Page } from '@playwright/test';
import { BASE, wpJson, wpEval } from './wp';

// Lawzeek CRS Calculator on the test page created by tests/bin/setup.php.
const PAGE = new URL('/crs-calculator-test/', BASE).toString();

/** Reads a saved lead straight from the WordPress database (no admin login needed). */
function leadByEmail(email: string): Record<string, string> | null {
  return wpJson(`$r = Lzcrs_Db::by_email(${JSON.stringify(email)}); echo 'LZJSON:' . wp_json_encode( $r ? $r[0] : null );`);
}

/** Sets the booking link (or clears it) straight in WordPress. */
function setBooking(url: string, mode = 'button') {
  wpEval(`$s = Lzcrs_Settings::get(); $s['cta_url'] = ${JSON.stringify(url)}; $s['cta_mode'] = ${JSON.stringify(mode)}; $s['cta_text'] = ''; update_option( 'lzcrs_settings', $s );`);
}

async function fillToContact(page: Page) {
  const calc = page.locator('[data-lzcrs]');
  const title = calc.locator('.lzcrs-title');
  const next = calc.getByRole('button', { name: 'Next' });
  await expect(title).toHaveText('About you');
  await calc.getByLabel('Your age').fill('29');
  await next.click();
  await expect(title).toHaveText('Education');
  await expect(title).toBeFocused(); // focus moves to the new step for keyboard and screen-reader users
  await calc.getByLabel('Highest education').selectOption('bachelor');
  await next.click();
  await calc.getByLabel('Listening').selectOption('7.5');
  for (const s of ['Reading', 'Writing', 'Speaking']) await calc.getByLabel(s).selectOption('6.5');
  await next.click();
  await calc.getByLabel('Skilled work experience in Canada').selectOption('1');
  await calc.getByLabel('Skilled work experience outside Canada').selectOption('3');
  await next.click();
  await expect(title).toHaveText('A few extras');
  await next.click();
  await expect(title).toHaveText('Your result is ready');
  return calc;
}

test('a visitor gets a CRS score and the lead is saved in WordPress, with no outside requests', async ({ page }) => {
  const hosts = new Set<string>();
  page.on('request', (r) => { if (/^https?:/.test(r.url())) hosts.add(new URL(r.url()).host); }); // data: URLs are not network requests
  const email = `free.${Date.now()}@example.com`;

  await page.goto(PAGE);
  await expect(page.locator('.lzcrs-kicker')).toContainText('Check your Canada PR chances');
  const calc = await fillToContact(page);

  // Validation before anything is sent
  await calc.getByRole('button', { name: 'Show my score' }).click();
  await expect(calc.getByRole('alert')).toHaveText('Enter your name.');
  await calc.getByLabel('Full name').fill('Free Plugin Visitor');
  await calc.getByLabel('Email').fill(email);
  await calc.getByRole('button', { name: 'Show my score' }).click();
  await expect(calc.getByRole('alert')).toContainText('Tick the box');
  await calc.getByRole('checkbox', { name: /may use my answers/ }).check();
  await calc.getByRole('checkbox', { name: /Send me updates/ }).check();
  await calc.getByRole('button', { name: 'Show my score' }).click();

  await expect(calc.locator('.lzcrs-title')).toHaveText('Your CRS estimate');
  await expect(calc.locator('[data-lzcrs-total]')).toContainText('438');
  await expect(calc.locator('.lzcrs-pill')).toHaveText('62 below the recent cutoff of 500');
  await expect(calc.locator('.lzcrs-gaps li').first()).toContainText('Pass a French test at NCLC 7');
  await expect(calc.locator('.lzcrs-gaps')).toContainText('reaches the cutoff');
  await calc.getByText('Full breakdown').click();
  await expect(calc.getByRole('row', { name: /Core human capital/ })).toContainText('362');

  const lead = leadByEmail(email);
  expect(lead).toMatchObject({ name: 'Free Plugin Visitor', total: '438', band: 'cold', consent_marketing: '1', page_url: PAGE });
  expect([...hosts]).toEqual([new URL(BASE).host]); // no CDN, fonts, analytics or Lawzeek calls

  await calc.getByRole('button', { name: 'Start over' }).click();
  await expect(calc.locator('.lzcrs-title')).toHaveText('About you');
});

test('the calculator works on a phone without sideways scrolling', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 740 });
  await page.goto(PAGE);
  await fillToContact(page);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  const box = await page.locator('.lzcrs-card').boundingBox();
  expect(box!.width).toBeLessThanOrEqual(375);
});

test('an age outside 16–80 is caught before moving on', async ({ page }) => {
  await page.goto(PAGE);
  const calc = page.locator('[data-lzcrs]');
  await calc.getByLabel('Your age').fill('12');
  await calc.getByRole('button', { name: 'Next' }).click();
  await expect(calc.getByRole('alert')).toHaveText('Enter an age between 16 and 80.');
  await expect(calc.locator('.lzcrs-title')).toHaveText('About you');
});

test('the REST endpoint rejects bad data and quietly drops honeypot spam', async ({ request }) => {
  const answers = { age: 29, edu: 'bachelor', caEdu: 'none', L: 7.5, R: 6.5, W: 6.5, S: 6.5, fr: 'none', caExp: 1, frExp: 3, sibling: false, pnp: false };
  const url = new URL('/wp-json/lzcrs/v1/submit', BASE).toString();
  const bad = await request.post(url, { data: { answers: { ...answers, age: 'old' }, name: 'X', email: 'x@example.com', privacy: true } });
  expect(bad.status()).toBe(422);
  const email = `bot.${Date.now()}@example.com`;
  const spam = await request.post(url, { data: { answers, name: 'Bot', email, privacy: true, marketing: false, website: 'http://spam.example' } });
  expect(spam.status()).toBe(200);
  expect((await spam.json()).total).toBe(438);
  expect(leadByEmail(email)).toBeNull();
});


test('the last step offers the consultant\'s booking link, and the calculator follows the theme', async ({ page }) => {
  setBooking('https://calendly.com/lawzeek-demo/30min');
  try {
    await page.goto(PAGE);
    const calc = page.locator('[data-lzcrs]');
    await expect(calc).toHaveAttribute('data-lzcrs-theme', 'button'); // read the theme's own button style
    const calc2 = await fillToContact(page);
    await calc2.getByLabel('Full name').fill('Booking Visitor');
    await calc2.getByLabel('Email').fill(`book.${Date.now()}@example.com`);
    await calc2.getByRole('checkbox', { name: /may use my answers/ }).check();
    await calc2.getByRole('button', { name: 'Show my score' }).click();
    const book = calc2.getByRole('region', { name: 'Next step' });
    await expect(book).toContainText('Talk to a licensed consultant');
    const link = book.getByRole('link', { name: 'Book a consultation' });
    await expect(link).toHaveAttribute('href', 'https://calendly.com/lawzeek-demo/30min');
    await expect(link).toHaveAttribute('target', '_blank');
    await expect(book.locator('iframe')).toHaveCount(0); // button mode: nothing from Calendly loads
  } finally {
    setBooking('');
  }
});
