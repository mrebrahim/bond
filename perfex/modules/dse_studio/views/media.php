<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $prev = date('Y-m', strtotime($month . '-01 -1 month')); $next = date('Y-m', strtotime($month . '-01 +1 month')); $goals = dse_content_goals(); ?>
<div id="wrapper"><div class="content">
  <div class="panel_s"><div class="panel-body">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
      <h4 class="tw-font-semibold" style="margin:0">📅 كالندر المحتوى — <?= html_escape($month); ?></h4>
      <div><a class="btn btn-default btn-sm" href="?month=<?= $prev; ?>">→ <?= $prev; ?></a> <a class="btn btn-default btn-sm" href="?month=<?= date('Y-m'); ?>">الشهر ده</a> <a class="btn btn-default btn-sm" href="?month=<?= $next; ?>"><?= $next; ?> ←</a></div>
    </div>
    <p class="text-muted" style="margin:8px 0 0">لكل عميل: هدف واحد للشهر برقم نقيسه ← المواضيع بتواريخها (من مواعيد التنزيل) ← السكربت لكل موضوع بالـ CTA اللي بيخدم الهدف ← آخر الشهر نكتب النتيجة ونشوف وصلنا ولا لأ.</p>
  </div></div>

  <div class="panel_s"><div class="panel-body" style="overflow-x:auto">
    <?php if (!$rows) { ?><p class="text-muted">مفيش عملاء بباقات شغالة</p><?php } else { ?>
    <table class="table table-condensed">
      <thead><tr><th>العميل</th><th>هدف الشهر</th><th>المستهدف / اللي اتحقق</th><th>المواضيع</th><th>ناقص</th><th>نزل</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r) { $p = $r['plan']; $sm = $r['summary']; ?>
        <tr>
          <td><b><?= html_escape($r['company']); ?></b><?= $r['slots'] ? '' : '<div class="small text-warning">مفيش مواعيد تنزيل</div>'; ?></td>
          <td><?= $p ? $goals[$p['goal']]['label'] : '<span class="text-muted">لسه</span>'; ?></td>
          <td><?php if ($p && $p['target']) { ?><?= (int) $p['target']; ?> <?= $goals[$p['goal']]['kpi']; ?><?= $p['result'] !== null ? ' · اتحقق ' . (int) $p['result'] . ' <span class="label label-' . ($sm['achieved'] >= 100 ? 'success' : 'warning') . '">' . (int) $sm['achieved'] . '%</span>' : ''; ?><?php } else { echo '—'; } ?></td>
          <td><?= $sm ? (int) $sm['total'] : 0; ?></td>
          <td class="small"><?= $sm ? ($sm['no_topic'] ? '<span class="text-danger">' . (int) $sm['no_topic'] . ' من غير موضوع</span> ' : '') . ($sm['no_script'] ? '<span class="text-warning">' . (int) $sm['no_script'] . ' من غير سكربت</span>' : '') : ''; ?></td>
          <td><?= $sm ? (int) $sm['published'] . ' / ' . (int) $sm['total'] : ''; ?></td>
          <td><a class="btn btn-<?= $p ? 'default' : 'primary'; ?> btn-xs" href="<?= admin_url('dse_studio/media_plan/' . $r['clientid'] . '?month=' . $month); ?>"><?= $p ? 'افتح الكالندر' : '➕ اعمل كالندر الشهر'; ?></a></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    <?php } ?>
  </div></div>
</div></div>
<?php init_tail(); ?>
</body></html>
