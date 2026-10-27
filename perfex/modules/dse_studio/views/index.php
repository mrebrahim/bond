<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content dse">
    <div class="row">
      <div class="col-md-12">
        <h4 class="tw-font-semibold tw-mb-3"><i class="fa fa-video-camera"></i> <?= _l('dse_studio'); ?></h4>
      </div>

      <div class="col-md-4">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">افتح عميل</h5>
          <select class="form-control selectpicker" data-live-search="true" onchange="if(this.value){location.href='<?= admin_url('dse_studio/client/'); ?>'+this.value}">
            <option value="">اختار العميل…</option>
            <?php foreach ($clients as $c) { ?>
              <option value="<?= (int) $c['userid']; ?>"><?= html_escape($c['company']); ?> — <?= dse_client_code($c['userid']); ?></option>
            <?php } ?>
          </select>
          <hr>
          <a class="btn btn-default btn-block" href="<?= admin_url('dse_studio/calendar'); ?>">📅 كالندر الاستوديو</a>
          <a class="btn btn-<?= $alerts ? 'danger' : 'default'; ?> btn-block" href="<?= admin_url('dse_studio/alerts'); ?>">🔔 التنبيهات (<?= (int) $alerts; ?>)</a>
          <a class="btn btn-default btn-block" href="<?= admin_url('dse_studio/outbox'); ?>">💬 رسايل الواتساب</a>
        </div></div>
      </div>

      <div class="col-md-8">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">جلسات النهارده</h5>
          <?php if (!$today) { ?><p class="text-muted">مفيش جلسات النهارده</p><?php } else { ?>
          <table class="table table-condensed">
            <thead><tr><th>الوقت</th><th>العميل</th><th>الاستوديو</th><th>الكمية</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($today as $s) { ?>
              <tr>
                <td><?= dse_time($s['start_at']); ?> – <?= dse_time($s['end_at']); ?></td>
                <td><a href="<?= admin_url('dse_studio/client/' . $s['clientid']); ?>"><?= html_escape($s['company']); ?></a></td>
                <td><?= html_escape($s['studio']); ?></td>
                <td><?= dse_num($s['qty']) . ' ' . dse_unit($s['type']); ?><?= $s['outfits'] ? ' · ' . (int) $s['outfits'] . ' لبس' : ''; ?></td>
                <td><?= dse_session_statuses()[$s['status']] ?? $s['status']; ?></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          <?php } ?>
        </div></div>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">الباقات الشغالة والمستنية الدفع</h5>
          <table class="table table-condensed">
            <thead><tr><th>العميل</th><th>الباقة</th><th>الحالة</th><th>لحد</th><th>الرصيد الباقي</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($packages as $p) { ?>
              <tr>
                <td><a href="<?= admin_url('dse_studio/client/' . $p['clientid']); ?>"><?= html_escape($p['company']); ?></a></td>
                <td><?= html_escape($p['name']); ?></td>
                <td><span class="label label-<?= $p['status'] === 'active' ? 'success' : 'warning'; ?>"><?= dse_package_statuses()[$p['status']]; ?></span></td>
                <td><?= dse_day($p['end_date']); ?></td>
                <td><?= html_escape($p['balance_text']); ?></td>
                <td class="text-nowrap">
                  <?php if (staff_can('delete', DSE_STUDIO_MODULE_NAME)) { ?>
                    <?php if (!$p['busy']) { ?>
                      <?= form_open(admin_url('dse_studio/delete_package/' . $p['id'] . '?back=home'), ['style' => 'display:inline', 'onsubmit' => "return confirm('تمسح باقة " . html_escape(addslashes($p['company'])) . " نهائي؟ (الفواتير بتفضل في Perfex)')"]); ?>
                        <button class="btn btn-danger btn-xs" title="الباقة اتعملت بالغلط ومفيش عليها شغل">🗑 امسح</button>
                      <?= form_close(); ?>
                    <?php } ?>
                    <?= form_open(admin_url('dse_studio/cancel_package/' . $p['id'] . '?back=home'), ['style' => 'display:inline', 'onsubmit' => "return confirm('تلغي باقة " . html_escape(addslashes($p['company'])) . "؟ (تقدر ترجّعها من صفحة العميل)')"]); ?>
                      <button class="btn btn-default btn-xs">الغي</button>
                    <?= form_close(); ?>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div></div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body></html>
