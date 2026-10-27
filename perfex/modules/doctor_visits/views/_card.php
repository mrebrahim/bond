<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php /* كارت الدكتور: $d + $back */ $cool = $d['cooling'] ?? ''; ?>
<div class="dv-card dv-doc <?= $cool ? 'cool-' . $cool : ''; ?>" style="--c:<?= $d['color']; ?>">
  <div class="dv-row">
    <div style="min-width:0">
      <a href="<?= admin_url('doctor_visits/doctor/' . $d['lead_id']); ?>"><b><?= html_escape($d['name']); ?></b></a>
      <span class="dv-tag"><?= html_escape($d['specialty']); ?></span><?php if ($d['grade']) { ?><span class="dv-tag"><?= $d['grade']; ?></span><?php } ?><?php if (!empty($d['priority_rank'])) { ?><span class="dv-tag" style="<?= (int) $d['priority_rank'] === 1 ? 'background:#fef3c7;color:#92400e' : ''; ?>">P<?= (int) $d['priority_rank']; ?></span><?php } ?>
      <div class="dv-muted">
        🏢 <?= html_escape($d['building']); ?><?= $d['floor'] ? ' · ' . _l('dv_floor') . ' ' . html_escape($d['floor']) : ''; ?>
        <?= $d['gatekeeper_name'] ? ' · 👩‍⚕️ ' . html_escape($d['gatekeeper_name']) : ''; ?>
        <?= $d['best_time'] ? ' · ⏰ ' . html_escape($d['best_time']) : ''; ?>
      </div>
      <?php if ((int) $d['stage'] < 9 && !$d['lost']) { ?>
        <div class="dv-muted"><?= ($d['clinic_days'] ?? '') !== '' || ($d['clinic_hours'] ?? '') !== '' ? '📅 ' . html_escape(trim($d['clinic_days'] . ' · ' . $d['clinic_hours'], ' ·')) : '📅 ' . _l('dv_ask_nurse_hours'); ?></div>
        <?php if (($d['notes'] ?? '') !== '') { ?><div class="dv-muted">💡 <?= html_escape(mb_substr($d['notes'], 0, 120)); ?></div><?php } ?>
      <?php } ?>
    </div>
    <span class="dv-chip" style="background:<?= $d['color']; ?>"><?= $d['lost'] ? _l('dv_outcome_lost') : dv_stage_label($d['stage']); ?></span>
  </div>
  <?php if (!$d['lost']) { ?>
    <div class="dv-bar"><div style="width:<?= (int) $d['progress']; ?>%;background:<?= $d['color']; ?>"></div></div>
    <div class="dv-muted">
      <?= (int) $d['progress']; ?>% ·
      <?php if ($d['stage'] < 9) { echo sprintf(_l('dv_steps_left'), count($d['steps_left']), implode(' ← ', array_slice($d['steps_left'], 0, 3)) . (count($d['steps_left']) > 3 ? '…' : ''), (int) $d['touches_left']); } ?>
    </div>
    <div style="margin-block-start:4px">➡️ <?= html_escape($d['next_step']); ?><?= $d['next_step_xp'] ? ' <b style="color:#16a34a">+' . (int) $d['next_step_xp'] . ' XP</b>' : ''; ?></div>
    <?php if ($d['next_action']) { ?><div class="dv-muted <?= $d['overdue'] ? 'text-danger' : ''; ?>">📌 <?= html_escape($d['next_action']); ?> — <?= dv_date($d['next_action_at'], true); ?></div><?php } ?>
    <?php if ($cool) { ?><div class="bold" style="color:<?= $cool === 'red' ? '#dc2626' : '#c2410c'; ?>"><?= $cool === 'red' ? sprintf(_l('dv_cooling_red'), (int) $d['days_since_touch']) : _l('dv_cooling_orange'); ?></div><?php } ?>
    <?php if (!empty($d['why'])) { ?><div><?php foreach ($d['why'] as $w) { ?><span class="dv-tag"><?= _l('dv_why_' . $w); ?></span><?php } ?></div><?php } ?>
  <?php } ?>
  <div class="dv-row" style="margin-block-start:8px;justify-content:flex-start">
    <?php if ($dv_can['create'] && !$d['lost'] && $d['stage'] < 9) { ?>
      <a class="dv-btn dv-btn-primary dv-btn-sm" href="<?= admin_url('doctor_visits/log/' . $d['lead_id'] . '?back=' . urlencode($back ?? 'doctor_visits/queue')); ?>">✍️ <?= _l('dv_log_visit'); ?></a>
      <a class="dv-btn dv-btn-wa dv-btn-sm" href="<?= admin_url('doctor_visits/wa/' . $d['lead_id'] . '?back=' . urlencode($back ?? 'doctor_visits/queue')); ?>">💬 <?= _l('dv_wa_button'); ?></a>
    <?php } ?>
    <?php if ($d['last_touch']) { ?><span class="dv-muted"><?= sprintf(_l('dv_last_touch'), (int) $d['days_since_touch']); ?></span><?php } ?>
  </div>
</div>
