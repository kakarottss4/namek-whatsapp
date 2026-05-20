jQuery(function ($) {

  // ── Test connection (top bar) ───────────────────────────────────────────

  $('#namek-test-send').on('click', function () {
    var $btn    = $(this);
    var phone   = $('#namek-test-phone').val().trim();
    var message = $('#namek-test-message').val().trim();
    var $result = $('#namek-test-result');

    if (!phone || !message) {
      $result.attr('class', 'err').text('Enter a phone number and message first.');
      return;
    }

    $btn.prop('disabled', true).text('Sending…');
    $result.attr('class', '').text('');

    $.post(namekWA.ajax_url, {
      action:  'namek_wa_test_send',
      nonce:   namekWA.nonce,
      phone:   phone,
      message: message,
    })
    .done(function (res) {
      if (res.success) {
        $result.attr('class', 'ok').text('✓ ' + (res.data || 'Sent!'));
      } else {
        $result.attr('class', 'err').text('✗ ' + (res.data || 'Failed.'));
      }
    })
    .fail(function () {
      $result.attr('class', 'err').text('✗ Request failed — check console.');
    })
    .always(function () {
      $btn.prop('disabled', false).text('Send Test Message');
    });
  });

  // ── Per-trigger test buttons ────────────────────────────────────────────

  $('.namek-trigger-test').on('click', function () {
    var $btn  = $(this);
    var $row  = $btn.closest('tr');
    var key   = $btn.data('key');

    // Use the current textarea content, replace placeholders with sample text
    var template = $row.find('textarea').val();
    var preview  = template.replace(/\{[^}]+\}/g, function (match) {
      var samples = {
        '{name}':         'Ananya',
        '{full_name}':    'Ananya Sharma',
        '{order_id}':     '1042',
        '{order_total}':  '₹2,500.00',
        '{order_status}': 'Processing',
        '{product_list}': 'Herbal Tea ×2, Honey ×1',
        '{site_name}':    'My Store',
        '{note}':         'Your parcel will be dispatched tomorrow.',
        '{product_name}': 'Herbal Tea',
        '{stock_qty}':    '3',
      };
      return samples[match] !== undefined ? samples[match] : match;
    });

    var phone = prompt('Send test to phone number (with country code, no +):\ne.g. 919876543210', '');
    if (!phone) return;

    // Remove old result if any
    $row.find('.namek-trigger-result').remove();
    var $result = $('<span class="namek-trigger-result"></span>');
    $btn.after($result);

    $btn.prop('disabled', true);

    $.post(namekWA.ajax_url, {
      action:  'namek_wa_test_send',
      nonce:   namekWA.nonce,
      phone:   phone,
      message: preview,
    })
    .done(function (res) {
      if (res.success) {
        $result.addClass('ok').text('✓ Sent');
      } else {
        $result.addClass('err').text('✗ ' + (res.data || 'Failed'));
      }
    })
    .fail(function () {
      $result.addClass('err').text('✗ Failed');
    })
    .always(function () {
      $btn.prop('disabled', false);
      setTimeout(function () { $result.fadeOut(400, function () { $(this).remove(); }); }, 5000);
    });
  });

});
