# Namek WhatsApp — WordPress Plugin Reference

## Overview

A WordPress plugin that sends automatic WhatsApp notifications for WooCommerce events by calling the Namek WhatsApp Services API. Distributed privately to Namek clients as a zip file — not on the WordPress marketplace.

**Current version:** 1.0.0
**Plugin name in WP:** Namek WhatsApp for WooCommerce
**Menu location:** WooCommerce → Namek WhatsApp

---

## Project Structure

```
whatsapp-wp-plugin/
  build.sh                              ← run to package the plugin into a zip
  namek-whatsapp.zip                    ← distribute this to clients
  INSTALL.md                            ← client-facing install + test guide
  REFERENCE.md                          ← this file
  namek-whatsapp/
    namek-whatsapp.php                  ← plugin entry point, AJAX handler
    includes/
      class-api.php                     ← HTTP calls to Namek API (send + send_group)
      class-settings.php               ← WP admin settings page + trigger definitions
      class-woocommerce.php            ← all WooCommerce hooks and handlers
    assets/
      admin.js                          ← test send buttons (jQuery + AJAX)
```

---

## How It Works

```
WooCommerce event fires (e.g. order status → Processing)
        ↓
class-woocommerce.php intercepts the WP hook
        ↓
checks if trigger is enabled + fetches message template from wp_options
        ↓
replaces placeholders ({name}, {order_id}, etc.) with real order data
        ↓
class-api.php calls POST to Namek API endpoint with Bearer token
        ↓
customer receives WhatsApp message
```

The plugin runs entirely on the client's WordPress server. Nothing is hosted by Namek except the API itself.

---

## Settings (stored in wp_options)

| Option key | Description |
|---|---|
| `namek_wa_endpoint` | Full personal message URL e.g. `https://notification.namek.co.in/api/v1/send` |
| `namek_wa_group_endpoint` | Full group message URL e.g. `https://notification.namek.co.in/api/v1/send-group` |
| `namek_wa_api_key` | Unit API key from Namek dashboard |
| `namek_wa_country_code` | Default country code prepended to 10-digit numbers (default: `91`) |
| `namek_wa_admin_phone` | Admin's WhatsApp number for admin-targeted triggers |
| `namek_wa_{key}_enabled` | `1` or `''` — whether a trigger is on |
| `namek_wa_{key}_template` | Message template string for each trigger |

Settings survive plugin deletion and reinstall — stored in WP database, not plugin files.

---

## API Methods (class-api.php)

### `Namek_WA_API::send($phone, $message)`
Sends to an individual. Uses `namek_wa_endpoint`. Normalises phone number automatically.

### `Namek_WA_API::send_group($group_id, $message)`
Sends to a WhatsApp group. Uses `namek_wa_group_endpoint`. Passes `group_id` (e.g. `120363012345678901@g.us`) directly.

### `Namek_WA_API::format_phone($phone)`
Normalises phone numbers:
- 10 digits → prepend country code
- 11 digits starting with 0 → strip 0, prepend country code
- 12+ digits → use as-is
- Strips all non-digit characters first

Both methods return `['success' => true]` or `['success' => false, 'error' => 'reason — HTTP 4xx → url']`

---

## Triggers (class-woocommerce.php)

| Key | WP Hook | Target |
|---|---|---|
| `order_pending` | `woocommerce_order_status_pending` | Customer |
| `order_processing` | `woocommerce_order_status_processing` | Customer |
| `order_on_hold` | `woocommerce_order_status_on-hold` | Customer |
| `order_completed` | `woocommerce_order_status_completed` | Customer |
| `order_cancelled` | `woocommerce_order_status_cancelled` | Customer |
| `order_refunded` | `woocommerce_order_status_refunded` | Customer |
| `payment_failed` | `woocommerce_order_status_failed` | Customer |
| `order_note` | `woocommerce_new_customer_note` | Customer |
| `new_customer` | `woocommerce_created_customer` | Customer |
| `new_order_admin` | `woocommerce_checkout_order_created` | Admin phone |
| `low_stock_admin` | `woocommerce_low_stock` | Admin phone |

Customer triggers use the billing phone from the WooCommerce order.
Admin triggers use the `namek_wa_admin_phone` setting.

---

## Message Placeholders

