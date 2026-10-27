<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="dv">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>

  <div class="dv-card dv-row">
    <div class="dv-ring" style="--p:<?= $d['today_target'] ? min(100, round(100 * $d['today_done'] / $d['today_target'])) : 100; ?>"><div><?= (int) $d['today_done']; ?>/<?= (int) $d['today_target']; ?><small><?= _l('dv_today_goal'); ?></small></div></div>
    <div style="flex:1;min-width:200px">
      <b><?= _l('dv_level'); ?> <?= (int) $d['level']['level']; ?> · <?= html_escape($d['level']['name']); ?></b> <span class="dv-muted"><?= number_format($d['level']['xp']); ?> XP</span>
      <div class="dv-bar"><div style="width:<?= (int) $d['level']['pct']; ?>%;background:#8b5cf6"></div></div>
      <?php if ($d['level']['next_at'] !== null) { ?>
        <div class="dv-muted"><?= sprintf(_l('dv_to_next_level'), (int) $d['level']['to_next'], (int) $d['level']['level'] + 1, html_escape($d['level']['next_reward'] ?: $d['level']['next_name'])); ?>
          <?php if ($d['xp_path']) { echo sprintf(_l('dv_xp_path'), html_escape(implode(' + ', array_map(function ($p) { return $p['label'] . ' (+' . $p['xp'] . ')'; }, array_slice($d['xp_path'], 0, 3))))); } ?></div>
      <?php } ?>
    </div>
  </div>

  <div class="dv-kpis">
    <div class="dv-kpi"><span class="dv-muted"><?= _l('dv_value_per_visit'); ?></span><b><?= dv_money($d['value_per_visit']); ?></b><?php if ($d['cold_start']) { ?><span class="dv-muted"><?= _l('dv_cold_start'); ?></span><?php } ?></div>
    <div class="dv-kpi"><span class="dv-muted"><?= _l('dv_streak'); ?></span><b>🔥 <?= (int) $d['streak']; ?> <?= _l('dv_days'); ?></b></div>
    <div class="dv-kpi"><span class="dv-muted"><?= _l('dv_pipeline'); ?></span><b><?= dv_money($d['pipeline']); ?></b></div>
    <div class="dv-kpi"><span class="dv-muted"><?= _l('dv_won_month'); ?></span><b><?= (int) $d['won_month']['n']; ?> · <?= dv_money($d['won_value_month']); ?></b>
      <div class="dv-bar"><div style="width:<?= $d['monthly_target'] ? min(100, round(100 * $d['won_value_month'] / $d['monthly_target'])) : 0; ?>%;background:#16a34a"></div></div>
      <span class="dv-muted"><?= _l('dv_month_target'); ?> <?= dv_money($d['monthly_target']); ?></span></div>
  </div>

  <div class="dv-card" style="margin-block-start:10px">
    <h5 class="bold">🎯 <?= _l('dv_quests'); ?></h5>
    <?php foreach ($d['quests'] as $q) { ?>
      <div class="dv-row" style="min-height:36px">
        <span><?= $q['complete'] ? '✅' : '⬜'; ?> <?= sprintf(_l('dv_quest_' . $q['key']), (int) $q['target']); ?></span>
        <span class="dv-muted"><?= (int) $q['done']; ?>/<?= (int) $q['target']; ?></span>
      </div>
    <?php } ?>
  </div>

  <div class="dv-card" style="background:#faf5ff;border-color:#e9d5ff">
    <h5 class="bold">🔥 <?= _l('dv_closing_zone'); ?></h5>
    <?php if ($d['zone']['hot']) { ?>
      <div class="dv-muted"><?= sprintf(_l('dv_closing_zone_sub'), count($d['zone']['hot']), dv_money($d['zone']['hot_value'])); ?> · <?= sprintf(_l('dv_warm_zone'), count($d['zone']['warm'])); ?></div>
      <?php foreach ($d['zone']['hot'] as $x) { $this->load->view('doctor_visits/_card', ['d' => $x, 'back' => 'doctor_visits/dashboard']); } ?>
    <?php } else { ?>
      <div class="dv-muted"><?= sprintf(_l('dv_zone_empty'), (int) round($d['visits_per_close'])); ?> · <?= sprintf(_l('dv_warm_zone'), count($d['zone']['warm'])); ?></div>
    <?php } ?>
  </div>

  <div class="dv-card">
    <h5 class="bold">📊 <?= _l('dv_funnel'); ?></h5>
    <div class="dv-scroll"><table class="dv-t">
      <thead><tr><th></th><th><?= _l('dv_r_visits'); ?></th><th><?= _l('dv_r_visited'); ?></th><th><?= _l('dv_r_access'); ?></th><th><?= _l('dv_r_wa'); ?></th><th><?= _l('dv_r_meeting'); ?></th><th><?= _l('dv_r_close'); ?></th></tr></thead>
      <?php foreach ($d['funnel'] as $f) { ?>
        <tr><td><?= html_escape($f['group']); ?></td><td><?= (int) $f['visits']; ?></td><td><?= (int) $f['visited']; ?></td><td><?= (int) $f['access_rate']; ?>%</td><td><?= (int) $f['wa_rate']; ?>%</td><td><?= (int) $f['meeting_rate']; ?>%</td><td><?= (int) $f['won']; ?></td></tr>
      <?php } ?>
    </table></div>
  </div>

  <?php if ($d['milestones']) { ?>
    <div class="dv-card"><h5 class="bold">⭐ <?= _l('dv_milestones'); ?></h5>
      <?php foreach ($d['milestones'] as $m) { ?><span class="dv-tag" style="font-size:13px;padding:4px 10px"><?= _l('dv_ms_' . $m['key']); ?> · <?= dv_date($m['at']); ?></span><?php } ?>
    </div>
  <?php } ?>

  <?php if ($d['leaderboard']) { ?>
    <div class="dv-card"><h5 class="bold">🏅 <?= _l('dv_leaderboard'); ?></h5>
      <?php foreach ($d['leaderboard'] as $i => $l) { ?><div class="dv-row"><span><?= $i + 1; ?>. <?= html_escape($l['name']); ?></span><b><?= (int) $l['advances']; ?></b></div><?php } ?>
    </div>
  <?php } ?>
  <?php $this->load->view('doctor_visits/_feedback'); ?>
</div></div></div>
<?php init_tail(); ?>
</body></html>
