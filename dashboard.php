<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/payments.php';

$user = require_login();
$uid  = (int)$user['id'];

expire_stale_orders($uid);

$paid     = paid_row($uid);                 // condition 1
$lock     = lockout_info($uid);             // extra condition: too many failures
$attempts = user_payments($uid);            // condition 3: every attempt + its response
$last     = $attempts[0] ?? null;
$totalFailed = total_failed($uid);
$rupees   = number_format($app_config['amount_paise'] / 100, 2);

page_header('Payment');
?>
<h1>Hello, <?= e($user['name']) ?></h1>
<p class="muted">Plan: <?= e($app_config['product_name']) ?></p>

<?php if ($paid): ?>
  <!-- CONDITION 1: already paid -->
  <div class="card">
    <div class="alert ok" role="status"><strong>You have already made the payment.</strong> No further payment is needed.</div>
    <table>
      <tr><th>Amount</th><td>INR <?= e(number_format($paid['amount'] / 100, 2)) ?></td></tr>
      <tr><th>Payment ID</th><td><?= e($paid['razorpay_payment_id']) ?></td></tr>
      <tr><th>Order ID</th><td><?= e($paid['razorpay_order_id']) ?></td></tr>
      <tr><th>Paid on</th><td><?= e($paid['timeupdated'] ?? $paid['timecreated']) ?></td></tr>
    </table>
    <?php if ($paid['status'] === 'duplicate'): ?>
      <div class="alert warn" style="margin-top:12px">This payment was recorded as a duplicate and needs a refund from the Razorpay dashboard.</div>
    <?php endif; ?>
  </div>

<?php elseif ($lock['locked']): ?>
  <!-- EXTRA: lockout after repeated failures -->
  <div class="card">
    <div class="alert warn" role="alert">
      <strong>Too many failed payments (<?= (int)$lock['failures'] ?>).</strong>
      You can try again in <span id="countdown" data-seconds="<?= (int)$lock['remaining'] ?>"></span>.
    </div>
  </div>
  <script>
    (function () {
      var el = document.getElementById('countdown'), s = parseInt(el.dataset.seconds, 10);
      function tick() {
        if (s <= 0) { location.reload(); return; }
        el.textContent = Math.floor(s / 60) + 'm ' + String(s % 60).padStart(2, '0') + 's';
        s--; setTimeout(tick, 1000);
      }
      tick();
    })();
  </script>

<?php else: ?>
  <div class="card">
    <?php if ($last && $last['status'] === 'failed'): ?>
      <!-- CONDITION 2: failed -> show failure, allow another try -->
      <div class="alert error" role="alert">
        <strong>Your last payment failed.</strong>
        <?= e($last['error_description'] ?: 'The payment was not completed.') ?>
        You can try again (<?= (int)$lock['failures'] ?> of <?= (int)$app_config['max_failed_attempts'] ?> recent failures used).
      </div>
    <?php elseif ($last && in_array($last['status'], ['cancelled', 'created', 'expired'], true)): ?>
      <div class="alert info">Your last attempt was not completed. No money was taken.</div>
    <?php endif; ?>

    <h2><?= e($app_config['product_name']) ?></h2>
    <div class="amount">INR <?= e($rupees) ?></div>
    <div id="pay-msg" class="alert error" role="alert" hidden></div>
    <button id="pay-btn" type="button"><?= $last && $last['status'] === 'failed' ? 'Try payment again' : 'Pay now' ?></button>
    <p class="muted" style="margin-top:12px">Test mode: use Razorpay test cards/UPI, no real money moves.</p>
  </div>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
  (function () {
    var CSRF = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var btn = document.getElementById('pay-btn'), msg = document.getElementById('pay-msg');

    function showError(t) { msg.textContent = t; msg.hidden = false; btn.disabled = false; }

    function post(url, data) {
      var body = new URLSearchParams(data); body.append('csrf', CSRF);
      return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (j) { j._ok = r.ok; return j; }); });
    }

    function logAndReload(orderId, outcome, error) {
      post('log_attempt.php', { order_id: orderId, outcome: outcome, error: JSON.stringify(error || {}) })
        .catch(function () {}).then(function () { location.reload(); });
    }

    btn.addEventListener('click', function () {
      btn.disabled = true; msg.hidden = true;

      post('create_order.php', {}).then(function (o) {
        if (!o._ok) {
          if (o.redirect) { location.href = o.redirect; return; }
          if (o.reload)   { location.reload(); return; }
          return showError(o.error || 'Could not start payment.');
        }

        var finished = false;       // makes sure we report exactly one outcome per attempt
        var rzp = new Razorpay({
          key: o.key, amount: o.amount, currency: o.currency, order_id: o.order_id,
          name: o.name, description: o.description, prefill: o.prefill,
          theme: { color: '#2f6fed' },
          retry: { enabled: false },   // every retry goes through OUR server so our rules (lockout etc.) apply
          handler: function (resp) {   // success -> verify on the server
            finished = true;
            var f = document.createElement('form'); f.method = 'POST'; f.action = 'verify.php';
            var fields = { csrf: CSRF, razorpay_payment_id: resp.razorpay_payment_id,
                           razorpay_order_id: resp.razorpay_order_id, razorpay_signature: resp.razorpay_signature };
            Object.keys(fields).forEach(function (k) {
              var i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = fields[k]; f.appendChild(i);
            });
            document.body.appendChild(f); f.submit();
          },
          modal: {
            ondismiss: function () {   // closed the popup without paying
              if (!finished) { finished = true; logAndReload(o.order_id, 'cancelled', {}); }
            }
          }
        });
        rzp.on('payment.failed', function (resp) {
          if (finished) return;
          finished = true;
          logAndReload(o.order_id, 'failed', resp.error);
        });
        rzp.open();
      }).catch(function () { showError('Network problem. Please try again.'); });
    });
  })();
  </script>
<?php endif; ?>

<?php if ($attempts): ?>
  <button>Reset All Payment</button>
  <!-- CONDITION 3: every attempt and its stored response -->
  <div class="card">
    <h2>Payment history <span class="muted">(<?= count($attempts) ?> attempt<?= count($attempts) === 1 ? '' : 's' ?>, <?= (int)$totalFailed ?> failed)</span></h2>
    <div class="scroll">
    <table>
      <thead><tr><th>#</th><th>When</th><th>Status</th><th>Details</th><th>Stored response</th></tr></thead>
      <tbody>
      <?php foreach ($attempts as $i => $a):
          $pretty = json_encode(json_decode((string)$a['response'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      ?>
        <tr>
          <td><?= count($attempts) - $i ?></td>
          <td><?= e($a['timecreated']) ?></td>
          <td><span class="badge b-<?= e($a['status']) ?>"><?= e(status_label((string)$a['status'])) ?></span></td>
          <td>
            INR <?= e(number_format($a['amount'] / 100, 2)) ?><br>
            <span class="muted">Order <?= e($a['razorpay_order_id']) ?></span>
            <?php if ($a['razorpay_payment_id']): ?><br><span class="muted">Payment <?= e($a['razorpay_payment_id']) ?></span><?php endif; ?>
            <?php if ($a['error_description']): ?><br><span style="color:var(--bad)"><?= e($a['error_code']) ?>: <?= e($a['error_description']) ?></span><?php endif; ?>
          </td>
          <td><details><summary>View JSON</summary><pre><?= e($pretty) ?></pre></details></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>
<?php page_footer();
