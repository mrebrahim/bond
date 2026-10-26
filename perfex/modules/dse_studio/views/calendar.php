<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="panel_s"><div class="panel-body">
      <h4 class="tw-font-semibold">
        📅 كالندر الاستوديو — أسبوع <?= dse_day($start); ?>
        <span class="pull-left">
          <a class="btn btn-default btn-sm" href="<?= admin_url('dse_studio/calendar?week=' . date('Y-m-d', strtotime($start . ' -7 days'))); ?>">→ اللي فات</a>
          <a class="btn btn-default btn-sm" href="<?= admin_url('dse_studio/calendar'); ?>">الأسبوع ده</a>
          <a class="btn btn-default btn-sm" href="<?= admin_url('dse_studio/calendar?week=' . date('Y-m-d', strtotime($start . ' +7 days'))); ?>">الجاي ←</a>
        </span>
      </h4>
      <?php for ($d = 0; $d < 7; $d++) {
          $day  = date('Y-m-d', strtotime($start . ' +' . $d . ' days'));
          $list = array_filter($sessions, function ($s) use ($day) { return substr($s['start_at'], 0, 10) === $day; }); ?>
        <h5 class="bold mtop20 <?= $day === date('Y-m-d') ? 'text-success' : ''; ?>"><?= dse_day($day); ?><?= $day === date('Y-m-d') ? ' (النهارده)' : ''; ?></h5>
        <?php if (!$list) { ?><p class="text-muted small">فاضي</p><?php } else { ?>
        <table class="table table-condensed">
          <tbody>
          <?php foreach ($list as $s) { ?>
            <tr class="<?= $s['status'] === 'cancelled' ? 'text-muted' : ''; ?>">
              <td style="width:130px"><?= dse_time($s['start_at']); ?> – <?= dse_time($s['end_at']); ?></td>
              <td><?= html_escape($s['studio']); ?></td>
              <td><a href="<?= admin_url('dse_studio/client/' . $s['clientid']); ?>"><?= html_escape($s['company']); ?></a></td>
              <td><?= dse_num($s['qty']) . ' ' . dse_unit($s['type']); ?><?= $s['outfits'] ? ' · ' . (int) $s['outfits'] . ' لبس' : ''; ?></td>
              <td><?= dse_session_statuses()[$s['status']]; ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
        <?php } ?>
      <?php } ?>
    </div></div>
  </div>
</div>
<?php init_tail(); ?>
</body></html>
