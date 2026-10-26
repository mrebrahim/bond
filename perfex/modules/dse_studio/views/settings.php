<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $o = function ($k) { return html_escape((string) get_option($k)); }; ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-7">
        <div class="panel_s"><div class="panel-body">
          <h4 class="tw-font-semibold">⚙️ إعدادات الاستوديو والواتساب</h4>
          <?= form_open(admin_url('dse_studio/settings')); ?>
            <h5 class="bold mtop20">الواتساب (Evolution API)</h5>
            <div class="form-group">
              <label>إرسال الرسايل للعملاء</label>
              <select name="dse_wa_enabled" class="form-control">
                <option value="1" <?= get_option('dse_wa_enabled') === '1' ? 'selected' : ''; ?>>✅ شغال</option>
                <option value="0" <?= get_option('dse_wa_enabled') !== '1' ? 'selected' : ''; ?>>⛔ مقفول (الرسايل بتتجمع ومبتتبعتش)</option>
              </select>
            </div>
            <?php if ($state !== null) { ?><p>حالة الرقم: <b><?= html_escape($state); ?></b> <?= $state === 'open' ? '✅' : '⚠️'; ?></p><?php } ?>
            <div class="form-group"><label>Evolution URL</label><input name="dse_evo_url" value="<?= $o('dse_evo_url'); ?>" class="form-control" dir="ltr"></div>
            <div class="form-group"><label>API key <?= get_option('dse_evo_key') ? '(متسجل — سيبه فاضي علشان يفضل زي ما هو)' : ''; ?></label><input type="password" name="dse_evo_key" class="form-control" dir="ltr" autocomplete="new-password"></div>
            <div class="form-group"><label>اسم الـ instance</label><input name="dse_evo_instance" value="<?= $o('dse_evo_instance'); ?>" class="form-control" dir="ltr"></div>
            <div class="form-group"><label>أقصى رسايل أوتوماتيك لنفس العميل في اليوم</label><input type="number" name="dse_wa_daily_cap" value="<?= $o('dse_wa_daily_cap'); ?>" class="form-control"></div>
            <div class="form-group"><label>أرقام الفريق اللي بتتضاف لأي جروب عميل (مفصولة بفاصلة)</label><input name="dse_wa_team_numbers" value="<?= $o('dse_wa_team_numbers'); ?>" class="form-control" dir="ltr"></div>
            <div class="form-group"><label>لينك التقييم ({client_code} و {post_id} بيتبدلوا)</label><input name="dse_feedback_url" value="<?= $o('dse_feedback_url'); ?>" class="form-control" dir="ltr"></div>
            <div class="form-group"><label>نص قواعد التعامل</label><textarea name="dse_rules_text" rows="8" class="form-control"><?= $o('dse_rules_text'); ?></textarea></div>

            <h5 class="bold mtop20">الحجز والخصم</h5>
            <div class="row">
              <div class="col-xs-6 form-group"><label>دقايق لكل ريل</label><input type="number" name="dse_minutes_per_reel" value="<?= $o('dse_minutes_per_reel'); ?>" class="form-control"></div>
              <div class="col-xs-6 form-group"><label>ريلز لكل لبس</label><input type="number" name="dse_reels_per_outfit" value="<?= $o('dse_reels_per_outfit'); ?>" class="form-control"></div>
              <div class="col-xs-6 form-group"><label>الإلغاء المتأخر (ساعات قبل الميعاد)</label><input type="number" name="dse_late_cancel_hours" value="<?= $o('dse_late_cancel_hours'); ?>" class="form-control"></div>
              <div class="col-xs-6 form-group"><label>خصم الغياب / الإلغاء المتأخر (وحدات)</label><input type="number" step="0.5" name="dse_penalty_units" value="<?= $o('dse_penalty_units'); ?>" class="form-control"></div>
            </div>

            <h5 class="bold mtop20">تنبيه العميل اللي مابيصوّرش</h5>
            <div class="row">
              <div class="col-xs-6 form-group"><label>بعد كام يوم من غير تصوير</label><input type="number" name="dse_inactive_days" value="<?= $o('dse_inactive_days'); ?>" class="form-control"></div>
              <div class="col-xs-6 form-group"><label>مين يوصله التنبيه (أرقام الموظفين — فاضي = الأدمنز)</label><input name="dse_notify_staff_ids" value="<?= $o('dse_notify_staff_ids'); ?>" class="form-control" dir="ltr"></div>
            </div>
            <p class="small text-muted">أرقام الموظفين: <?php foreach ($staff as $s) { echo (int) $s['staffid'] . '=' . html_escape($s['firstname']) . ' · '; } ?></p>
            <button class="btn btn-primary">احفظ</button>
          <?= form_close(); ?>
        </div></div>
      </div>

      <div class="col-md-5">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">الاستوديوهات</h5>
          <?php foreach ($studios as $s) { ?>
            <?= form_open(admin_url('dse_studio/studio/toggle/' . $s['id']), ['class' => 'mbot5']); ?>
              <?= html_escape($s['name']); ?> <?= $s['active'] ? '' : '<span class="text-muted">(مقفول)</span>'; ?>
              <button class="btn btn-link btn-xs"><?= $s['active'] ? 'اقفله' : 'افتحه'; ?></button>
            <?= form_close(); ?>
          <?php } ?>
          <?= form_open(admin_url('dse_studio/studio/add'), ['class' => 'form-inline mtop10']); ?>
            <input name="name" class="form-control input-sm" placeholder="اسم الاستوديو" required>
            <button class="btn btn-default btn-sm">ضيف</button>
          <?= form_close(); ?>
        </div></div>
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">جرّب الواتساب</h5>
          <?= form_open(admin_url('dse_studio/test_wa'), ['class' => 'form-inline']); ?>
            <input name="to" class="form-control input-sm" dir="ltr" placeholder="01xxxxxxxxx" required>
            <button class="btn btn-default btn-sm">ابعت رسالة تجربة</button>
          <?= form_close(); ?>
          <p class="small text-muted mtop5">بتتبعت على طول (حتى لو الإرسال مقفول) — علشان تتأكد إن الربط مع Evolution شغال.</p>
        </div></div>
        <div class="panel_s"><div class="panel-body small">
          <b>مهم:</b> الرسايل والتنبيهات بتشتغل مع الـ cron بتاع Perfex — اتأكد إنه متظبط على السيرفر (Setup → Settings → Cron Job)، وإن التوقيت Africa/Cairo.
        </div></div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body></html>
