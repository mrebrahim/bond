<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="panel_s"><div class="panel-body">
      <h4 class="tw-font-semibold">🔔 التنبيهات
        <a class="btn btn-default btn-sm pull-left" href="<?= admin_url('dse_studio/alerts' . ($all ? '' : '?all=1')); ?>"><?= $all ? 'المفتوحة بس' : 'كله'; ?></a>
      </h4>
      <?php if (!$alerts) { ?><p class="text-muted">مفيش تنبيهات — كله تمام</p><?php } ?>
      <?php foreach ($alerts as $a) { ?>
        <div class="alert alert-<?= $a['handled_at'] ? 'default' : ($a['priority'] === 'urgent' ? 'danger' : 'warning'); ?>">
          <div><?php if ($a['clientid']) { ?><a class="bold" href="<?= admin_url('dse_studio/client/' . $a['clientid']); ?>"><?= html_escape($a['company']); ?></a> · <?php } ?><?= html_escape($a['message']); ?></div>
          <div class="small text-muted"><?= dse_day($a['created_at']) . ' ' . dse_time($a['created_at']); ?><?= $a['handled_at'] ? ' · اتقفل: ' . html_escape($a['resolution']) : ''; ?></div>
          <?php if (!$a['handled_at']) { ?>
            <?= form_open(admin_url('dse_studio/resolve_alert/' . $a['id']), ['class' => 'form-inline mtop5']); ?>
              <input name="resolution" class="form-control input-sm" placeholder="حصل إيه؟" required style="min-width:240px">
              <button class="btn btn-default btn-sm">اقفل</button>
            <?= form_close(); ?>
          <?php } ?>
        </div>
      <?php } ?>
    </div></div>
  </div>
</div>
<?php init_tail(); ?>
</body></html>
