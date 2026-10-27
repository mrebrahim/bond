<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$val = [];
foreach ($settings as $s) {
    if ((int) $s['staff_id'] === 0) {
        $val[$s['key']] = $s['value'];
    }
}
$token = $val['api_token'] ?? '';
?>
<div id="wrapper"><div class="content"><div class="dv" style="max-width:1100px">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>
  <h4 class="bold">⚙️ <?= _l('dv_smoke_title'); ?></h4>

  <div class="row">
    <div class="col-md-6">
      <div class="dv-card">
        <h5 class="bold"><?= _l('dv_edit_settings'); ?></h5>
        <?= form_open(admin_url('doctor_visits/settings')); ?>
          <input type="hidden" name="save_settings" value="1">
          <?php foreach (['monthly_target', 'avg_deal_value', 'default_visits_per_close', 'default_touches_to_win', 'min_daily_for_streak', 'cold_start_min_wins', 'off_days', 'quest_hour', 'qr_whatsapp_number', 'api_rate_limit', 'sprint_price', 'path_stalled_days'] as $k) { ?>
            <label class="dv-muted" style="display:block;margin:6px 0 2px" dir="ltr"><?= $k; ?></label>
            <input name="<?= $k; ?>" value="<?= html_escape($val[$k] ?? ''); ?>" class="dv-in" dir="ltr">
          <?php } ?>
          <?php foreach (['xp_rules', 'level_thresholds', 'level_names', 'level_rewards'] as $k) { ?>
            <label class="dv-muted" style="display:block;margin:6px 0 2px" dir="ltr"><?= $k; ?> (JSON)</label>
            <textarea name="<?= $k; ?>" class="dv-in" rows="3" dir="ltr" style="font-family:monospace;font-size:12px"><?= html_escape($val[$k] ?? ''); ?></textarea>
          <?php } ?>
          <button class="dv-btn dv-btn-primary" style="width:100%;margin-block-start:10px"><?= _l('dv_save'); ?></button>
        <?= form_close(); ?>
      </div>
    </div>

    <div class="col-md-6">
      <div class="dv-card">
        <h5 class="bold">🔌 <?= _l('dv_api'); ?></h5>
        <div class="dv-muted">Base</div><code dir="ltr" style="word-break:break-all"><?= html_escape($api_base); ?></code>
        <div class="dv-muted" style="margin-block-start:6px"><?= _l('dv_api_token'); ?></div>
        <code dir="ltr" style="word-break:break-all"><?= html_escape($token); ?></code>
        <?= form_open(admin_url('doctor_visits/settings'), ['onsubmit' => "return confirm('n8n هيقف لحد ما تحط الـ Token الجديد')"]); ?><input type="hidden" name="regenerate_token" value="1"><button class="dv-btn dv-btn-sm" style="margin-block-start:6px"><?= _l('dv_regenerate'); ?></button><?= form_close(); ?>
        <pre dir="ltr" style="font-size:11px;margin-block-start:8px;white-space:pre-wrap">POST /qr_event      {"code":"DV-7K2P","phone":"2010…","message":"…","received_at":"2026-11-03 20:00:00"}
