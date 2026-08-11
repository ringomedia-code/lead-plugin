# RM Form Leads

Collects form submissions from your WordPress site and routes them to **PBX**, **Repair Desk**, and/or **RingoLeads**, with support for any number of business locations, each using its own API keys and referral sources.

Current version: **1.6.3**
Repository: https://github.com/ringomedia-code/lead-plugin

## Features

- Route leads from multiple business locations, each with its own PBX, Repair Desk, and RingoLeads API key
- Track referral sources per location for PBX, Repair Desk, and RingoLeads
- Passes any custom form field straight through to RingoLeads, no plugin changes needed for new form fields
- Logs every delivery attempt (success or failure) to an **API History** page in wp-admin
- Emails a configurable address automatically when a delivery fails
- Validates that no two locations share the same API key, to prevent leads being misrouted
- Auto-updates directly from GitHub Releases, no manual zip installs needed after the first one

## Installation

1. Download the latest release zip from the [Releases page](https://github.com/ringomedia-code/lead-plugin/releases/latest) (or from a teammate).
2. In wp-admin, go to **Plugins → Add New → Upload Plugin**, choose the zip, and click **Install Now**.
3. Click **Activate**.
4. Go to the new **RM Form Leads** menu item in the wp-admin sidebar to configure it.

After this first install, updates show up automatically in **Plugins** whenever a new version is released on GitHub — no need to re-upload a zip.

## Configuring the plugin

All settings live under **RM Form Leads** in the wp-admin sidebar.

### Integrations

Toggle on **PBX**, **Repair Desk**, and/or **RingoLeads** depending on which systems this site should send leads to.

### Locations

Each location card holds:

- **PBX API key** — the API key for that location's PBX account
- **Repair Desk API key** — the API key for that location's Repair Desk account
- **RingoLeads API key** — the key generated for that client/location under RingoLeads → Lead API
- **Label** — optional, just for your own reference (e.g. "Downtown Store")

Location 1 is used by default. Click **Add location** for additional branches.

> Every location must use a *different* PBX key, a *different* Repair Desk key, and a *different* RingoLeads key from every other location. Saving with a duplicate key is blocked, with an error explaining which key is duplicated, so this stops leads from silently being routed to the wrong branch.

### PBX referral sources

Click **Fetch referrals from PBX** (using the first location's PBX API key) to pull the list of referral sources configured in your PBX account. Then pick which ones you want to track and add a referral row for each.

Each row shows the exact CSS class to add to your form, built from the referral's **numeric PBX ID** — for example `form_submit_request-11` for Location 1, `form_submit_request_2-11` for Location 2, and so on. The number must match the PBX referral ID exactly, since it's passed straight through to PBX to attribute the lead.

### Repair Desk referral sources

Same idea, but Repair Desk doesn't have a fixed referral list, so type any label you want (e.g. "Google Ads") and the matching class is generated from that text, e.g. `rd_form_request-google_ads` for Location 1, `rd_form_request_2-google_ads` for Location 2.

### RingoLeads

RingoLeads uses one CSS class to select both the lead source and the location/API key. Supported sources are Facebook, Facebook Ads, Google Maps, Website, Google, Instagram, Google Ads, Organic, Calendly, SMS, AI, and Zapier.

For Location 1, use `rl_<source>`, for example `rl_google_ads`. For Location 2 add `_2`, for example `rl_google_ads_2`; Location 3 uses `_3`, and so on. The plugin sends the matching human-readable value in the RingoLeads `source` field and sends the current page URL separately in `source_url`.

Legacy `rl_form_request`, `rl_form_request_2`, etc. classes remain supported for backward compatibility and send `source: wordpress`.

RingoLeads also stores any extra form field it doesn't recognise (e.g. `service`, `timeframe`) verbatim as a qualifying answer.

### Failure notifications

Set an email address to be notified whenever a lead fails to send to PBX, Repair Desk, or RingoLeads.

## Adding the classes to your forms

On the front end, add the generated class (shown next to each referral) to the form or its wrapping element:

- **PBX forms:** `form_submit_request` (Location 1), `form_submit_request_2` (Location 2), `form_submit_request_3` (Location 3), etc, with `-<referral id>` appended if you want referral tracking.
- **Repair Desk forms:** `rd_form_request` (Location 1), `rd_form_request_2` (Location 2), etc, with `-<referral slug>` appended.
- **RingoLeads forms:** use one source class, e.g. `rl_website` or `rl_google_ads` for Location 1, `rl_website_2` or `rl_google_ads_2` for Location 2, etc. Any extra field on the form is passed straight through.

If a form's class has no referral suffix at all, PBX submissions fall back to referral ID `26` (Google Ads).

## API History

**RM Form Leads → API History** lists every lead delivery attempt: which API it went to, whether it succeeded or failed, the customer's details, and the raw response, which is useful for debugging a lead that didn't show up where expected.

## For developers: releasing a new version

This repo auto-publishes a GitHub Release whenever the plugin's version number changes:

1. Bump `Version:` in `rm-form-leads.php` (and the matching `RMFL_PLUGIN_VERSION` constant).
2. Commit and push to `main`.
3. GitHub Actions ([.github/workflows/release.yml](.github/workflows/release.yml)) detects the version bump, builds a clean zip, and publishes it as a new GitHub Release tagged `v<version>`.
4. Sites running the plugin will see the update in wp-admin automatically, via the bundled [Plugin Update Checker](includes/plugin-update-checker/) pointed at this repo.

No manual tagging or zip-building needed — just bump the version and push.


## RingoLeads source slugs (v1.6.3)

RingoLeads source CSS classes now send canonical lowercase CRM slugs, e.g. `rl_form_request_website` sends `source: website` and `rl_form_request_google_ads_2` sends `source: google_ads` using Location 2's API key.
