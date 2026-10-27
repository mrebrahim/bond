<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="dv">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>
  <form method="get" class="dv-card dv-row" style="justify-content:flex-start">
    <label class="dv-muted"><?= _l('dv_from'); ?> <input type="date" name="from" value="<?= html_escape($from); ?>" class="dv-in" style="width:auto"></label>
    <label class="dv-muted"><?= _l('dv_to'); ?> <input type="date" name="to" value="<?= html_escape($to); ?>" class="dv-in" style="width:auto"></label>
    <label class="dv-muted"><?= _l('dv_group_by'); ?>
      <select name="group" class="dv-in" style="width:auto">
        <?php foreach (['building' => 'dv_by_building', 'specialty' => 'dv_by_specialty', 'staff' => 'dv_by_staff'] as $k => $l) { ?><option value="<?= $k; ?>" <?= $group === $k ? 'selected' : ''; ?>><?= _l($l); ?></option><?php } ?>
      </select></label>
    <button class="dv-btn dv-btn-sm dv-btn-primary">↻</button>
    <?php if ($dv_can['all']) { ?><a class="dv-btn dv-btn-sm" href="<?= admin_url('doctor_visits/export_csv?from=' . urlencode($from) . '&to=' . urlencode($to)); ?>">⬇ <?= _l('dv_export_csv'); ?></a><?php } ?>
  </form>

  <div class="dv-card">
    <h5 class="bold">📊 Funnel</h5>
    <div class="dv-scroll"><table class="dv-t">
      <thead><tr><th></th><th><?= _l('dv_r_visits'); ?></th><th><?= _l('dv_r_visited'); ?></th><th>Doctor Access</th><th>WhatsApp Capture</th><th>Meeting</th><th>Show</th><th>Close</th><th>Won</th><th><?= _l('dv_r_vpc'); ?></th></tr></thead>
      <?php foreach ($funnel as $f) { ?>
        <tr><td><b><?= html_escape($f['group']); ?></b></td><td><?= (int) $f['visits']; ?></td><td><?= (int) $f['visited']; ?></td>
          <td><?= (int) $f['access_rate']; ?>%</td><td><?= (int) $f['wa_rate']; ?>%</td><td><?= (int) $f['meeting_rate']; ?>%</td><td><?= (int) $f['show_rate']; ?>%</td><td><?= (int) $f['close_rate']; ?>%</td>
          <td><?= (int) $f['won']; ?></td><td><?= $f['visits_per_close'] === null ? '—' : $f['visits_per_close']; ?></td></tr>
      <?php } ?>
    </table></div>
  </div>

  <div class="row">
    <div class="col-md-6"><div class="dv-card">
      <h5 class="bold">❌ <?= _l('dv_lost_reasons'); ?></h5>
      <?php $tot = array_sum(array_column($lost, 'n')) ?: 1; foreach ($lost as $l) { ?>
        <div class="dv-row"><span><?= dv_lost_reason_label($l['lost_reason']); ?></span><b><?= (int) $l['n']; ?></b></div>
        <div class="dv-bar"><div style="width:<?= round(100 * $l['n'] / $tot); ?>%;background:#dc2626"></div></div>
      <?php } ?>
      <div class="dv-muted" style="margin-block-start:8px"><?= _l('dv_sales_cycle'); ?>: <b><?= $cycle === null ? '—' : $cycle . ' ' . _l('dv_days'); ?></b></div>
    </div></div>
    <div class="col-md-6"><div class="dv-card">
      <h5 class="bold">👩‍⚕️ <?= _l('dv_gatekeepers'); ?></h5>
      <table class="dv-t"><thead><tr><th></th><th><?= _l('dv_building'); ?></th><th><?= _l('dv_doctors'); ?></th><th><?= _l('dv_reached'); ?></th></tr></thead>
        <?php foreach ($gatekeepers as $g) { ?><tr><td><?= html_escape($g['name']); ?></td><td><?= html_escape($g['building']); ?></td><td><?= (int) $g['doctors']; ?></td><td><?= (int) $g['reached']; ?></td></tr><?php } ?>
      </table>
    </div></div>
  </div>
</div></div></div>
<?php init_tail(); ?>
</body></html>
