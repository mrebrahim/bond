<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$doc  = $ctx['doctor'];
$sug  = $ctx['suggested'];
$back = $back ?: admin_url('doctor_visits/doctor/' . $doc['lead_id']);
$tpls = [];
foreach ($ctx['templates'] as $t) {
    [$text, $missing] = dv_fill_template($t['body'], $ctx['vars']);
    $tpls[$t['key']] = ['name' => $t['name'], 'text' => $text, 'missing' => $missing];
}
?>
<div id="wrapper"><div class="content"><div class="dv">
  <?php $this->load->view('doctor_visits/_style'); ?>
  <div class="dv-card">
    <div class="dv-row"><b>💬 <?= html_escape($doc['name']); ?></b><span class="dv-chip" style="background:<?= $doc['color']; ?>"><?= dv_stage_label($doc['stage']); ?></span></div>
    <div class="dv-muted" dir="ltr" style="text-align:end"><?= html_escape($ctx['phone'] ?: '—'); ?></div>
  </div>

  <?php if (!$ctx['phone']) { ?>
    <div class="dv-card text-danger"><?= _l('dv_wa_no_phone'); ?> — <a href="<?= admin_url('doctor_visits/doctor/' . $doc['lead_id']); ?>"><?= _l('dv_profile'); ?></a></div>
  <?php } else { ?>
    <?php if (!$ctx['consent']) { ?>
      <div class="dv-card" style="background:#fff7ed;border-color:#fdba74">
        ⚠️ <?= _l('dv_consent_no'); ?> — <?= _l('dv_wa_need_consent'); ?>
        <?= form_open(admin_url('doctor_visits/consent/' . $doc['lead_id'])); ?>
          <input type="hidden" name="on" value="1"><input type="hidden" name="back" value="<?= html_escape(current_url() . '?back=' . urlencode((string) $this->input->get('back'))); ?>">
          <button class="dv-btn" style="width:100%;margin-block-start:8px">✔ <?= _l('dv_consent_today'); ?></button>
        <?= form_close(); ?>
      </div>
    <?php } ?>

    <div class="dv-card">
      <select id="dv-tpl" class="dv-in" onchange="dvTpl(this.value)">
        <?php foreach ($tpls as $k => $t) { ?><option value="<?= html_escape($k); ?>" <?= $sug && $sug['key'] === $k ? 'selected' : ''; ?>><?= html_escape($t['name']); ?><?= $sug && $sug['key'] === $k ? ' — ' . _l('dv_wa_suggested') : ''; ?></option><?php } ?>
        <option value="__custom"><?= _l('dv_wa_custom'); ?></option>
      </select>
      <textarea id="dv-msg" class="dv-in" rows="7" style="margin-block-start:8px" oninput="dvCheck()"></textarea>
      <div id="dv-missing" class="text-danger small" style="margin-block-start:4px"></div>
      <button id="dv-open" type="button" class="dv-btn dv-btn-wa" style="width:100%;margin-block-start:8px" onclick="dvOpen()" <?= $ctx['consent'] ? '' : 'disabled'; ?>>💬 <?= _l('dv_wa_open'); ?></button>
    </div>

    <div id="dv-confirm" class="dv-card" style="display:none">
      <b><?= _l('dv_wa_sent_q'); ?></b>
      <?= form_open(admin_url('doctor_visits/wa/' . $doc['lead_id']), ['class' => 'dv-row', 'style' => 'margin-block-start:8px;flex-wrap:nowrap']); ?>
        <input type="hidden" name="template" id="dv-tpl-key"><input type="hidden" name="back" value="<?= html_escape($back); ?>">
        <button name="sent" value="1" class="dv-btn dv-btn-primary" style="flex:1"><?= _l('dv_yes'); ?></button>
        <button name="sent" value="0" class="dv-btn" style="flex:1"><?= _l('dv_no'); ?></button>
      <?= form_close(); ?>
    </div>
  <?php } ?>
</div></div></div>
<?php init_tail(); ?>
<script>
var DV_T = <?= json_encode($tpls, JSON_UNESCAPED_UNICODE); ?>, DV_PHONE = <?= json_encode($ctx['phone']); ?>, DV_CONSENT = <?= $ctx['consent'] ? 'true' : 'false'; ?>;
var DV_MISS = <?= json_encode(_l('dv_wa_missing')); ?>;
function dvTpl(k){var m=document.getElementById('dv-msg');m.value=k==='__custom'?'':(DV_T[k]?DV_T[k].text:'');document.getElementById('dv-tpl-key').value=k;dvCheck()}
function dvCheck(){var v=document.getElementById('dv-msg').value,miss=(v.match(/\{[a-z_]+\}/g)||[]);var el=document.getElementById('dv-missing');el.textContent=miss.length?DV_MISS.replace('%s',miss.join(' ')):'';document.getElementById('dv-open').disabled=!DV_CONSENT||!v.trim()||miss.length>0}
function dvOpen(){window.open('https://wa.me/'+DV_PHONE+'?text='+encodeURIComponent(document.getElementById('dv-msg').value),'_blank');document.getElementById('dv-confirm').style.display='';document.getElementById('dv-confirm').scrollIntoView({behavior:'smooth'})}
document.addEventListener('visibilitychange',function(){if(!document.hidden&&document.getElementById('dv-confirm')&&document.getElementById('dv-confirm').style.display==='')document.getElementById('dv-confirm').scrollIntoView()});
if(document.getElementById('dv-tpl'))dvTpl(document.getElementById('dv-tpl').value);
</script>
</body></html>
