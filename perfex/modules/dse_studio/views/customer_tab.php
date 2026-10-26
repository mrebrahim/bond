<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// تاب "الاستوديو" جوه بروفايل العميل في Perfex — ملخص + لينك للصفحة الكاملة
$CI = &get_instance();
$CI->load->model('dse_studio/dse_studio_model');
$cid = isset($client) ? (int) $client->userid : 0;
$sum = null;
if ($cid) {
    try {
        $sum = $CI->dse_studio_model->client_summary($cid);
    } catch (Exception $e) {
        $sum = null;
    }
}
?>
<h4 class="customer-profile-group-heading"><?= _l('dse_studio'); ?></h4>
<?php if ($sum) { ?>
  <?php if ($sum['package']) { ?>
    <p><b><?= html_escape($sum['package']['name']); ?></b> — لحد <?= dse_day($sum['package']['end_date']); ?></p>
    <p>
      <?php foreach ($sum['balance'] as $t => $b) { ?>
        <span class="label label-default" style="font-size:13px;margin-left:6px"><?= dse_type_name($t); ?>: فاضل <?= dse_num($b['remaining']); ?> من <?= dse_num($b['total']); ?></span>
      <?php } ?>
    </p>
  <?php } else { ?>
    <p class="text-warning">مفيش باقة شغالة.</p>
  <?php } ?>
  <?php if ($sum['next']) { ?><p>📅 الجلسة الجاية: <?= dse_day($sum['next']['start_at']) . ' ' . dse_time($sum['next']['start_at']); ?></p><?php } ?>
  <p><?= $sum['client']['wa_group_jid'] ? '✅ جروب الواتساب متربط' : '⚠️ لسه مفيش جروب واتساب'; ?></p>
<?php } ?>
<a class="btn btn-primary" href="<?= admin_url('dse_studio/client/' . $cid); ?>">افتح صفحة الاستوديو (الحجز، الباقات، الواتساب)</a>
