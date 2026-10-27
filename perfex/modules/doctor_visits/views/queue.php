<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="dv">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>

  <div class="dv-card dv-row">
    <div class="dv-ring" style="--p:<?= $target ? min(100, round(100 * $done / $target)) : 100; ?>"><div><?= (int) $done; ?>/<?= (int) $target; ?><small><?= _l('dv_today_goal'); ?></small></div></div>
    <div style="flex:1;min-width:160px">
      <b><?= _l('dv_level'); ?> <?= (int) $level['level']; ?> · <?= html_escape($level['name']); ?></b>
      <div class="dv-bar"><div style="width:<?= (int) $level['pct']; ?>%;background:#8b5cf6"></div></div>
      <?php if ($level['next_at'] !== null) { ?><div class="dv-muted"><?= sprintf(_l('dv_to_next_level'), (int) $level['to_next'], (int) $level['level'] + 1, html_escape($level['next_reward'] ?: $level['next_name'])); ?></div><?php } ?>
    </div>
  </div>

  <?= form_open(admin_url('doctor_visits/queue'), ['class' => 'dv-card']); ?>
    <label class="dv-muted">📍 <?= _l('dv_im_in_building'); ?></label>
    <select name="building_id" class="dv-in" onchange="this.form.submit()">
      <option value="0"><?= _l('dv_all_buildings'); ?></option>
      <?php foreach ($buildings as $b) { ?><option value="<?= (int) $b['id']; ?>" <?= (int) $here === (int) $b['id'] ? 'selected' : ''; ?>><?= html_escape($b['name']); ?> (<?= (int) $b['coverage']; ?>%)</option><?php } ?>
    </select>
  <?= form_close(); ?>

  <?php if ($zone['hot']) { ?>
    <div class="dv-card" style="background:#faf5ff;border-color:#e9d5ff">
      <b>🔥 <?= _l('dv_closing_zone'); ?></b> — <?= sprintf(_l('dv_closing_zone_sub'), count($zone['hot']), dv_money($zone['hot_value'])); ?>
      <?php if ($zone['warm']) { ?><div class="dv-muted"><?= sprintf(_l('dv_warm_zone'), count($zone['warm'])); ?></div><?php } ?>
    </div>
  <?php } ?>

  <?php if (!$doctors) { ?><div class="dv-card dv-muted"><?= _l('dv_empty_queue'); ?></div><?php } ?>
  <?php foreach (array_slice($doctors, 0, 40) as $d) { $this->load->view('doctor_visits/_card', ['d' => $d, 'back' => 'doctor_visits/queue']); } ?>
  <?php $this->load->view('doctor_visits/_feedback'); ?>
</div></div></div>
<?php init_tail(); ?>
</body></html>
