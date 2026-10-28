<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $q = function (array $set) use ($f) { return admin_url('doctor_visits/path/report?' . http_build_query(array_filter(array_merge($f, $set)))); }; ?>
<div id="wrapper"><div class="content"><div class="dv" style="max-width:1200px">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>
  <h4 class="bold">📊 <?= _l('dv_menu_path_report'); ?></h4>

  <div class="dv-kpis" style="margin-block-end:10px">
    <div class="dv-kpi"><b><?= (int) $r['kpi']['doctors']; ?></b><span class="dv-muted"><?= _l('dv_doctors'); ?></span></div>
    <div class="dv-kpi"><b><?= (int) $r['kpi']['avg']; ?>%</b><span class="dv-muted"><?= _l('dv_pr_avg'); ?></span></div>
    <a class="dv-kpi" style="text-decoration:none;color:inherit;<?= $r['kpi']['stalled'] ? 'border-color:#dc2626' : ''; ?>" href="<?= $q(['stalled' => 1]); ?>"><b style="color:#dc2626"><?= (int) $r['kpi']['stalled']; ?></b><span class="dv-muted"><?= sprintf(_l('dv_pr_stalled_kpi'), (int) $r['days']); ?></span></a>
    <div class="dv-kpi"><b style="color:#0f7a62"><?= (int) $r['kpi']['complete']; ?></b><span class="dv-muted"><?= _l('dv_pr_complete'); ?></span></div>
  </div>

  <div class="dv-card">
    <h5 class="bold"><?= _l('dv_pr_where'); ?></h5>
    <div class="dv-row" style="justify-content:flex-start;gap:6px">
      <?php foreach ($r['steps'] as $s) { $n = (int) ($r['dist'][$s['key']] ?? 0); ?>
        <a class="dv-tag ph-<?= $s['phase']; ?>" style="padding:6px 10px;font-size:13px;background:var(--cbg);color:var(--c);<?= $f['step'] === $s['key'] ? 'outline:2px solid var(--c)' : ''; ?>" href="<?= $q(['step' => $f['step'] === $s['key'] ? null : $s['key']]); ?>"><?= (int) $s['sort']; ?> <?= html_escape($s['title']); ?> · <b><?= $n; ?></b></a>
      <?php } ?>
      <a class="dv-tag" style="padding:6px 10px;font-size:13px;background:#e2f4ee;color:#0f7a62" href="<?= $q(['step' => 'done']); ?>">✓ <?= _l('dv_path_all_done_short'); ?> · <b><?= (int) ($r['dist']['done'] ?? 0); ?></b></a>
    </div>
  </div>

  <form method="get" class="dv-card dv-row" style="justify-content:flex-start">
    <select name="staff_id" class="dv-in" style="width:auto"><option value=""><?= _l('dv_pr_all_staff'); ?></option><?php foreach ($staff as $s) { ?><option value="<?= (int) $s['staffid']; ?>" <?= (int) $f['staff_id'] === (int) $s['staffid'] ? 'selected' : ''; ?>><?= html_escape($s['firstname'] . ' ' . $s['lastname']); ?></option><?php } ?></select>
    <select name="building_id" class="dv-in" style="width:auto"><option value=""><?= _l('dv_pr_all_buildings'); ?></option><?php foreach ($buildings as $b) { ?><option value="<?= (int) $b['id']; ?>" <?= (int) $f['building_id'] === (int) $b['id'] ? 'selected' : ''; ?>><?= html_escape($b['name']); ?></option><?php } ?></select>
    <select name="step" class="dv-in" style="width:auto"><option value=""><?= _l('dv_pr_all_steps'); ?></option><?php foreach ($r['steps'] as $s) { ?><option value="<?= html_escape($s['key']); ?>" <?= $f['step'] === $s['key'] ? 'selected' : ''; ?>><?= (int) $s['sort'] . ' ' . html_escape($s['title']); ?></option><?php } ?><option value="done" <?= $f['step'] === 'done' ? 'selected' : ''; ?>>✓ <?= _l('dv_path_all_done_short'); ?></option></select>
    <label class="dv-muted" style="margin:0"><input type="checkbox" name="stalled" value="1" <?= $f['stalled'] ? 'checked' : ''; ?>> <?= _l('dv_pr_stalled_only'); ?></label>
    <button class="dv-btn dv-btn-sm dv-btn-primary">↻</button>
    <?php if (array_filter($f)) { ?><a class="dv-muted" href="<?= admin_url('doctor_visits/path/report'); ?>"><?= _l('dv_pr_clear'); ?></a><?php } ?>
  </form>

  <div class="dv-card dv-scroll">
    <?php if (!$r['rows']) { ?><p class="dv-muted"><?= _l('dv_pr_empty'); ?></p><?php } else { ?>
    <table class="dv-t">
      <thead><tr><th><?= _l('dv_doctor_name'); ?></th><th><?= _l('dv_assigned'); ?></th><th style="min-width:140px"><?= _l('dv_pr_progress'); ?></th><th><?= _l('dv_pr_current'); ?></th><th><?= _l('dv_pr_last'); ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($r['rows'] as $x) { ?>
        <tr style="<?= $x['stalled'] ? 'background:#fef2f2' : ''; ?>">
          <td><a href="<?= admin_url('doctor_visits/doctor/' . $x['lead_id'] . '#dv-path'); ?>"><b><?= html_escape($x['name']); ?></b></a><div class="dv-muted"><?= html_escape($x['specialty']); ?> · <?= html_escape($x['building']); ?><?= $x['lost'] ? ' · Lost' : ''; ?></div></td>
          <td class="dv-muted"><?= html_escape($x['staff']); ?></td>
          <td><div class="dv-row dv-muted" style="gap:4px"><span><?= (int) $x['pct']; ?>%</span><span><?= (int) $x['done']; ?> / <?= (int) $x['total']; ?></span></div><div class="dv-bar" style="margin:2px 0"><div style="width:<?= (int) $x['pct']; ?>%;background:#0f7a62"></div></div></td>
          <td><?= html_escape($x['step']); ?><?= $x['step_total'] ? ' <span class="dv-muted">' . (int) $x['step_done'] . '/' . (int) $x['step_total'] . '</span>' : ''; ?></td>
          <td class="dv-muted"><?= $x['last_check'] ? dv_date($x['last_check'], true) : _l('dv_pr_never'); ?></td>
          <td><?= $x['stalled'] ? '<span class="dv-chip" style="background:#dc2626">' . sprintf(_l('dv_pr_stalled'), (int) $x['stalled_days']) . '</span>' : ''; ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    <?php } ?>
  </div>
</div></div></div>
<?php init_tail(); ?>
</body></html>
