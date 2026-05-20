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

## GitHub Repository

**URL:** https://github.com/kakarottss4/namek-whatsapp
**Visibility:** Public (proprietary licence — no open source licence file)
**Constant in plugin:** `define('NAMEK_WA_GITHUB_REPO', 'kakarottss4/namek-whatsapp');`

The repo contains source code only. The zip is **never committed** — it is attached as an asset to each GitHub Release.

---

## Release Workflow (how to ship an update)

```bash
cd /Users/santosh/Desktop/Projects/whatsapp/whatsapp-wp-plugin

# 1. Make your changes to the plugin files

# 2. Bump version in TWO places:
#      namek-whatsapp/namek-whatsapp.php  → Version: x.x.x
#                                         → define('NAMEK_WA_VERSION', 'x.x.x');

# 3. Rebuild the zip
bash build.sh

# 4. Commit, tag, and push
git add .
git commit -m "vX.X.X — description of changes"
git tag vX.X.X
git push origin main --tags

# 5. Go to GitHub → Releases → Draft a new release
#    → Choose tag vX.X.X
#    → Title: vX.X.X
#    → Attach namek-whatsapp.zip as a release asset
#    → Publish release
```

All client WordPress sites will show the "Update available" banner on their next daily check. Client clicks Update — done. Settings are preserved.

---

## Distribution & Build Process

**For manual distribution (new clients before they set up auto-update):**
1. Run `bash build.sh` → get `namek-whatsapp.zip`
2. Send zip to client
3. WP Admin → Plugins → Add New → Upload Plugin → Install → Activate
4. Settings are preserved on future updates (stored in WP database, not plugin files)

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

## Completed Builds

### ✓ Renamed plugin
Plugin name is `Namek WhatsApp` (v1.1.0). WooCommerce is one module inside a broader plugin.

### ✓ Restructured into modules
```
includes/
  class-api.php               ← shared
  class-settings.php          ← shared
  class-updater.php           ← GitHub auto-updater
  modules/
    class-woocommerce.php     ← WooCommerce triggers
```

### ✓ GitHub auto-updater
Built in `includes/class-updater.php`. Hooks into WP update checker, calls GitHub releases API every 12 hours. Clients see the standard WP "Update available" banner.

### ✓ Group message trigger
`new_order_group` trigger — sends new order notification to a WhatsApp group. Group JID configured in settings under Connection → WhatsApp Group ID.

### ✓ Namek brand UI
Settings page uses Namek's full design system: Inter/Outfit fonts, `#3d0000` primary, `#c25558` accent, rose backgrounds, matching card/input/button styles.

---

## Future Builds

### Additional WP integrations (future modules)
Each added as a new file under `includes/modules/` — only loads if the relevant plugin is active:
- **Contact Form 7** — send WA on form submit, map form fields to placeholders
- **Gravity Forms** — same concept, wider adoption in premium WP sites
- **WooCommerce Bookings** — confirmed, reminder 24h before, cancellation
- **Custom trigger builder** — client defines their own hook + message without code

### Client self-service API key rotation
- Currently: admin rotates key from Namek dashboard, gives new key to client manually
- Plan: "Rotate API Key" button in the Namek unit's setup portal
- New key generated server-side, shown once — client updates plugin settings themselves

---

## Known Decisions & Constraints

- **Full URL in endpoint fields** — client pastes the complete URL (`https://.../api/v1/send`), plugin uses it directly. Do not strip or reconstruct the path — group and future endpoints will differ.
- **sslverify: false** — disabled in wp_remote_post to support local/dev environments and self-signed certs. Acceptable for an internal B2B plugin.
- **Single API key for both personal and group** — both endpoints authenticate with the same `namek_wa_api_key`. No separate key needed.
- **No WP marketplace** — distributed privately. Keeps client list controlled, avoids public support burden.
