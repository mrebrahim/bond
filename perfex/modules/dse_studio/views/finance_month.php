<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$m    = $f['month'];
$prev = date('Y-m', strtotime($m . '-01 -1 month'));
$next = date('Y-m', strtotime($m . '-01 +1 month'));
$chg  = function ($v, $good_up = true) {
    if ($v === null) { return '<span class="text-muted small">—</span>'; }
    $up = $v >= 0;
    return '<span class="small ' . (($up === $good_up) ? 'pos' : 'neg') . '">' . ($up ? '▲' : '▼') . ' ' . abs((int) $v) . '%</span>';
};
$colors = ['#2563eb', '#16a34a', '#9333ea', '#f59e0b', '#0ea5e9', '#dc2626', '#64748b', '#db2777', '#0d9488'];
$sm = max(1, max(array_merge([1], array_column($f['streams'], 'cash'))));
?>
<style>
.fin .k{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
.fin .k b{display:block;font-size:22px;line-height:1.3}
.fin .k small{color:#64748b}
.fin .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:12px}
.fin .pos{color:#16a34a}.fin .neg{color:#dc2626}
.fin .hbar{height:10px;border-radius:5px;background:#f1f5f9;overflow:hidden;margin:3px 0 10px}
.fin .hbar i{display:block;height:100%;border-radius:5px}
.fin .stack{display:flex;height:16px;border-radius:8px;overflow:hidden;margin:6px 0 12px}
.fin table td,.fin table th{vertical-align:middle!important}
</style>
<div id="wrapper"><div class="content fin">
  <div class="panel_s"><div class="panel-body">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
      <h4 class="tw-font-semibold" style="margin:0">🔍 تفاصيل شهر <?= html_escape($m); ?> <span class="label label-default">للأدمن بس</span></h4>
      <div>
        <a class="btn btn-default btn-sm" href="<?= admin_url('dse_studio/finance?month=' . $m); ?>">← اللوحة المالية</a>
        <a class="btn btn-default btn-sm" href="?month=<?= $prev; ?>">→ <?= $prev; ?></a>
        <a class="btn btn-default btn-sm" href="?month=<?= $next; ?>"><?= $next; ?> ←</a>
      </div>
    </div>
  </div></div>

  <div class="grid">
    <div class="k"><small>💵 الكاش اللي دخل (بالجنيه)</small><b><?= dse_money($f['cash']); ?></b><?php $nonegp = array_diff_key($f['cash_by_currency'], ['EGP' => 1]); if ($nonegp) { echo '<small>جوّاه: '; foreach ($nonegp as $code => $v) { echo html_escape($code) . ' ' . dse_num($v) . ' '; } echo '</small><br>'; } ?><?= $chg($f['cash_change']); ?> <small>عن <?= $f['prev_month']; ?> (<?= dse_money($f['prev_cash']); ?>)</small></div>
    <div class="k"><small>🧾 المصروفات</small><b><?= dse_money($f['expenses']); ?></b><?= $chg($f['expenses_change'], false); ?> <small>عن <?= $f['prev_month']; ?> (<?= dse_money($f['prev_expenses']); ?>)</small></div>
    <div class="k"><small>📈 صافي الربح</small><b class="<?= $f['profit'] >= 0 ? 'pos' : 'neg'; ?>"><?= dse_money($f['profit']); ?></b><small>هامش <?= $f['margin'] === null ? '—' : (int) $f['margin'] . '%'; ?> · الشهر اللي قبله <?= dse_money($f['prev_profit']); ?></small></div>
    <div class="k"><small>🧾 فواتير اتعملت الشهر ده</small><b><?= dse_money($f['invoices']['total']); ?></b><small><?= (int) $f['invoices']['count']; ?> فاتورة · اتحصّل <?= dse_money($f['invoices']['paid']); ?> · باقي <span class="neg"><?= dse_money($f['invoices']['unpaid']); ?></span></small></div>
    <div class="k"><small>🤝 باقات اتقفلت</small><b><?= dse_money($f['booked']['total']); ?></b><small>جديد <?= (int) $f['booked']['new_count']; ?> · تجديد <?= (int) $f['booked']['renew_count']; ?></small></div>
  </div>

  <div class="row">
    <!-- الإيرادات حسب المصدر -->
    <div class="col-md-6">
      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">💰 الإيرادات جاية منين؟ <span class="small text-muted">(الكاش اللي دخل متوزع على بنود الفواتير)</span></h5>
        <?php if (!$f['streams']) { ?><p class="text-muted">مفيش إيرادات الشهر ده</p><?php } else { ?>
        <div class="stack"><?php foreach ($f['streams'] as $i => $s) { if ($s['cash'] > 0) { ?><div title="<?= html_escape($s['label']); ?>" style="width:<?= max(0.5, $s['share']); ?>%;background:<?= $s['key'] === 'other' ? '#cbd5e1' : $colors[$i % count($colors)]; ?>"></div><?php } } ?></div>
        <?php foreach ($f['streams'] as $i => $s) { ?>
          <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
            <span><?= $i === 0 && $s['key'] !== 'other' ? '🥇 ' : ''; ?><b><?= html_escape($s['label']); ?></b></span>
            <span><b><?= dse_money($s['cash']); ?></b> <span class="text-muted small"><?= (int) $s['share']; ?>%</span> <?= $chg($s['change']); ?></span>
          </div>
          <div class="hbar"><i style="width:<?= round(100 * $s['cash'] / $sm); ?>%;background:<?= $s['key'] === 'other' ? '#cbd5e1' : $colors[$i % count($colors)]; ?>"></i></div>
          <?php if ($s['invoiced'] > 0.5) { ?><div class="small text-muted" style="margin:-8px 0 8px">اتفوتر الشهر ده: <?= dse_money($s['invoiced']); ?></div><?php } ?>
        <?php } ?>
        <?php } ?>
        <?php if ($f['unclassified']) { ?>
          <div class="alert alert-warning small mtop10" style="margin-bottom:0">
            <b>❓ بنود مش متصنفة</b> — ضيف كلمة منها لمصدرها تحت علشان تتحسب صح:
            <ul style="margin:4px 0 0;padding-inline-start:18px"><?php foreach ($f['unclassified'] as $t => $v) { ?><li><?= html_escape($t); ?> — <?= dse_money($v); ?></li><?php } ?></ul>
          </div>
        <?php } ?>
      </div></div>

      <details class="panel_s" id="streams">
        <summary class="panel-body bold" style="cursor:pointer">⚙️ تصنيف الإيرادات (المصادر وكلماتها)</summary>
        <div class="panel-body" style="border-top:1px solid #eee">
          <p class="small text-muted">كل بند في الفاتورة بيروح لأول مصدر فيه كلمة من كلماته (الترتيب مهم: الكورسات قبل الخدمات). افصل الكلمات بفاصلة. خلي الاسم فاضي علشان تشيل مصدر.</p>
          <?= form_open(admin_url('dse_studio/finance_month?month=' . $m)); ?>
            <?php $i = 0; foreach (array_merge($f['stream_defs'], ['new1' => ['label' => '', 'keywords' => []], 'new2' => ['label' => '', 'keywords' => []]]) as $k => $x) { $key = strpos($k, 'new') === 0 ? 'x' . $i : $k; $i++; ?>
              <div class="row">
                <div class="col-xs-5 form-group" style="margin-bottom:6px"><input name="streams[<?= html_escape($key); ?>][label]" value="<?= html_escape($x['label']); ?>" class="form-control input-sm" placeholder="مصدر جديد"></div>
                <div class="col-xs-7 form-group" style="margin-bottom:6px"><input name="streams[<?= html_escape($key); ?>][keywords]" value="<?= html_escape(implode(', ', $x['keywords'])); ?>" class="form-control input-sm" placeholder="كلمة, كلمة"></div>
              </div>
            <?php } ?>
            <button class="btn btn-primary btn-sm">احفظ التصنيف</button>
          <?= form_close(); ?>
        </div>
      </details>
    </div>

    <!-- المصروفات -->
    <div class="col-md-6">
      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">🧾 صرفنا في إيه؟</h5>
        <?php if (!$f['categories']) { ?><p class="text-muted">مفيش مصروفات متسجلة الشهر ده — سجّلها من Perfex ← Expenses بتصنيفاتها (إعلانات، مرتبات، اشتراكات…)</p><?php } ?>
        <?php foreach ($f['categories'] as $i => $c) { ?>
          <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
            <span><?= $i === 0 ? '🔺 ' : ''; ?><b><?= html_escape($c['name']); ?></b></span>
            <span><b><?= dse_money($c['amount']); ?></b> <span class="text-muted small"><?= (int) $c['share']; ?>%</span> <?= $chg($c['change'], false); ?></span>
          </div>
          <div class="hbar"><i style="width:<?= (int) $c['share']; ?>%;background:#dc2626"></i></div>
        <?php } ?>
      </div></div>

      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">📋 أكبر المصروفات</h5>
        <?php if (!$f['top_expenses']) { ?><p class="text-muted small">—</p><?php } else { ?>
        <table class="table table-condensed small">
          <thead><tr><th>المصروف</th><th>التصنيف</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
          <tbody><?php foreach ($f['top_expenses'] as $e) { ?>
            <tr><td><?= html_escape((string) ($e['expense_name'] ?: '—')); ?></td><td><?= html_escape((string) ($e['cat'] ?? '')); ?></td><td><?= dse_day($e['date']); ?></td><td class="neg"><?= dse_money($e['amount']); ?></td></tr>
          <?php } ?></tbody>
        </table>
        <?php } ?>
      </div></div>
    </div>
  </div>
</div></div>
<?php init_tail(); ?>
</body></html>
