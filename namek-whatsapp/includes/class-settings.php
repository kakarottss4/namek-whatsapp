<?php
defined('ABSPATH') || exit;

class Namek_WA_Settings {

    public static function init() {
        add_action('admin_menu',            [__CLASS__, 'add_menu']);
        add_action('admin_init',            [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function add_menu() {
        add_submenu_page(
            'woocommerce',
            'Namek WhatsApp',
            'Namek WhatsApp',
            'manage_options',
            'namek-whatsapp',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        $fields = ['namek_wa_endpoint', 'namek_wa_group_endpoint', 'namek_wa_group_id', 'namek_wa_api_key', 'namek_wa_country_code', 'namek_wa_admin_phone'];
        foreach (self::get_triggers() as $key => $trigger) {
            $fields[] = "namek_wa_{$key}_enabled";
            $fields[] = "namek_wa_{$key}_template";
        }
        foreach ($fields as $field) {
            register_setting('namek_wa_settings', $field);
        }
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'woocommerce_page_namek-whatsapp') return;
        wp_enqueue_script(
            'namek-wa-admin',
            NAMEK_WA_URL . 'assets/admin.js',
            ['jquery'],
            NAMEK_WA_VERSION,
            true
        );
        wp_localize_script('namek-wa-admin', 'namekWA', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('namek_wa_nonce'),
        ]);
    }

    public static function get_triggers() {
        return [
            'order_pending' => [
                'label'   => 'Order Placed (Pending Payment)',
                'desc'    => 'Customer places an order, awaiting payment.',
                'default' => "Hi {name}, we've received your order #{order_id} for {order_total}. We'll confirm once payment is received.",
                'target'  => 'customer',
            ],
            'order_processing' => [
                'label'   => 'Order Confirmed (Processing)',
                'desc'    => 'Payment received, order is being prepared.',
                'default' => "Hi {name}, your order #{order_id} for {order_total} is confirmed and being processed. Thank you!",
                'target'  => 'customer',
            ],
            'order_on_hold' => [
                'label'   => 'Order On Hold',
                'desc'    => 'Order placed on hold (e.g. bank transfer awaited).',
                'default' => "Hi {name}, your order #{order_id} is on hold. We'll notify you once it's confirmed.",
                'target'  => 'customer',
            ],
            'order_completed' => [
                'label'   => 'Order Completed / Shipped',
                'desc'    => 'Order marked as completed.',
                'default' => "Hi {name}, your order #{order_id} has been completed. Thank you for shopping with {site_name}!",
                'target'  => 'customer',
            ],
            'order_cancelled' => [
                'label'   => 'Order Cancelled',
                'desc'    => 'Order is cancelled by admin or customer.',
                'default' => "Hi {name}, your order #{order_id} has been cancelled. Contact us if you have any questions.",
                'target'  => 'customer',
            ],
            'order_refunded' => [
                'label'   => 'Order Refunded',
                'desc'    => 'A refund is issued for the order.',
                'default' => "Hi {name}, your refund for order #{order_id} ({order_total}) has been processed.",
                'target'  => 'customer',
            ],
            'payment_failed' => [
                'label'   => 'Payment Failed',
                'desc'    => 'A payment attempt fails at checkout.',
                'default' => "Hi {name}, the payment for order #{order_id} could not be processed. Please try again or contact us.",
                'target'  => 'customer',
            ],
            'order_note' => [
                'label'   => 'Order Note Added',
                'desc'    => 'Admin adds a customer-visible note to an order.',
                'default' => "Hi {name}, a note has been added to your order #{order_id}: {note}",
                'target'  => 'customer',
            ],
            'new_customer' => [
                'label'   => 'New Customer Registration',
                'desc'    => 'A new customer account is created.',
                'default' => "Hi {name}, welcome to {site_name}! We're glad to have you. Feel free to reach out if you need any help.",
                'target'  => 'customer',
            ],
            'new_order_admin' => [
                'label'   => 'New Order Alert (Admin)',
                'desc'    => 'Sent to your admin phone when any new order is placed.',
                'default' => "New order #{order_id} from {full_name} — {order_total}.\nItems: {product_list}",
                'target'  => 'admin',
            ],
            'low_stock_admin' => [
                'label'   => 'Low Stock Alert (Admin)',
                'desc'    => 'Sent to your admin phone when a product goes below stock threshold.',
                'default' => "Low stock alert: {product_name} has only {stock_qty} unit(s) remaining.",
                'target'  => 'admin',
            ],
            'new_order_group' => [
                'label'   => 'New Order → WhatsApp Group',
                'desc'    => 'Sent to your configured WhatsApp group when a new order is placed.',
                'default' => "New order #{order_id} from {full_name} — {order_total}.\nItems: {product_list}",
                'target'  => 'group',
            ],
        ];
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;
        $triggers  = self::get_triggers();
        $saved     = !empty($_GET['settings-updated']);
        $logo_url  = NAMEK_WA_URL . 'assets/namek.png';
        ?>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

        <div class="wrap namek-wa-wrap">

          <!-- Hero -->
          <div class="nw-hero">
            <div class="nw-hero-inner">
              <div class="nw-hero-brand">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Namek" class="nw-logo-img">
                <div>
                  <h1 class="nw-hero-title">Namek WhatsApp</h1>
                  <p class="nw-hero-sub">WooCommerce notifications via WhatsApp</p>
                </div>
              </div>
              <span class="nw-version-badge">v<?php echo esc_html(NAMEK_WA_VERSION); ?></span>
            </div>
          </div>

          <?php if ($saved): ?>
          <div class="nw-alert nw-alert-success">&#10003;&nbsp; Settings saved successfully.</div>
          <?php endif; ?>

          <form method="post" action="options.php">
            <?php settings_fields('namek_wa_settings'); ?>

            <!-- ── Connection ───────────────────────────────── -->
            <div class="nw-card">
              <div class="nw-card-header">
                <h2 class="nw-card-title">Connection</h2>
              </div>
              <div class="nw-card-body">

                <div class="nw-field-row">
                  <div class="nw-field">
                    <label class="nw-label">Personal Message URL</label>
                    <input type="url" name="namek_wa_endpoint"
                      value="<?php echo esc_attr(get_option('namek_wa_endpoint')); ?>"
                      class="nw-input" placeholder="https://notification.namek.co.in/api/v1/send">
                    <span class="nw-hint">Full endpoint for sending to individual customers</span>
                  </div>
                  <div class="nw-field">
                    <label class="nw-label">Group Message URL</label>
                    <input type="url" name="namek_wa_group_endpoint"
                      value="<?php echo esc_attr(get_option('namek_wa_group_endpoint')); ?>"
                      class="nw-input" placeholder="https://notification.namek.co.in/api/v1/send-group">
                    <span class="nw-hint">Full endpoint for sending to WhatsApp groups</span>
                  </div>
                </div>

                <div class="nw-field-row">
                  <div class="nw-field">
                    <label class="nw-label">WhatsApp Group ID</label>
                    <input type="text" name="namek_wa_group_id"
                      value="<?php echo esc_attr(get_option('namek_wa_group_id')); ?>"
                      class="nw-input" placeholder="120363012345678901@g.us">
                    <span class="nw-hint">Group JID — find it via GET /api/v1/groups on your Namek server. Always enter the full JID including <code style="font-size:10px">@g.us</code></span>
                  </div>
                  <div class="nw-field">
                    <label class="nw-label">Admin Phone</label>
                    <input type="text" name="namek_wa_admin_phone"
                      value="<?php echo esc_attr(get_option('namek_wa_admin_phone')); ?>"
                      class="nw-input" placeholder="919876543210">
                    <span class="nw-hint">Receives new order &amp; low stock alerts. Always include country code with no + or spaces — e.g. <strong>91</strong>9876543210 for India, <strong>1</strong>4155551234 for US</span>
                  </div>
                </div>

                <div class="nw-field-row">
                  <div class="nw-field">
                    <label class="nw-label">API Key</label>
                    <input type="password" name="namek_wa_api_key"
                      value="<?php echo esc_attr(get_option('namek_wa_api_key')); ?>"
                      class="nw-input" placeholder="Your unit API key from Namek dashboard"
                      autocomplete="new-password">
                    <span class="nw-hint">Keep this secret — provided by Namek</span>
                  </div>
                </div>

                <div class="nw-test-bar">
                  <span class="nw-test-label">Test connection</span>
                  <input type="text" id="namek-test-phone" class="nw-input nw-test-input" placeholder="Phone e.g. 919876543210">
                  <input type="text" id="namek-test-message" class="nw-input nw-test-msg" placeholder="Hello from Namek!">
                  <button type="button" class="nw-btn" id="namek-test-send">Send Test</button>
                  <span id="namek-test-result"></span>
                </div>

              </div>
            </div>

            <!-- ── Triggers ─────────────────────────────────── -->
            <div class="nw-card">
              <div class="nw-card-header nw-card-header-flex">
                <h2 class="nw-card-title">WooCommerce Triggers</h2>
                <div class="nw-placeholders">
                  <?php foreach (['{name}','{full_name}','{order_id}','{order_total}','{order_status}','{product_list}','{site_name}','{note}','{product_name}','{stock_qty}'] as $ph): ?>
                  <code class="nw-ph"><?php echo esc_html($ph); ?></code>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="nw-card-body nw-card-body-triggers">

                <?php foreach ($triggers as $key => $trigger):
                  $enabled  = get_option("namek_wa_{$key}_enabled", '');
                  $template = get_option("namek_wa_{$key}_template", $trigger['default']);
                  $is_admin = $trigger['target'] === 'admin';
                  $is_group = $trigger['target'] === 'group';
                  $badge_class = $is_group ? 'nw-badge-group' : ($is_admin ? 'nw-badge-admin' : 'nw-badge-customer');
                  $badge_text  = $is_group ? '&#x2192; Group' : ($is_admin ? '&#x2192; Admin' : '&#x2192; Customer');
                ?>
                <div class="nw-tc <?php echo ($is_admin || $is_group) ? 'nw-tc-muted' : ''; ?>">
                  <div class="nw-tc-left">
                    <label class="nw-toggle" title="Enable / disable">
                      <input type="checkbox"
                        name="namek_wa_<?php echo esc_attr($key); ?>_enabled"
                        value="1" <?php checked($enabled, '1'); ?>>
                      <span class="nw-toggle-track"><span class="nw-toggle-thumb"></span></span>
                    </label>
                    <div class="nw-tc-info">
                      <div class="nw-tc-title"><?php echo esc_html($trigger['label']); ?></div>
                      <div class="nw-tc-desc"><?php echo esc_html($trigger['desc']); ?></div>
                      <span class="nw-badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                    </div>
                  </div>
                  <div class="nw-tc-right">
                    <label class="nw-label" style="margin-bottom:5px">Message Template</label>
                    <textarea
                      name="namek_wa_<?php echo esc_attr($key); ?>_template"
                      rows="3"
                      class="nw-input nw-textarea"
                    ><?php echo esc_textarea($template); ?></textarea>
                    <div class="nw-tc-actions">
                      <button type="button" class="nw-btn nw-btn-sm nw-trigger-test" data-key="<?php echo esc_attr($key); ?>">
                        Send Test
                      </button>
                      <span class="namek-trigger-result"></span>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>

              </div>
            </div>

            <!-- Save -->
            <div class="nw-save-row">
              <button type="submit" class="nw-btn nw-btn-save">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Save Settings
              </button>
            </div>

          </form>
        </div>

        <style>
        /* ── Tokens ──────────────────────────────────────────── */
        .namek-wa-wrap {
          --p:    #3d0000;
          --p-lt: #6b1a1a;
          --acc:  #c25558;
          --bg:   #fffffc;
          --bgs:  #fff7f7;
          --bgr:  #ffeded;
          --bgrd: #ffe0e0;
          --tx:   #1a0a0a;
          --txs:  #7d6161;
          --txm:  #a89494;
          --grn:  #2d8a4e;
          --grnb: #e8f5ee;
          --blu:  #2980b9;
          --blub: #e8f0fe;
          --bdr:  #f0e0e0;
          --r:    12px;
          --rs:   8px;
          font-family: 'Inter', -apple-system, sans-serif;
          color: var(--tx);
        }

        /* Hero */
        .nw-hero {
          background: linear-gradient(135deg, var(--bgr) 0%, var(--bgrd) 100%);
          border-radius: var(--r);
          margin: 16px 0 20px;
        }
        .nw-hero-inner {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 20px 28px;
        }
        .nw-hero-brand { display: flex; align-items: center; gap: 14px; }
        .nw-logo-img { height: 62px; width: auto; object-fit: contain; }
        .nw-hero-title {
          font-family: 'Outfit', sans-serif;
          font-size: 20px; font-weight: 700;
          color: var(--p) !important;
          margin: 0 0 2px !important; padding: 0 !important;
        }
        .nw-hero-sub { font-size: 13px; color: var(--txs); margin: 0; }
        .nw-version-badge {
          background: var(--p); color: #fff;
          font-size: 11px; font-weight: 600;
          padding: 4px 12px; border-radius: 20px; letter-spacing: .5px;
        }

        /* Alert */
        .nw-alert { padding: 12px 16px; border-radius: var(--rs); font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .nw-alert-success { background: var(--grnb); color: var(--grn); border-left: 3px solid var(--grn); }

        /* Card */
        .nw-card {
          background: #fff;
          border: 1px solid var(--bdr);
          border-radius: var(--r);
          margin-bottom: 20px;
          box-shadow: 0 1px 4px rgba(61,0,0,.05);
          overflow: hidden;
        }
        .nw-card-header {
          padding: 14px 24px;
          border-bottom: 1px solid var(--bgr);
          background: var(--bgs);
        }
        .nw-card-header-flex {
          display: flex;
          align-items: center;
          gap: 16px;
          flex-wrap: wrap;
        }
        .nw-card-title {
          font-family: 'Outfit', sans-serif !important;
          font-size: 14px !important; font-weight: 600 !important;
          color: var(--p) !important;
          margin: 0 !important; padding: 0 !important;
          white-space: nowrap;
        }
        .nw-card-body { padding: 22px 24px 24px; }
        .nw-card-body-triggers { padding: 0; }

        /* Fields */
        .nw-field-row {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 16px;
          margin-bottom: 16px;
        }
        .nw-field { display: flex; flex-direction: column; gap: 5px; }
        .nw-label {
          display: block;
          font-size: 11px !important; font-weight: 600 !important;
          color: var(--txs) !important;
          text-transform: uppercase; letter-spacing: .5px;
          margin: 0 !important;
        }
        .nw-hint { font-size: 11px; color: var(--txm); line-height: 1.4; }

        /* Inputs */
        .namek-wa-wrap .nw-input {
          padding: 9px 13px !important;
          border: 2px solid var(--bgrd) !important;
          border-radius: var(--rs) !important;
          font-size: 13px !important;
          font-family: 'Inter', sans-serif !important;
          color: var(--tx) !important;
          background: var(--bg) !important;
          outline: none !important; box-shadow: none !important;
          transition: border-color .2s, box-shadow .2s;
          width: 100%; box-sizing: border-box;
        }
        .namek-wa-wrap .nw-input:focus {
          border-color: var(--acc) !important;
          box-shadow: 0 0 0 3px rgba(194,85,88,.1) !important;
        }
        .nw-textarea { resize: vertical; min-height: 80px; line-height: 1.5; }

        /* Test bar */
        .nw-test-bar {
          display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
          margin-top: 16px; padding: 14px 0 4px;
          border-top: 1px solid var(--bgr);
        }
        .nw-test-label {
          font-size: 11px; font-weight: 600; color: var(--txs);
          text-transform: uppercase; letter-spacing: .4px; white-space: nowrap;
        }
        .nw-test-input { width: 200px !important; }
        .nw-test-msg   { width: 230px !important; }
        #namek-test-result { font-size: 13px; font-weight: 500; }
        #namek-test-result.ok  { color: var(--grn); }
        #namek-test-result.err { color: var(--acc); }

        /* Placeholders in header */
        .nw-placeholders {
          display: flex; flex-wrap: wrap; align-items: center; gap: 4px;
        }
        .nw-ph {
          font-size: 10px;
          background: var(--bgrd); color: var(--p);
          padding: 2px 6px; border-radius: 4px;
          font-family: 'Courier New', monospace;
        }

        /* Trigger cards */
        .nw-tc {
          display: flex;
          gap: 0;
          border-bottom: 1px solid var(--bgr);
        }
        .nw-tc:last-child { border-bottom: none; }
        .nw-tc-muted { background: var(--bgs); }

        .nw-tc-left {
          display: flex;
          align-items: flex-start;
          gap: 12px;
          padding: 18px 20px;
          width: 260px;
          min-width: 260px;
          border-right: 1px solid var(--bgr);
        }
        .nw-tc-info { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .nw-tc-title { font-size: 13px; font-weight: 600; color: var(--tx); line-height: 1.3; }
        .nw-tc-desc  { font-size: 11px; color: var(--txm); line-height: 1.5; }

        .nw-tc-right {
          flex: 1;
          padding: 18px 20px;
          display: flex;
          flex-direction: column;
          gap: 8px;
          min-width: 0;
        }
        .nw-tc-actions {
          display: flex;
          align-items: center;
          gap: 10px;
        }

        /* Badges */
        .nw-badge {
          display: inline-block;
          font-size: 10px; font-weight: 600;
          padding: 2px 8px; border-radius: 20px; letter-spacing: .3px;
        }
        .nw-badge-customer { background: var(--grnb); color: var(--grn); }
        .nw-badge-admin    { background: var(--blub); color: var(--blu); }
        .nw-badge-group    { background: var(--bgrd); color: var(--p); }

        /* Toggle */
        .nw-toggle { position: relative; display: inline-block; cursor: pointer; flex-shrink: 0; margin-top: 2px; }
        .nw-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .nw-toggle-track {
          display: block; width: 38px; height: 22px;
          background: #ddd; border-radius: 22px;
          transition: background .2s; position: relative;
        }
        .nw-toggle-thumb {
          position: absolute; top: 3px; left: 3px;
          width: 16px; height: 16px;
          background: #fff; border-radius: 50%;
          transition: transform .2s;
          box-shadow: 0 1px 4px rgba(0,0,0,.2);
        }
        .nw-toggle input:checked ~ .nw-toggle-track { background: var(--acc); }
        .nw-toggle input:checked ~ .nw-toggle-track .nw-toggle-thumb { transform: translateX(16px); }

        /* Buttons */
        .namek-wa-wrap .nw-btn {
          display: inline-flex !important; align-items: center; gap: 6px;
          padding: 8px 16px !important;
          background: var(--bgrd) !important; color: var(--p) !important;
          border: none !important; border-radius: var(--rs) !important;
          font-size: 13px !important; font-weight: 600 !important;
          font-family: 'Inter', sans-serif !important;
          cursor: pointer !important;
          transition: background .2s, color .2s, transform .15s !important;
          text-shadow: none !important; box-shadow: none !important;
          height: auto !important; line-height: 1.4 !important;
          white-space: nowrap; text-decoration: none !important;
        }
        .namek-wa-wrap .nw-btn:hover { background: var(--acc) !important; color: #fff !important; }
        .namek-wa-wrap .nw-btn:active { transform: scale(.97) !important; }
        .namek-wa-wrap .nw-btn-sm { padding: 6px 12px !important; font-size: 12px !important; }
        .namek-wa-wrap .nw-btn-save {
          background: var(--p) !important; color: #fff !important;
          padding: 12px 28px !important; font-size: 14px !important;
          border-radius: var(--r) !important;
          box-shadow: 0 4px 14px rgba(61,0,0,.18) !important;
        }
        .namek-wa-wrap .nw-btn-save:hover { background: var(--p-lt) !important; }

        /* Save row */
        .nw-save-row { padding: 4px 0 24px; }

        /* Trigger result */
        .namek-trigger-result { font-size: 12px; }
        .namek-trigger-result.ok  { color: var(--grn); }
        .namek-trigger-result.err { color: var(--acc); }
        </style>
        <?php
    }
}
