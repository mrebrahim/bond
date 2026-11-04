<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$labels = ['' => ['لسه', 'default'], 'contacted' => ['اتكلمنا', 'info'], 'later' => ['بعدين', 'warning'], 'lost' => ['مش هيجدد', 'danger']];
$tabs   = ['open' => 'المفتوحين', 'contacted' => 'اتكلمنا', 'later' => 'بعدين', 'lost' => 'مش هيجدد', '' => 'الكل', 'churn' => '📉 ليه بيمشوا'];
?>
<div id="wrapper"><div class="content">
  <div class="panel_s"><div class="panel-body">
    <h4 class="tw-font-semibold" style="margin-top:0">🔄 مستنيين تجديد <span class="text-muted"><?= $status === 'churn' ? '' : '(' . count($rows) . ')'; ?></span></h4>
    <p class="text-muted" style="margin:0">العملاء اللي آخر باقة ليهم خلصت أو هتخلص خلال <?= (int) (get_option('dse_renewal_days_before') ?: 7); ?> أيام ومجددوش. ابعتله "طلب تجديد الاشتراك" على الجروب، وسجّل حصل إيه. لما يجدد بيختفي من هنا لوحده.
      العملاء القدام: افتح صفحة العميل ← "➕ باقة جديدة" ← علّم <b>باقة قديمة (خلصت قبل السيستم)</b>.</p>
    <div style="margin-top:10px">
      <?php foreach ($tabs as $k => $t) { ?><a class="btn btn-<?= $status === $k ? 'primary' : 'default'; ?> btn-sm" href="<?= admin_url('dse_studio/renewals?status=' . $k); ?>"><?= $t; ?></a> <?php } ?>
    </div>
  </div></div>

  <?php if ($status === 'churn') { $ch = $churn; ?>
  <div class="panel_s"><div class="panel-body">
    <h5 class="bold" style="margin-top:0">📉 ليه العملاء بيمشوا <span class="small text-muted">(آخر 12 شهر · <?= (int) $ch['total']; ?> حالة · خسّرنا <?= dse_money($ch['value']); ?>)</span></h5>
    <p class="small text-muted">بيتسجل لوحده لما حد يلغي باقة أو يتعلّم "مش هيجدد" (السبب إجباري). كل شهر بص على أكتر سبب واشتغل على "هنعمل إيه".</p>
    <?php if (!$ch['by']) { ?><p class="text-success">مفيش عملاء مشيوا آخر 12 شهر 👌</p><?php } ?>
    <?php foreach ($ch['by'] as $i => $x) { ?>
      <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:8px">
        <span><?= $i === 0 ? '🔺 ' : ''; ?><b><?= html_escape($x['label']); ?></b> <span class="text-muted small"><?= (int) $x['count']; ?> مرة · <?= (int) $x['clients']; ?> عميل</span></span>
        <span><b><?= (int) $x['share']; ?>%</b> <span class="small text-muted">· <?= dse_money($x['value']); ?></span></span>
      </div>
      <div class="progress" style="height:8px;margin:3px 0 4px"><div class="progress-bar progress-bar-danger" style="width:<?= (int) $x['share']; ?>%"></div></div>
      <?php if ($x['fix']) { ?><div class="small" style="color:#0f766e">✅ هنعمل إيه: <?= html_escape($x['fix']); ?></div><?php } ?>
    <?php } ?>
  </div></div>
  <?php if ($ch['recent']) { ?>
  <div class="panel_s"><div class="panel-body" style="overflow-x:auto">
    <h5 class="bold" style="margin-top:0">آخر الحالات</h5>
    <table class="table table-condensed small">
      <thead><tr><th>العميل</th><th>إمتى</th><th>إيه اللي حصل</th><th>السبب</th><th>قال إيه</th><th>الخسارة</th><th>مين سجّل</th></tr></thead>
      <tbody><?php foreach ($ch['recent'] as $r) { ?>
        <tr><td><a href="<?= admin_url('dse_studio/client/' . $r['clientid']); ?>"><?= html_escape($r['company']); ?></a></td><td><?= dse_day($r['created_at']); ?></td>
          <td><?= $r['kind'] === 'cancel' ? 'لغى الباقة' : 'مش هيجدد'; ?></td><td><?= html_escape($r['label']); ?></td><td><?= html_escape((string) $r['note']); ?></td>
          <td><?= dse_money($r['lost_value']); ?></td><td class="text-muted"><?= html_escape($r['staff']); ?></td></tr>
      <?php } ?></tbody>
    </table>
  </div></div>
  <?php } ?>
  <?php } else { ?>
  <div class="panel_s"><div class="panel-body" style="overflow-x:auto">
    <?php if (!$rows) { ?><p class="text-muted">مفيش عملاء هنا 👌</p><?php } else { ?>
    <table class="table table-condensed">
      <thead><tr><th>العميل</th><th>آخر باقة</th><th>خلصت</th><th>المتابعة</th><th>طلب التجديد</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r) { $p = $r['package']; $lb = $labels[$r['renewal_status']] ?? $labels['']; ?>
        <tr>
          <td><a href="<?= admin_url('dse_studio/client/' . $r['clientid']); ?>"><b><?= html_escape($r['company']); ?></b></a>
            <div class="small text-muted"><?= html_escape($r['code']); ?><?= $r['phone'] ? ' · <span dir="ltr">' . html_escape($r['phone']) . '</span>' : ''; ?></div></td>
          <td><?= html_escape($p['name']); ?> · <?= dse_money($p['price']); ?><?= $r['historic'] ? ' <span class="label label-default">قديمة</span>' : ''; ?>
            <div class="small text-muted"><?= dse_day($p['start_date']); ?> ← <?= dse_day($p['end_date']); ?></div></td>
          <td><?php if ($r['ended']) { ?><span class="label label-<?= $r['days'] > 30 ? 'default' : 'danger'; ?>">من <?= (int) $r['days']; ?> يوم</span><?php } else { ?><span class="label label-warning">هتخلص بعد <?= -(int) $r['days']; ?> يوم</span><?php } ?>
            <?= $p['status'] === 'active' && $r['ended'] ? '<div class="small text-muted">في فترة السماح</div>' : ''; ?></td>
          <td style="min-width:220px">
            <?= form_open(admin_url('dse_studio/renewal_status/' . $r['clientid'] . '?back=' . urlencode($status)), ['class' => 'form-inline']); ?>
              <select name="status" class="form-control input-sm" onchange="this.form.querySelector('.dse-why').style.display=this.value==='lost'?'':'none'"><?php foreach ($labels as $k => $l) { ?><option value="<?= $k; ?>" <?= $r['renewal_status'] === $k ? 'selected' : ''; ?>><?= $l[0]; ?></option><?php } ?></select>
              <span class="dse-why" style="<?= $r['renewal_status'] === 'lost' ? '' : 'display:none'; ?>"><?= str_replace(' required', '', dse_churn_select('reason', false)); ?></span>
              <input name="note" value="<?= html_escape((string) $r['renewal_note']); ?>" class="form-control input-sm" placeholder="حصل إيه…" style="width:130px">
              <button class="btn btn-default btn-xs">احفظ</button>
            <?= form_close(); ?>
            <?php if ($r['renewal_updated_at']) { ?><div class="small text-muted"><span class="label label-<?= $lb[1]; ?>"><?= $lb[0]; ?></span> <?= dse_day($r['renewal_updated_at']); ?></div><?php } ?>
          </td>
          <td>
            <?php if ($r['last_msg']) { ?><div class="small text-muted">آخر طلب: <?= dse_day($r['last_msg']['created_at']); ?> (<?= html_escape($r['last_msg']['status']); ?>)</div><?php } ?>
            <?php if ($r['group']) { ?>
              <?= form_open(admin_url('dse_studio/renewal_message/' . $r['clientid']), ['class' => 'inline-block', 'onsubmit' => "return confirm('تبعت طلب التجديد لجروب " . html_escape(addslashes($r['company'])) . "؟')"]); ?><button class="btn btn-success btn-xs">💬 ابعت طلب التجديد</button><?= form_close(); ?>
            <?php } else { ?><span class="small text-warning">مالوش جروب — اعمله من صفحة العميل</span><?php } ?>
          </td>
          <td><a class="btn btn-primary btn-xs" href="<?= admin_url('dse_studio/client/' . $r['clientid']); ?>">🔄 جدّد</a></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    <?php } ?>
  </div></div>
  <?php } ?>
</div></div>
<?php init_tail(); ?>
</body></html>
