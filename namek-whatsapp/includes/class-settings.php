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
        $triggers = self::get_triggers();
        $saved    = !empty($_GET['settings-updated']);
        ?>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

        <div class="wrap namek-wa-wrap">

          <!-- Hero -->
          <div class="nw-hero">
            <div class="nw-hero-inner">
              <div class="nw-hero-brand">
                <div class="nw-logo">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" fill="white"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.125.553 4.122 1.522 5.854L.054 23.293a.75.75 0 00.921.921l5.44-1.468A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0z" fill="white" opacity=".25"/>
                  </svg>
                </div>
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

            <!-- Connection -->
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
                    <span class="nw-hint">Group JID from your Namek dashboard — used for group notification triggers</span>
                  </div>
                  <div class="nw-field" style="display:flex;align-items:flex-end">
                    <div style="padding:10px 14px;background:var(--nw-bg-soft);border-radius:var(--nw-radius-sm);border:1px solid var(--nw-border);font-size:12px;color:var(--nw-text-muted);line-height:1.6">
                      Find your group ID in the Namek dashboard under your unit's group list, or via<br>
                      <code style="font-size:11px;background:var(--nw-bg-rose-deep);color:var(--nw-primary);padding:1px 5px;border-radius:3px">GET /api/v1/groups</code>
                    </div>
                  </div>
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
                  <div class="nw-field">
                    <label class="nw-label">Admin Phone</label>
                    <input type="text" name="namek_wa_admin_phone"
                      value="<?php echo esc_attr(get_option('namek_wa_admin_phone')); ?>"
                      class="nw-input" placeholder="919876543210">
                    <span class="nw-hint">Receives new order &amp; low stock alerts — include country code, no +</span>
                  </div>
                </div>

                <div class="nw-field-row">
                  <div class="nw-field" style="max-width:200px">
                    <label class="nw-label">Default Country Code</label>
                    <input type="text" name="namek_wa_country_code"
                      value="<?php echo esc_attr(get_option('namek_wa_country_code', '91')); ?>"
                      class="nw-input" placeholder="91">
                    <span class="nw-hint">Prepended to 10-digit numbers (91 = India)</span>
                  </div>
                </div>

                <div class="nw-test-bar">
                  <span class="nw-test-label">Test connection</span>
                  <input type="text" id="namek-test-phone" class="nw-input" style="width:190px" placeholder="Phone e.g. 919876543210">
                  <input type="text" id="namek-test-message" class="nw-input" style="width:220px" placeholder="Hello from Namek!">
                  <button type="button" class="nw-btn" id="namek-test-send">Send Test</button>
                  <span id="namek-test-result"></span>
                </div>

              </div>
            </div>

            <!-- Triggers -->
            <div class="nw-card">
              <div class="nw-card-header">
                <h2 class="nw-card-title">WooCommerce Triggers</h2>
              </div>
              <div class="nw-card-body">

                <div class="nw-placeholders">
                  <span class="nw-ph-label">Placeholders:</span>
                  <?php foreach (['{name}','{full_name}','{order_id}','{order_total}','{order_status}','{product_list}','{site_name}','{note}','{product_name}','{stock_qty}'] as $ph): ?>
                  <code class="nw-ph"><?php echo esc_html($ph); ?></code>
                  <?php endforeach; ?>
                </div>

                <div class="nw-trigger-list">
                  <?php foreach ($triggers as $key => $trigger):
                    $enabled  = get_option("namek_wa_{$key}_enabled", '');
                    $template = get_option("namek_wa_{$key}_template", $trigger['default']);
                    $is_admin = $trigger['target'] === 'admin';
                    $is_group = $trigger['target'] === 'group';
                  ?>
                  <div class="nw-trigger-row<?php echo ($is_admin || $is_group) ? ' nw-trigger-admin' : ''; ?>">
                    <div class="nw-trigger-meta">
                      <div class="nw-trigger-top">
                        <label class="nw-toggle">
                          <input type="checkbox"
                            name="namek_wa_<?php echo esc_attr($key); ?>_enabled"
                            value="1" <?php checked($enabled, '1'); ?>>
                          <span class="nw-toggle-track"><span class="nw-toggle-thumb"></span></span>
                        </label>
                        <strong class="nw-trigger-name"><?php echo esc_html($trigger['label']); ?></strong>
                      </div>
                      <p class="nw-trigger-desc"><?php echo esc_html($trigger['desc']); ?></p>
                      <?php if ($is_group): ?>
                        <span class="nw-badge nw-badge-group">&#x2192; WhatsApp Group</span>
                      <?php elseif ($is_admin): ?>
                        <span class="nw-badge nw-badge-admin">&#x2192; Admin phone</span>
                      <?php else: ?>
                        <span class="nw-badge nw-badge-customer">&#x2192; Customer</span>
                      <?php endif; ?>
                    </div>
                    <div class="nw-trigger-template">
                      <textarea
                        name="namek_wa_<?php echo esc_attr($key); ?>_template"
                        rows="3"
                        class="nw-input nw-textarea"
                      ><?php echo esc_textarea($template); ?></textarea>
                    </div>
                    <div class="nw-trigger-action">
                      <button type="button" class="nw-btn nw-btn-sm nw-trigger-test" data-key="<?php echo esc_attr($key); ?>">Test</button>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>

              </div>
            </div>

            <div style="margin-bottom:24px">
              <button type="submit" class="nw-btn nw-btn-primary">Save Settings</button>
            </div>

          </form>
        </div>

        <style>
        /* ── Namek brand tokens ─────────────────────────────── */
        .namek-wa-wrap {
          --nw-primary:      #3d0000;
          --nw-primary-lt:   #6b1a1a;
          --nw-accent:       #c25558;
          --nw-bg:           #fffffc;
          --nw-bg-soft:      #fff7f7;
          --nw-bg-rose:      #ffeded;
          --nw-bg-rose-deep: #ffe0e0;
          --nw-text:         #1a0a0a;
          --nw-text-sec:     #7d6161;
          --nw-text-muted:   #a89494;
          --nw-green:        #2d8a4e;
          --nw-green-bg:     #e8f5ee;
          --nw-blue:         #2980b9;
          --nw-blue-bg:      #e8f0fe;
          --nw-border:       #f0e0e0;
          --nw-radius:       12px;
          --nw-radius-sm:    8px;
          font-family: 'Inter', -apple-system, sans-serif;
          color: var(--nw-text);
          max-width: 980px;
        }

        /* Hero */
        .nw-hero {
          background: linear-gradient(135deg, var(--nw-bg-rose) 0%, var(--nw-bg-rose-deep) 100%);
          border-radius: var(--nw-radius);
          margin: 16px 0 20px;
          overflow: hidden;
        }
        .nw-hero-inner {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 22px 28px;
        }
        .nw-hero-brand { display: flex; align-items: center; gap: 14px; }
        .nw-logo {
          width: 44px; height: 44px;
          background: var(--nw-primary);
          border-radius: 10px;
          display: flex; align-items: center; justify-content: center;
          flex-shrink: 0;
        }
        .nw-hero-title {
          font-family: 'Outfit', sans-serif;
          font-size: 20px;
          font-weight: 700;
          color: var(--nw-primary) !important;
          margin: 0 0 2px !important;
          padding: 0 !important;
          line-height: 1.2;
        }
        .nw-hero-sub { font-size: 13px; color: var(--nw-text-sec); margin: 0; }
        .nw-version-badge {
          background: var(--nw-primary);
          color: #fff;
          font-size: 11px;
          font-weight: 600;
          padding: 4px 12px;
          border-radius: 20px;
          letter-spacing: .5px;
        }

        /* Alert */
        .nw-alert {
          padding: 12px 16px;
          border-radius: var(--nw-radius-sm);
          font-size: 13px;
          font-weight: 500;
          margin-bottom: 16px;
        }
        .nw-alert-success { background: var(--nw-green-bg); color: var(--nw-green); border-left: 3px solid var(--nw-green); }

        /* Cards */
        .nw-card {
          background: #fff;
          border: 1px solid var(--nw-border);
          border-radius: var(--nw-radius);
          margin-bottom: 20px;
          box-shadow: 0 1px 4px rgba(61,0,0,.05);
          overflow: hidden;
        }
        .nw-card-header {
          padding: 15px 24px;
          border-bottom: 1px solid var(--nw-bg-rose);
          background: var(--nw-bg-soft);
        }
        .nw-card-title {
          font-family: 'Outfit', sans-serif !important;
          font-size: 14px !important;
          font-weight: 600 !important;
          color: var(--nw-primary) !important;
          margin: 0 !important;
          padding: 0 !important;
        }
        .nw-card-body { padding: 20px 24px; }

        /* Fields */
        .nw-field-row {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 16px;
          margin-bottom: 16px;
        }
        .nw-field { display: flex; flex-direction: column; gap: 5px; }
        .nw-label {
          font-size: 11px !important;
          font-weight: 600 !important;
          color: var(--nw-text-sec) !important;
          text-transform: uppercase;
          letter-spacing: .5px;
          margin: 0 !important;
        }
        .nw-hint { font-size: 11px; color: var(--nw-text-muted); }

        /* Inputs — override WP defaults */
        .namek-wa-wrap .nw-input {
          padding: 9px 13px !important;
          border: 2px solid var(--nw-bg-rose-deep) !important;
          border-radius: var(--nw-radius-sm) !important;
          font-size: 13px !important;
          font-family: 'Inter', sans-serif !important;
          color: var(--nw-text) !important;
          background: var(--nw-bg) !important;
          outline: none !important;
          box-shadow: none !important;
          transition: border-color .2s, box-shadow .2s;
          width: 100%;
          box-sizing: border-box;
        }
        .namek-wa-wrap .nw-input:focus {
          border-color: var(--nw-accent) !important;
          box-shadow: 0 0 0 3px rgba(194,85,88,.1) !important;
        }
        .nw-textarea { resize: vertical; min-height: 68px; }

        /* Test bar */
        .nw-test-bar {
          display: flex;
          align-items: center;
          gap: 8px;
          flex-wrap: wrap;
          margin-top: 16px;
          padding-top: 16px;
          border-top: 1px solid var(--nw-bg-rose);
        }
        .nw-test-label {
          font-size: 11px;
          font-weight: 600;
          color: var(--nw-text-sec);
          text-transform: uppercase;
          letter-spacing: .4px;
          white-space: nowrap;
        }
        #namek-test-result { font-size: 13px; font-weight: 500; }
        #namek-test-result.ok  { color: var(--nw-green); }
        #namek-test-result.err { color: var(--nw-accent); }

        /* Placeholders bar */
        .nw-placeholders {
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          gap: 5px;
          margin-bottom: 16px;
          padding: 10px 14px;
          background: var(--nw-bg-soft);
          border-radius: var(--nw-radius-sm);
          border: 1px solid var(--nw-border);
        }
        .nw-ph-label {
          font-size: 11px;
          font-weight: 600;
          color: var(--nw-text-muted);
          text-transform: uppercase;
          letter-spacing: .4px;
          margin-right: 4px;
        }
        .nw-ph {
          font-size: 11px;
          background: var(--nw-bg-rose-deep);
          color: var(--nw-primary);
          padding: 2px 7px;
          border-radius: 4px;
          font-family: 'Courier New', monospace;
        }

        /* Trigger list */
        .nw-trigger-list { display: flex; flex-direction: column; }
        .nw-trigger-row {
          display: grid;
          grid-template-columns: 210px 1fr 58px;
          gap: 14px;
          align-items: start;
          padding: 14px 0;
          border-bottom: 1px solid var(--nw-bg-rose);
        }
        .nw-trigger-row:last-child { border-bottom: none; padding-bottom: 0; }
        .nw-trigger-admin {
          background: var(--nw-bg-soft);
          border-radius: 8px;
          padding: 12px 10px;
          margin: 0 -10px;
        }
        .nw-trigger-top { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
        .nw-trigger-name { font-size: 13px; font-weight: 600; color: var(--nw-text); }
        .nw-trigger-desc { font-size: 11px; color: var(--nw-text-muted); margin: 0 0 7px; line-height: 1.5; }
        .nw-trigger-action { display: flex; align-items: flex-start; padding-top: 2px; }

        /* Badges */
        .nw-badge {
          display: inline-block;
          font-size: 10px;
          font-weight: 600;
          padding: 2px 8px;
          border-radius: 20px;
          letter-spacing: .3px;
        }
        .nw-badge-customer { background: var(--nw-green-bg);  color: var(--nw-green); }
        .nw-badge-admin    { background: var(--nw-blue-bg);   color: var(--nw-blue); }
        .nw-badge-group    { background: var(--nw-bg-rose-deep); color: var(--nw-primary); }

        /* Toggle */
        .nw-toggle { position: relative; display: inline-block; cursor: pointer; flex-shrink: 0; line-height: 1; }
        .nw-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .nw-toggle-track {
          display: block;
          width: 36px; height: 20px;
          background: #d0c0c0;
          border-radius: 20px;
          transition: background .2s;
          position: relative;
        }
        .nw-toggle-thumb {
          position: absolute;
          top: 3px; left: 3px;
          width: 14px; height: 14px;
          background: #fff;
          border-radius: 50%;
          transition: transform .2s;
          box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        .nw-toggle input:checked ~ .nw-toggle-track { background: var(--nw-accent); }
        .nw-toggle input:checked ~ .nw-toggle-track .nw-toggle-thumb { transform: translateX(16px); }

        /* Buttons */
        .namek-wa-wrap .nw-btn {
          display: inline-flex !important;
          align-items: center;
          gap: 6px;
          padding: 8px 16px !important;
          background: var(--nw-bg-rose-deep) !important;
          color: var(--nw-primary) !important;
          border: none !important;
          border-radius: var(--nw-radius-sm) !important;
          font-size: 13px !important;
          font-weight: 600 !important;
          font-family: 'Inter', sans-serif !important;
          cursor: pointer !important;
          transition: background .2s, color .2s !important;
          text-shadow: none !important;
          box-shadow: none !important;
          height: auto !important;
          line-height: 1.4 !important;
          white-space: nowrap;
        }
        .namek-wa-wrap .nw-btn:hover {
          background: var(--nw-accent) !important;
          color: #fff !important;
        }
        .namek-wa-wrap .nw-btn-primary {
          background: var(--nw-primary) !important;
          color: #fff !important;
          padding: 11px 28px !important;
          font-size: 14px !important;
          border-radius: var(--nw-radius) !important;
        }
        .namek-wa-wrap .nw-btn-primary:hover {
          background: var(--nw-primary-lt) !important;
        }
        .namek-wa-wrap .nw-btn-sm {
          padding: 6px 12px !important;
          font-size: 12px !important;
        }

        /* Trigger test result */
        .namek-trigger-result { font-size: 11px; margin-top: 4px; display: block; }
        .namek-trigger-result.ok  { color: var(--nw-green); }
        .namek-trigger-result.err { color: var(--nw-accent); }
        </style>
        <?php
    }
}
