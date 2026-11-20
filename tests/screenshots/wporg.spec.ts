import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import path from 'node:path';
import { BASE, wpEval, wpJson } from '../e2e/wp';

// Screenshots for the WordPress.org plugin page, in the order of "== Screenshots ==" in readme.txt.
const OUT = path.resolve(__dirname, '../../.wordpress-org');
const shot = (n: number) => path.join(OUT, `screenshot-${n}.png`);
const PAGE = '/crs-screenshot/';
const DEMO = '@example.org';

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

async function fillToContact(page: Page) {
  const calc = page.locator('[data-lzcrs]');
  const next = calc.getByRole('button', { name: 'Next' });
  await calc.getByLabel('Your age').fill('29');
  await next.click();
  await calc.getByLabel('Highest education').selectOption('masters');
  await next.click();
  await calc.getByLabel('Listening').selectOption('7.5');
  for (const s of ['Reading', 'Writing', 'Speaking']) await calc.getByLabel(s).selectOption('6.5');
  await next.click();
  await calc.getByLabel('Skilled work experience in Canada').selectOption('1');
  await calc.getByLabel('Skilled work experience outside Canada').selectOption('3');
  await next.click();
  await next.click();
  await expect(calc.locator('.lzcrs-title')).toHaveText('Your result is ready');
  return calc;
}

/** Hides admin notices from other plugins / WordPress so the screen shows only the plugin. */
async function tidy(page: Page) {
  await page.addStyleTag({ content: '#wpadminbar .ab-top-secondary, #wpadminbar li:not(#wp-admin-bar-wp-logo):not(#wp-admin-bar-site-name), .notice:not(.lzcrs-notice), .update-nag, .update-plugins, .awaiting-mod { display:none !important }' });
}

test('WordPress.org screenshots', async ({ page, context }) => {
  // Back up the settings and any existing leads; they are put back at the end.
  const backup = wpJson<string>(`global $wpdb; echo 'LZJSON:' . wp_json_encode( base64_encode( wp_json_encode( array( get_option( 'lzcrs_settings' ), $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', Lzcrs_Db::table() ), ARRAY_A ) ) ) ) );`);
  try {
  wpEval(`
    $p = get_page_by_path( 'crs-screenshot' );
    $d = array( 'post_title' => 'Express Entry CRS calculator', 'post_name' => 'crs-screenshot', 'post_status' => 'publish', 'post_type' => 'page',
      'post_content' => '<!-- wp:lawzeek/crs-calculator {"title":"Check your Canada PR chances"} /-->' );
    if ( $p ) { $d['ID'] = $p->ID; wp_update_post( $d ); } else { wp_insert_post( $d ); }
    global $wpdb; $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Lzcrs_Db::table() ) );
    $s = Lzcrs_Settings::get();
    $s['emails'] = 'leads@example.org';
    $s['cta_url'] = 'https://calendly.com/maple-immigration/consultation'; $s['cta_mode'] = 'button'; $s['cta_text'] = '';
    $s['cutoff'] = 489;
    update_option( 'lzcrs_settings', $s ); update_option( 'lzcrs_onboarded', time() );
    global $wpdb; $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE email LIKE %s', Lzcrs_Db::table(), '%${DEMO}' ) ); Lzcrs_Db::flush_cache();`);

  // 1. The calculator on a page.
  await page.goto(PAGE);
  const calc = page.locator('[data-lzcrs]');
  await expect(calc).toBeVisible();
  await page.screenshot({ path: shot(1), fullPage: true });

  // 2. The result with the booking step.
  await fillToContact(page);
  await calc.getByLabel('Full name').fill('Aarav Sharma');
  await calc.getByLabel('Email').fill(`aarav.sharma${DEMO}`);
  await calc.getByRole('checkbox', { name: /may use my answers/ }).check();
  await calc.getByRole('button', { name: 'Show my score' }).click();
  await expect(calc.locator('.lzcrs-title')).toHaveText('Your CRS estimate');
  await calc.screenshot({ path: shot(2) });

  // Demo leads over the last 30 days, cloned from the real one above with different names and scores.
  wpEval(`
    global $wpdb; $t = Lzcrs_Db::table();
    $base = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE email = %s', $t, 'aarav.sharma${DEMO}' ), ARRAY_A );
    $people = array( 'Priya Patel','Wei Zhang','Fatima Khan','Diego Ramirez','Olena Kovalenko','Arjun Mehta','Nguyen Thi Lan','Samuel Okafor','Maria Santos','Hamid Rahimi','Chen Li','Ayesha Siddiqui','Lucas Oliveira','Sofia Rossi','Rohan Gupta','Amira Haddad','Kwame Mensah','Elena Popescu','Ravi Iyer','Hana Kim','Tomas Novak','Zara Ahmed' );
    $statuses = array( 'new','new','contacted','booked','new','retained','contacted','new','lost','booked','new' );
    mt_srand( 7 );
    foreach ( $people as $i => $name ) {
      $row = $base; unset( $row['id'] );
      $total = mt_rand( 360, 540 );
      $row['name'] = $name;
      $row['email'] = strtolower( str_replace( ' ', '.', $name ) ) . '${DEMO}';
      $row['total'] = $total;
      $row['band'] = $total >= 480 ? 'hot' : ( $total >= 430 ? 'warm' : 'cold' );
      $row['status'] = $statuses[ $i % count( $statuses ) ];
      $row['created_at'] = gmdate( 'Y-m-d H:i:s', time() - mt_rand( 0, 29 * DAY_IN_SECONDS ) );
      $row['consent_at'] = $row['created_at'];
      $row['consent_marketing'] = $i % 3 ? 1 : 0;
      $wpdb->insert( $t, $row );
    }
    Lzcrs_Db::flush_cache();`);

  await signInAsAdmin(context);
  await page.setViewportSize({ width: 1280, height: 1000 });

  // 3. Dashboard.
  await page.goto('/wp-admin/admin.php?page=lzcrs-dashboard');
  await tidy(page);
  await expect(page.getByTestId('lzcrs-chart')).toBeVisible();
  await page.screenshot({ path: shot(3) });

  // 4. Leads list.
  await page.goto('/wp-admin/admin.php?page=lzcrs-leads');
  await tidy(page);
  await page.screenshot({ path: shot(4) });

  // 5. One lead.
  const id = wpJson<number>(`global $wpdb; echo 'LZJSON:' . (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE email = %s', Lzcrs_Db::table(), 'aarav.sharma${DEMO}' ) );`);
  await page.goto(`/wp-admin/admin.php?page=lzcrs-leads&lead=${id}`);
  await tidy(page);
  await page.screenshot({ path: shot(5) });

  // 6. Settings.
  await page.goto('/wp-admin/admin.php?page=lzcrs-settings');
  await tidy(page);
  await page.screenshot({ path: shot(6) });

  // 7. Guided setup.
  await page.goto('/wp-admin/admin.php?page=lzcrs-onboarding&step=welcome');
  await expect(page.getByTestId('lzcrs-onboarding')).toBeVisible();
  await page.screenshot({ path: shot(7) });

  } finally {
  // Leave the test site as it was.
  wpEval(`
    global $wpdb; $t = Lzcrs_Db::table();
    $b = json_decode( base64_decode( '${backup}' ), true );
    update_option( 'lzcrs_settings', $b[0] );
    $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $t ) );
    foreach ( $b[1] as $row ) { $wpdb->insert( $t, $row ); }
    if ( $p = get_page_by_path( 'crs-screenshot' ) ) { wp_delete_post( $p->ID, true ); }
    Lzcrs_Db::flush_cache();`);
  }
});
