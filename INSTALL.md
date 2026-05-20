# Namek WhatsApp for WooCommerce — Test Guide

Thanks for testing! This plugin connects your WooCommerce store to Namek WhatsApp Services so customers automatically receive WhatsApp messages when their order status changes.

---

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+ installed and active
- PHP 7.4+
- A Namek API endpoint URL and API key (provided by Namek)

---

## Installation

1. Download the `namek-whatsapp.zip` file
2. Go to your WordPress admin → **Plugins → Add New → Upload Plugin**
3. Choose the zip file → click **Install Now** → **Activate**
4. You'll now see **Namek WhatsApp** under the WooCommerce menu in the sidebar

---

## Configuration

Go to **WooCommerce → Namek WhatsApp**

### Connection (fill these in first)

| Field | What to enter |
|---|---|
| API Endpoint | The Namek server URL (provided by Namek) |
| API Key | Your unit API key (provided by Namek) |
| Default Country Code | `91` for India — prepended to 10-digit numbers automatically |
| Admin Phone | Your WhatsApp number for admin alerts (include country code, no +) e.g. `919876543210` |

Click **Save Settings** after filling these in.

### Test the connection

Before enabling any triggers, use the **Test connection** bar at the bottom of the Connection section:
- Enter a phone number (with country code, no +, e.g. `919876543210`)
- Enter any message
- Click **Send Test Message**
- You should receive the WhatsApp message within a few seconds

If you get an error, double-check the API endpoint URL and API key.

---

## Enabling Triggers

Each trigger has:
- A **toggle** (green = on) — enable the ones you want
- A **message template** — customise the text sent to the customer or admin
- A **Test button** — sends a preview to a number you choose

### Available triggers

| Trigger | Sent to |
|---|---|
| Order Placed (Pending Payment) | Customer |
| Order Confirmed (Processing) | Customer |
| Order On Hold | Customer |
| Order Completed / Shipped | Customer |
| Order Cancelled | Customer |
| Order Refunded | Customer |
| Payment Failed | Customer |
| Order Note Added | Customer |
| New Customer Registration | Customer |
| New Order Alert | Admin phone |
| Low Stock Alert | Admin phone |

### Message placeholders

Use these in your templates — they get replaced with real values automatically:

| Placeholder | Replaced with |
|---|---|
| `{name}` | Customer's first name |
| `{full_name}` | Customer's full name |
| `{order_id}` | Order number |
| `{order_total}` | Order total amount |
| `{order_status}` | Current order status |
| `{product_list}` | List of items in the order |
| `{site_name}` | Your store name |
| `{note}` | Order note text (for the note trigger) |
| `{product_name}` | Product name (for low stock alert) |
| `{stock_qty}` | Remaining stock quantity (for low stock alert) |

---

## Testing Triggers

The easiest way to test without placing real orders:

1. Enable a trigger (e.g. Order Completed)
2. Click the **Test** button next to it
3. Enter a phone number when prompted
4. You'll receive the message with sample values filling the placeholders

To test with a real order flow:
1. Place a test order in your store
2. Go to WooCommerce → Orders → open the order
3. Change the order status (e.g. set to Processing, then Completed)
4. The customer's billing phone should receive the WhatsApp message

---

## Phone Number Format

The plugin automatically handles common formats:
- `9876543210` → adds country code → `919876543210`
- `09876543210` → strips leading 0, adds country code → `919876543210`
- `919876543210` → used as-is

Make sure customers enter their phone number during checkout (WooCommerce billing phone field).

---

## Feedback

Please note down:
- Any triggers that didn't fire when expected
- Any error messages you saw
- Whether the test send worked but live orders didn't (or vice versa)
- Any message formatting issues

Send feedback to: hello@namek.co.in
