<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/* مسار ما بعد التحليل: $path (steps/done/total/open) · $mode: lead | daily | ref · $lead_id */
$mode   = $mode ?? 'lead';
$phases = ['before' => _l('dv_path_before'), 'during' => _l('dv_path_during'), 'after' => _l('dv_path_after')];
$can    = $mode === 'daily' || ($mode === 'lead' && $dv_can['create']);
$fmt    = function ($c) {
    if (!$c) {
        return '';
    }
    $t = strtotime($c['created_at']);
    $s = sprintf(_l('dv_path_checked_by'), $c['firstname'] ?: '—', date('d/m', $t) . ' ' . date('g:i', $t) . (date('G', $t) >= 12 ? 'م' : 'ص'));
    return strpos((string) $c['source'], 'auto') === 0 ? $s . ' · ' . _l('dv_path_auto_from') . ' ' . substr($c['source'], 5) : $s;
};
?>
<div class="dv-lanes">
  <?php foreach ($phases as $ph => $phname) { $list = array_filter($path['steps'], function ($s) use ($ph) { return $s['phase'] === $ph; }); if (!$list) { continue; } ?>
    <section class="dv-lane ph-<?= $ph; ?>">
      <h5><span class="dv-sw"></span><?= $phname; ?></h5>
      <?php foreach ($list as $s) { $n = count($s['items']); ?>
        <details class="dv-step <?= !empty($s['full']) ? 'full' : ''; ?>" data-step="<?= html_escape($s['key']); ?>" data-num="<?= (int) $s['sort']; ?>" <?= $mode !== 'ref' && $path['open'] === $s['key'] ? 'open' : ''; ?>>
          <summary>
            <span class="dv-num"><?= !empty($s['full']) ? '✓' : (int) $s['sort']; ?></span>
            <span><b><?= html_escape($s['title']); ?></b><small><?= html_escape((string) $s['subtitle']); ?></small></span>
            <?php if ($mode !== 'ref') { ?><span class="dv-cnt"><span class="d"><?= (int) $s['done']; ?></span> / <?= $n; ?></span><?php } ?>
          </summary>
          <?php if ($mode !== 'ref') { ?><div class="dv-mini"><i style="width:<?= $n ? round(100 * $s['done'] / $n) : 0; ?>%"></i></div><?php } ?>
          <ul class="dv-cl">
            <?php foreach ($s['items'] as $it) { $c = $it['check'] ?? null; ?>
              <li>
                <?php if ($mode === 'ref') { ?>
                  <span class="dv-ref">• <?= html_escape($it['text']); ?><?= $it['auto_rule'] ? ' <small class="dv-auto">' . _l('dv_path_auto') . '</small>' : ''; ?></span>
                <?php } else { ?>
                  <?= form_open(admin_url('doctor_visits/path/' . ($mode === 'daily' ? 'daily_toggle' : 'toggle')), ['class' => 'dv-tick']); ?>
                    <input type="hidden" name="lead_id" value="<?= (int) ($lead_id ?? 0); ?>"><input type="hidden" name="item_key" value="<?= html_escape($it['key']); ?>"><input type="hidden" name="checked" value="<?= $c ? 0 : 1; ?>">
                    <label title="<?= html_escape($fmt($c)); ?>">
                      <input type="checkbox" <?= $c ? 'checked' : ''; ?> <?= $can ? '' : 'disabled'; ?>>
                      <span><?= html_escape($it['text']); ?><?= !empty($it['auto']) ? ' <small class="dv-auto">' . _l('dv_path_auto') . '</small>' : ''; ?></span>
                    </label>
                  <?= form_close(); ?>
                <?php } ?>
              </li>
            <?php } ?>
          </ul>
          <?php if ($s['tip']) { ?><p class="dv-tip"><?= html_escape($s['tip']); ?></p><?php } ?>
        </details>
      <?php } ?>
    </section>
  <?php } ?>
</div>
