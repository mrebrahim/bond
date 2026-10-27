<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="dv">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>
  <div class="dv-card">
    <div class="dv-row"><h4 class="bold">🏢 <?= html_escape($b['name']); ?></h4><?php if ($b['maps_url']) { ?><a class="dv-btn dv-btn-sm" target="_blank" href="<?= html_escape($b['maps_url']); ?>">📍 Maps</a><?php } ?></div>
    <div class="dv-muted"><?= html_escape($b['cluster']); ?> <?= html_escape($b['address']); ?></div>
    <div class="dv-bar"><div style="width:<?= (int) $b['coverage']; ?>%;background:#16a34a"></div></div>
    <div class="dv-muted"><?= _l('dv_coverage'); ?> <?= (int) $b['coverage']; ?>% · <?= count($doctors); ?> <?= _l('dv_doctors'); ?></div>
    <?php if ($b['notes']) { ?><div style="margin-block-start:6px">📝 <?= nl2br(html_escape($b['notes'])); ?></div><?php } ?>
  </div>

  <?php if ($dv_can['create']) { ?>
    <details class="dv-card" <?= $doctors ? '' : 'open'; ?>><summary class="bold">➕ <?= _l('dv_add_doctor_here'); ?></summary>
      <?= form_open(admin_url('doctor_visits/quick_add')); ?>
        <input type="hidden" name="building_id" value="<?= (int) $b['id']; ?>">
        <input name="name" class="dv-in" placeholder="<?= _l('dv_doctor_name'); ?> *" required style="margin-block:6px">
        <input name="specialty" class="dv-in" placeholder="<?= _l('dv_specialty'); ?> *" required style="margin-block-end:6px" list="dv-specs">
        <datalist id="dv-specs"><option>أسنان</option><option>جلدية</option><option>نسا وتوليد</option><option>أطفال</option><option>عظام</option><option>عيون</option><option>باطنة</option><option>أنف وأذن</option><option>تجميل</option><option>قلب</option></datalist>
        <div class="dv-row" style="flex-wrap:nowrap">
          <input name="floor" class="dv-in" placeholder="<?= _l('dv_floor'); ?>" style="max-width:110px">
          <input name="phone" class="dv-in" placeholder="<?= _l('dv_phone'); ?>" dir="ltr" inputmode="tel">
        </div>
        <div class="dv-row" style="margin-block-start:6px;flex-wrap:nowrap">
          <button class="dv-btn" style="flex:1"><?= _l('dv_save'); ?></button>
          <button class="dv-btn dv-btn-primary" name="then_log" value="1" style="flex:1"><?= _l('dv_save'); ?> + <?= _l('dv_log_visit'); ?></button>
        </div>
      <?= form_close(); ?>
    </details>
  <?php } ?>

  <?php $floor = '__'; foreach ($doctors as $d) { ?>
    <?php if ($floor !== (string) $d['floor']) { $floor = (string) $d['floor']; ?><h5 class="bold" style="margin-block:12px 6px"><?= $floor !== '' ? _l('dv_floor') . ' ' . html_escape($floor) : _l('dv_no_floor'); ?></h5><?php } ?>
    <?php $this->load->view('doctor_visits/_card', ['d' => $d, 'back' => 'doctor_visits/building/' . $b['id']]); ?>
  <?php } ?>
  <?php $this->load->view('doctor_visits/_feedback'); ?>
</div></div></div>
<?php init_tail(); ?>
</body></html>
