=== CP Referrer and Conversion Tracking ===
Contributors: codepeople
Donate link: https://wordpress.dwbooster.com
Tags: referrer, conversion, utm, logs, stats
Requires at least: 5.8
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track visitor referral websites, native UTM campaign parameters, custom links, and conversions from forms, bookings, and WooCommerce.

== Description ==

CP Referrer and Conversion Tracking is a practical tool for evaluating the results of your marketing campaigns. It records the referral website and standard UTM campaign parameters of each visitor and attributes conversions—such as purchases, contact form submissions, appointments, bookings, and user registrations—back to their original traffic sources.

The main features include:

* Records the external HTTP referrer, initial landing page, and native UTM parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`) of each website visitor (Tracking Logs).
* Displays interactive daily and hourly charts alongside yearly, monthly, weekly, daily, and UTM source/medium/campaign analytics summaries (Tracking Stats).
* Built-in UTM Campaign URL Builder and custom referral parameter generator with live previews and one-click copying (Referral Sources).
* Records both initial (first-touch) and most recent (last-touch) referrers and UTM parameters for conversions (Conversions).
* Includes a lightweight WordPress Dashboard Widget showing a 7-day referral sparkline, top referrers, and recent conversions at a glance.
* Includes built-in conversion tracking integrations for popular WordPress plugins (Add-Ons).
* Built-in GDPR and privacy controls: salted IP hashing, IP anonymization, Do Not Track / Global Privacy Control support, cookieless mode, WordPress Privacy Policy Guide integration, and Personal Data Export/Erasure support.

= Recording the Referrer & Native UTM Parameters =

The CP Referrer and Conversion Tracking plugin detects and stores the referring website whenever an external HTTP referer header is present (for example, when a visitor clicks a link from Google, Facebook, or another external website) as well as standard UTM query parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, and `utm_content`) even when browsers or mobile apps strip the HTTP referer header.

Visitors with identified referrers or UTM tags are listed under the **Tracking Logs** menu. The referrer, captured UTM parameters, visitor identifier (salted hash, anonymized IP, or full IP depending on your privacy setting), landing page, and timestamp of the first visit are recorded in the logs and included as dedicated columns in CSV exports.

To avoid storing excessive log data, old logs are automatically deleted after 90 days by default. You can customize this retention period, toggle native UTM tracking, or trigger an immediate cleanup from the **General Settings** screen.

= Graphics and Statistics of Referral Websites & UTM Campaigns =

Recorded logs power interactive charts showing daily and hourly referral trends, as well as a percentage breakdown of top referral sources, UTM sources, UTM mediums, and UTM campaigns.

Historical summaries are also calculated for logs received per year, over the last 12 months, over the last 12 weeks, and over the last 30 days. This helps you evaluate referral trends across different time frames. *Note: Historical totals may be affected by the automatic deletion of expired logs.*

This section is located under the **Tracking Stats** menu.

= UTM Campaign URL Builder & Custom Referral Links =

The **Referral Sources** section includes a built-in **UTM Campaign URL Builder** with live previews and one-click copying—standard UTM links are tracked automatically without manual registration. You can also define custom non-UTM query parameter rules (such as `?ref=partner1`) for bespoke marketing platforms.

= Tracking Conversions =

CP Referrer and Conversion Tracking can identify the referral source and UTM campaign behind conversions such as contact form submissions, WooCommerce orders, bookings, appointment requests, and new user registrations.

Each conversion lists both the initial visit's referrer/UTM tags and the latest session's referrer/UTM tags if the visitor returned through a different source or campaign before converting.

To record conversions from a supported plugin, enable its integration under the **Add-Ons** menu. Built-in conversion tracking add-ons are included for the following plugins and features:

* [WooCommerce](https://wordpress.org/plugins/woocommerce/) (HPOS and Classic Order Storage compatible, with a dedicated Order Attribution sidebar metabox)
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
* **Do Not Track (DNT) & Global Privacy Control (GPC):** Automatically skip logging, UTM capture, and cookies when visitors enable browser privacy signals.
* **Cookieless Mode:** Disable first-party tracking cookies (`cprreftrack`, `cprreftracklatest`, `cprreftrackutm`, and `cprreftrackutmlatest`) while keeping server-side first-touch referrer and UTM attribution active.
* **Clean Uninstall:** Optionally remove all database tables and plugin options when deleting the plugin from WordPress.
* **WordPress Privacy Tools:** Automatically adds suggested disclosure language to **Settings > Privacy > Policy Guide** and supports **Export Personal Data** and **Erase Personal Data** requests for matching conversion records (including UTM attribution).

For developers: If you need to track a custom conversion programmatically or read captured UTM parameters, please see the FAQ section below for the supported hooks.

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

= For developers: Which hooks are supported for registering custom conversions and reading UTM data? =

Register a custom conversion:

    do_action( 'cpreftrack_register_conversion', 'my_custom_conversion_identifier', 'My Conversion ID or Data' );

Retrieve the current visitor's recorded referrer:

    $referrer = apply_filters( 'cpreftrack_referrer', '' );

Retrieve the current visitor's recorded first-touch UTM parameters (associative array):

    $utm = apply_filters( 'cpreftrack_utm_data', array() );

Retrieve the current visitor's complete attribution snapshot (first/last referrer, landing page, and first/last UTM parameters):

    $attribution = apply_filters( 'cpreftrack_attribution_data', array() );

Customize how UTM-only visits (without an HTTP referer header) are labeled:

    add_filter( 'cpreftrack_utm_referrer_label', function( $label, $utm_params ) {
        return $label;
    }, 10, 2 );

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

1. Tracking logs with referrers, UTM parameters, and landing pages
2. Tracking stats and interactive charts with UTM breakdowns
3. UTM Campaign URL Builder and custom referral rules
4. Conversions with first-touch and last-touch referrer and UTM attribution

== Changelog ==

= 2.1.0 =
* Added Native UTM Parameter Tracking (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`) with automatic first-touch and last-touch attribution even when HTTP referer headers are stripped.
* Added a dedicated **UTM Parameters** column to Tracking Logs, first-touch and last-touch UTM attribution badges to Conversions, and dedicated UTM columns (`UTM Source`, `UTM Medium`, `UTM Campaign`, `UTM Term`, `UTM Content`) to CSV exports.
* Added an interactive **UTM Campaign URL Builder** with live preview and one-click copy under Referral Sources.
* Added **UTM Source**, **UTM Medium**, and **UTM Campaign** breakdown dimensions to Tracking Stats.
* Added a native **WordPress Dashboard Widget** displaying 7-day referral and conversion KPIs, an inline SVG 7-day activity sparkline, top referrers, and recent conversions (toggleable from General Settings).
* Added a dedicated **WooCommerce Order Attribution Metabox** (HPOS and Classic Order Storage compatible) displaying origin type, first-touch and last-touch referrers, initial landing page, and first/last UTM chips directly inside WooCommerce orders, plus formal WooCommerce HPOS (`custom_order_tables`) compatibility declaration and the `cpreftrack_attribution_data` developer filter.
* Added a toggle in General Settings to enable/disable native UTM tracking, updated WordPress GDPR Privacy Policy Guide & Personal Data Exporter to include UTM attribution, and added the `cpreftrack_utm_data` and `cpreftrack_utm_referrer_label` developer filters.
* Added a practical built-in **Help & User Guide** screen covering quick-start setup, UTM best practices, attribution, WooCommerce, GDPR privacy, and troubleshooting, with contextual Help links from every plugin screen, the WP Dashboard widget, and the WooCommerce Order metabox.
* Synchronized `CPREFTRACK_VERSION` and `CPREFTRACK_DB_VERSION` constants to `2.1.0` with automatic `dbDelta()` migration adding `utm_data` to the conversions table.

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

= 2.1.0 =
Adds Native UTM Parameter Tracking (first-touch and last-touch), interactive UTM Campaign URL Builder, UTM analytics breakdowns, a native WordPress Dashboard overview widget, and a dedicated WooCommerce Order Attribution metabox (HPOS & Classic compatible).

= 2.0.9 =
Major update adding GDPR/privacy controls, clean uninstall support, modernized admin UI, WooCommerce HPOS support, security hardening, and PHP 8.2+ compatibility.
