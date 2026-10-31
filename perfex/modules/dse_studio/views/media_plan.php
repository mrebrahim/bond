<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$goals    = dse_content_goals();
$pillars  = dse_content_pillars();
$statuses = dse_content_statuses();
$ptypes   = dse_post_types();
$g        = $plan ? $goals[$plan['goal']] : $goals['sales'];
$prev     = date('Y-m', strtotime($month . '-01 -1 month'));
$next     = date('Y-m', strtotime($month . '-01 +1 month'));
$base     = admin_url('dse_studio/media_plan/' . (int) $client['userid']);
$cid      = (int) $client['userid'];
$self     = $base . '?month=' . $month; // form_open($self) في CI بيروح للصفحة الرئيسية — لازم الـ URL كامل
$item_form = function ($it) use ($plan, $pillars, $statuses, $ptypes, $month, $self) {
    $new = !$it;
    $it  = $it ?: ['id' => '', 'publish_date' => date('Y-m') === $month ? date('Y-m-d') : $month . '-01', 'type' => 'reel', 'pillar' => 'educational', 'status' => 'idea', 'topic' => '', 'hook' => '', 'cta' => '', 'script' => '', 'post_url' => '', 'notes' => ''];
    ob_start(); ?>
    <?= form_open($self, ['class' => 'dse-item-form']); ?>
      <input type="hidden" name="action" value="item"><input type="hidden" name="id" value="<?= html_escape((string) $it['id']); ?>">
      <div class="row">
        <div class="col-xs-6 col-sm-3 form-group"><label>تاريخ النشر</label><input type="date" name="publish_date" value="<?= html_escape($it['publish_date']); ?>" min="<?= $month; ?>-01" max="<?= date('Y-m-t', strtotime($month . '-01')); ?>" class="form-control" required></div>
        <div class="col-xs-6 col-sm-3 form-group"><label>النوع</label><select name="type" class="form-control"><?php foreach ($ptypes as $k => $t) { ?><option value="<?= $k; ?>" <?= $it['type'] === $k ? 'selected' : ''; ?>><?= $t['label']; ?></option><?php } ?></select></div>
        <div class="col-xs-6 col-sm-3 form-group"><label>العمود</label><select name="pillar" class="form-control"><?php foreach ($pillars as $k => $l) { ?><option value="<?= $k; ?>" <?= $it['pillar'] === $k ? 'selected' : ''; ?>><?= $l; ?></option><?php } ?></select></div>
        <div class="col-xs-6 col-sm-3 form-group"><label>الحالة</label><select name="status" class="form-control"><?php foreach ($statuses as $k => $l) { ?><option value="<?= $k; ?>" <?= $it['status'] === $k ? 'selected' : ''; ?>><?= $l[0]; ?></option><?php } ?></select></div>
        <div class="col-xs-12 form-group"><label>الموضوع</label><input name="topic" value="<?= html_escape($it['topic']); ?>" class="form-control" placeholder="مثلًا: 3 غلطات بتعملها وإنت بتغسل سنانك" <?= $new ? 'required' : ''; ?>></div>
        <div class="col-xs-12 col-sm-6 form-group"><label>الهوك (أول 3 ثواني)</label><input name="hook" value="<?= html_escape((string) $it['hook']); ?>" class="form-control" placeholder="إنت بتغسل سنانك غلط من سنين…"></div>
        <div class="col-xs-12 col-sm-6 form-group"><label>الـ CTA (فاضي = CTA الشهر)</label><input name="cta" value="<?= html_escape((string) $it['cta']); ?>" class="form-control" placeholder="<?= html_escape((string) ($plan['cta'] ?? '')); ?>"></div>
        <?php if (!$new) { ?>
        <div class="col-xs-12 form-group"><label>السكربت <span class="small text-muted dse-wc"></span></label><textarea name="script" class="form-control dse-script" rows="8" placeholder="الهوك ← المشكلة ← المعلومة / الحل ← الدليل ← الـ CTA"><?= html_escape((string) $it['script']); ?></textarea></div>
        <div class="col-xs-12 col-sm-8 form-group"><label>لينك البوست (لما ينزل)</label><input name="post_url" value="<?= html_escape((string) $it['post_url']); ?>" class="form-control" dir="ltr" placeholder="https://..."></div>
        <div class="col-xs-12 col-sm-4 form-group"><label>ملاحظات</label><input name="notes" value="<?= html_escape((string) $it['notes']); ?>" class="form-control"></div>
        <?php } ?>
      </div>
      <button class="btn btn-primary btn-sm"><?= $new ? '➕ ضيف الموضوع' : 'احفظ'; ?></button>
    <?= form_close(); ?>
    <?php return ob_get_clean();
};
?>
<div id="wrapper"><div class="content dse-media">
  <div class="panel_s"><div class="panel-body">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
      <h4 class="tw-font-semibold" style="margin:0">📅 كالندر <?= html_escape($client['company']); ?> — <?= html_escape($month); ?></h4>
      <div>
        <select class="form-control input-sm" style="display:inline-block;width:auto" onchange="location.href='<?= admin_url('dse_studio/media_plan/'); ?>'+this.value+'?month=<?= $month; ?>'">
          <?php foreach ($clients as $c) { ?><option value="<?= (int) $c['clientid']; ?>" <?= (int) $c['clientid'] === $cid ? 'selected' : ''; ?>><?= html_escape($c['company']); ?></option><?php } ?>
        </select>
        <a class="btn btn-default btn-sm" href="<?= $base; ?>?month=<?= $prev; ?>">→ <?= $prev; ?></a>
        <a class="btn btn-default btn-sm" href="<?= $base; ?>?month=<?= $next; ?>"><?= $next; ?> ←</a>
        <a class="btn btn-link btn-sm" href="<?= admin_url('dse_studio/media?month=' . $month); ?>">كل العملاء</a>
      </div>
    </div>
  </div></div>

  <div class="row">
    <!-- هدف الشهر -->
    <div class="col-md-5">
      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">🎯 هدف الشهر</h5>
        <?= form_open($self); ?>
          <input type="hidden" name="action" value="plan">
          <div class="form-group"><label>الهدف (واحد بس — كل المحتوى والـ CTA بيخدموه)</label>
            <select name="goal" class="form-control"><?php foreach ($goals as $k => $x) { ?><option value="<?= $k; ?>" <?= ($plan['goal'] ?? 'sales') === $k ? 'selected' : ''; ?>><?= $x['label']; ?></option><?php } ?></select></div>
          <div class="row">
            <div class="col-xs-6 form-group"><label>المستهدف (<?= $g['kpi']; ?>)</label><input type="number" min="0" name="target" value="<?= html_escape((string) ($plan['target'] ?? '')); ?>" class="form-control" placeholder="30"></div>
            <div class="col-xs-6 form-group"><label>اللي اتحقق (آخر الشهر)</label><input type="number" min="0" name="result" value="<?= html_escape((string) ($plan['result'] ?? '')); ?>" class="form-control"></div>
          </div>
          <div class="form-group"><label>الـ CTA الأساسي للشهر</label><input name="cta" value="<?= html_escape((string) ($plan['cta'] ?? $g['cta'])); ?>" class="form-control dse-cta">
            <div class="mtop5"><?php foreach ($g['examples'] as $ex) { ?><button type="button" class="btn btn-default btn-xs mbot5 dse-cta-ex"><?= html_escape($ex); ?></button> <?php } ?></div></div>
          <div class="form-group"><label>لينك الحجز / الواتساب / الصفحة</label><input name="cta_link" value="<?= html_escape((string) ($plan['cta_link'] ?? '')); ?>" class="form-control" dir="ltr" placeholder="https://wa.me/20…"></div>
          <div class="form-group"><label>ملاحظات الشهر (عرض، مناسبة، رسالة أساسية…)</label><textarea name="notes" class="form-control" rows="2"><?= html_escape((string) ($plan['notes'] ?? '')); ?></textarea></div>
          <div class="form-group"><label>تعليق على النتيجة</label><input name="result_note" value="<?= html_escape((string) ($plan['result_note'] ?? '')); ?>" class="form-control" placeholder="أحسن 3 مواضيع كانوا … · المحتوى اللي بيبيع …"></div>
          <button class="btn btn-primary btn-sm">احفظ الهدف</button>
          <?php if ($summary && $summary['achieved'] !== null) { ?><span class="label label-<?= $summary['achieved'] >= 100 ? 'success' : 'warning'; ?>" style="font-size:13px">وصلنا <?= (int) $summary['achieved']; ?>% من الهدف</span><?php } ?>
        <?= form_close(); ?>
      </div></div>

      <?php if ($plan) { ?>
      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0">📊 توزيع المحتوى (المقترح لهدف "<?= $g['label']; ?>")</h5>
        <?php foreach ($summary['mix'] as $p => $x) { ?>
          <div class="small"><?= $pillars[$p]; ?> — <?= (int) $x['count']; ?> موضوع · <b><?= (int) $x['have']; ?>%</b> <span class="text-muted">(المقترح <?= (int) $x['want']; ?>%)</span></div>
          <div class="progress" style="height:6px;margin:2px 0 8px"><div class="progress-bar <?= abs($x['have'] - $x['want']) > 15 ? 'progress-bar-warning' : 'progress-bar-success'; ?>" style="width:<?= min(100, (int) $x['have']); ?>%"></div></div>
        <?php } ?>
        <div class="small">
          <?php foreach ($statuses as $k => $l) { if (!empty($summary['by']['status'][$k])) { ?><span class="label label-<?= $l[1]; ?>"><?= $l[0]; ?> <?= (int) $summary['by']['status'][$k]; ?></span> <?php } } ?>
        </div>
      </div></div>
      <?php } ?>

      <div class="panel_s"><div class="panel-body small">
        <h5 class="bold" style="margin-top:0">💡 نصايح الكالندر</h5>
        <ul style="padding-inline-start:18px;margin:0">
          <li>الكالندر يخلص ويتوافق عليه قبل ميعاد التصوير — صوّر مواضيع الشهر كله في جلسة أو اتنين.</li>
          <li>هدف واحد ورقم واحد في الشهر. آخر الشهر اكتب اللي اتحقق، وكرّر أكتر مواضيع جابت نتيجة.</li>
          <li>كل فيديو بـ CTA واحد بس، وكله بيصب في هدف الشهر.</li>
          <li>حلقة البودكاست أو فيديو اليوتيوب الطويل اتقطّعه ريلز (Repurpose) — موضوع واحد = 3 بوستات.</li>
          <li>العميل المشغول: ابعتله السكربت يسجّل عليه فويس، ونعمل عليه B-roll.</li>
        </ul>
      </div></div>
    </div>

    <!-- المواضيع -->
    <div class="col-md-7">
      <?php if (!$plan) { ?>
        <div class="alert alert-info">ابدأ بهدف الشهر واحفظه — وبعدها املا المواضيع من مواعيد التنزيل.</div>
      <?php } else { ?>
      <div class="panel_s"><div class="panel-body">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
          <h5 class="bold" style="margin:0">🗓 مواضيع الشهر (<?= (int) $summary['total']; ?>)<?= $summary['no_topic'] ? ' · <span class="text-danger">' . (int) $summary['no_topic'] . ' من غير موضوع</span>' : ''; ?><?= $summary['no_script'] ? ' · <span class="text-warning">' . (int) $summary['no_script'] . ' من غير سكربت</span>' : ''; ?></h5>
          <?= form_open($self, ['class' => 'inline-block']); ?><input type="hidden" name="action" value="fill"><button class="btn btn-success btn-sm" <?= $slots ? '' : 'disabled title="حط مواعيد التنزيل في بروفايل العميل"'; ?>>📅 املا الشهر من مواعيد التنزيل</button><?= form_close(); ?>
        </div>
        <?php if (!$slots) { ?><p class="small text-warning mtop5">العميل مالوش مواعيد تنزيل — حطها من <a href="<?= admin_url('dse_studio/client/' . $cid . '#publish-slots'); ?>">بروفايله</a>، أو ضيف المواضيع بإيدك تحت.</p><?php } ?>
      </div></div>

      <?php foreach ($items as $it) { $st = $statuses[$it['status']]; ?>
        <details class="panel_s dse-item" id="item-<?= (int) $it['id']; ?>" <?= $open === (int) $it['id'] ? 'open' : ''; ?>>
          <summary class="panel-body" style="cursor:pointer;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <b style="min-width:90px"><?= dse_day($it['publish_date']); ?></b>
            <span><?= $ptypes[$it['type']]['label'] ?? $it['type']; ?></span>
            <span class="small text-muted"><?= $pillars[$it['pillar']] ?? ''; ?></span>
            <span style="flex:1 1 200px"><?= trim($it['topic']) !== '' ? html_escape($it['topic']) : '<span class="text-danger">✏️ اكتب الموضوع</span>'; ?></span>
            <span class="label label-<?= $st[1]; ?>"><?= $st[0]; ?></span>
          </summary>
          <div class="panel-body" style="border-top:1px solid #eee">
            <div class="row">
              <div class="col-md-8"><?= $item_form($it); ?>
                <?= form_open($self, ['class' => 'inline-block mtop5', 'onsubmit' => "return confirm('تمسح الموضوع ده؟')"]); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $it['id']; ?>"><button class="btn btn-link btn-xs text-danger">🗑 امسح الموضوع</button><?= form_close(); ?>
              </div>
              <div class="col-md-4 small">
                <b>📋 مانوال السكربت</b>
                <ul style="padding-inline-start:16px;margin-top:4px">
                  <?php foreach (dse_script_manual($plan['goal']) as $rule) { ?><li style="margin-bottom:4px"><?= html_escape($rule); ?></li><?php } ?>
                </ul>
                <?php if ($plan['cta_link']) { ?><div class="text-muted">لينك الـ CTA: <span dir="ltr"><?= html_escape($plan['cta_link']); ?></span></div><?php } ?>
              </div>
            </div>
          </div>
        </details>
      <?php } ?>

      <details class="panel_s" <?= $items ? '' : 'open'; ?>>
        <summary class="panel-body bold" style="cursor:pointer">➕ ضيف موضوع</summary>
        <div class="panel-body" style="border-top:1px solid #eee"><?= $item_form(null); ?></div>
      </details>
      <?php } ?>
    </div>
  </div>
</div></div>
<?php init_tail(); ?>
<script>
// أمثلة الـ CTA بتتحط في الخانة
document.querySelectorAll('.dse-cta-ex').forEach(function (b) { b.addEventListener('click', function () { document.querySelector('.dse-cta').value = b.textContent; }); });
// عدد كلمات السكربت ومدته التقريبية (2.5 كلمة في الثانية)
document.querySelectorAll('.dse-script').forEach(function (ta) {
  var out = ta.closest('.form-group').querySelector('.dse-wc');
  var f = function () { var w = ta.value.trim() ? ta.value.trim().split(/\s+/).length : 0; out.textContent = w ? '· ' + w + ' كلمة ≈ ' + Math.round(w / 2.5) + ' ثانية' : ''; };
  ta.addEventListener('input', f); f();
});
// افتح الموضوع اللي اتحفظ
if (location.hash.indexOf('#item-') === 0) { var d = document.querySelector(location.hash); if (d) { d.open = true; d.scrollIntoView(); } }
</script>
</body></html>
