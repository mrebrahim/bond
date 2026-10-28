<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.dse-msg .dse-grid{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start}
.dse-msg textarea{width:100%;box-sizing:border-box;font-family:inherit;font-size:15px;line-height:1.7;min-height:150px;direction:rtl}
.dse-msg .wa-bubble{background:#dcf8c6;border-radius:10px 0 10px 10px;padding:10px 12px;white-space:pre-wrap;font-size:14px;line-height:1.7;box-shadow:0 1px 1px rgba(0,0,0,.08);word-break:break-word}
.dse-msg .wa-chat{background:#ece5dd;border-radius:10px;padding:12px}
.dse-msg .var{display:inline-block;margin:2px;padding:2px 8px;border-radius:6px;background:#eef2ff;color:#3730a3;font-size:12px;cursor:pointer;border:0}
.dse-msg .var:hover{background:#e0e7ff}
</style>
<div id="wrapper"><div class="content dse-msg">
  <div class="panel_s"><div class="panel-body">
    <h4 class="tw-font-semibold" style="margin-top:0">💬 رسايل الواتساب للعميل</h4>
    <p class="text-muted" style="margin:0">عدّل النص اللي بيروح جروب العميل. اضغط على أي متغير يتحط مكان المؤشر — بيتملا لوحده وقت الإرسال، والسطر اللي متغيراته فاضية بيتشال. التعديل بيمشي على الرسايل الجاية بس.</p>
  </div></div>

  <?php foreach (['auto' => '⚡ بتتبعت لوحدها', 'manual' => '👆 بزرار من صفحة العميل'] as $group => $gname) { ?>
    <h5 class="bold" style="margin:18px 4px 8px"><?= $gname; ?></h5>
    <?php foreach ($list as $k => $m) { if ($m['auto'] !== ($group === 'auto')) { continue; } ?>
      <div class="panel_s" id="m-<?= $k; ?>"><div class="panel-body">
        <div class="dse-grid">
          <div style="flex:1 1 380px;min-width:0">
            <h5 class="bold" style="margin-top:0"><?= html_escape($m['label']); ?>
              <?= $m['edited'] ? '<span class="label label-info">متعدّلة</span>' : '<span class="label label-default">الأصلي</span>'; ?></h5>
            <?= form_open(admin_url('dse_studio/messages')); ?>
              <input type="hidden" name="key" value="<?= $k; ?>">
              <textarea name="body" class="form-control" data-key="<?= $k; ?>"><?= html_escape($m['body']); ?></textarea>
              <div style="margin:6px 0">
                <?php foreach ($m['vars'] as $v => $desc) { ?><button type="button" class="var" data-var="{<?= $v; ?>}" title="<?= html_escape($desc); ?>">{<?= $v; ?>} <small><?= html_escape($desc); ?></small></button><?php } ?>
              </div>
              <button class="btn btn-primary">احفظ</button>
              <?php if ($m['edited']) { ?><button class="btn btn-default" name="reset" value="1" onclick="return confirm('ترجّع النص الأصلي؟')">رجّع الأصلي</button><?php } ?>
            <?= form_close(); ?>
          </div>
          <div style="flex:1 1 280px;min-width:0">
            <div class="text-muted small" style="margin-bottom:4px">معاينة (بيانات تجربة)</div>
            <div class="wa-chat"><div class="wa-bubble" data-preview="<?= $k; ?>"><?= html_escape($m['preview']); ?></div></div>
          </div>
        </div>
      </div></div>
    <?php } ?>
  <?php } ?>
</div></div>
<?php init_tail(); ?>
<script>
// المتغير يتحط مكان المؤشر
document.querySelectorAll('.dse-msg .var').forEach(function (b) {
  b.addEventListener('click', function () {
    var ta = b.closest('form').querySelector('textarea'), v = b.dataset.var, s = ta.selectionStart || ta.value.length;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(ta.selectionEnd || s);
    ta.focus(); ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.dispatchEvent(new Event('input'));
  });
});
// معاينة لايف بنفس قواعد السيرفر (السطر اللي متغيراته فاضية بيتشال)
var DSE_SAMPLE = <?= json_encode(array_map(function ($k) { return dse_wa_sample_vars($k); }, array_combine(array_keys($list), array_keys($list))), JSON_UNESCAPED_UNICODE); ?>;
document.querySelectorAll('.dse-msg textarea[data-key]').forEach(function (ta) {
  ta.addEventListener('input', function () {
    var vars = DSE_SAMPLE[ta.dataset.key] || {}, out = [];
    ta.value.split(/\r?\n/).forEach(function (line) {
      var names = (line.match(/\{([a-z_]+)\}/g) || []).map(function (x) { return x.slice(1, -1); });
      if (names.length && !names.some(function (n) { return String(vars[n] || '').trim() !== ''; })) { return; }
      out.push(line.replace(/\{([a-z_]+)\}/g, function (_, n) { return vars[n] || ''; }).replace(/\s*·\s*$/, ''));
    });
    document.querySelector('[data-preview="' + ta.dataset.key + '"]').textContent = out.join('\n').replace(/\n{3,}/g, '\n\n').trim();
  });
});
</script>
</body></html>
