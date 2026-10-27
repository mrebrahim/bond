<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (!empty($dv_feedback)) { $f = $dv_feedback; ?>
  <?php if (!empty($f['toast'])) { $t = $f['toast']; ?>
    <div class="dv-toast" id="dv-toast" onclick="this.remove()">
      <div><b><?= sprintf(_l('dv_toast'), number_format($t['value']), (int) $t['number'], (int) $t['of']); ?></b></div>
      <div class="dv-muted" style="color:#cbd5e1">
        <?= $t['xp'] ? sprintf(_l('dv_toast_xp'), (int) $t['xp']) : ''; ?>
        <?= !empty($f['combo']) ? ' · ' . sprintf(_l('dv_combo'), (int) $f['combo']) : ''; ?>
        <?= !empty($f['quest']) ? ' · ' . _l('dv_quest_done') : ''; ?>
        <?php foreach ($f['milestones'] ?? [] as $m) { echo ' · ' . sprintf(_l('dv_milestone'), _l('dv_ms_' . $m)); } ?>
        <?= !empty($t['late']) ? '<div style="color:#fdba74">' . _l('dv_toast_late') . '</div>' : ''; ?>
      </div>
    </div>
    <script>setTimeout(function(){var t=document.getElementById('dv-toast');if(t)t.remove()},6000)</script>
  <?php } ?>
  <?php if (!empty($f['level_up'])) { $l = $f['level_up']; ?>
    <div class="dv-celebrate" onclick="this.remove()"><div>
      <div style="font-size:46px">🏆</div>
      <h3 style="margin:6px 0"><?= sprintf(_l('dv_level_up'), html_escape($l['name'])); ?></h3>
      <?php if ($l['reward']) { ?><p><?= sprintf(_l('dv_unlocked'), html_escape($l['reward'])); ?></p><?php } ?>
    </div></div>
    <script>(function(){var c=['#16a34a','#1d4ed8','#f97316','#8b5cf6','#eab308'];for(var i=0;i<60;i++){var d=document.createElement('div');d.className='dv-confetti';d.style.insetInlineStart=Math.random()*100+'vw';d.style.background=c[i%c.length];d.style.animationDelay=Math.random()*0.8+'s';document.querySelector('.dv').appendChild(d);setTimeout(function(x){return function(){x.remove()}}(d),3500)}})()</script>
  <?php } ?>
<?php } ?>
