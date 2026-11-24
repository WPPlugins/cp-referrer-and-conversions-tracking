=== CP Referrer and Conversion Tracking ===
Contributors: codepeople
Donate link: https://wordpress.dwbooster.com
Tags: referrer, conversion, referer, logs, stats
Requires at least: 5.8
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 2.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track visitor referral websites, generate custom campaign links, and attribute conversions from forms, bookings, and WooCommerce.

== Description ==

CP Referrer and Conversion Tracking is a practical tool for evaluating the results of your marketing campaigns. It records the referral website of each visitor and attributes conversions—such as purchases, contact form submissions, appointments, bookings, and user registrations—back to their original traffic sources.

The main features include:

* Records the external HTTP referrer and initial landing page of each website visitor (Tracking Logs).
* Displays interactive daily and hourly charts alongside yearly, monthly, weekly, and daily analytics summaries (Tracking Stats).
* Allows you to create custom campaign links for different marketing platforms with live previews and one-click copying (Referral Sources).
* Records both the initial (first-touch) referrer and the most recent (last-touch) referrer for conversions (Conversions).
* Includes built-in conversion tracking integrations for popular WordPress plugins (Add-Ons).
* Built-in GDPR and privacy controls: salted IP hashing, IP anonymization, Do Not Track / Global Privacy Control support, cookieless mode, WordPress Privacy Policy Guide integration, and Personal Data Export/Erasure support.

= Recording the Referrer =

The CP Referrer and Conversion Tracking plugin detects and stores the referring website whenever an external HTTP referer header is present (for example, when a visitor clicks a link from Google, Facebook, or another external website).

Visitors with identified referrers are listed under the **Tracking Logs** menu. The referrer, visitor identifier (salted hash, anonymized IP, or full IP depending on your privacy setting), landing page, and timestamp of the first visit are recorded in the logs.

To avoid storing excessive log data, old logs are automatically deleted after 90 days by default. You can customize this retention period or trigger an immediate cleanup from the **General Settings** screen.

= Graphics and Statistics of Referral Websites =

Recorded logs power interactive charts showing daily and hourly referral trends, as well as a percentage breakdown of top referral sources.

Historical summaries are also calculated for logs received per year, over the last 12 months, over the last 12 weeks, and over the last 30 days. This helps you evaluate referral trends across different time frames. *Note: Historical totals may be affected by the automatic deletion of expired logs.*

This section is located under the **Tracking Stats** menu.

= Creating Custom Referral Links for Marketing Campaigns =

The **Referral Sources** section lets you build custom links for different marketing platforms, making it easier to identify exact traffic sources.

For example, you can set up dedicated entry URLs for a Google Ads campaign and for Facebook Ads. This ensures the exact referral source is reported even if a visitor's browser strips the automatic HTTP referer header.

= Tracking Conversions =

CP Referrer and Conversion Tracking can identify the referral source behind conversions such as contact form submissions, WooCommerce orders, bookings, appointment requests, and new user registrations.

Each conversion lists both the initial visit's referrer and the latest session's referrer if the visitor returned through a different source before converting.

To record conversions from a supported plugin, enable its integration under the **Add-Ons** menu. Built-in conversion tracking add-ons are included for the following plugins and features:

* [WooCommerce](https://wordpress.org/plugins/woocommerce/) (HPOS and Classic Order Storage compatible)
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7/)
* [Contact Form to Email](https://wordpress.org/plugins/contact-form-to-email/)
* [Appointment Hour Booking](https://wordpress.org/plugins/appointment-hour-booking/)
* [Appointment Booking Calendar](https://wordpress.org/plugins/appointment-booking-calendar/)
* [Booking Calendar Contact Form](https://wordpress.org/plugins/booking-calendar-contact-form/)
* [Calculated Fields Form](https://wordpress.org/plugins/calculated-fields-form/)
* [Contact Form with PayPal](https://wordpress.org/plugins/cp-contact-form-with-paypal/)
* [CP Polls](https://wordpress.org/plugins/cp-polls/)
* [WP Time Slots Booking Form](https://wordpress.org/plugins/wp-time-slots-booking-form/)
* WordPress User Registrations

Conversions are listed under the **Conversions** menu.

= Privacy and GDPR Controls =

Under **General Settings**, you can tailor data collection to match your site's privacy requirements:

* **Visitor IP Address Handling:** Choose between one-way salted hashing (recommended for GDPR), WordPress Core IP anonymization, or legacy full IP storage.
* **Do Not Track (DNT) & Global Privacy Control (GPC):** Automatically skip logging and cookies when visitors enable browser privacy signals.
* **Cookieless Mode:** Disable first-party tracking cookies (`cprreftrack` and `cprreftracklatest`) while keeping server-side first-touch attribution active.
* **Clean Uninstall:** Optionally remove all database tables and plugin options when deleting the plugin from WordPress.
* **WordPress Privacy Tools:** Automatically adds suggested disclosure language to **Settings > Privacy > Policy Guide** and supports **Export Personal Data** and **Erase Personal Data** requests for matching conversion records.

For developers: If you need to track a custom conversion programmatically, please see the FAQ section below for the supported hooks.

== Installation ==

To install **CP Referrer and Conversion Tracking**, follow these steps:

1. Download and unzip the CP Referrer and Conversion Tracking plugin archive.
2. Upload the entire `cp-referrer-and-conversions-tracking/` directory to the `/wp-content/plugins/` directory (or upload the `.zip` file directly via **Plugins > Add New > Upload Plugin**).
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Open **CP Referrer Tracking** in the WordPress admin sidebar to view the dashboard, configure privacy and retention settings, and enable conversion add-ons.

== Frequently Asked Questions ==

= I need a new plugin conversion integrated or a new feature. =

Feel free to contact us in the support forum. We will point you to an existing solution if one is available or add your request to our development roadmap.

= How do I remove all plugin database tables and settings when uninstalling? =

Go to **CP Referrer Tracking > General Settings**, enable **Delete All Plugin Data on Uninstall**, and save your settings before deleting the plugin from the WordPress **Plugins** screen.

= For developers: Which hooks are supported for registering custom conversions? =

Register a custom conversion:

    do_action( 'cpreftrack_register_conversion', 'my_custom_conversion_identifier', 'My Conversion ID or Data' );

Retrieve the current visitor's recorded referrer:

    $referrer = apply_filters( 'cpreftrack_referrer', '' );

Customize how a conversion appears in the Conversions list:

    add_filter( 'my_custom_conversion_identifier', 'my_custom_conversion_identifier_fn', 10, 1 );

    function my_custom_conversion_identifier_fn( $text ) {
        // Modify and escape the output as needed.
        return esc_html( $text );
    }

Customize the capability required to access plugin admin screens (defaults to `manage_options`):

    add_filter( 'cpreftrack_capability', function() {
        return 'manage_options';
    } );

== Screenshots ==

1. Tracking logs with referrers and landing pages
2. Tracking stats and interactive charts
3. Defining custom referral links for marketing platforms
4. Conversions with first-touch and last-touch attribution

== Changelog ==

= 2.0.9 =
* Added GDPR and privacy settings: salted one-way IP hashing, IP anonymization, Do Not Track (DNT) / Global Privacy Control (GPC) support, cookieless tracking mode, WordPress Privacy Policy Guide integration, and Personal Data Exporter & Eraser support.
* Added a multisite-compatible `uninstall.php` script with an opt-in setting to remove all plugin tables and options upon deletion.
* Modernized the admin interface with a unified navigation shell, live KPI dashboard cards, native date filters, a campaign link builder with live preview and one-click copy, toggle switches, and interactive HiDPI canvas charts with hover tooltips.
* Removed legacy jQuery UI 1.8.20 Cupertino styles, IE8 `excanvas.min.js`, and unscoped global CSS; all admin styles are cleanly scoped under `.cprt-admin`.
* Security hardening: added `ABSPATH` guards to all PHP files, standardized capability checks (`manage_options`), moved all destructive and settings actions to POST with CSRF nonces and PRG redirects, added nonce verification and formula-injection protection to CSV exports, updated all dynamic SQL queries to `$wpdb->prepare()` with `$wpdb->esc_like()`, replaced unsafe `unserialize()` with JSON storage and `allowed_classes => false` fallback, and added `HttpOnly`, `SameSite=Lax`, and `Secure` attributes to tracking cookies.
* Performance improvements: added SQL `LIMIT`/`OFFSET` pagination to all list tables, added database indexes via `dbDelta()`, and replaced full-table PHP stats loops with SQL `GROUP BY` aggregation.
* Add-on & PHP 8.2+ fixes: added WooCommerce HPOS support, fixed refunded status detection in CP Contact Form with PayPal, fixed User Registration add-on auto-detection, prevented manually disabled add-ons from auto-enabling, fixed conversions bulk-delete SQL syntax, and resolved PHP 8+ compatibility issues.

= 1.01.30 =
* Compatible with WordPress 7.1

= 1.01.29 =
* Compatible with WordPress 7.0

= 1.01.28 =
* Support URL update

= 1.01.27 =
* Compatible with WordPress 6.9

= 1.01.26 =
* General improvements

= 1.01.25 =
* Compatible with WordPress 6.8

= 1.01.24 =
* Compatible with WordPress 6.7

= 1.01.23 =
* Compatible with WordPress 6.6

= 1.01.22 =
* Compatible with WordPress 6.5
* Removed tag: marketing

= 1.01.21 =
* Tracking fixes

= 1.01.20 =
* Compatible with WordPress 6.4

= 1.01.19 =
* Code improvements

= 1.01.18 =
* Compatible with WordPress 6.3

= 1.01.17 =
* Compatible with WordPress 6.2

= 1.01.16 =
* PHP 8 updates

= 1.01.15 =
* Compatible with WordPress 6.1

= 1.01.14 =
* Contact Form 7 integration improvement

= 1.01.12 =
* Added user registration tracking add-on

= 1.01.11 =
* Added tracking of entry page
* Compatible with WordPress 6.0

= 1.01.10 =
* Fixed CSV export feature

= 1.01.09 =
* Compatible with WordPress 5.9

= 1.01.08 =
* Compatible with WordPress 5.8

= 1.01.07 =
* Compatible with WordPress 5.7

= 1.01.06 =
* Compatible with WordPress 5.6

= 1.01.05 =
* Compatible with WordPress 5.5

= 1.01.04 =
* Compatible with WordPress 5.4

= 1.01.03 =
* Conversion tracking for WooCommerce orders
* Conversion tracking for Contact Form 7 submissions
* Auto-detection added for Contact Form to Email
* Auto-detection added for Appointment Hour Booking
* Auto-detection added for Appointment Booking Calendar
* Auto-detection added for Booking Calendar Contact Form
* Auto-detection added for Calculated Fields Form
* Auto-detection added for Contact Form with PayPal
* Auto-detection added for CP Polls
* Auto-detection added for WP Time Slots Booking Form

= 1.01.01 =
* First version published

== Upgrade Notice ==

= 2.0.9 =
Major update adding GDPR/privacy controls, clean uninstall support, modernized admin UI, WooCommerce HPOS support, security hardening, and PHP 8.2+ compatibility.
