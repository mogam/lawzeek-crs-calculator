=== Lawzeek CRS Calculator ===
Contributors: pushpakt
Tags: crs calculator, express entry, immigration, canada, lead generation
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Canada Express Entry CRS score calculator for immigration consultants. Visitors see their score and how to raise it; you get every result as a lead.

== Description ==

Lawzeek CRS Calculator adds a short, mobile-friendly Comprehensive Ranking System (CRS) calculator to your website. It is made for Regulated Canadian Immigration Consultants (RCICs), immigration lawyers and their teams.

Visitors answer five quick steps:

* age
* education
* language tests
* work experience
* a few extras

To see their result, they share their name and email. They then see:

* their estimated CRS score
* how far they are from the recent cutoff
* the fastest ways to raise their score, for example a better IELTS result, a French test, more Canadian work, or a provincial nomination

You get every result as a lead in your WordPress dashboard, with an email alert.

= Features =

* **CRS calculator** for single applicants, based on IRCC's current CRS criteria (job-offer points removed since March 2025).
* **Score gap plan:** shows each visitor which changes would raise their score the most, and whether a change reaches the cutoff.
* **Leads in WordPress:**
  * a Lawzeek dashboard showing leads this month, hot leads and average score;
  * a Leads screen with score, Hot, Warm or Cold rating, and likely pathway (CEC, FSW, PNP);
  * search, filters and status tracking;
  * CSV export.
* **Email alert** for every new lead.
* **Booking as the last step:** add your Calendly, Cal.com, Acuity or any appointment link. Visitors see a "Book a consultation" button right after their score. With Calendly, you can show the calendar inside the result instead.
* **Matches your theme:** the calculator picks up your theme's fonts, button colour and corner style automatically, and works on light and dark themes. Prefer your own colour? Choose "Custom colour".
* **Your setup:**
  * your recent cutoff;
  * the phone field can be optional, required or hidden.
* **Privacy built in:**
  * a consent checkbox linked to your privacy policy;
  * optional marketing consent;
  * works with WordPress's Export and Erase Personal Data tools;
  * suggested privacy policy text.
* **Spam protection:** a hidden honeypot field and a rate limit. No CAPTCHA, and no third-party service.
* **Easy to add:** a "CRS Calculator" block, or the shortcode `[lawzeek_crs]`.
* **Fast and self-contained:**
  * no external requests;
  * no tracking;
  * no cookies;
  * scripts load only on pages that show the calculator.
* **Translation-ready.**

= Source code =

The admin screens are styled with Tailwind CSS. The source file is `assets/src/admin.css` and the config is `assets/src/tailwind.config.js`. The built file, `assets/css/admin.css`, is left unminified. Every rule is scoped to the plugin's own screens, so the rest of WordPress is unaffected. The front-end calculator uses plain CSS and JavaScript, with no build step.

The admin screens use the Google Sans font, bundled in `assets/fonts` under the SIL Open Font License 1.1 (see `assets/fonts/OFL.txt`). It is loaded from your own site, never from Google.

The full source, build tools and tests are public on GitHub: https://github.com/mogam/lawzeek-crs-calculator

= Disclaimer =

The calculator gives an estimate, not legal advice. The official score is calculated by IRCC when a candidate submits an Express Entry profile. Keep the "recent cutoff" setting up to date after each draw.

= Coming later =

We are building Lawzeek for immigration consultants, a separate service with Express Entry draw alerts, online booking, a website chatbot and a team lead dashboard. It is not available yet. This plugin works fully on its own, has no locked features and never requires an account.

== Installation ==

1. Install and activate the plugin (Plugins → Add New → search "Lawzeek CRS Calculator").
2. Go to **Lawzeek → Settings** in the admin menu. Check the email that receives new leads, and set the recent Express Entry cutoff.
3. Edit any page and add the **CRS Calculator** block, or paste `[lawzeek_crs]`.
4. Visit the page and try it. Your test appears under **Lawzeek → Leads**.

