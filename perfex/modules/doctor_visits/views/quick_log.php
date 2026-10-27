<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="dv">
  <?php $this->load->view('doctor_visits/_style'); $this->load->view('doctor_visits/_nav'); ?>

  <?php if (!$doc) { ?>
    <!-- الخطوة 1: اختار الدكتور -->
    <div class="dv-card">
      <input id="dv-search" class="dv-in" placeholder="🔎 <?= _l('dv_search_doctor'); ?>" autofocus oninput="dvFilter(this.value)">
      <div id="dv-list" style="margin-block-start:8px">
        <?php $last = null; foreach ($doctors as $d) { ?>
          <?php if ($last !== $d['building']) { $last = $d['building']; ?><div class="dv-muted dv-grp" style="margin-block:8px 4px">🏢 <?= html_escape($d['building'] ?: '—'); ?></div><?php } ?>
          <a class="dv-btn dv-item" style="width:100%;justify-content:space-between;margin-block-end:6px" data-s="<?= html_escape(mb_strtolower($d['name'] . ' ' . $d['specialty'] . ' ' . $d['building'])); ?>"
             href="<?= admin_url('doctor_visits/log/' . $d['lead_id']); ?>">
            <span><?= html_escape($d['name']); ?> <span class="dv-muted"><?= html_escape($d['specialty']); ?><?= $d['floor'] ? ' · ' . _l('dv_floor') . ' ' . html_escape($d['floor']) : ''; ?></span></span>
            <span class="dv-chip" style="background:<?= $d['color']; ?>"><?= dv_stage_label($d['stage']); ?></span>
          </a>
        <?php } ?>
      </div>
      <a class="dv-btn" style="width:100%" href="<?= admin_url('doctor_visits/buildings'); ?>">➕ <?= _l('dv_quick_add'); ?></a>
    </div>
    <script>function dvFilter(q){q=q.trim().toLowerCase();document.querySelectorAll('#dv-list .dv-item').forEach(function(a){a.style.display=!q||a.dataset.s.indexOf(q)>-1?'':'none'});document.querySelectorAll('#dv-list .dv-grp').forEach(function(g){g.style.display=q?'none':''})}</script>
  <?php } else { ?>
    <!-- الخطوة 2: زرار ← (ملاحظة) ← الخطوة الجاية ← حفظ — كله في شاشة واحدة -->
    <?= form_open(admin_url('doctor_visits/log'), ['id' => 'dv-log']); ?>
      <input type="hidden" name="lead_id" value="<?= (int) $doc['lead_id']; ?>">
      <input type="hidden" name="back" value="<?= html_escape($back); ?>">
      <input type="hidden" name="outcome" id="dv-outcome">
      <div class="dv-card" style="padding-block:8px">
        <div class="dv-row">
          <b><?= html_escape($doc['name']); ?></b>
          <span class="dv-chip" style="background:<?= $doc['color']; ?>"><?= dv_stage_label($doc['stage']); ?> · <?= (int) $doc['progress']; ?>%</span>
        </div>
        <div class="dv-muted">🏢 <?= html_escape($doc['building']); ?><?= $doc['gatekeeper_name'] ? ' · 👩‍⚕️ ' . html_escape($doc['gatekeeper_name']) : ''; ?> · ➡️ <?= html_escape($doc['next_step']); ?></div>
      </div>

      <div class="dv-grid">
        <?php foreach (dv_outcome_buttons() as $o) { ?>
          <button type="button" class="dv-out <?= $o === 'LOST' ? 'lost' : ''; ?>" data-o="<?= $o; ?>" onclick="dvPick('<?= $o; ?>')"><?= dv_outcome_label($o); ?></button>
        <?php } ?>
      </div>

      <div id="dv-lost" style="display:none;margin-block-start:8px">
        <select name="lost_reason" class="dv-in"><option value=""><?= _l('dv_lost_reason'); ?>…</option>
          <?php foreach (dv_lost_reasons() as $r) { ?><option value="<?= $r; ?>"><?= dv_lost_reason_label($r); ?></option><?php } ?></select>
      </div>
      <div id="dv-won" style="display:none;margin-block-start:8px">
        <input type="number" name="deal_value" class="dv-in" placeholder="<?= _l('dv_deal_value'); ?>">
      </div>
      <?php if (!$doc['wa_consent']) { ?>
        <label id="dv-consent" style="display:none;margin-block-start:8px;min-height:44px;align-items:center;gap:8px"><input type="checkbox" name="consent_now" value="1" style="width:22px;height:22px"> <?= _l('dv_consent_today'); ?></label>
      <?php } ?>

      <div id="dv-next" style="margin-block-start:10px">
        <input name="next_action" class="dv-in" placeholder="📌 <?= _l('dv_next_action_ph'); ?>" value="<?= html_escape($doc['next_step']); ?>">
        <div class="dv-quick">
          <button type="button" onclick="dvDays(1)"><?= _l('dv_tomorrow'); ?></button>
          <button type="button" onclick="dvDays(3)"><?= _l('dv_in_3_days'); ?></button>
          <button type="button" onclick="dvDays(7)"><?= _l('dv_next_week'); ?></button>
          <input type="datetime-local" name="next_action_at" id="dv-next-at" class="dv-in" style="flex:1;min-width:170px">
        </div>
      </div>
      <textarea name="note" class="dv-in" rows="2" placeholder="<?= _l('dv_note'); ?>" style="margin-block-start:6px"></textarea>
      <div class="dv-muted">⚠️ <?= _l('dv_note_warning'); ?></div>
      <details class="dv-muted" style="margin-block:6px"><summary><?= _l('dv_visited_at'); ?></summary><input type="datetime-local" name="visited_at" class="dv-in"></details>
      <button class="dv-btn dv-btn-primary" id="dv-save" style="width:100%;margin-block-start:6px" disabled>💾 <?= _l('dv_save'); ?></button>
    <?= form_close(); ?>
    <script>
    function dvPick(o){
      document.getElementById('dv-outcome').value=o;
      document.querySelectorAll('.dv-out').forEach(function(b){b.classList.toggle('on',b.dataset.o===o)});
      var end=(o==='WON'||o==='LOST');
      document.getElementById('dv-lost').style.display=o==='LOST'?'':'none';
      document.getElementById('dv-won').style.display=o==='WON'?'':'none';
      document.getElementById('dv-next').style.display=end?'none':'';
      var c=document.getElementById('dv-consent'); if(c){c.style.display=(o==='WA'||o==='DOC')?'flex':'none'; if(o==='WA'){c.querySelector('input').checked=true}}
      if(!end && !document.getElementById('dv-next-at').value){dvDays(o==='CLOSED'?1:3)}
      document.getElementById('dv-save').disabled=false;
    }
    function dvDays(n){var d=new Date();d.setDate(d.getDate()+n);d.setHours(n===1?18:19,0,0,0);var p=function(x){return (x<10?'0':'')+x};document.getElementById('dv-next-at').value=d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':00'}
    </script>
  <?php } ?>
  <?php $this->load->view('doctor_visits/_feedback'); ?>
</div></div></div>
<?php init_tail(); ?>
</body></html>
