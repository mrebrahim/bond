<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="dv" style="max-width:1100px">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>
  <h4 class="bold" style="margin-block-end:2px">📘 <?= _l('dv_menu_manual'); ?></h4>
  <p class="dv-muted" style="font-size:13px"><?= _l('dv_manual_sub'); ?></p>

  <?php if (!$steps && !$daily['total']) { ?>
    <div class="dv-card text-warning"><?= _l('dv_path_no_seed'); ?></div>
  <?php } ?>

  <?php if ($daily['total']) { ?>
  <div class="dv-card dv-daily">
    <div class="dv-row">
      <h5 class="bold" style="margin:0">🎒 <?= _l('dv_manual_today'); ?> <span class="dv-muted"><?= dv_date(date('Y-m-d')); ?></span></h5>
      <div style="flex:0 1 220px;min-width:140px">
        <div class="dv-row dv-muted"><span><?= _l('dv_path_done'); ?></span><span class="dv-path-txt"><?= (int) $daily['done']; ?> / <?= (int) $daily['total']; ?></span></div>
        <div class="dv-bar" style="margin:2px 0 0"><div class="dv-path-bar" style="width:<?= round(100 * $daily['done'] / max(1, $daily['total'])); ?>%;background:#4b4fa6"></div></div>
      </div>
    </div>
    <div class="dv-one" style="margin-block-start:8px"><?php $this->load->view('doctor_visits/_path_lanes', ['path' => $daily, 'mode' => 'daily']); ?></div>
  </div>
  <?php } ?>

  <?php if ($steps) { ?>
  <div class="dv-card">
    <h5 class="bold">🧭 <?= _l('dv_path_title'); ?></h5>
    <div class="dv-muted" style="margin-block-end:8px"><?= _l('dv_manual_per_doctor'); ?></div>
    <?php $this->load->view('doctor_visits/_path_lanes', ['path' => ['steps' => $steps, 'open' => null], 'mode' => 'ref']); ?>
  </div>
  <?php } ?>
</div></div></div>
<?php init_tail(); ?>
<?php $this->load->view('doctor_visits/_path_js'); ?>
</body></html>
