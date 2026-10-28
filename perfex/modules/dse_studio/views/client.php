<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$cid      = (int) $client['userid'];
$can_book = staff_can('create', DSE_STUDIO_MODULE_NAME);
$can_pkg  = staff_can('edit', DSE_STUDIO_MODULE_NAME);
$inv_status = [1 => 'مش مدفوعة', 2 => 'مدفوعة', 3 => 'مدفوعة جزئيًا', 4 => 'متأخرة', 5 => 'ملغية', 6 => 'درافت'];
$inv_name = function ($i) { return function_exists('format_invoice_number') ? format_invoice_number($i['id']) : ($i['prefix'] . $i['number']); };
?>
<div id="wrapper">
  <div class="content dse">
    <div class="row">
      <div class="col-md-12">
        <h4 class="tw-font-semibold">
          <?= html_escape($client['company']); ?> <small><?= $client['code']; ?></small>
          <a class="btn btn-default btn-sm pull-left" href="<?= admin_url('clients/client/' . $cid); ?>">ملف العميل في Perfex</a>
        </h4>
      </div>

      <!-- ===== الرصيد ===== -->
      <div class="col-md-12">
        <div class="panel_s"><div class="panel-body">
          <?php if ($package) { ?>
            <h5 class="bold">الباقة الشغالة: <?= html_escape($package['name']); ?> <small>لحد <?= dse_day($package['end_date']); ?></small></h5>
            <div class="row">
              <?php foreach ($balance as $type => $b) { ?>
                <div class="col-sm-4 col-xs-6">
                  <div class="dse-stat">
                    <div class="dse-stat-num"><?= dse_num($b['remaining']); ?></div>
                    <div><?= dse_type_name($type); ?> فاضل</div>
                    <small class="text-muted">من <?= dse_num($b['total']); ?> · اتستخدم <?= dse_num($b['used']); ?> · محجوز <?= dse_num($b['reserved']); ?></small>
                  </div>
                </div>
              <?php } ?>
            </div>
            <?php if ($paid < 0.999) { ?>
              <div class="alert alert-warning mtop10">
                💰 دافع <b><?= (int) round($paid * 100); ?>%</b> من الباقة (<?= dse_money($package['price'] * $paid); ?> من <?= dse_money($package['price']); ?>) —
                الشغل اللي اتسلّم: <?php foreach ($delivered as $t => $d) { if ($d['total'] > 0) { echo dse_num($d['done']) . '/' . dse_num($d['total']) . ' ' . dse_unit($t) . ' (' . (int) round($d['ratio'] * 100) . '%) &nbsp; '; } } ?>
              </div>
            <?php } ?>
            <?php if ($next) { ?><p class="mtop10">📅 الجلسة الجاية: <?= dse_day($next['start_at']) . ' ' . dse_time($next['start_at']) . ' — ' . html_escape($next['studio']); ?></p><?php } ?>
          <?php } else { ?>
            <p class="text-warning bold">مفيش باقة شغالة — الباقة بتتفعل لما الفاتورة المربوطة بيها تتدفع.</p>
          <?php } ?>
        </div></div>
      </div>

      <!-- ===== الباقات والدفع ===== -->
      <div class="col-md-12">
        <div class="panel_s"><div class="panel-body">
          <h4 class="bold tw-mt-0">📦 الباقات والدفع</h4>
          <?php foreach ($packages as $p) {
              $open   = in_array($p['status'], ['pending', 'active'], true);
              $pinv   = $pkg_invoices[$p['id']];
              $paidv  = $pkg_paid[$p['id']]; ?>
            <div class="dse-pkg <?= $open ? '' : 'is-closed'; ?>">
              <div>
                <b><?= html_escape($p['name']); ?></b> · <?= dse_money($p['price']); ?> · <?= dse_day($p['start_date']); ?> ← <?= dse_day($p['end_date']); ?>
                <span class="label label-<?= ['pending' => 'warning', 'active' => 'success', 'expired' => 'default', 'cancelled' => 'default'][$p['status']]; ?>"><?= dse_package_statuses()[$p['status']]; ?></span>
                <?php if (!empty($p['historic'])) { ?><span class="label label-default">قديمة — قبل السيستم</span><?php } ?>
                <?php $gdays = isset($p['grace_days']) && $p['grace_days'] !== null && $p['grace_days'] !== '' ? (int) $p['grace_days'] : (int) (get_option('dse_grace_days') !== '' ? get_option('dse_grace_days') : 15); $gend = date('Y-m-d', strtotime($p['end_date'] . ' +' . $gdays . ' days')); ?>
                <?php if ($p['status'] === 'active' && $p['end_date'] < date('Y-m-d')) { ?><span class="label label-warning">⏳ فترة سماح لحد <?= dse_day($gend); ?> علشان الرصيد اللي فاضل<?= isset($p['grace_days']) && $p['grace_days'] !== null && $p['grace_days'] !== '' ? ' (اتمدّت)' : ''; ?></span><?php } ?>
              </div>
              <div class="small mtop5">
                <?php if (!empty($p['historic']) && array_sum(array_map(function ($b) { return $b['total'] - $b['used']; }, $balances[$p['id']])) <= 0.001) { ?>
                  <span class="dse-chip">الرصيد الباقي: 0 — اتصرفت كلها<?= $balances[$p['id']] ? ' (' . implode(' · ', array_map(function ($t, $b) { return dse_num($b['total']) . ' ' . dse_unit($t); }, array_keys($balances[$p['id']]), $balances[$p['id']])) . ')' : ''; ?></span>
                <?php } else { foreach ($balances[$p['id']] as $t => $b) { ?>
                  <span class="dse-chip"><?= dse_type_name($t); ?>: <?= dse_num($b['used']); ?>/<?= dse_num($b['total']); ?></span>
                <?php } } ?>
              </div>

              <!-- الفلوس: كل الفواتير (أقساط / فرق ترقية) -->
              <div class="dse-money mtop5">
                <div class="dse-bar"><div style="width:<?= $p['price'] > 0 ? min(100, round($paidv / $p['price'] * 100)) : 100; ?>%"></div></div>
                <?php if (!empty($p['historic']) && !$pinv) { ?><div class="small text-muted">اتدفعت برا السيستم — لو عايز تعرف دفع كام سجّل دفعاته أو اربط فواتيره تحت</div><?php } else { ?>
                <div class="small">مدفوع <b><?= dse_money($paidv); ?></b> من <?= dse_money($p['price']); ?>
                  <?= $p['price'] - $paidv > 0.5 ? ' · <span class="text-danger">الباقي ' . dse_money($p['price'] - $paidv) . '</span>' : ' · ✅ خالص'; ?></div><?php } ?>
                <?php foreach ($pinv as $i) { ?>
                  <div class="small text-muted">
                    🧾 <a href="<?= admin_url('invoices/list_invoices/' . $i['invoice_id']); ?>" target="_blank"><?= html_escape($inv_name(['id' => $i['invoice_id'], 'prefix' => $i['prefix'], 'number' => $i['number']])); ?></a>
                    <?= $i['note'] ? '(' . html_escape($i['note']) . ')' : ''; ?> — <?= dse_money($i['paid']); ?> من <?= dse_money($i['total']); ?>
                    · <?= $inv_status[(int) $i['status']] ?? ''; ?>
                    <?php if ($can_pkg && $p['status'] !== 'cancelled') { ?>
                      <?= form_open(admin_url('dse_studio/unlink_invoice/' . $p['id'] . '/' . $i['invoice_id']), ['class' => 'inline-block', 'onsubmit' => "return confirm('تشيل الفاتورة دي من الباقة؟')"]); ?><button class="btn btn-link btn-xs">شيلها</button><?= form_close(); ?>
                    <?php } ?>
                  </div>
                <?php } ?>
                <?php if (!$pinv) { ?><div class="small text-muted">مفيش دفعات لسه</div><?php } ?>
                <?php $pays = $pkg_payments[$p['id']] ?? []; if ($pays) { ?>
                  <div class="small mtop5"><b>الدفعات (<?= count($pays); ?>):</b>
                    <?php foreach ($pays as $n => $py) { ?>
                      <div>💵 <?= $n + 1; ?>) <b><?= dse_money($py['amount']); ?></b> · <?= dse_day($py['date']); ?><?= !empty($py['mode_name']) ? ' · ' . html_escape($py['mode_name']) : ''; ?><?= $py['note'] ? ' — ' . html_escape($py['note']) : ''; ?></div>
                    <?php } ?>
                  </div>
                <?php } ?>
              </div>

              <?php if ($pkg_events[$p['id']]) { ?>
                <div class="small mtop5">
                  <?php foreach ($pkg_events[$p['id']] as $e) {
                      $oi = json_decode($e['old_items'], true) ?: [];
                      $ni = json_decode($e['new_items'], true) ?: [];
                      $ch = [];
                      foreach ($ni as $t => $q) { if (($oi[$t] ?? 0) != $q) { $ch[] = dse_type_name($t) . ' ' . dse_num($oi[$t] ?? 0) . '←' . dse_num($q); } } ?>
                    <div><?= in_array($e['event'], ['renew', 'grace'], true) ? '' : '⬆️ '; ?><?= ['upgrade' => 'ترقية', 'downgrade' => 'تخفيض', 'edit' => 'تعديل', 'renew' => '🔄 تجديد', 'grace' => '⏳ مدّ السماح'][$e['event']] ?? $e['event']; ?> <?= dse_day($e['created_at']); ?>:
                      <?= implode(' · ', $ch); ?><?= $e['old_price'] != $e['new_price'] ? ' · السعر ' . dse_money($e['old_price']) . ' ← ' . dse_money($e['new_price']) : ''; ?>
                      <?= $e['old_end_date'] != $e['new_end_date'] ? ' · لحد ' . dse_day($e['new_end_date']) : ''; ?><?= $e['note'] ? ' — ' . html_escape($e['note']) : ''; ?></div>
                  <?php } ?>
                </div>
              <?php } ?>

                  <?php $left = round((float) $p['price'] - $paidv, 2); if ($left > 0.5 && $can_pkg && $p['status'] !== 'cancelled') { ?>
                  <details class="dse-box" <?= $open ? 'open' : ''; ?>>
                    <summary>💵 <?= $open ? 'سجّل دفعة (قسط)' : 'سجّل دفعة على الباقة دي (حتى لو خلصت)'; ?> — الباقي <?= dse_money($left); ?></summary>
                    <?= form_open(admin_url('dse_studio/record_payment/' . $p['id'])); ?>
                      <div class="row">
                        <div class="col-xs-6 col-sm-3 form-group"><label>المبلغ</label><input type="number" name="amount" min="1" max="<?= $left; ?>" step="0.01" value="<?= $left; ?>" class="form-control" required></div>
                        <div class="col-xs-6 col-sm-3 form-group"><label>التاريخ</label><input type="date" name="date" value="<?= $open ? date('Y-m-d') : min(date('Y-m-d'), $p['end_date']); ?>" max="<?= date('Y-m-d'); ?>" class="form-control"></div>
                        <div class="col-xs-6 col-sm-3 form-group"><label>طريقة الدفع</label><select name="paymentmode" class="form-control"><?php foreach ($pay_modes as $pm) { ?><option value="<?= (int) $pm['id']; ?>"><?= html_escape($pm['name']); ?></option><?php } ?></select></div>
                        <div class="col-xs-6 col-sm-3 form-group"><label>ملاحظة</label><input name="note" class="form-control" placeholder="القسط التاني…"></div>
                      </div>
                      <button class="btn btn-success btn-sm">سجّل الدفعة</button>
                      <span class="small text-muted">بتتسجل على فاتورة الباقة في Perfex (ولو مفيش فاتورة بتتعمل لوحدها بالباقي). سجّل كل قسط لوحده وقت ما يتدفع — مفيش حد للعدد.</span>
                    <?= form_close(); ?>
                  </details>
                  <?php } ?>
              <?php if ($can_pkg && $p['status'] !== 'cancelled') { ?>
                  <details class="dse-box">
                    <summary>🧾 اربط فاتورة عملتها في Perfex (اختياري — لأي باقة حتى القديمة)</summary>
                    <p class="small text-muted">لو عامل فاتورة بنفسك في Perfex (قسط أو فرق ترقية) اربطها هنا — السيستم بيجمع المدفوع من كل الفواتير.</p>
                  <?= form_open(admin_url('dse_studio/link_invoice/' . $p['id']), ['class' => 'form-inline']); ?>
                    <select name="invoice_id" class="form-control input-sm" required>
                      <option value="">اربط فاتورة (قسط / دفعة)…</option>
                      <?php foreach ($free_invoices as $i) { ?><option value="<?= (int) $i['id']; ?>"><?= html_escape($inv_name($i)) . ' — ' . dse_money($i['total']) . ' — ' . ($inv_status[(int) $i['status']] ?? ''); ?></option><?php } ?>
                    </select>
                    <input name="note" class="form-control input-sm" placeholder="ملاحظة (القسط التاني…)" style="width:150px">
                    <button class="btn btn-default btn-xs">اربط</button>
                  <?= form_close(); ?>
                  </details>
              <?php } ?>
              <?php if ($open && $can_pkg) { ?>
                <div class="dse-pkg-actions">
                  <details class="dse-box">
                    <summary>⬆️ ترقية / تعديل الباقة</summary>
                  <?= form_open(admin_url('dse_studio/upgrade_package/' . $p['id'])); ?>
                    <p class="small text-muted">اكتب <b>الإجمالي الجديد</b> لكل خدمة و<b>السعر الجديد للباقة كلها</b> — اللي اتصوّر واتنشر بيفضل محسوب.</p>
                    <div class="row">
                      <?php foreach (dse_types() as $t => $info) { $cur = $balances[$p['id']][$t] ?? null; ?>
                        <div class="col-xs-4 form-group"><label><?= $info['name']; ?></label>
                          <input type="number" name="items[<?= $t; ?>]" min="<?= $cur ? $cur['used'] + $cur['reserved'] : 0; ?>" step="0.5" value="<?= $cur ? dse_num($cur['total']) : 0; ?>" class="form-control"></div>
                      <?php } ?>
                      <div class="col-xs-6 form-group"><label>السعر الجديد للباقة (ج.م)</label><input type="number" name="price" min="0" value="<?= (float) $p['price']; ?>" class="form-control" required></div>
                      <div class="col-xs-6 form-group"><label>مدّ المدة (أيام)</label><input type="number" name="extend_days" min="0" value="0" class="form-control"></div>
                      <div class="col-xs-12 form-group"><label>فاتورة الفرق (اعملها في Perfex الأول)</label>
                        <select name="invoice_id" class="form-control">
                          <option value="">— بعدين —</option>
                          <?php foreach ($free_invoices as $i) { ?><option value="<?= (int) $i['id']; ?>"><?= html_escape($inv_name($i)) . ' — ' . dse_money($i['total']) . ' — ' . ($inv_status[(int) $i['status']] ?? ''); ?></option><?php } ?>
                        </select></div>
                      <div class="col-xs-12 form-group"><input name="note" class="form-control" placeholder="ملاحظة (اختياري)"></div>
                    </div>
                    <button class="btn btn-primary btn-sm">احفظ الترقية</button>
                  <?= form_close(); ?>
                  </details>
                  <div class="mtop5">
                    <?php if ($p['status'] === 'pending' && is_admin()) { ?>
                      <?= form_open(admin_url('dse_studio/activate_package/' . $p['id']), ['class' => 'inline-block', 'onsubmit' => "return confirm('تفعّل الباقة من غير فاتورة مدفوعة؟')"]); ?><button class="btn btn-success btn-xs">فعّل يدوي</button><?= form_close(); ?>
                    <?php } ?>
                    <?php if (staff_can('delete', DSE_STUDIO_MODULE_NAME)) { ?>
                      <?= form_open(admin_url('dse_studio/cancel_package/' . $p['id']), ['class' => 'inline-block', 'onsubmit' => "return confirm('تلغي الباقة؟ (تقدر ترجّعها بعدين)')"]); ?><button class="btn btn-link btn-xs text-danger">الغي الباقة</button><?= form_close(); ?>
                    <?php } ?>
                  </div>
                </div>
              <?php } ?>

              <?php $gleft = array_sum(array_map(function ($b) { return $b['total'] - $b['used']; }, $balances[$p['id']]));
              if ($can_pkg && in_array($p['status'], ['active', 'expired'], true) && (!empty($p['historic']) || $gleft > 0.001)) { ?>
                <details class="dse-box">
                  <summary>⏳ مدّ فترة السماح — <?= $gend >= date('Y-m-d') && $gleft > 0.001 ? 'دلوقتي لحد ' . dse_day($gend) : 'خلصت'; ?></summary>
                  <?= form_open(admin_url('dse_studio/extend_grace/' . $p['id'])); ?>
                    <p class="small text-muted">للعميل اللي محتاج وقت أكتر يخلص الريلز والتصميمات اللي فاضلة — الباقة بتفضل (أو بترجع) شغالة وتقدر تحجز له لحد آخر يوم. <b>مفيش رسالة بتتبعت للعميل.</b></p>
                    <div class="row">
                      <div class="col-xs-6 col-sm-3 form-group"><label>المدة</label>
                        <select name="weeks" class="form-control"><option value="1">أسبوع</option><option value="2">أسبوعين</option><option value="3">3 أسابيع</option><option value="4">4 أسابيع</option></select></div>
                      <?php if (!empty($p['historic'])) { ?>
                        <?php foreach (dse_types() as $t => $info) { ?>
                          <div class="col-xs-6 col-sm-2 form-group"><label>فاضله <?= $info['name']; ?></label><input type="number" name="left[<?= $t; ?>]" min="0" step="0.5" value="<?= dse_num(max(0, ($balances[$p['id']][$t]['total'] ?? 0) - ($balances[$p['id']][$t]['used'] ?? 0))); ?>" class="form-control"></div>
                        <?php } ?>
                      <?php } ?>
                      <div class="col-xs-12 col-sm-<?= !empty($p['historic']) ? '3' : '9'; ?> form-group"><label>السبب (اختياري)</label><input name="note" class="form-control" placeholder="العميل كان مسافر…"></div>
                    </div>
                    <?php if (!empty($p['historic'])) { ?><p class="small text-muted">باقة قديمة: اكتب العميل لسه فاضله كام ريل / تصميم من الباقة دي — هو ده الرصيد اللي هيتحجز عليه.</p><?php } ?>
                    <button class="btn btn-warning btn-sm">مدّ</button>
                  <?= form_close(); ?>
                </details>
              <?php } ?>
              <?php if ($can_pkg && in_array($p['status'], ['active', 'expired', 'pending'], true)) { $days = (int) round((strtotime($p['end_date']) - strtotime($p['start_date'])) / 86400) + 1; ?>
                <details class="dse-box">
                  <summary>🔄 جدّد الاشتراك (الشهر الجاي)</summary>
                  <?= form_open(admin_url('dse_studio/renew_package/' . $p['id'])); ?>
                    <p class="small text-muted">باقة جديدة بنفس الخدمات والسعر — بتبدأ بعد ما دي تخلص، وبتتفعل لما تسجّل الدفعة عليها. اللي فاضل في الباقة دي بيفضل فيها لحد آخر يوم.</p>
                    <div class="row">
                      <div class="col-xs-6 col-sm-3 form-group"><label>تبدأ</label><input type="date" name="start_date" value="<?= max(date('Y-m-d'), date('Y-m-d', strtotime($p['end_date'] . ' +1 day'))); ?>" class="form-control"></div>
                      <div class="col-xs-6 col-sm-3 form-group"><label>المدة (أيام)</label><input type="number" name="validity_days" min="1" value="<?= $days; ?>" class="form-control"></div>
                      <div class="col-xs-12 col-sm-6 form-group"><label>السعر (ج.م)</label><input type="number" name="price" min="0" value="<?= (float) $p['price']; ?>" class="form-control"></div>
                      <?php foreach (dse_types() as $t => $info) { ?>
                        <div class="col-xs-4 form-group"><label><?= $info['name']; ?></label><input type="number" name="items[<?= $t; ?>]" min="0" step="0.5" value="<?= dse_num($balances[$p['id']][$t]['total'] ?? 0); ?>" class="form-control"></div>
                      <?php } ?>
                    </div>
                    <button class="btn btn-primary btn-sm">جدّد</button>
                  <?= form_close(); ?>
                </details>
              <?php } ?>
              <?php if ($can_pkg) { ?>
                <!-- تصحيح الغلط: تعديل البيانات / مسح / رجوع -->
                <details class="dse-box">
                  <summary>✏️ تعديل بيانات الباقة (الاسم والتواريخ)</summary>
                  <?= form_open(admin_url('dse_studio/edit_package/' . $p['id'])); ?>
                    <div class="row">
                      <div class="col-xs-12 col-sm-4 form-group"><label>اسم الباقة</label><input name="name" value="<?= html_escape($p['name']); ?>" class="form-control" required></div>
                      <div class="col-xs-6 col-sm-4 form-group"><label>تبدأ</label><input type="date" name="start_date" value="<?= $p['start_date']; ?>" class="form-control" required></div>
                      <div class="col-xs-6 col-sm-4 form-group"><label>تخلص</label><input type="date" name="end_date" value="<?= $p['end_date']; ?>" class="form-control" required></div>
                      <div class="col-xs-12 form-group"><label>ملاحظات</label><input name="notes" value="<?= html_escape($p['notes']); ?>" class="form-control"></div>
                    </div>
                    <p class="small text-muted">عدد الريلز والتصميمات والسعر بيتعدّلوا من "ترقية / تعديل الباقة".</p>
                    <button class="btn btn-primary btn-sm">احفظ</button>
                  <?= form_close(); ?>
                </details>
              <?php } ?>
              <?php if (staff_can('delete', DSE_STUDIO_MODULE_NAME)) { ?>
                <div class="mtop5">
                  <?php if ($p['status'] === 'cancelled') { ?>
                    <?= form_open(admin_url('dse_studio/restore_package/' . $p['id']), ['class' => 'inline-block']); ?><button class="btn btn-default btn-xs">↩️ رجّع الباقة (اتلغت بالغلط)</button><?= form_close(); ?>
                  <?php } ?>
                  <?php if (!$pkg_busy[$p['id']]) { ?>
                    <?= form_open(admin_url('dse_studio/delete_package/' . $p['id']), ['class' => 'inline-block', 'onsubmit' => "return confirm('تمسح الباقة دي نهائي؟ (الفواتير بتفضل في Perfex زي ما هي)')"]); ?><button class="btn btn-danger btn-xs">🗑 امسح الباقة (اتعملت بالغلط)</button><?= form_close(); ?>
                  <?php } else { ?>
                    <span class="small text-muted">مينفعش تتمسح — اتسجل عليها تصوير أو نشر (الغيها بدل كده)</span>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          <?php } ?>

          <?php if (!$packages) { ?>
            <div class="alert alert-info">العميل مالوش باقات لسه. اعمل الباقة من تحت — وبعدها هيظهر تحتها <b>تسجيل الأقساط</b> و<b>الترقية</b>.</div>
          <?php } ?>
          <?php if ($can_pkg) { ?>
          <details class="dse-box" <?= $packages ? '' : 'open'; ?>>
          <summary>➕ باقة جديدة <span class="small text-muted">(للتجديد استخدم "🔄 جدّد الاشتراك" على الباقة نفسها)</span></summary>
          <?= form_open(admin_url('dse_studio/create_package/' . $cid)); ?>
            <div class="row">
              <div class="col-xs-6 form-group"><label>اسم الباقة</label><input name="name" class="form-control" placeholder="Growth" required></div>
              <div class="col-xs-6 form-group"><label>السعر (ج.م)</label><input type="number" name="price" min="0" class="form-control" required></div>
              <div class="col-xs-6 form-group"><label>تبدأ</label><input type="date" name="start_date" value="<?= date('Y-m-d'); ?>" class="form-control"></div>
              <div class="col-xs-6 form-group"><label>المدة (أيام)</label><input type="number" name="validity_days" value="30" min="1" class="form-control"></div>
              <?php foreach (dse_types() as $t => $info) { ?>
                <div class="col-xs-4 form-group"><label><?= $info['name']; ?></label><input type="number" name="items[<?= $t; ?>]" min="0" step="0.5" class="form-control"></div>
              <?php } ?>
              <div class="col-xs-12 form-group"><label>الفاتورة (اعملها من Perfex الأول)</label>
                <select name="invoice_id" class="form-control">
                  <option value="">— بعدين —</option>
                  <?php foreach ($free_invoices as $i) { ?><option value="<?= (int) $i['id']; ?>"><?= html_escape($inv_name($i)) . ' — ' . dse_money($i['total']) . ' — ' . ($inv_status[(int) $i['status']] ?? ''); ?></option><?php } ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>نوع الباقة</label>
              <select name="historic" class="form-control" onchange="var d=this.form.querySelector('[name=start_date]');d.max=this.value==='1'?'<?= date('Y-m-d', strtotime('-1 day')); ?>':'';this.form.querySelector('.dse-hist-hint').style.display=this.value==='1'?'block':'none'">
                <option value="0">باقة جديدة (بتتفعل لما تسجّل الدفعة)</option>
                <option value="1">باقة قديمة — خلصت قبل السيستم (اتدفعت واتصرفت برا)</option>
              </select>
              <p class="small text-muted dse-hist-hint" style="display:none;margin:4px 0 0">اكتب تاريخ بداية آخر باقة كانت معاه ومدتها — الرصيد الباقي بيتصفّر (الريلز والتصميمات بتتحسب اتعملت كلها)، وعدد الريلز اختياري. العميل هيظهر في "🔄 مستنيين تجديد" علشان نطالبه يجدد.</p>
            </div>
            <button class="btn btn-primary">اعمل الباقة</button>
            <a class="btn btn-default" href="<?= admin_url('invoices/invoice?customer_id=' . $cid); ?>" target="_blank">اعمل فاتورة في Perfex</a>
          <?= form_close(); ?>
          </details>
          <?php } ?>
        </div></div>
      </div>

      <div class="col-md-6">
        <!-- ===== الحجز ===== -->
        <?php if ($can_book) { ?>
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">📅 احجز جلسة</h5>
          <?= form_open(admin_url('dse_studio/book/' . $cid)); ?>
            <div class="row">
              <div class="col-xs-6 form-group"><label>الميعاد</label><input type="datetime-local" name="start_at" class="form-control" required></div>
              <div class="col-xs-6 form-group"><label>الاستوديو</label>
                <select name="studio_id" class="form-control" required><?php foreach ($studios as $s) { ?><option value="<?= (int) $s['id']; ?>"><?= html_escape($s['name']); ?></option><?php } ?></select></div>
              <div class="col-xs-6 form-group"><label>النوع</label>
                <select name="type" class="form-control"><option value="reel">ريلز</option><option value="trainer_hours">ساعات مدرب</option></select></div>
              <div class="col-xs-6 form-group"><label>العدد</label><input type="number" name="qty" min="0.5" step="0.5" class="form-control" required></div>
              <div class="col-xs-12 form-group"><label>المصور</label>
                <select name="photographer_id" class="form-control"><option value="">—</option><?php foreach ($staff as $s) { ?><option value="<?= (int) $s['staffid']; ?>"><?= html_escape($s['firstname'] . ' ' . $s['lastname']); ?></option><?php } ?></select></div>
              <div class="col-xs-12 form-group"><label>ملاحظات</label><input name="notes" class="form-control"></div>
            </div>
            <p class="text-muted small">المدة واللبسات بتتحسب لوحدها، والعميل بتوصله رسالة "تم الحجز" في الجروب.</p>
            <button class="btn btn-primary">احجز</button>
          <?= form_close(); ?>
        </div></div>
        <?php } ?>

        <!-- ===== الجلسات ===== -->
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">الجلسات</h5>
          <?php foreach ($sessions as $s) { ?>
            <div class="dse-row">
              <div>
                <b><?= dse_day($s['start_at']) . ' ' . dse_time($s['start_at']); ?></b> · <?= html_escape($s['studio']); ?> ·
                <?= dse_num($s['qty']) . ' ' . dse_unit($s['type']); ?><?= $s['outfits'] ? ' · ' . (int) $s['outfits'] . ' لبس' : ''; ?>
                <span class="label label-<?= ['booked' => 'info', 'done' => 'success', 'no_show' => 'danger', 'cancelled' => 'default'][$s['status']]; ?>"><?= dse_session_statuses()[$s['status']]; ?></span>
                <?= $s['status'] === 'done' ? ' (اتصور ' . dse_num($s['actual_qty']) . ')' : ''; ?><?= $s['late_cancel'] ? ' · إلغاء متأخر' : ''; ?>
                <?php if ($s['notes']) { ?><div class="small text-muted" style="white-space:pre-line"><?= html_escape($s['notes']); ?></div><?php } ?>
              </div>
              <?php if ($s['status'] === 'done' && $can_book) { ?>
                <details class="dse-box dse-box-sm">
                  <summary>✏️ صحّح العدد</summary>
                  <?= form_open(admin_url('dse_studio/correct_qty/' . $s['id']), ['class' => 'form-inline']); ?>
                    <label class="small">اتصور فعلًا</label>
                    <input type="number" name="actual_qty" min="0.5" step="0.5" value="<?= dse_num($s['actual_qty']); ?>" class="form-control input-sm" style="width:80px" required>
                    <input name="reason" class="form-control input-sm" placeholder="السبب (اتكتب غلط…)" required style="min-width:180px">
                    <button class="btn btn-primary btn-xs">صحّح</button>
                  <?= form_close(); ?>
                </details>
              <?php } ?>
              <?php if ($s['status'] === 'booked' && $can_book) { ?>
                <div class="dse-actions">
                  <?= form_open(admin_url('dse_studio/complete/' . $s['id']), ['class' => 'form-inline']); ?>
                    <input type="number" name="actual_qty" min="0.5" step="0.5" value="<?= dse_num($s['qty']); ?>" class="form-control input-sm" style="width:70px" required>
                    <button class="btn btn-success btn-xs">اتصورت</button>
                  <?= form_close(); ?>
                  <?= form_open(admin_url('dse_studio/reschedule/' . $s['id']), ['class' => 'form-inline']); ?>
                    <input type="datetime-local" name="start_at" class="form-control input-sm" required>
                    <button class="btn btn-default btn-xs">غيّر الميعاد</button>
                  <?= form_close(); ?>
                  <?= form_open(admin_url('dse_studio/no_show/' . $s['id']), ['class' => 'form-inline', 'onsubmit' => "return confirm('العميل مجاش؟ هيتخصم من رصيده')"]); ?>
                    <button class="btn btn-warning btn-xs">مجاش</button>
                  <?= form_close(); ?>
                  <?= form_open(admin_url('dse_studio/cancel_session/' . $s['id']), ['class' => 'form-inline', 'onsubmit' => "return confirm('تلغي الجلسة؟ لو قبل الميعاد بأقل من 24 ساعة هيتخصم')"]); ?>
                    <input name="reason" placeholder="السبب" class="form-control input-sm">
                    <button class="btn btn-danger btn-xs">الغي</button>
                  <?= form_close(); ?>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
          <?php if (!$sessions) { ?><p class="text-muted">لسه مفيش جلسات</p><?php } ?>
        </div></div>

      </div>

      <div class="col-md-6">
        <!-- ===== الواتساب ===== -->
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">💬 واتساب العميل</h5>
          <?php if ($client['wa_group_jid']) { ?>
            <p>✅ الجروب متربط <?php if ($client['wa_group_link']) { ?>· <a href="<?= html_escape($client['wa_group_link']); ?>" target="_blank">لينك الجروب</a><?php } ?></p>
          <?php } ?>
          <?php if ($can_book) { ?>
            <?= form_open(admin_url('dse_studio/wa/' . $cid . '/phone'), ['class' => 'form-inline mbot10']); ?>
              <input name="wa_phone" value="<?= html_escape($client['wa_phone']); ?>" placeholder="01xxxxxxxxx" class="form-control input-sm" dir="ltr">
              <button class="btn btn-default btn-sm">احفظ رقم الواتساب</button>
            <?= form_close(); ?>
            <?php if (!$client['wa_group_jid']) { ?>
              <?= form_open(admin_url('dse_studio/wa/' . $cid . '/create_group'), ['class' => 'inline-block']); ?><button class="btn btn-success btn-sm">اعمل جروب واتساب</button><?= form_close(); ?>
              <?= form_open(admin_url('dse_studio/wa/' . $cid . '/link_group'), ['class' => 'form-inline mtop5']); ?>
                <input name="link" placeholder="أو لينك جروب موجود https://chat.whatsapp.com/..." class="form-control input-sm" dir="ltr" style="min-width:260px">
                <button class="btn btn-default btn-sm">اربط</button>
              <?= form_close(); ?>
            <?php } else { ?>
              <?= form_open(admin_url('dse_studio/wa/' . $cid . '/add_team'), ['class' => 'inline-block']); ?><button class="btn btn-default btn-sm">ضيف أرقام الفريق للجروب</button><?= form_close(); ?>
            <?php } ?>
            <hr>
            <h6 class="bold">مانوال العميل الجديد</h6>
            <?php foreach (dse_manual_keys() as $k => $label) { ?>
              <?= form_open(admin_url('dse_studio/wa/' . $cid . '/manual'), ['class' => 'inline-block mbot5']); ?>
                <input type="hidden" name="key" value="<?= $k; ?>"><button class="btn btn-default btn-xs">ابعت: <?= $label; ?></button>
              <?= form_close(); ?>
            <?php } ?>
          <?php } ?>
          <?php if ($outbox) { ?>
            <hr><h6 class="bold">آخر الرسايل</h6>
            <?php foreach ($outbox as $o) { ?>
              <div class="small"><span class="label label-<?= ['sent' => 'success', 'queued' => 'info', 'sending' => 'info', 'failed' => 'danger'][$o['status']] ?? 'default'; ?>"><?= $o['status']; ?></span>
                <?= html_escape(mb_substr($o['body'], 0, 70)); ?>… <span class="text-muted"><?= dse_day($o['created_at']) . ' ' . dse_time($o['created_at']); ?></span></div>
            <?php } ?>
          <?php } ?>
        </div></div>

        <!-- ===== السكربتات ===== -->
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">📝 السكربتات</h5>
          <?php foreach ($scripts as $s) { ?>
            <div class="dse-row">
              <div><b><?= html_escape($s['title']); ?></b>
                <span class="label label-<?= ['draft' => 'default', 'sent' => 'info', 'approved' => 'success', 'changes' => 'warning'][$s['status']]; ?>"><?= ['draft' => 'مسودة', 'sent' => 'اتبعت', 'approved' => 'العميل وافق', 'changes' => 'طالب تعديل'][$s['status']]; ?></span>
                <div class="small text-muted" style="white-space:pre-line"><?= html_escape(mb_substr($s['body'], 0, 200)); ?></div>
              </div>
              <?php if ($can_book && $s['status'] !== 'approved') { ?>
                <div class="dse-actions">
                  <?php foreach (['send' => 'ابعت للعميل', 'approved' => 'وافق', 'changes' => 'طالب تعديل'] as $a => $l) { ?>
                    <?= form_open(admin_url('dse_studio/script_action/' . $s['id'] . '/' . $a), ['class' => 'inline-block']); ?><button class="btn btn-default btn-xs"><?= $l; ?></button><?= form_close(); ?>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
          <?php if ($can_book) { ?>
            <?= form_open(admin_url('dse_studio/add_script/' . $cid)); ?>
              <input name="title" class="form-control mbot5" placeholder="عنوان السكربت" required>
              <textarea name="body" rows="5" class="form-control mbot5" placeholder="نص السكربت" required></textarea>
              <button name="send" value="1" class="btn btn-primary btn-sm">احفظ وابعت للعميل</button>
              <button class="btn btn-default btn-sm">احفظ بس</button>
            <?= form_close(); ?>
          <?php } ?>
        </div></div>

        <!-- ===== النشر ===== -->
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">🎬 نزل على السوشيال</h5>
          <?php if ($can_book) { ?>
            <?= form_open(admin_url('dse_studio/publish/' . $cid)); ?>
              <div class="row">
                <div class="col-xs-4"><select name="type" class="form-control"><option value="reel">ريل</option><option value="design">تصميم</option></select></div>
                <div class="col-xs-8"><input name="post_url" class="form-control" dir="ltr" placeholder="https://instagram.com/p/..." required></div>
              </div>
              <p class="text-muted small mtop5">العميل بتوصله رسالة فيها لينك البوست ولينك التقييم والرصيد الباقي. التصميم بيتخصم من الرصيد.</p>
              <button class="btn btn-primary btn-sm">سجّل إنه نزل</button>
            <?= form_close(); ?>
          <?php } ?>
          <?php foreach ($posts as $p) { ?>
            <div class="small"><?= $p['type'] === 'design' ? '🎨' : '🎬'; ?> <a href="<?= html_escape($p['post_url']); ?>" target="_blank" dir="ltr"><?= html_escape(mb_substr($p['post_url'], 0, 50)); ?></a> <span class="text-muted"><?= dse_day($p['published_at']); ?></span></div>
          <?php } ?>
        </div></div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<style>
.dse .dse-stat{border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-bottom:10px;text-align:center}
.dse .dse-stat-num{font-size:28px;font-weight:700}
.dse .dse-row{border-bottom:1px solid #f1f5f9;padding:8px 0}
.dse .dse-pkg{border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;margin-bottom:10px}
.dse .dse-pkg.is-closed{opacity:.6}
.dse .dse-chip{display:inline-block;background:#f1f5f9;border-radius:999px;padding:2px 9px;margin:0 0 3px 4px}
.dse .dse-bar{height:6px;background:#f1f5f9;border-radius:99px;overflow:hidden;margin:4px 0}
.dse .dse-bar div{height:100%;background:#16a34a}
.dse .dse-upgrade{background:#f8fafc;border-radius:8px;padding:10px;margin-top:8px}
.dse .dse-pkg-actions{margin-top:10px}
.dse details.dse-box{border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;margin-top:8px;background:#fff}
.dse details.dse-box[open]{background:#f8fafc}
.dse details.dse-box>summary{cursor:pointer;font-weight:600;color:#1d4ed8;list-style:none}
.dse details.dse-box>summary::-webkit-details-marker{display:none}
.dse details.dse-box[open]>summary{margin-bottom:8px}
.dse details.dse-box-sm{padding:4px 10px;margin-top:4px;font-size:12px}
.dse .dse-actions{margin-top:6px}
.dse .dse-actions form,.dse .inline-block{display:inline-block;margin:2px}
</style>
</body></html>