== Frequently Asked Questions ==

= Does it need an account or an API key? =

No. Everything runs and is stored on your own WordPress site.

= Does it send visitor data anywhere? =

No. Answers and contact details are saved in your WordPress database only. The only email sent is the new-lead alert to the address you choose, through your site's normal email.

= Does it score couples (with a spouse)? =

Not yet. This version scores single applicants, and tells visitors that a consultant can review spouse factors with them.

= How do I add my Calendly or booking link? =

Go to Lawzeek → Settings → Booking and paste your link. Choose "Button" (works with any link) or "Calendar in the result" (Calendly links only).

= The calculator does not match my theme. =

It reads your theme's button style when the page loads. If your theme does not style buttons, the calculator uses your link colour or the custom colour from Settings. You can also choose "Custom colour" under Lawzeek → Settings → Look.

= Can I change the heading? =

Yes. In the block, use the Heading setting in the sidebar. With the shortcode, use `[lawzeek_crs title="Check your Canada PR chances"]`.

= My new-lead emails don't arrive. =

The plugin uses WordPress's built-in `wp_mail()`. Many hosts need an SMTP plugin to deliver email reliably.

= What happens to my leads if I delete the plugin? =

They are kept, unless you tick "Also delete all leads and settings" under Lawzeek → Settings before deleting the plugin.

= How is spam handled? =

A hidden field catches most bots. Each visitor can submit up to 10 times in 10 minutes. IP addresses are never stored, only a one-way coded value used for the limit.

== Screenshots ==

1. The calculator on a website, matching the theme's fonts and colours.
2. The result: estimated score, distance to the cutoff, the fastest ways to raise it and the booking step.
3. The Lawzeek dashboard: your shortcode first, then leads this month, hot leads, average score and setup steps.
4. Leads list with Hot, Warm and Cold ratings, search, filters and CSV export.
5. A lead's profile, score breakdown, status and consent record.
6. Settings: leads, scoring, booking, look, privacy.

== Changelog ==

= 1.0.0 =
* First release:
  * dashboard with your shortcode, lead numbers and a 30-day leads chart;
  * CRS calculator (single applicant, CRS rules of 2025-03-25);
  * leads screen, CSV export and email alerts;
  * block and shortcode;
  * privacy tools.

== Upgrade Notice ==

= 1.0.0 =
First release.

== External services ==

This plugin makes no requests of its own to any outside server. It can connect to one service, only if the site owner turns it on:

**Calendly** (calendly.com) shows a booking calendar inside the calculator result.

* When: only if the site owner enters a calendly.com link in Settings → Booking and chooses "Calendar in the result". It is off by default.
* What is sent: when a visitor reaches their result, their browser loads the site owner's Calendly booking page in a frame. The frame's address includes your site's domain name (Calendly requires it for embedding), and Calendly receives the usual browser request data, such as the IP address and browser type. Calendly may show its own cookie notice. The plugin does not send the visitor's name, email, answers or score to Calendly. If the visitor books a call, they enter their details on Calendly's page.
* Calendly terms for people who book: https://calendly.com/legal/participant-terms-conditions
* Calendly terms for account holders: https://calendly.com/legal/customer-terms-conditions
* Calendly privacy notice: https://calendly.com/legal/privacy-notice

== Privacy ==

This plugin stores the data visitors enter in the calculator in your WordPress database:

* name, email and phone;
* their answers;
* consent choices;
* the page URL.

It also stores a one-way hash of the visitor's IP address, used for rate limiting only.

The plugin does not send this data to Lawzeek or to any third party, and makes no external HTTP requests.

If you choose to show your Calendly calendar inside the result, Calendly's booking page loads in the visitor's browser when they reach their result. See "External services" below. With the default "Button" option, nothing loads until the visitor clicks. Site owners can export or erase a visitor's data from Tools → Export / Erase Personal Data.
