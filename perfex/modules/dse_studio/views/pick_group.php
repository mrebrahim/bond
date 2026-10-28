<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content">
  <div class="panel_s"><div class="panel-body">
    <h4 class="tw-font-semibold" style="margin-top:0">📋 اختار جروب <?= html_escape($client['company']); ?> <span class="text-muted"><?= html_escape($client['code']); ?></span></h4>
    <p class="text-muted">دي كل الجروبات اللي رقم السيستم عضو فيها. لو جروب العميل مش هنا: ضيف رقم السيستم للجروب من الموبايل (أو خليه أدمن يوافق على الطلب) واعمل Refresh.</p>
    <input type="search" class="form-control" placeholder="🔍 دوّر باسم الجروب…" oninput="var q=this.value.trim();document.querySelectorAll('.dse-g').forEach(function(r){r.style.display=r.dataset.s.indexOf(q)>-1?'':'none'})" style="max-width:360px;margin-bottom:10px">
    <?php if (!$groups) { ?><p class="text-warning">رقم السيستم مش في أي جروب لسه.</p><?php } ?>
    <table class="table table-condensed">
      <tbody>
      <?php foreach ($groups as $g) { $mine = $g['linked'] && (int) $g['linked']['clientid'] === (int) $client['userid']; ?>
        <tr class="dse-g" data-s="<?= html_escape($g['subject']); ?>">
          <td><b><?= html_escape($g['subject']); ?></b><?= $g['size'] ? ' <span class="text-muted small">· ' . (int) $g['size'] . ' عضو</span>' : ''; ?>
            <?php if ($g['linked'] && !$mine) { ?><div class="small text-warning">مربوط بـ <?= html_escape($g['linked']['company']); ?></div><?php } ?></td>
          <td class="text-left" style="width:120px">
            <?php if ($mine) { ?><span class="label label-success">مربوط بالعميل ده</span>
            <?php } elseif (!$g['linked']) { ?>
              <?= form_open(admin_url('dse_studio/wa/' . (int) $client['userid'] . '/link_group')); ?>
                <input type="hidden" name="link" value="<?= html_escape($g['id']); ?>"><button class="btn btn-success btn-xs">اربطه بالعميل</button>
              <?= form_close(); ?>
            <?php } ?>
          </td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    <a href="<?= admin_url('dse_studio/client/' . (int) $client['userid']); ?>">← رجوع لصفحة العميل</a>
  </div></div>
</div></div>
<?php init_tail(); ?>
</body></html>
