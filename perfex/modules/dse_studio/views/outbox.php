<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="panel_s"><div class="panel-body">
      <h4 class="tw-font-semibold">💬 رسايل الواتساب للعملاء</h4>
      <?php if (!$enabled) { ?>
        <div class="alert alert-warning">الإرسال مقفول — الرسايل بتتجمع هنا ومبتتبعتش لحد ما الأدمن يفتحه من الإعدادات.</div>
      <?php } ?>
      <table class="table table-condensed">
        <thead><tr><th>العميل</th><th>الرسالة</th><th>الحالة</th><th>الوقت</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($outbox as $o) { ?>
          <tr>
            <td><a href="<?= admin_url('dse_studio/client/' . $o['clientid']); ?>"><?= html_escape($o['company']); ?></a></td>
            <td style="white-space:pre-line;max-width:420px"><?= html_escape(mb_substr($o['body'], 0, 160)); ?></td>
            <td>
              <span class="label label-<?= ['sent' => 'success', 'queued' => 'info', 'sending' => 'info', 'failed' => 'danger'][$o['status']] ?? 'default'; ?>">
                <?= ['sent' => 'اتبعتت', 'queued' => 'مستنية', 'sending' => 'بتتبعت', 'failed' => 'فشلت'][$o['status']] ?? $o['status']; ?></span>
              <?= $o['manual'] ? '<small class="text-muted">يدوي</small>' : ''; ?>
              <?php if ($o['error']) { ?><div class="small text-danger"><?= html_escape($o['error']); ?></div><?php } ?>
            </td>
            <td class="small"><?= dse_day($o['created_at']) . ' ' . dse_time($o['created_at']); ?></td>
            <td><?php if ($o['status'] === 'failed') { ?><?= form_open(admin_url('dse_studio/retry/' . $o['id'])); ?><button class="btn btn-default btn-xs">ابعتها تاني</button><?= form_close(); ?><?php } ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<?php init_tail(); ?>
</body></html>
