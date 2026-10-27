<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php /* كارت "مسار ما بعد التحليل" في بروفايل الدكتور */ ?>
<div class="dv-card dv-path" id="dv-path">
  <?php if (!$path['total']) { ?>
    <h5 class="bold">🧭 <?= _l('dv_path_title'); ?></h5><div class="text-warning"><?= _l('dv_path_no_seed'); ?></div>
  <?php } else { ?>
    <div class="dv-row">
      <h5 class="bold" style="margin:0">🧭 <?= _l('dv_path_title'); ?></h5>
      <div style="flex:0 1 260px;min-width:160px">
        <div class="dv-row dv-muted"><span><?= _l('dv_path_done'); ?></span><span class="dv-path-txt"><?= (int) $path['done']; ?> / <?= (int) $path['total']; ?></span></div>
        <div class="dv-bar" style="margin:2px 0 0"><div class="dv-path-bar" style="width:<?= round(100 * $path['done'] / max(1, $path['total'])); ?>%;background:#0f7a62"></div></div>
      </div>
    </div>
    <div class="dv-path-next" style="margin-block:6px 10px"><?= $path['next'] ? '➡️ ' . _l('dv_path_next') . ': <b>' . html_escape($path['next']['text']) . '</b>' : '🎉 ' . _l('dv_path_all_done'); ?></div>
    <?php $this->load->view('doctor_visits/_path_lanes', ['path' => $path, 'mode' => 'lead', 'lead_id' => $lead_id]); ?>
  <?php } ?>
</div>
