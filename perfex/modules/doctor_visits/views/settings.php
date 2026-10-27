<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content dv">
    <div class="row">
      <div class="col-md-12">
        <h4 class="tw-font-semibold"><i class="fa fa-user-md"></i> <?= _l('dv_smoke_title'); ?></h4>
        <p class="text-muted"><?= _l('dv_smoke_intro'); ?></p>
      </div>

      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold"><?= _l('dv_perfex_env'); ?></h5>
          <table class="table table-condensed">
            <tr><td><?= _l('dv_perfex_version'); ?></td><td dir="ltr"><b><?= html_escape((string) $perfex['app']); ?></b></td></tr>
            <tr><td><?= _l('dv_php'); ?></td><td dir="ltr"><?= html_escape($perfex['php']); ?></td></tr>
            <tr><td><?= _l('dv_database'); ?></td><td dir="ltr"><?= html_escape($perfex['db']); ?></td></tr>
            <tr><td><?= _l('dv_timezone'); ?></td><td dir="ltr"><?= html_escape($perfex['timezone']); ?>
              <?php if ($perfex['timezone'] !== 'Africa/Cairo') { ?><div class="text-danger small" dir="rtl"><?= _l('dv_timezone_warn'); ?></div><?php } ?></td></tr>
          </table>

          <h5 class="bold mtop20"><?= _l('dv_tables'); ?></h5>
          <table class="table table-condensed">
            <thead><tr><th><?= _l('dv_table'); ?></th><th><?= _l('dv_rows'); ?></th></tr></thead>
            <?php foreach ($tables as $t => $n) { ?>
              <tr><td dir="ltr"><?= db_prefix() . $t; ?></td><td><?= $n === null ? '<span class="label label-danger">' . _l('dv_missing') . '</span>' : '<span class="label label-success">' . _l('dv_ok') . '</span> ' . (int) $n; ?></td></tr>
            <?php } ?>
          </table>
        </div></div>
      </div>

      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold"><?= _l('dv_stages'); ?></h5>
          <table class="table table-condensed">
            <thead><tr><th><?= _l('dv_stage'); ?></th><th><?= _l('dv_progress'); ?></th><th><?= _l('dv_lead_status'); ?></th></tr></thead>
            <?php foreach ($statuses as $s) { ?>
              <tr>
                <td><?= html_escape((string) $s['stage']); ?></td>
                <td><?= $s['progress'] === '' ? '' : (int) $s['progress'] . '%'; ?></td>
                <td><?php if ($s['row']) { ?><span class="dv-dot" style="background:<?= html_escape($s['row']['color']); ?>"></span> <?= html_escape($s['row']['name']); ?> <small class="text-muted">#<?= (int) $s['row']['id']; ?></small>
                    <?php } else { ?><span class="label label-danger"><?= _l('dv_missing'); ?></span> <?= html_escape($s['expected']); ?><?php } ?></td>
              </tr>
            <?php } ?>
          </table>
        </div></div>
      </div>

      <div class="col-md-12">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold"><?= _l('dv_lead_hooks'); ?></h5>
          <p class="text-muted small"><?= _l('dv_lead_hooks_help'); ?></p>
          <?php if (!$hooks) { ?><p class="text-warning"><?= _l('dv_lead_hooks_none'); ?></p><?php } else { ?>
          <table class="table table-condensed">
            <thead><tr><th><?= _l('dv_file'); ?></th><th><?= _l('dv_type'); ?></th><th><?= _l('dv_hook'); ?></th></tr></thead>
            <?php foreach ($hooks as $h) { ?>
              <tr><td dir="ltr"><?= html_escape($h['file']); ?></td><td dir="ltr"><?= html_escape($h['type']); ?></td><td dir="ltr"><code><?= html_escape($h['hook']); ?></code></td></tr>
            <?php } ?>
          </table>
          <?php } ?>
        </div></div>
      </div>

      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold"><?= _l('dv_settings'); ?></h5>
          <table class="table table-condensed">
            <thead><tr><th><?= _l('dv_key'); ?></th><th><?= _l('dv_value'); ?></th><th><?= _l('dv_scope'); ?></th></tr></thead>
            <?php foreach ($settings as $s) { ?>
              <tr>
                <td dir="ltr"><code><?= html_escape($s['key']); ?></code></td>
                <td dir="ltr" class="dv-val"><?= $s['key'] === 'api_token' ? _l('dv_hidden') . ' ' . html_escape(substr((string) $s['value'], 0, 4)) . '…' : html_escape((string) $s['value']); ?></td>
                <td><?= (int) $s['staff_id'] === 0 ? _l('dv_global') : '#' . (int) $s['staff_id']; ?></td>
              </tr>
            <?php } ?>
          </table>
        </div></div>
      </div>

      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold"><?= _l('dv_templates'); ?></h5>
          <?php foreach ($templates as $t) { ?>
            <div class="dv-tpl">
              <div><code dir="ltr"><?= html_escape($t['key']); ?></code> — <b><?= html_escape($t['name']); ?></b>
                <span class="text-muted small"> · <?= _l('dv_template_stages'); ?> <?= (int) $t['stage_min']; ?>–<?= (int) $t['stage_max']; ?> · <?= _l('dv_template_days'); ?>: <?= (int) $t['days_since_touch_min']; ?></span></div>
              <div class="small"><?= html_escape($t['body']); ?></div>
            </div>
          <?php } ?>
        </div></div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<style>
.dv .dv-dot{display:inline-block;width:10px;height:10px;border-radius:50%;vertical-align:middle}
.dv .dv-val{max-width:360px;word-break:break-all;font-size:12px}
.dv .dv-tpl{border-bottom:1px solid #f1f5f9;padding:8px 0}
</style>
</body></html>
