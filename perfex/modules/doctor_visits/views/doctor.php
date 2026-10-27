<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="dv">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>
  <?php $this->load->view('doctor_visits/_card', ['d' => $doc, 'back' => 'doctor_visits/doctor/' . $doc['lead_id']]); ?>
  <?php if ($doc['stage'] == 9) { ?><div class="dv-card" style="background:#f0fdf4;border-color:#86efac">🎉 <?= _l('dv_convert_hint'); ?> — <a href="<?= admin_url('leads/index/' . $doc['lead_id']); ?>"><?= _l('dv_open_lead'); ?></a></div><?php } ?>

  <div class="row">
    <div class="col-md-7">
      <div class="dv-card">
        <h5 class="bold">🕒 <?= _l('dv_timeline'); ?> <span class="dv-muted">(<?= count($timeline); ?> <?= _l('dv_touches'); ?>)</span></h5>
        <?php foreach ($timeline as $v) { $late = strtotime($v['logged_at']) - strtotime($v['visited_at']) > 86400; ?>
          <div style="border-block-end:1px solid #f1f5f9;padding-block:6px">
            <div class="dv-row"><b><?= dv_outcome_label($v['outcome']); ?></b><span class="dv-muted"><?= dv_date($v['visited_at'], true); ?><?= $late ? ' · <span class="text-warning">' . _l('dv_late') . '</span>' : ''; ?></span></div>
            <div class="dv-muted"><?= html_escape($v['staff_name']); ?><?= $v['stage_after'] > $v['stage_before'] ? ' · ⬆️ ' . dv_stage_label($v['stage_after']) : ''; ?><?= $v['lost_reason'] ? ' · ' . dv_lost_reason_label($v['lost_reason']) : ''; ?></div>
            <?php if ($v['note']) { ?><div><?= nl2br(html_escape($v['note'])); ?></div><?php } ?>
            <?php if ($v['next_action']) { ?><div class="dv-muted">📌 <?= html_escape($v['next_action']); ?> — <?= dv_date($v['next_action_at'], true); ?></div><?php } ?>
          </div>
        <?php } ?>
      </div>
    </div>

    <div class="col-md-5">
      <div class="dv-card">
        <h5 class="bold">📱 <?= _l('dv_qr'); ?> <code dir="ltr"><?= html_escape($doc['qr_code']); ?></code></h5>
        <div id="dv-qr" style="display:flex;justify-content:center;margin-block:8px"></div>
        <div class="dv-muted"><?= _l('dv_qr_help'); ?></div>
        <div class="dv-row" style="margin-block-start:8px;justify-content:flex-start">
          <button type="button" class="dv-btn dv-btn-sm" onclick="navigator.clipboard.writeText(<?= html_escape(json_encode($qr_link)); ?>);this.textContent='✓'"><?= _l('dv_copy_link'); ?></button>
          <button type="button" class="dv-btn dv-btn-sm" onclick="dvPng()"><?= _l('dv_download_png'); ?></button>
        </div>
      </div>

      <div class="dv-card">
        <h5 class="bold">💬 <?= _l('dv_consent'); ?></h5>
        <?php if ($doc['wa_consent']) { ?>
          <div>✅ <?= sprintf(_l('dv_consent_yes'), dv_date($doc['wa_consent_at']), html_escape($doc['wa_consent_source'])); ?></div>
        <?php } else { ?>
          <div class="text-warning"><?= _l('dv_consent_no'); ?></div>
          <?php if ($dv_can['create']) { ?>
            <?= form_open(admin_url('doctor_visits/consent/' . $doc['lead_id'])); ?><input type="hidden" name="on" value="1"><button class="dv-btn dv-btn-sm" style="margin-block-start:6px">✔ <?= _l('dv_consent_today'); ?></button><?= form_close(); ?>
          <?php } ?>
        <?php } ?>
      </div>

      <div class="dv-card">
        <h5 class="bold">📝 <?= _l('dv_profile'); ?></h5>
        <?= form_open(admin_url('doctor_visits/save_doctor/' . $doc['lead_id'])); ?>
          <?php
          $f = function ($name, $label, $value, $type = 'text', $ltr = false) use ($dv_can) {
              echo '<label class="dv-muted" style="margin:6px 0 2px;display:block">' . $label . '</label><input ' . ($dv_can['edit'] ? '' : 'disabled ') . 'type="' . $type . '" name="' . $name . '" value="' . html_escape((string) $value) . '" class="dv-in"' . ($ltr ? ' dir="ltr"' : '') . '>';
          };
          $f('name', _l('dv_doctor_name'), $doc['name']);
          $f('specialty', _l('dv_specialty'), $doc['specialty']);
          ?>
          <label class="dv-muted" style="margin:6px 0 2px;display:block"><?= _l('dv_building'); ?></label>
          <select name="building_id" class="dv-in" <?= $dv_can['edit'] ? '' : 'disabled'; ?>><?php foreach ($buildings as $b) { ?><option value="<?= (int) $b['id']; ?>" <?= (int) $b['id'] === (int) $doc['building_id'] ? 'selected' : ''; ?>><?= html_escape($b['name']); ?></option><?php } ?></select>
          <?php
          $f('floor', _l('dv_floor'), $doc['floor']);
          $f('phone', _l('dv_phone'), $doc['phonenumber'], 'tel', true);
          $f('clinic_whatsapp', _l('dv_clinic_whatsapp'), $doc['clinic_whatsapp'], 'tel', true);
          $f('clinic_hours', _l('dv_clinic_hours'), $doc['clinic_hours']);
          $f('gatekeeper_name', _l('dv_gatekeeper'), $doc['gatekeeper_name']);
          $f('best_time', _l('dv_best_time'), $doc['best_time']);
          $f('digital_score', _l('dv_digital_score') . ' — ' . _l('dv_grade') . ' ' . $doc['grade'], $doc['digital_score'], 'number');
          $f('facebook_url', _l('dv_facebook'), $doc['facebook_url'], 'url', true);
          $f('instagram_url', _l('dv_instagram'), $doc['instagram_url'], 'url', true);
          $f('gmaps_url', _l('dv_gmaps'), $doc['gmaps_url'], 'url', true);
          $f('audit_link', _l('dv_audit_link'), $doc['audit_link'], 'url', true);
          $f('key_insight', _l('dv_key_insight'), $doc['key_insight']);
          $f('demo_link', _l('dv_demo_link'), $doc['demo_link'], 'url', true);
          $f('offer_link', _l('dv_offer_link'), $doc['offer_link'], 'url', true);
          $f('meeting_at', _l('dv_meeting_at'), $doc['meeting_at'] ? date('Y-m-d\TH:i', strtotime($doc['meeting_at'])) : '', 'datetime-local');
          $f('deal_value', _l('dv_deal_value'), $doc['deal_value'], 'number');
          ?>
          <?php if ($dv_can['all']) { ?>
            <label class="dv-muted" style="margin:6px 0 2px;display:block"><?= _l('dv_assigned'); ?></label>
            <select name="assigned_staff_id" class="dv-in"><?php foreach ($staff as $s) { ?><option value="<?= (int) $s['staffid']; ?>" <?= (int) $s['staffid'] === (int) $doc['assigned_staff_id'] ? 'selected' : ''; ?>><?= html_escape($s['firstname'] . ' ' . $s['lastname']); ?></option><?php } ?></select>
          <?php } ?>
          <?php if ($dv_can['edit']) { ?><button class="dv-btn dv-btn-primary" style="width:100%;margin-block-start:10px"><?= _l('dv_save'); ?></button><?php } ?>
        <?= form_close(); ?>
        <a class="dv-muted" href="<?= admin_url('leads/index/' . $doc['lead_id']); ?>"><?= _l('dv_open_lead'); ?> ↗</a>
      </div>
    </div>
  </div>
  <?php $this->load->view('doctor_visits/_feedback'); ?>
</div></div></div>
<?php init_tail(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('dv-qr'), {text: <?= json_encode($qr_link); ?>, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M});
function dvPng(){var c=document.querySelector('#dv-qr canvas');var a=document.createElement('a');a.href=c.toDataURL('image/png');a.download=<?= json_encode($doc['qr_code'] . '.png'); ?>;a.click()}
</script>
</body></html>
