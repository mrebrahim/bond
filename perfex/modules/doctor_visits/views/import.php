<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$labels = [
    'building' => 'العمارة', 'name' => 'الاسم', 'specialty' => 'التخصص', 'floor' => 'الدور', 'social_summary' => 'السوشيال', 'facebook_url' => 'فيسبوك',
    'followers' => 'المتابعين', 'social_note' => 'ملاحظة السوشيال', 'instagram_url' => 'إنستجرام', 'gmaps_url' => 'الخريطة', 'clinic_days' => 'أيام العيادة',
    'clinic_hours' => 'الساعات', 'other_branches' => 'الفروع التانية', 'phone' => 'الموبايل', 'phones' => 'كل الأرقام', 'clinic_whatsapp' => 'واتساب العيادة',
    'verification' => 'حالة التحقق', 'priority_rank' => 'الأولوية', 'notes' => 'ملاحظات', 'gatekeeper_name' => 'الممرضة', 'best_time' => 'أنسب وقت',
    'visited' => 'زرناه؟', 'outcome' => 'النتيجة', 'next_action' => 'الخطوة الجاية', 'next_action_at' => 'تاريخ المتابعة', 'row_no' => '#',
];
$badge = ['new' => ['#16a34a', 'dv_import_new'], 'update' => ['#2563eb', 'dv_import_update'], 'same' => ['#94a3b8', 'dv_import_same'], 'skip' => ['#dc2626', 'dv_import_skip']];
?>
<div id="wrapper"><div class="content"><div class="dv" style="max-width:1200px">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>
  <h4 class="bold">📥 <?= _l('dv_menu_import'); ?></h4>

  <?php if (!$plan) { ?>
  <div class="dv-card">
    <?= form_open_multipart(admin_url('doctor_visits/import')); ?>
      <input type="file" name="file" accept=".xlsx,.csv" class="dv-in" required style="padding:10px">
      <button class="dv-btn dv-btn-primary" style="width:100%;margin-block-start:8px">⬆️ <?= _l('dv_import_upload'); ?></button>
    <?= form_close(); ?>
    <div class="dv-muted" style="margin-block-start:8px;line-height:1.8"><?= _l('dv_import_help'); ?></div>
    <div class="dv-muted" style="margin-block-start:4px">
      <?php foreach (['البرج', 'الدكتور / المكان', 'التخصص', 'الدور', 'السوشيال / الموقع', 'رابط فيسبوك', 'متابعين', 'ملاحظة فيسبوك', 'أيام العيادة', 'الساعات', 'العيادات / الفروع التانية', 'التليفون', 'حالة التحقق', 'الأولوية (1=أعلى)', 'ملاحظات', 'اسم الممرضة', 'أنسب وقت', 'زرنا؟', 'النتيجة', 'الخطوة الجاية', 'تاريخ المتابعة'] as $c) { ?><span class="dv-tag"><?= $c; ?></span><?php } ?>
    </div>
    <a class="dv-btn dv-btn-sm" style="margin-block-start:10px" href="<?= admin_url('doctor_visits/export_doctors'); ?>">⬇️ <?= _l('dv_import_export'); ?></a>
  </div>
  <?php } ?>

  <?php if ($file && $file['done']) { $r = $file['result']; ?>
    <div class="dv-card" style="background:#f0fdf4;border-color:#86efac">
      <h5 class="bold">✅ <?= html_escape($file['file']); ?></h5>
      <div><?= sprintf(_l('dv_import_done'), (int) $r['new'], (int) $r['update'], (int) $r['visits'], (int) $r['buildings'], (int) $r['skip'] + (int) $r['same']); ?></div>
      <?php if ($r['errors']) { ?><ul class="text-danger" style="margin-block:8px 0"><?php foreach ($r['errors'] as $e) { ?><li><?= html_escape($e); ?></li><?php } ?></ul><?php } ?>
      <a class="dv-btn dv-btn-sm" style="margin-block-start:8px" href="<?= admin_url('doctor_visits/buildings'); ?>">🏢 <?= _l('dv_menu_buildings'); ?></a>
    </div>
  <?php } ?>

  <?php if ($plan) { $c = $plan['count']; ?>
    <div class="dv-card">
      <div class="dv-row">
        <h5 class="bold" style="margin:0">📄 <?= html_escape($file['file']); ?> <span class="dv-muted">· <?= html_escape(implode('، ', $file['sheets'])); ?> · <?= count($plan['rows']); ?> <?= _l('dv_doctors'); ?></span></h5>
        <a class="dv-muted" href="<?= admin_url('doctor_visits/import'); ?>"><?= _l('dv_import_other'); ?></a>
      </div>
      <div class="dv-kpis" style="margin-block:10px">
        <?php foreach (['new', 'update', 'same', 'skip'] as $k) { ?>
          <div class="dv-kpi" style="border-color:<?= $badge[$k][0]; ?>"><b style="color:<?= $badge[$k][0]; ?>"><?= (int) $c[$k]; ?></b><span class="dv-muted"><?= _l($badge[$k][1]); ?></span></div>
        <?php } ?>
      </div>
      <?php if ($c['visits']) { ?><div>✍️ <?= sprintf(_l('dv_import_visits'), (int) $c['visits']); ?></div><?php } ?>
      <?php if ($plan['new_buildings']) { ?><div>🏢 <?= _l('dv_import_new_buildings'); ?>: <?php foreach ($plan['new_buildings'] as $b) { ?><span class="dv-tag"><?= html_escape($b); ?></span><?php } ?></div><?php } ?>
      <div class="dv-muted" style="margin-block-start:6px"><?= _l('dv_import_columns'); ?>:
        <?php foreach ($file['columns'] as $t => $f) { ?><span class="dv-tag" title="<?= html_escape($f); ?>"><?= html_escape($t); ?> ← <?= html_escape($labels[$f] ?? $f); ?></span><?php } ?>
      </div>
      <?php if ($file['ignored']) { ?><div class="text-warning" style="margin-block-start:6px">⚠️ <?= _l('dv_import_ignored'); ?>: <?= html_escape(implode('، ', array_unique($file['ignored']))); ?></div><?php } ?>

      <?php if ($c['new'] + $c['update'] > 0) { ?>
        <?= form_open(admin_url('doctor_visits/import/' . (int) $file['id']), ['onsubmit' => "this.querySelector('button').disabled=true"]); ?>
          <input type="hidden" name="confirm" value="1">
          <?php if ($staff) { ?>
            <label class="dv-muted" style="display:block;margin:8px 0 2px"><?= _l('dv_import_assign'); ?></label>
            <select name="staff_id" class="dv-in"><?php foreach ($staff as $s) { ?><option value="<?= (int) $s['staffid']; ?>" <?= (int) $s['staffid'] === (int) get_staff_user_id() ? 'selected' : ''; ?>><?= html_escape($s['firstname'] . ' ' . $s['lastname']); ?></option><?php } ?></select>
          <?php } ?>
          <button class="dv-btn dv-btn-primary" style="width:100%;margin-block-start:10px">✅ <?= sprintf(_l('dv_import_confirm'), (int) $c['new'], (int) $c['update']); ?></button>
        <?= form_close(); ?>
      <?php } else { ?>
        <p class="text-success bold" style="margin-block-start:10px"><?= _l('dv_import_nothing'); ?></p>
      <?php } ?>
    </div>

    <div class="dv-card dv-scroll">
      <table class="dv-t">
        <thead><tr><th>#</th><th><?= _l('dv_doctor_name'); ?></th><th><?= _l('dv_building'); ?></th><th><?= _l('dv_import_priority'); ?></th><th></th><th><?= _l('dv_import_details'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($plan['rows'] as $p) { $row = $p['row']; ?>
          <tr style="vertical-align:top">
            <td class="dv-muted"><?= (int) $row['line']; ?></td>
            <td><b><?= html_escape($row['name']); ?></b><div class="dv-muted"><?= html_escape($row['specialty']); ?><?= $row['floor'] !== '' ? ' · ' . _l('dv_floor') . ' ' . html_escape($row['floor']) : ''; ?></div></td>
            <td class="dv-muted"><?= html_escape($row['building'] ?: 'غير محدد'); ?></td>
            <td><?= $row['priority_rank'] ? '<span class="dv-tag">' . (int) $row['priority_rank'] . '</span>' : ''; ?></td>
            <td><span class="dv-chip" style="background:<?= $badge[$p['action']][0]; ?>"><?= _l($badge[$p['action']][1]); ?></span></td>
            <td style="font-size:12px">
              <?php if ($p['why']) { ?><div class="text-danger"><?= html_escape($p['why']); ?></div><?php } ?>
              <?php if ($p['action'] === 'new') { ?>
                <div class="dv-muted">
                  <?= $row['phone'] ? '📞 <span dir="ltr">' . html_escape($row['phones']) . '</span> · ' : ''; ?>
                  <?= $row['clinic_days'] !== '' || $row['clinic_hours'] !== '' ? '📅 ' . html_escape(trim($row['clinic_days'] . ' ' . $row['clinic_hours'])) . ' · ' : ''; ?>
                  <?= $row['facebook_url'] ? '<a href="' . html_escape($row['facebook_url']) . '" target="_blank" rel="noopener">fb</a>' . ($row['followers'] ? ' ' . html_escape($row['followers']) : '') . ' · ' : ''; ?>
                  <?= html_escape(mb_substr($row['notes'], 0, 80)); ?>
                </div>
              <?php } ?>
              <?php foreach ($p['changes'] as $f => $ch) { ?>
                <div><b><?= html_escape($labels[$f] ?? $f); ?>:</b> <span class="dv-muted" style="text-decoration:line-through"><?= html_escape(mb_substr($ch[0], 0, 40)) ?: '—'; ?></span> ← <?= html_escape(mb_substr($ch[1], 0, 60)); ?></div>
              <?php } ?>
              <?php if ($p['visit']) { ?><div style="color:#1d4ed8">✍️ <?= _l('dv_import_visit'); ?>: <?= dv_outcome_label($p['visit']); ?><?= $row['next_action'] !== '' ? ' · 📌 ' . html_escape($row['next_action']) : ''; ?></div><?php } ?>
              <?php foreach ($row['warnings'] as $w) { ?><div class="text-warning">⚠️ <?= html_escape($w); ?></div><?php } ?>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  <?php } ?>
  <?php $this->load->view('doctor_visits/_feedback'); ?>
</div></div></div>
<?php init_tail(); ?>
</body></html>