POST /visit         {"lead_id":12,"staff_id":7,"outcome":"DOC","next_action":"…","next_action_at":"2026-11-05 18:00"}
GET  /queue?staff_id=7
GET  /followups_due?staff_id=7
POST /opt_out       {"phone":"2010…"}
Header: X-DV-Token: &lt;token&gt;   ·   n8n regex: DV-[A-Z0-9]{4}</pre>
      </div>

      <div class="dv-card">
        <h5 class="bold"><?= _l('dv_perfex_env'); ?></h5>
        <table class="dv-t">
          <tr><td><?= _l('dv_perfex_version'); ?></td><td dir="ltr"><b><?= html_escape((string) $perfex['app']); ?></b></td></tr>
          <tr><td><?= _l('dv_php'); ?></td><td dir="ltr"><?= html_escape($perfex['php']); ?></td></tr>
          <tr><td><?= _l('dv_database'); ?></td><td dir="ltr"><?= html_escape($perfex['db']); ?></td></tr>
          <tr><td><?= _l('dv_timezone'); ?></td><td dir="ltr"><?= html_escape($perfex['timezone']); ?>
            <?php if ($perfex['timezone'] !== 'Africa/Cairo') { ?><div class="text-danger small" dir="rtl"><?= _l('dv_timezone_warn'); ?></div><?php } ?></td></tr>
        </table>
        <h5 class="bold" style="margin-block-start:10px"><?= _l('dv_tables'); ?></h5>
        <table class="dv-t">
          <?php foreach ($tables as $t => $n) { ?><tr><td dir="ltr"><?= db_prefix() . $t; ?></td><td><?= $n === null ? '<span class="label label-danger">' . _l('dv_missing') . '</span>' : '<span class="label label-success">' . _l('dv_ok') . '</span> ' . (int) $n; ?></td></tr><?php } ?>
        </table>
      </div>

      <div class="dv-card" id="path">
        <h5 class="bold">🧭 <?= _l('dv_path_title'); ?></h5>
        <?php if ($path_seed) { ?>
          <div><?= sprintf(_l('dv_path_seeded'), (int) $path_seed['counts']['steps'], (int) $path_seed['counts']['items'], (int) $path_seed['counts']['scripts']); ?> <span class="dv-muted"><?= html_escape($path_seed['at']); ?></span></div>
          <?php foreach ($path_seed['warnings'] as $w) { ?><div class="text-warning small">⚠️ <?= html_escape($w); ?></div><?php } ?>
        <?php } else { ?><div class="text-warning"><?= _l('dv_path_no_seed'); ?></div><?php } ?>
        <?= form_open_multipart(admin_url('doctor_visits/settings')); ?>
          <input type="file" name="path_seed" accept=".json" class="dv-in" required style="margin-block-start:6px">
          <button class="dv-btn dv-btn-sm" style="margin-block-start:6px">⬆️ <?= _l('dv_path_upload'); ?></button>
        <?= form_close(); ?>
      </div>

      <div class="dv-card">
        <h5 class="bold"><?= _l('dv_stages'); ?></h5>
        <table class="dv-t">
          <?php foreach ($statuses as $s) { ?>
            <tr><td><?= html_escape((string) $s['stage']); ?></td><td><?= $s['progress'] === '' ? '' : (int) $s['progress'] . '%'; ?></td>
              <td><?php if ($s['row']) { ?><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= html_escape($s['row']['color']); ?>"></span> <?= html_escape($s['row']['name']); ?> <small class="dv-muted">#<?= (int) $s['row']['id']; ?></small>
                <?php } else { ?><span class="label label-danger"><?= _l('dv_missing'); ?></span> <?= html_escape($s['expected']); ?><?php } ?></td></tr>
          <?php } ?>
        </table>
      </div>

      <div class="dv-card">
        <h5 class="bold"><?= _l('dv_lead_hooks'); ?></h5>
        <div class="dv-muted"><?= _l('dv_lead_hooks_help'); ?></div>
        <?php if (!$hooks) { ?><p class="text-warning"><?= _l('dv_lead_hooks_none'); ?></p><?php } else { ?>
          <table class="dv-t"><?php foreach ($hooks as $h) { ?><tr><td dir="ltr"><?= html_escape($h['file']); ?></td><td dir="ltr"><?= html_escape($h['type']); ?></td><td dir="ltr"><code><?= html_escape($h['hook']); ?></code></td></tr><?php } ?></table>
        <?php } ?>
      </div>
    </div>
  </div>

  <div class="dv-card" id="templates">
    <h5 class="bold">💬 <?= _l('dv_template_edit'); ?></h5>
    <div class="dv-muted"><?= _l('dv_variables'); ?>: <span dir="ltr">{doctor_name} {specialty} {building} {rep_name} {audit_link} {key_insight} {day_a} {day_b} {meeting_time} {offer_link} {demo_link}</span></div>
    <?php foreach (array_merge($templates, [['id' => '', 'key' => '', 'name' => '', 'body' => '', 'stage_min' => 0, 'stage_max' => 9, 'days_since_touch_min' => 0, 'sort' => count($templates) + 1, 'active' => 1]]) as $t) { ?>
      <details style="border-block-end:1px solid #f1f5f9;padding-block:8px" <?= $t['id'] === '' ? '' : ''; ?>>
        <summary><?= $t['id'] === '' ? '➕ ' . _l('dv_add_template') : '<code dir="ltr">' . html_escape($t['key']) . '</code> — ' . html_escape($t['name']) . ' <span class="dv-muted">· ' . (int) $t['stage_min'] . '–' . (int) $t['stage_max'] . ' · ' . (int) $t['days_since_touch_min'] . 'd' . ($t['active'] ? '' : ' · ⛔') . '</span>'; ?></summary>
        <?= form_open(admin_url('doctor_visits/settings')); ?>
          <input type="hidden" name="save_template" value="1"><input type="hidden" name="id" value="<?= html_escape((string) $t['id']); ?>">
          <div class="row">
            <div class="col-sm-3"><input name="key" value="<?= html_escape($t['key']); ?>" class="dv-in" dir="ltr" placeholder="key" required></div>
            <div class="col-sm-5"><input name="name" value="<?= html_escape($t['name']); ?>" class="dv-in" placeholder="name"></div>
            <div class="col-sm-1"><input type="number" name="stage_min" value="<?= (int) $t['stage_min']; ?>" class="dv-in" title="stage_min"></div>
            <div class="col-sm-1"><input type="number" name="stage_max" value="<?= (int) $t['stage_max']; ?>" class="dv-in" title="stage_max"></div>
            <div class="col-sm-1"><input type="number" name="days_since_touch_min" value="<?= (int) $t['days_since_touch_min']; ?>" class="dv-in" title="days"></div>
            <div class="col-sm-1"><input type="number" name="sort" value="<?= (int) $t['sort']; ?>" class="dv-in" title="sort"></div>
          </div>
          <textarea name="body" class="dv-in" rows="3" style="margin-block:6px" required><?= html_escape($t['body']); ?></textarea>
          <label><input type="checkbox" name="active" value="1" <?= $t['active'] ? 'checked' : ''; ?>> <?= _l('dv_active'); ?></label>
          <button class="dv-btn dv-btn-sm dv-btn-primary"><?= _l('dv_save'); ?></button>
        <?= form_close(); ?>
      </details>
    <?php } ?>
  </div>
</div></div></div>
<?php init_tail(); ?>
</body></html>
