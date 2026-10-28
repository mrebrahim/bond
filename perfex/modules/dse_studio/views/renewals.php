<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$labels = ['' => ['لسه', 'default'], 'contacted' => ['اتكلمنا', 'info'], 'later' => ['بعدين', 'warning'], 'lost' => ['مش هيجدد', 'danger']];
$tabs   = ['open' => 'المفتوحين', 'contacted' => 'اتكلمنا', 'later' => 'بعدين', 'lost' => 'مش هيجدد', '' => 'الكل'];
?>
<div id="wrapper"><div class="content">
  <div class="panel_s"><div class="panel-body">
    <h4 class="tw-font-semibold" style="margin-top:0">🔄 مستنيين تجديد <span class="text-muted">(<?= count($rows); ?>)</span></h4>
    <p class="text-muted" style="margin:0">العملاء اللي آخر باقة ليهم خلصت أو هتخلص خلال <?= (int) (get_option('dse_renewal_days_before') ?: 7); ?> أيام ومجددوش. ابعتله "طلب تجديد الاشتراك" على الجروب، وسجّل حصل إيه. لما يجدد بيختفي من هنا لوحده.
      العملاء القدام: افتح صفحة العميل ← "➕ باقة جديدة" ← علّم <b>باقة قديمة (خلصت قبل السيستم)</b>.</p>
    <div style="margin-top:10px">
      <?php foreach ($tabs as $k => $t) { ?><a class="btn btn-<?= $status === $k ? 'primary' : 'default'; ?> btn-sm" href="<?= admin_url('dse_studio/renewals?status=' . $k); ?>"><?= $t; ?></a> <?php } ?>
    </div>
  </div></div>

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
              <select name="status" class="form-control input-sm"><?php foreach ($labels as $k => $l) { ?><option value="<?= $k; ?>" <?= $r['renewal_status'] === $k ? 'selected' : ''; ?>><?= $l[0]; ?></option><?php } ?></select>
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
</div></div>
<?php init_tail(); ?>
</body></html>
