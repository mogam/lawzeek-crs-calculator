=== Lawzeek CRS Calculator ===
Contributors: lawzeek
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
  * a CRS Leads screen with score, Hot, Warm or Cold rating, and likely pathway (CEC, FSW, PNP);
  * search, filters and status tracking;
  * CSV export.
* **Email alert** for every new lead.
* **Your brand:**
  * your button colour;
  * your recent cutoff;
  * an optional "Book a consultation" button after the result;
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

= Disclaimer =

The calculator gives an estimate, not legal advice. The official score is calculated by IRCC when a candidate submits an Express Entry profile. Keep the "recent cutoff" setting up to date after each draw.

= Want more? =

Lawzeek for immigration consultants adds several features to this plugin:

* Express Entry draw alerts that bring old leads back
* online booking
* a website chatbot
* a lead dashboard for your team

This plugin works fully on its own and never requires an account.

== Installation ==

1. Install and activate the plugin (Plugins → Add New → search "Lawzeek CRS Calculator").
2. Go to **CRS Leads → Settings**. Check the email that receives new leads, and set the recent Express Entry cutoff.
3. Edit any page and add the **CRS Calculator** block, or paste `[lawzeek_crs]`.
4. Visit the page and try it. Your test appears under **CRS Leads**.

== Frequently Asked Questions ==

= Does it need an account or an API key? =

No. Everything runs and is stored on your own WordPress site.

= Does it send visitor data anywhere? =

No. Answers and contact details are saved in your WordPress database only. The only email sent is the new-lead alert to the address you choose, through your site's normal email.

= Does it score couples (with a spouse)? =

Not yet. This version scores single applicants, and tells visitors that a consultant can review spouse factors with them.

= Can I change the heading? =

Yes. In the block, use the Heading setting in the sidebar. With the shortcode, use `[lawzeek_crs title="Check your Canada PR chances"]`.

= My new-lead emails don't arrive. =

The plugin uses WordPress's built-in `wp_mail()`. Many hosts need an SMTP plugin to deliver email reliably.

= What happens to my leads if I delete the plugin? =

They are kept, unless you tick "Also delete all leads and settings" under CRS Leads → Settings before deleting the plugin.

= How is spam handled? =

A hidden field catches most bots. Each visitor can submit up to 10 times in 10 minutes. IP addresses are never stored, only a one-way coded value used for the limit.

== Screenshots ==

1. The calculator on a website: five short steps.
2. The result: estimated score, distance to the cutoff and the fastest ways to raise it.
3. CRS Leads in the WordPress dashboard.
4. A lead's profile, score breakdown and status.
5. Settings.

== Changelog ==

= 1.0.0 =
* First release:
  * CRS calculator (single applicant, CRS rules of 2025-03-25);
  * leads screen, CSV export and email alerts;
  * block and shortcode;
  * privacy tools.

== Upgrade Notice ==

= 1.0.0 =
First release.

== Privacy ==

This plugin stores the data visitors enter in the calculator in your WordPress database:

* name, email and phone;
* their answers;
* consent choices;
* the page URL.

It also stores a one-way hash of the visitor's IP address, used for rate limiting only.

The plugin does not send this data to Lawzeek or to any third party, and makes no external HTTP requests. Site owners can export or erase a visitor's data from Tools → Export / Erase Personal Data.
