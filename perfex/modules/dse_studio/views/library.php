<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$ptypes = dse_post_types();
$sps    = dse_specialties();
$url    = function ($over) use ($specialty, $type, $q) {
    $p = array_filter(array_merge(['specialty' => $specialty, 'type' => $type, 'q' => $q], $over), 'strlen');
    return admin_url('dse_studio/library') . ($p ? '?' . http_build_query($p) : '');
};
$total = array_sum($counts);
?>
<div id="wrapper"><div class="content">
  <div class="panel_s"><div class="panel-body">
    <h4 class="tw-font-semibold" style="margin-top:0">🔥 مكتبة المواضيع والسكربتات اللي عملت Viral</h4>
    <p class="text-muted" style="margin:0">أي موضوع في كالندر المحتوى تعلّمه "🔥 Viral" بيدخل هنا بتخصص العميل. قبل ما تكتب مواضيع شهر جديد، ابدأ من هنا — ومن كالندر العميل تقدر تاخد الموضوع بزرار.</p>
  </div></div>

  <div class="panel_s"><div class="panel-body">
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <a class="btn btn-sm <?= $specialty === '' ? 'btn-primary' : 'btn-default'; ?>" href="<?= $url(['specialty' => '']); ?>">الكل (<?= (int) $total; ?>)</a>
      <?php foreach ($sps as $k => $l) { $n = (int) ($counts[$k] ?? 0); ?>
        <a class="btn btn-sm <?= $specialty === $k ? 'btn-primary' : 'btn-default'; ?>" style="<?= $n || $specialty === $k ? '' : 'opacity:.55'; ?>" href="<?= $url(['specialty' => $k]); ?>"><?= html_escape($l); ?> (<?= $n; ?>)</a>
      <?php } ?>
      <?php if (!empty($counts['none'])) { ?><a class="btn btn-sm <?= $specialty === 'none' ? 'btn-primary' : 'btn-default'; ?>" href="<?= $url(['specialty' => 'none']); ?>">من غير تخصص (<?= (int) $counts['none']; ?>)</a><?php } ?>
    </div>
    <form method="get" action="<?= admin_url('dse_studio/library'); ?>" class="form-inline mtop10">
      <input type="hidden" name="specialty" value="<?= html_escape($specialty); ?>">
      <select name="type" class="form-control input-sm"><option value="">كل الأنواع</option><?php foreach ($ptypes as $k => $t) { ?><option value="<?= $k; ?>" <?= $type === $k ? 'selected' : ''; ?>><?= $t['label']; ?></option><?php } ?></select>
      <input name="q" value="<?= html_escape($q); ?>" placeholder="دوّر في الموضوع / الهوك / السكربت" class="form-control input-sm" style="min-width:240px">
      <button class="btn btn-default btn-sm">دوّر</button>
    </form>
    <?php if (!empty($counts['none'])) { ?><p class="small text-warning mtop10">فيه <?= (int) $counts['none']; ?> موضوع viral لعملاء مالهمش تخصص — حط التخصص في بروفايل العميل عشان يتصنّفوا.</p><?php } ?>
  </div></div>

  <?php if (!$rows) { ?>
    <div class="panel_s"><div class="panel-body text-muted">مفيش مواضيع viral<?= $specialty !== '' && $specialty !== 'none' ? ' لـ ' . html_escape($sps[$specialty]) : ''; ?> لسه. من كالندر العميل افتح الموضوع اللي كسّر واختار "عمل Viral؟ ← 🔥 أيوه".</div></div>
  <?php } ?>
  <?php foreach ($rows as $r) { ?>
    <div class="panel_s"><div class="panel-body">
      <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <b style="font-size:15px;flex:1 1 260px"><?= html_escape($r['topic']); ?></b>
        <span class="label label-danger">🔥<?= $r['views'] ? ' ' . number_format((int) $r['views']) . ' مشاهدة' : ' Viral'; ?></span>
        <span class="label label-default"><?= $ptypes[$r['type']]['label'] ?? $r['type']; ?></span>
        <span class="label label-info"><?= html_escape($r['specialty_label'] ?: 'من غير تخصص'); ?></span>
      </div>
      <div class="small text-muted mtop5"><?= html_escape($r['company']); ?> · <?= dse_day($r['publish_date']); ?> · <?= dse_content_pillars()[$r['pillar']] ?? ''; ?>
        <?php if ($r['post_url']) { ?> · <a href="<?= html_escape($r['post_url']); ?>" target="_blank" rel="noopener">البوست</a><?php } ?>
        · <a href="<?= admin_url('dse_studio/media_plan/' . (int) $r['clientid'] . '?month=' . substr($r['publish_date'], 0, 7) . '#item-' . (int) $r['id']); ?>">افتحه في الكالندر</a></div>
      <?php if ($r['hook']) { ?><div class="mtop5">🎣 <b>الهوك:</b> <?= html_escape($r['hook']); ?></div><?php } ?>
      <?php if ($r['cta']) { ?><div class="small">📣 <b>CTA:</b> <?= html_escape($r['cta']); ?></div><?php } ?>
      <?php if ($r['script']) { ?>
        <details class="mtop5"><summary>📝 السكربت</summary>
          <div style="white-space:pre-wrap;background:#f8fafc;border-radius:6px;padding:10px;margin-top:6px" class="dse-lib-script"><?= html_escape($r['script']); ?></div>
          <button type="button" class="btn btn-default btn-xs mtop5" onclick="var t=this.previousElementSibling.textContent;navigator.clipboard&&navigator.clipboard.writeText(t);this.textContent='✅ اتنسخ'">📋 انسخ السكربت</button>
        </details>
      <?php } ?>
    </div></div>
  <?php } ?>
</div></div>
<?php init_tail(); ?>
</body></html>
