<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-5">
        <div class="panel_s"><div class="panel-body">
          <h4 class="tw-font-semibold">🎬 نزل على السوشيال</h4>
          <p class="text-muted">حط لينك البوست — بيتبعت لجروب العميل على الواتساب ومعاه لينك التقييم والرصيد الباقي.</p>
          <?= form_open(admin_url('dse_studio/publish')); ?>
            <input type="hidden" name="back" value="publishing">
            <div class="form-group">
              <label>العميل</label>
              <select name="clientid" class="form-control selectpicker" data-live-search="true" required>
                <option value="">اختار العميل…</option>
                <?php foreach ($clients as $c) { ?>
                  <option value="<?= (int) $c['userid']; ?>" <?= $selected === (int) $c['userid'] ? 'selected' : ''; ?>
                    data-subtext="<?= $c['wa_group_jid'] ? '' : '⚠️ مفيش جروب'; ?>"><?= html_escape($c['company']); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group">
              <label>النوع</label>
              <select name="type" class="form-control">
                <?php foreach (dse_post_types() as $t => $pt) { ?><option value="<?= $t; ?>"><?= $pt['label']; ?><?= $t === 'design' ? ' (بيتخصم من الرصيد)' : ''; ?></option><?php } ?>
              </select>
            </div>
            <div class="form-group">
              <label>لينك البوست</label>
              <input name="post_url" class="form-control" dir="ltr" placeholder="https://www.instagram.com/p/..." required>
            </div>
            <div class="form-group">
              <label>عنوان (اختياري)</label>
              <input name="title" class="form-control">
            </div>
            <button class="btn btn-primary btn-block">ابعت لجروب العميل</button>
          <?= form_close(); ?>
        </div></div>
      </div>

      <div class="col-md-7">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">🗓 لازم ينزل النهارده <span class="text-muted small">(من مواعيد التنزيل في بروفايل كل عميل)</span></h5>
          <?php if (empty($due)) { ?><p class="text-muted">مفيش مواعيد تنزيل النهارده</p><?php } else { ?>
          <table class="table table-condensed"><tbody>
            <?php foreach ($due as $d) { ?>
              <tr class="<?= $d['done'] ? 'success' : ''; ?>">
                <td><?= dse_post_types()[$d['type']]['label'] ?? $d['type']; ?></td>
                <td><a href="<?= admin_url('dse_studio/publishing?client=' . (int) $d['clientid']); ?>"><b><?= html_escape($d['company']); ?></b></a></td>
                <td><?= $d['done'] ? '<span class="label label-success">✅ نزل</span>' : '<span class="label label-warning">⏳ لسه</span>'; ?></td>
              </tr>
            <?php } ?>
          </tbody></table>
          <?php } ?>
        </div></div>
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold">آخر اللي نزل</h5>
          <?php if (!$recent) { ?><p class="text-muted">لسه مفيش</p><?php } ?>
          <table class="table table-condensed">
            <tbody>
            <?php foreach ($recent as $p) { ?>
              <tr>
                <td><?= ['design' => '🎨', 'youtube' => '📺', 'podcast' => '🎙'][$p['type']] ?? '🎬'; ?></td>
                <td><b><?= html_escape($p['company']); ?></b><?= $p['title'] ? '<div class="small text-muted">' . html_escape($p['title']) . '</div>' : ''; ?></td>
                <td><a href="<?= html_escape($p['post_url']); ?>" target="_blank" dir="ltr">اللينك</a></td>
                <td class="small"><?= dse_day($p['published_at']) . ' ' . dse_time($p['published_at']); ?></td>
                <td>
                  <?php $st = $p['wa_status']; ?>
                  <span class="label label-<?= ['sent' => 'success', 'queued' => 'info', 'sending' => 'info', 'failed' => 'danger'][$st] ?? 'warning'; ?>">
                    <?= ['sent' => 'وصل الجروب', 'queued' => 'مستني', 'sending' => 'بيتبعت', 'failed' => 'فشل'][$st] ?? 'ماتبعتش (مفيش جروب)'; ?>
                  </span>
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
