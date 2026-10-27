<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $cur = $this->uri->segment(3) ?: 'queue'; ?>
<div class="dv-nav">
  <?php foreach (['queue' => 'dv_menu_queue', 'manual' => 'dv_menu_manual', 'log' => 'dv_menu_log', 'dashboard' => 'dv_menu_dashboard', 'buildings' => 'dv_menu_buildings', 'reports' => 'dv_menu_reports'] as $k => $l) { ?>
    <?php if ($k === 'log' && !$dv_can['create']) { continue; } ?>
    <a class="<?= $cur === $k ? 'on' : ''; ?>" href="<?= admin_url('doctor_visits/' . $k); ?>"><?= _l($l); ?></a>
  <?php } ?>
  <?php if ($dv_can['create']) { ?><a class="<?= $cur === 'import' ? 'on' : ''; ?>" href="<?= admin_url('doctor_visits/import'); ?>">📥</a><?php } ?>
  <?php if ($dv_can['settings']) { ?><a class="<?= $cur === 'settings' ? 'on' : ''; ?>" href="<?= admin_url('doctor_visits/settings'); ?>">⚙️</a><?php } ?>
</div>