| Placeholder | Value |
|---|---|
| `{name}` | Billing first name |
| `{full_name}` | Billing first + last name |
| `{order_id}` | WooCommerce order number |
| `{order_total}` | Formatted total with currency symbol |
| `{order_status}` | Human-readable status label |
| `{product_list}` | Comma-separated items × qty |
| `{site_name}` | WordPress site name |
| `{note}` | Order note text (order_note trigger only) |
| `{product_name}` | Product name (low_stock_admin only) |
| `{stock_qty}` | Remaining stock (low_stock_admin only) |

---

## Distribution & Build Process

```bash
# Make changes to plugin files
# Bump version in namek-whatsapp/namek-whatsapp.php → Version: x.x.x

cd /Users/santosh/Desktop/Projects/whatsapp/whatsapp-wp-plugin
bash build.sh
# → produces namek-whatsapp.zip
```

**Client install/update steps:**
1. WP Admin → Plugins → Deactivate → Delete (old version)
2. Plugins → Add New → Upload Plugin → choose zip → Install → Activate
3. Settings are preserved (stored in DB, not plugin files)

---

## Related Project

**Namek WhatsApp Services** (the API this plugin calls):
```
/Users/santosh/Desktop/Projects/whatsapp/whatsapp-services/whatsapp-node/
```
- Node.js + Express + Baileys (unofficial WhatsApp Web API)
- Multi-tenant — each client gets their own unit, API key, WhatsApp connection
- API docs at `/docs` on the server
- Key endpoints: `POST /api/v1/send`, `POST /api/v1/send-group`, `GET /api/v1/status`

---

## Planned / Future Builds

### 1. Rename plugin — drop "for WooCommerce"
- Plugin name: `Namek WhatsApp` (not "for WooCommerce")
- WooCommerce becomes one module inside a broader plugin
- Reason: plugin will expand to other WP integrations — no need to create a separate plugin each time

### 2. Restructure includes into modules
```
includes/
  class-api.php           ← shared, stays here
  class-settings.php      ← shared, stays here
  modules/
    class-woocommerce.php ← current triggers, moved here
    class-contact-form-7.php  ← future
    class-gravity-forms.php   ← future
    class-custom.php          ← future (manual send, custom hooks)
```
Each module only loads if the relevant plugin is active (check with `class_exists` or `is_plugin_active`).

### 3. GitHub auto-updater
- Create a dedicated GitHub repo: `namek-whatsapp-wp` (can be private)
- Plugin hooks into WP update checker, calls GitHub releases API
- Compares installed version vs latest GitHub release tag
- If newer: WordPress shows "Update available" banner → client clicks Update → auto-installs

**Release workflow once built:**
```bash
# 1. Bump version in namek-whatsapp.php
# 2. Build zip
bash build.sh
# 3. Tag and push to GitHub
git tag v1.1.0 && git push origin v1.1.0
# 4. Create GitHub Release, attach namek-whatsapp.zip
# → all client sites get the update banner overnight
```

**Implementation:** ~30 lines of PHP using the `plugins_api` and `pre_set_site_transient_update_plugins` filters, pointing at `https://api.github.com/repos/namek/namek-whatsapp-wp/releases/latest`.

### 4. Group message triggers
- Add group_id field in settings (client enters their WA group JID)
- New triggers: "New order → notify group", "Daily summary → notify group"
- Already have `Namek_WA_API::send_group()` ready in class-api.php
- Settings already have `namek_wa_group_endpoint` field

### 5. Additional WP integrations (future modules)
- **Contact Form 7** — send WA on form submit, map form fields to placeholders
- **Gravity Forms** — same concept, wider adoption in premium WP sites
- **WooCommerce Bookings** — appointment confirmed, reminder 24h before, cancellation
- **User registration (core WP)** — welcome message outside of WooCommerce flow
- **Custom trigger builder** — let client define their own hook + message without code

### 6. Client self-service API key rotation
- Currently: admin must rotate key from Namek dashboard, give new key to client
- Plan: add a "Rotate API Key" button in the Namek unit's setup portal
- New key generated server-side, shown once — client updates plugin settings
- No dependency on Namek admin for a security-sensitive action

---

## Known Decisions & Constraints

- **Full URL in endpoint fields** — client pastes the complete URL (`https://.../api/v1/send`), plugin uses it directly. Do not strip or reconstruct the path — group and future endpoints will differ.
- **sslverify: false** — disabled in wp_remote_post to support local/dev environments and self-signed certs. Acceptable for an internal B2B plugin.
- **Single API key for both personal and group** — both endpoints authenticate with the same `namek_wa_api_key`. No separate key needed.
- **No WP marketplace** — distributed privately. Keeps client list controlled, avoids public support burden.
