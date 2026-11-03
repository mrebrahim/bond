<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$m     = $f['month'];
$prev  = date('Y-m', strtotime($m . '-01 -1 month'));
$next  = date('Y-m', strtotime($m . '-01 +1 month'));
$pct   = function ($v) { return $v === null ? '—' : (int) $v . '%'; };
$mx    = max(1, max(array_map(function ($t) { return max($t['cash'], $t['expenses'], $t['new'] + $t['renew']); }, $f['trend'])));
$mix   = $f['booked']['mix'];
$mixt  = max(1, array_sum($mix));
$mixl  = ['content' => ['🎬 محتوى (ريلز / تصميم / يوتيوب / بودكاست)', '#2563eb'], 'ads_fee' => ['📣 أتعاب الإعلانات', '#16a34a'], 'ads_budget' => ['💸 ميزانيات إعلانات (للمنصات)', '#94a3b8'], 'programming' => ['💻 برمجة (ويب / أبلكيشن / أوتوميشن)', '#9333ea']];
$rec   = $f['receivables'];
$ren   = $f['renewals'];
?>
<style>
.fin .k{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px;height:100%}
.fin .k b{display:block;font-size:22px;line-height:1.3}
.fin .k small{color:#64748b}
.fin .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:12px}
.fin .pos{color:#16a34a}.fin .neg{color:#dc2626}
.fin .bars{display:flex;align-items:flex-end;gap:6px;height:170px;padding-top:10px}
.fin .bars .m{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;min-width:0}
.fin .bars .g{display:flex;align-items:flex-end;gap:2px;height:140px;width:100%;justify-content:center}
.fin .bars .g i{display:block;width:30%;border-radius:3px 3px 0 0}
.fin .bars .lbl{font-size:10px;color:#64748b;white-space:nowrap}
.fin .stack{display:flex;height:14px;border-radius:7px;overflow:hidden;margin:6px 0 10px}
.fin .dot{display:inline-block;width:10px;height:10px;border-radius:3px;margin-inline-end:4px;vertical-align:middle}
.fin table td,.fin table th{vertical-align:middle!important}
</style>
<div id="wrapper"><div class="content fin">
  <div class="panel_s"><div class="panel-body">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
      <h4 class="tw-font-semibold" style="margin:0">💰 اللوحة المالية — <?= html_escape($m); ?> <span class="label label-default">للأدمن بس</span></h4>
      <div><a class="btn btn-primary btn-sm" href="<?= admin_url('dse_studio/finance_month?month=' . $m); ?>">🔍 تفاصيل الشهر ده</a> <a class="btn btn-default btn-sm" href="?month=<?= $prev; ?>">→ <?= $prev; ?></a> <a class="btn btn-default btn-sm" href="?month=<?= date('Y-m'); ?>">الشهر ده</a> <a class="btn btn-default btn-sm" href="?month=<?= $next; ?>"><?= $next; ?> ←</a></div>
    </div>
  </div></div>

  <!-- الكاش والربح -->
  <div class="grid">
    <div class="k"><small>💵 الكاش اللي دخل (فعلي)</small><b><?= dse_money($f['cash']['all']); ?></b><small>منه <?= dse_money($f['cash']['studio']); ?> على الباقات</small></div>
    <div class="k"><small>🧾 المصروفات</small><b><?= dse_money($f['expenses']['total']); ?></b><small>من Expenses في Perfex</small></div>
    <div class="k"><small>📈 صافي الربح</small><b class="<?= $f['profit'] >= 0 ? 'pos' : 'neg'; ?>"><?= dse_money($f['profit']); ?></b><small>هامش <?= $pct($f['margin']); ?> · <?= $f['profit'] >= 0 ? 'بتكسب ✅' : 'خسران الشهر ده ⚠️'; ?></small></div>
    <div class="k"><small>🤝 مبيعات اتقفلت (متعاقد عليها)</small><b><?= dse_money($f['booked']['total']); ?></b><small>جديد <?= dse_money($f['booked']['new']); ?> (<?= (int) $f['booked']['new_count']; ?>) · تجديد <?= dse_money($f['booked']['renew']); ?> (<?= (int) $f['booked']['renew_count']; ?>)</small></div>
    <div class="k"><small>⏳ فلوس برا (أقساط الباقات)</small><b class="neg"><?= dse_money($rec['packages']); ?></b><small><?= count($rec['rows']); ?> باقة<?= $rec['waiting']['count'] ? ' · ' . (int) $rec['waiting']['count'] . ' مستنية أول دفعة' : ''; ?><?= $rec['other_invoices'] > 0.5 ? ' · + ' . dse_money($rec['other_invoices']) . ' فواتير تانية' : ''; ?></small></div>
  </div>

  <!-- العملاء -->
  <div class="grid">
    <div class="k"><small>👥 العملاء النشطين دلوقتي</small><b><?= (int) $f['active']; ?></b><small>باقتهم شغالة</small></div>
    <div class="k"><small>🔄 معدل التجديد (الشهر)</small><b class="pos"><?= $pct($ren['renewal_rate']); ?></b><small>جدد <?= (int) $ren['renewed']; ?> من <?= (int) ($ren['renewed'] + $ren['churned']); ?><?= $ren['pending'] ? ' · ' . (int) $ren['pending'] . ' لسه بيقرروا' : ''; ?></small></div>
    <div class="k"><small>📉 معدل الفقد Churn (الشهر)</small><b class="neg"><?= $pct($ren['churn_rate']); ?></b><small>آخر 6 شهور: <?= $pct($f['churn_6m']); ?> فقد · <?= $pct($f['renewal_6m']); ?> تجديد</small></div>
    <div class="k"><small>💳 متوسط العميل في الشهر (ARPA)</small><b><?= dse_money($f['arpa']); ?></b><small>كاش الباقات ÷ العملاء النشطين</small></div>
    <div class="k"><small>💎 قيمة العميل على المدى (LTV)</small><b><?= $f['ltv'] ? dse_money($f['ltv']) : '—'; ?></b><small>ARPA ÷ معدل الفقد (6 شهور)</small></div>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">📊 آخر 12 شهر</h5>
        <div class="small"><span class="dot" style="background:#16a34a"></span>كاش داخل <span class="dot" style="background:#dc2626;margin-inline-start:10px"></span>مصروفات <span class="dot" style="background:#2563eb;margin-inline-start:10px"></span>مبيعات (جديد + تجديد)</div>
        <div class="bars">
          <?php foreach ($f['trend'] as $t) { ?>
            <div class="m" style="cursor:pointer" onclick="location.href='<?= admin_url('dse_studio/finance_month?month='); ?><?= $t['month']; ?>'" title="<?= html_escape($t['month'] . ' · كاش ' . dse_money($t['cash']) . ' · مصروفات ' . dse_money($t['expenses']) . ' · ربح ' . dse_money($t['profit']) . ' · مبيعات ' . dse_money($t['new'] + $t['renew'])); ?>">
              <div class="g">
                <i style="height:<?= round(130 * $t['cash'] / $mx); ?>px;background:#16a34a"></i>
                <i style="height:<?= round(130 * $t['expenses'] / $mx); ?>px;background:#dc2626"></i>
                <i style="height:<?= round(130 * ($t['new'] + $t['renew']) / $mx); ?>px;background:#2563eb"></i>
              </div>
              <div class="lbl"><?= substr($t['month'], 5); ?>/<?= substr($t['month'], 2, 2); ?></div>
            </div>
          <?php } ?>
        </div>
        <div style="overflow-x:auto"><table class="table table-condensed small mtop10">
          <thead><tr><th>الشهر</th><th>كاش</th><th>مصروفات</th><th>ربح</th><th>مبيعات جديدة</th><th>تجديدات</th><th>معدل التجديد</th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($f['trend']) as $t) { ?>
            <tr><td><a href="<?= admin_url('dse_studio/finance_month?month=' . $t['month']); ?>" title="تفاصيل الشهر"><?= $t['month']; ?> 🔍</a></td><td><?= dse_money($t['cash']); ?></td><td><?= dse_money($t['expenses']); ?></td>
              <td class="<?= $t['profit'] >= 0 ? 'pos' : 'neg'; ?>"><?= dse_money($t['profit']); ?></td><td><?= dse_money($t['new']); ?></td><td><?= dse_money($t['renew']); ?></td><td><?= $pct($t['renewal_rate']); ?></td></tr>
          <?php } ?>
          <tr class="bold"><td>آخر 12 شهر</td><td><?= dse_money($f['ytd_cash']); ?></td><td><?= dse_money($f['ytd_expenses']); ?></td><td class="<?= $f['ytd_cash'] - $f['ytd_expenses'] >= 0 ? 'pos' : 'neg'; ?>"><?= dse_money($f['ytd_cash'] - $f['ytd_expenses']); ?></td><td colspan="3"></td></tr>
          </tbody>
        </table></div>
      </div></div>

      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">⏳ الفلوس اللي برا (أقساط الباقات)</h5>
        <div class="grid" style="grid-template-columns:repeat(4,1fr)">
          <?php foreach ($rec['aging'] as $k => $v) { ?><div class="k" style="padding:8px"><small><?= $k; ?> يوم</small><b style="font-size:16px" class="<?= $k === '90+' && $v > 0 ? 'neg' : ''; ?>"><?= dse_money($v); ?></b></div><?php } ?>
        </div>
        <?php if (!$rec['rows']) { ?><p class="text-success">مفيش أقساط متأخرة 👌</p><?php } else { ?>
        <div style="overflow-x:auto"><table class="table table-condensed small">
          <thead><tr><th>العميل</th><th>الباقة</th><th>السعر</th><th>الباقي</th><th>من</th></tr></thead>
          <tbody><?php foreach (array_slice($rec['rows'], 0, 25) as $r) { ?>
            <tr><td><a href="<?= admin_url('dse_studio/client/' . $r['clientid']); ?>"><?= html_escape($r['company']); ?></a></td><td><?= html_escape($r['package']); ?> <span class="text-muted">(<?= dse_package_statuses()[$r['status']] ?? $r['status']; ?>)</span></td>
              <td><?= dse_money($r['price']); ?></td><td class="neg bold"><?= dse_money($r['left']); ?></td><td><?= (int) $r['age']; ?> يوم</td></tr>
          <?php } ?></tbody>
        </table></div>
        <?php } ?>
        <?php if ($rec['waiting']['count']) { ?><p class="small text-muted">+ <?= (int) $rec['waiting']['count']; ?> باقة مستنية أول دفعة بقيمة <?= dse_money($rec['waiting']['amount']); ?> (مش محسوبة في الفلوس برا لحد ما يدفع أول قسط)</p><?php } ?>
        <?php if ($rec['other_invoices'] > 0.5) { ?><p class="small text-muted">+ <?= dse_money($rec['other_invoices']); ?> فواتير تانية في Perfex مش متربطة بباقات ومش متدفعة — <a href="<?= admin_url('invoices'); ?>">الفواتير</a></p><?php } ?>
      </div></div>
    </div>

    <div class="col-md-4">
      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">🧩 المبيعات الشهر ده على الخدمات</h5>
        <div class="stack"><?php foreach ($mix as $k => $v) { if ($v > 0) { ?><div style="width:<?= 100 * $v / $mixt; ?>%;background:<?= $mixl[$k][1]; ?>"></div><?php } } ?></div>
        <?php foreach ($mix as $k => $v) { ?><div class="small"><span class="dot" style="background:<?= $mixl[$k][1]; ?>"></span><?= $mixl[$k][0]; ?>: <b><?= dse_money($v); ?></b> <span class="text-muted"><?= array_sum($mix) > 0 ? round(100 * $v / $mixt) . '%' : ''; ?></span></div><?php } ?>
        <?php if ($mix['ads_budget'] > 0) { ?><p class="small text-muted mtop10">ميزانيات الإعلانات بتروح للمنصات (مش إيراد ليك). لو بتدفعها من حساب الشركة، سجّلها كمصروف في Perfex علشان صافي الربح يطلع صح.</p><?php } ?>
      </div></div>

      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">🧾 المصروفات بالتصنيف</h5>
        <?php if (!$f['expenses']['by']) { ?><p class="text-muted small">مفيش مصروفات متسجلة الشهر ده — سجّلها من Perfex ← Expenses</p><?php } ?>
        <?php foreach ($f['expenses']['by'] as $cat => $v) { ?>
          <div class="small"><?= html_escape($cat); ?>: <b><?= dse_money($v); ?></b></div>
          <div class="progress" style="height:5px;margin:2px 0 6px"><div class="progress-bar progress-bar-danger" style="width:<?= round(100 * $v / max(1, $f['expenses']['total'])); ?>%"></div></div>
        <?php } ?>
      </div></div>

      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">🏆 أكتر العملاء دفعوا (12 شهر)</h5>
        <?php foreach ($f['top'] as $i => $t) { ?><div class="small"><?= $i + 1; ?>) <a href="<?= admin_url('dse_studio/client/' . $t['clientid']); ?>"><?= html_escape($t['company']); ?></a> — <b><?= dse_money($t['paid']); ?></b></div><?php } ?>
        <?php if (!$f['top']) { ?><p class="text-muted small">لسه مفيش مدفوعات</p><?php } ?>
      </div></div>

      <div class="panel_s"><div class="panel-body small">
        <h5 class="bold" style="margin-top:0">📘 إزاي الأرقام بتتحسب</h5>
        <ul style="padding-inline-start:16px;margin:0">
          <li><b>الكاش:</b> كل الدفعات المتسجلة في Perfex في الشهر (اللي في إيدك فعلًا).</li>
          <li><b>المبيعات المتعاقد عليها:</b> سعر الباقات اللي بدأت في الشهر — جديد = أول باقة للعميل، تجديد = عنده باقة قبلها.</li>
          <li><b>التجديد / الفقد:</b> الباقات اللي خلصت في الشهر: جدد = عمل باقة خلال 30 يوم من نهايتها · فقدناه = عدّى 30 يوم · لسه بيقرروا = أقل من 30 يوم.</li>
          <li><b>الفلوس برا:</b> سعر كل باقة (مش ملغية) ناقص المدفوع عليها، بعمرها من بداية الباقة. الباقة اللي لسه مادفعش فيها ولا جنيه بتظهر لوحدها "مستنية أول دفعة".</li>
          <li>الباقات القديمة (قبل السيستم) مش داخلة في المبيعات ولا الفلوس برا.</li>
        </ul>
      </div></div>
    </div>
  </div>
</div></div>
<?php init_tail(); ?>
</body></html>
