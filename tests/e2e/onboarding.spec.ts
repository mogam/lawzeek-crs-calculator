import { expect, test, type BrowserContext } from '@playwright/test';
import { BASE, wpEval, wpJson } from './wp';

/**
 * Signs the browser in as the first administrator with cookies made by WordPress itself
 * (no password is typed or stored). The session ends when the cookies expire in one hour.
 */
async function signInAsAdmin(context: BrowserContext) {
  const c = wpJson<{ auth: string; authName: string; loggedIn: string; loggedInName: string; adminPath: string; sitePath: string }>(`
    $u = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
    $id = $u[0]->ID; $exp = time() + HOUR_IN_SECONDS;
    echo 'LZJSON:' . wp_json_encode( array(
      'auth' => wp_generate_auth_cookie( $id, $exp, 'auth' ), 'authName' => AUTH_COOKIE,
      'loggedIn' => wp_generate_auth_cookie( $id, $exp, 'logged_in' ), 'loggedInName' => LOGGED_IN_COOKIE,
      'adminPath' => ADMIN_COOKIE_PATH, 'sitePath' => COOKIEPATH,
    ) );`);
  const domain = new URL(BASE).hostname;
  await context.addCookies([
    { name: c.authName, value: c.auth, domain, path: c.adminPath, httpOnly: true },
    { name: c.loggedInName, value: c.loggedIn, domain, path: c.sitePath, httpOnly: true },
  ]);
}

/** Saves the current settings and setup state so each test can put them back. */
function snapshot(): string {
  return wpJson<string>(`echo 'LZJSON:' . wp_json_encode( wp_json_encode( array( get_option( 'lzcrs_settings' ), get_option( 'lzcrs_onboarded', false ) ) ) );`);
}
function restore(saved: string) {
  wpEval(`$s = json_decode( ${JSON.stringify(saved)}, true ); update_option( 'lzcrs_settings', $s[0] ); false === $s[1] ? delete_option( 'lzcrs_onboarded' ) : update_option( 'lzcrs_onboarded', $s[1] );`);
}

test('after activation the admin is taken to the setup guide once', async ({ page, context }) => {
  const saved = snapshot();
  try {
    await signInAsAdmin(context);
    wpEval(`delete_option( 'lzcrs_onboarded' ); Lzcrs_Onboarding::activate();`);
    await page.goto('/wp-admin/');
    await expect(page).toHaveURL(/page=lzcrs-onboarding/);
    await expect(page.getByRole('heading', { name: 'Welcome to Lawzeek' })).toBeVisible();
    await page.goto('/wp-admin/');
    await expect(page).not.toHaveURL(/page=lzcrs-onboarding/); // only once
  } finally {
    restore(saved);
  }
});

test('the three setup steps save the basic details and open the dashboard', async ({ page, context }) => {
  const saved = snapshot();
  try {
    await signInAsAdmin(context);
    wpEval(`delete_option( 'lzcrs_onboarded' );`);
    await page.goto('/wp-admin/admin.php?page=lzcrs-onboarding');
    const steps = page.getByRole('list', { name: 'Setup steps' });
    await expect(steps.locator('[aria-current="step"]')).toContainText('Step 1: Welcome');
    await page.getByRole('link', { name: "Let's get started" }).click();

    await expect(page.getByRole('heading', { name: 'Built for immigration consultants' })).toBeVisible();
    await expect(steps.locator('[aria-current="step"]')).toContainText('Step 2: Benefits');
    await page.getByRole('link', { name: 'Continue' }).click();

    await expect(page.getByRole('heading', { name: 'Get the best start' })).toBeVisible();
    await page.getByLabel('Email for new leads').fill('not an email');
    await page.getByLabel('Recent Express Entry cutoff').fill('515');
    await page.getByRole('button', { name: 'Finish setup' }).click();
    await expect(page.getByRole('alert')).toContainText('not valid'); // stays on the form and explains
    await expect(page).toHaveURL(/step=3/);

    await page.getByLabel('Email for new leads').fill('owner@lzcrs.test');
    await page.getByLabel('Booking link').fill('https://calendly.com/lawzeek-demo/30min');
    await page.getByLabel('Recent Express Entry cutoff').fill('515');
    await page.getByRole('button', { name: 'Finish setup' }).click();

    await expect(page).toHaveURL(/page=lzcrs-dashboard/);
    await expect(page.getByTestId('lzcrs-welcome')).toBeVisible();
    await expect(page.getByTestId('lzcrs-shortcode')).toHaveText('[lawzeek_crs]');
    const s = wpJson<{ emails: string; cta_url: string; cutoff: number; done: boolean }>(
      `$s = Lzcrs_Settings::get(); echo 'LZJSON:' . wp_json_encode( array( 'emails' => $s['emails'], 'cta_url' => $s['cta_url'], 'cutoff' => (int) $s['cutoff'], 'done' => (bool) get_option( 'lzcrs_onboarded' ) ) );`
    );
    expect(s).toEqual({ emails: 'owner@lzcrs.test', cta_url: 'https://calendly.com/lawzeek-demo/30min', cutoff: 515, done: true });
  } finally {
    restore(saved);
  }
});

test('Exit setup skips the guide and works on a phone without sideways scrolling', async ({ page, context }) => {
  const saved = snapshot();
  try {
    await signInAsAdmin(context);
    wpEval(`delete_option( 'lzcrs_onboarded' );`);
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/wp-admin/admin.php?page=lzcrs-onboarding&step=2');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.getByRole('link', { name: 'Exit setup' }).click();
    await expect(page).toHaveURL(/page=lzcrs-dashboard/);
    expect(wpJson<boolean>(`echo 'LZJSON:' . wp_json_encode( (bool) get_option( 'lzcrs_onboarded' ) );`)).toBe(true);
  } finally {
    restore(saved);
  }
});
