<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php /* جوه صفحة الـ Lead في Perfex: الملخص + آخر الزيارات + لينك للصفحة الكاملة */ ?>
<div style="padding:10px 0">
  <div><b><?= dv_stage_label($doc['stage']); ?></b> · <?= (int) $doc['progress']; ?>% · <?= html_escape($doc['specialty']); ?> · 🏢 <?= html_escape($doc['building']); ?></div>
  <div style="height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;margin:6px 0"><div style="height:100%;width:<?= (int) $doc['progress']; ?>%;background:<?= $doc['color']; ?>"></div></div>
  <div class="text-muted">➡️ <?= html_escape($doc['next_step']); ?> · <?= sprintf(_l('dv_steps_left'), count($doc['steps_left']), implode(' ← ', array_slice($doc['steps_left'], 0, 3)), (int) $doc['touches_left']); ?></div>
  <?php if ($doc['next_action']) { ?><div class="text-muted">📌 <?= html_escape($doc['next_action']); ?> — <?= dv_date($doc['next_action_at'], true); ?></div><?php } ?>
  <hr>
  <?php foreach (array_slice($timeline, 0, 8) as $v) { ?>
    <div style="border-bottom:1px solid #f1f5f9;padding:4px 0"><b><?= dv_outcome_label($v['outcome']); ?></b> <span class="text-muted"><?= dv_date($v['visited_at'], true); ?> · <?= html_escape($v['staff_name']); ?></span>
      <?php if ($v['note']) { ?><div><?= html_escape($v['note']); ?></div><?php } ?></div>
  <?php } ?>
  <p style="margin-top:10px"><a class="btn btn-primary" href="<?= admin_url('doctor_visits/doctor/' . $doc['lead_id']); ?>"><?= _l('doctor_visits'); ?> — <?= _l('dv_profile'); ?> / QR / <?= _l('dv_wa_button'); ?></a></p>
</div>
