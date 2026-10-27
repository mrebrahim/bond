<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="dv">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>
  <?php foreach ($buildings as $b) { ?>
    <a class="dv-card dv-row" style="display:flex;color:inherit;text-decoration:none" href="<?= admin_url('doctor_visits/building/' . $b['id']); ?>">
      <div><b>🏢 <?= html_escape($b['name']); ?></b> <?php if ($b['cluster']) { ?><span class="dv-tag"><?= html_escape($b['cluster']); ?></span><?php } ?>
        <div class="dv-muted"><?= (int) $b['doctors']; ?> <?= _l('dv_doctors'); ?> · <?= _l('dv_coverage'); ?> <?= (int) $b['coverage']; ?>%</div>
        <div class="dv-bar" style="width:200px"><div style="width:<?= (int) $b['coverage']; ?>%;background:<?= $b['coverage'] >= 100 ? '#16a34a' : '#3b82f6'; ?>"></div></div>
      </div>
      <span>‹</span>
    </a>
  <?php } ?>
  <?php if ($dv_can['create']) { ?>
    <details class="dv-card" <?= $buildings ? '' : 'open'; ?>><summary class="bold">➕ <?= _l('dv_add_building'); ?></summary>
      <?= form_open(admin_url('doctor_visits/buildings')); ?>
        <input name="name" class="dv-in" placeholder="<?= _l('dv_building_name'); ?> *" required style="margin-block:6px">
        <input name="cluster" class="dv-in" placeholder="<?= _l('dv_cluster'); ?>" style="margin-block-end:6px">
        <input name="address" class="dv-in" placeholder="<?= _l('dv_address'); ?>" style="margin-block-end:6px">
        <input name="maps_url" class="dv-in" placeholder="<?= _l('dv_maps_url'); ?>" dir="ltr" style="margin-block-end:6px">
        <input type="number" name="clinics_estimate" class="dv-in" placeholder="<?= _l('dv_clinics_estimate'); ?>" style="margin-block-end:6px">
        <textarea name="notes" class="dv-in" rows="2" placeholder="<?= _l('dv_notes'); ?>"></textarea>
        <button class="dv-btn dv-btn-primary" style="width:100%;margin-block-start:6px"><?= _l('dv_save'); ?></button>
      <?= form_close(); ?>
    </details>
  <?php } ?>
  <?php $this->load->view('doctor_visits/_feedback'); ?>
</div></div></div>
<?php init_tail(); ?>
</body></html>
