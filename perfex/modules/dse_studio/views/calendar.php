<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
// شبكة الأسبوع: عمود لكل يوم، والجلسة مكانها وطولها حسب وقتها
$px_per_hour = 64;
$from_h      = 10;
$to_h        = 22;
foreach ($sessions as $s) {
    $from_h = min($from_h, (int) date('G', strtotime($s['start_at'])));
    $to_h   = max($to_h, (int) ceil((strtotime($s['end_at']) - strtotime(substr($s['end_at'], 0, 10))) / 3600));
}
$to_h    = min(24, max($to_h, $from_h + 1));
$palette = ['#2563eb', '#16a34a', '#d97706', '#9333ea', '#db2777', '#0891b2'];
$color   = [];
foreach ($studios as $i => $st) {
    $color[$st['id']] = $palette[$i % count($palette)];
}
$week_url = function ($week, $extra = []) use ($studio, $cancelled) {
    $q = array_filter(array_merge(['week' => $week, 'studio' => $studio ?: null, 'cancelled' => $cancelled ? 1 : null], $extra), function ($v) { return $v !== null; });
    return admin_url('dse_studio/calendar?' . http_build_query($q));
};
$status_cls = ['booked' => '', 'done' => 'is-done', 'no_show' => 'is-noshow', 'cancelled' => 'is-cancelled'];
$today      = date('Y-m-d');
$end_day    = date('Y-m-d', strtotime($start . ' +6 days'));
?>
<div id="wrapper">
  <div class="content dse-cal">
    <div class="panel_s"><div class="panel-body">

      <div class="dse-cal-head">
        <h4 class="tw-font-semibold tw-m-0">📅 كالندر الاستوديو <small><?= dse_day($start); ?> ← <?= dse_day($end_day); ?></small></h4>
        <div class="btn-group">
          <a class="btn btn-default btn-sm" href="<?= $week_url(date('Y-m-d', strtotime($start . ' -7 days'))); ?>">→ اللي فات</a>
          <a class="btn btn-default btn-sm" href="<?= $week_url($today); ?>">الأسبوع ده</a>
          <a class="btn btn-default btn-sm" href="<?= $week_url(date('Y-m-d', strtotime($start . ' +7 days'))); ?>">الجاي ←</a>
        </div>
      </div>

      <div class="dse-cal-filters">
        <a href="<?= $week_url($start, ['studio' => null]); ?>" class="dse-pill <?= !$studio ? 'active' : ''; ?>">كل الاستوديوهات</a>
        <?php foreach ($studios as $st) { ?>
          <a href="<?= admin_url('dse_studio/calendar?' . http_build_query(array_filter(['week' => $start, 'studio' => $st['id'], 'cancelled' => $cancelled ? 1 : null]))); ?>"
             class="dse-pill <?= (int) $studio === (int) $st['id'] ? 'active' : ''; ?>">
            <span class="dse-dot" style="background:<?= $color[$st['id']]; ?>"></span><?= html_escape($st['name']); ?>
          </a>
        <?php } ?>
        <a href="<?= admin_url('dse_studio/calendar?' . http_build_query(array_filter(['week' => $start, 'studio' => $studio ?: null, 'cancelled' => $cancelled ? null : 1]))); ?>"
           class="dse-pill dse-pill-muted"><?= $cancelled ? '✓ ' : ''; ?>اعرض الملغية</a>
        <span class="dse-count"><?= count(array_filter($sessions, function ($s) { return $s['status'] !== 'cancelled'; })); ?> جلسة الأسبوع ده</span>
      </div>

      <div class="dse-cal-scroll">
        <div class="dse-grid" style="--h:<?= $px_per_hour; ?>px">
          <!-- عمود الساعات -->
          <div class="dse-col dse-hours">
            <div class="dse-day-head"></div>
            <div class="dse-day-body" style="height:<?= ($to_h - $from_h) * $px_per_hour; ?>px">
              <?php for ($h = $from_h; $h < $to_h; $h++) { ?>
                <div class="dse-hour" style="top:<?= ($h - $from_h) * $px_per_hour; ?>px"><?= dse_time(mktime($h, 0, 0)); ?></div>
              <?php } ?>
            </div>
          </div>

          <?php for ($d = 0; $d < 7; $d++) {
              $day  = date('Y-m-d', strtotime($start . ' +' . $d . ' days'));
              $list = array_filter($sessions, function ($s) use ($day) { return substr($s['start_at'], 0, 10) === $day; }); ?>
            <div class="dse-col <?= $day === $today ? 'is-today' : ''; ?>">
              <div class="dse-day-head">
                <div class="dse-day-name"><?= explode(' ', dse_day($day))[0]; ?></div>
                <div class="dse-day-date"><?= date('d/m', strtotime($day)); ?></div>
              </div>
              <div class="dse-day-body" style="height:<?= ($to_h - $from_h) * $px_per_hour; ?>px">
                <?php for ($h = $from_h; $h < $to_h; $h++) { ?><div class="dse-line" style="top:<?= ($h - $from_h) * $px_per_hour; ?>px"></div><?php } ?>
                <?php foreach ($list as $s) {
                    $st  = strtotime($s['start_at']);
                    $top = ((int) date('G', $st) - $from_h + (int) date('i', $st) / 60) * $px_per_hour;
                    $hgt = max(34, (strtotime($s['end_at']) - $st) / 3600 * $px_per_hour - 2); ?>
                  <a class="dse-ev <?= $status_cls[$s['status']] ?? ''; ?>" href="<?= admin_url('dse_studio/client/' . $s['clientid']); ?>"
                     style="top:<?= $top; ?>px;height:<?= $hgt; ?>px;--c:<?= $color[$s['studio_id']] ?? '#64748b'; ?>"
                     title="<?= html_escape($s['company'] . ' · ' . $s['studio'] . ' · ' . dse_time($s['start_at']) . ' – ' . dse_time($s['end_at'])); ?>">
                    <div class="dse-ev-time"><?= dse_time($s['start_at']); ?> – <?= dse_time($s['end_at']); ?></div>
                    <div class="dse-ev-name"><?= html_escape($s['company']); ?></div>
                    <div class="dse-ev-meta">
                      <?= dse_num($s['qty']) . ' ' . dse_unit($s['type']); ?><?= $s['outfits'] ? ' · ' . (int) $s['outfits'] . ' لبس' : ''; ?>
                      <?= !$studio ? ' · ' . html_escape($s['studio']) : ''; ?><?= $s['photographer'] ? ' · 📷 ' . html_escape(trim($s['photographer'])) : ''; ?>
                    </div>
                    <?php if ($s['status'] !== 'booked') { ?><div class="dse-ev-status"><?= dse_session_statuses()[$s['status']]; ?></div><?php } ?>
                  </a>
                <?php } ?>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>

      <div class="dse-legend">
        <span><span class="dse-sw"></span> محجوزة</span>
        <span><span class="dse-sw is-done"></span> اتصورت</span>
        <span><span class="dse-sw is-noshow"></span> مجاش</span>
        <span class="text-muted">· اضغط على أي جلسة تفتح صفحة العميل (منها تغيّر الميعاد أو تقفل الجلسة)</span>
      </div>
    </div></div>
  </div>
</div>
<?php init_tail(); ?>
<style>
.dse-cal-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px}
.dse-cal-head small{color:#64748b;font-weight:400;margin-inline-start:6px}
.dse-cal-filters{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:14px}
.dse-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border:1px solid #e2e8f0;border-radius:999px;color:#334155;font-size:13px;background:#fff}
.dse-pill:hover{background:#f8fafc;text-decoration:none;color:#0f172a}
.dse-pill.active{background:#0f172a;border-color:#0f172a;color:#fff}
.dse-pill-muted{color:#64748b}
.dse-dot{width:10px;height:10px;border-radius:50%;display:inline-block}
.dse-count{margin-inline-start:auto;color:#64748b;font-size:13px}
.dse-cal-scroll{overflow-x:auto;border:1px solid #e2e8f0;border-radius:10px}
.dse-grid{display:flex;min-width:980px}
.dse-col{flex:1 1 0;border-inline-start:1px solid #eef2f7;min-width:0}
.dse-col.dse-hours{flex:0 0 64px;border-inline-start:0;background:#fafbfc}
.dse-day-head{height:54px;display:flex;flex-direction:column;align-items:center;justify-content:center;border-bottom:1px solid #e2e8f0;background:#fafbfc;position:sticky;top:0}
.dse-day-name{font-weight:600;color:#334155}
.dse-day-date{font-size:12px;color:#94a3b8}
.dse-col.is-today .dse-day-head{background:#eff6ff}
.dse-col.is-today .dse-day-name,.dse-col.is-today .dse-day-date{color:#1d4ed8}
.dse-col.is-today .dse-day-body{background:#f8fbff}
.dse-day-body{position:relative}
.dse-line{position:absolute;inset-inline:0;border-top:1px dashed #eef2f7}
.dse-hour{position:absolute;inset-inline:0;font-size:11px;color:#94a3b8;text-align:center;transform:translateY(-1px);padding-top:2px}
.dse-ev{position:absolute;inset-inline:4px;overflow:hidden;border-radius:8px;padding:4px 7px;font-size:12px;line-height:1.35;
  background:color-mix(in srgb,var(--c) 12%,#fff);border-inline-start:4px solid var(--c);color:#0f172a;box-shadow:0 1px 2px rgba(15,23,42,.06)}
.dse-ev:hover{text-decoration:none;color:#0f172a;box-shadow:0 4px 12px rgba(15,23,42,.12);z-index:2}
.dse-ev-time{font-size:11px;color:#475569}
.dse-ev-name{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dse-ev-meta{font-size:11px;color:#475569}
.dse-ev-status{font-size:10px;font-weight:600;margin-top:2px}
.dse-ev.is-done{background:#f0fdf4;border-color:#16a34a}
.dse-ev.is-noshow{background:#fef2f2;border-color:#dc2626}
.dse-ev.is-cancelled{background:#f8fafc;border-color:#cbd5e1;color:#94a3b8;text-decoration:line-through}
.dse-legend{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;font-size:12px;color:#334155}
.dse-sw{display:inline-block;width:12px;height:12px;border-radius:3px;background:#dbeafe;border-inline-start:3px solid #2563eb;vertical-align:middle}
.dse-sw.is-done{background:#f0fdf4;border-color:#16a34a}
.dse-sw.is-noshow{background:#fef2f2;border-color:#dc2626}
</style>
</body></html>
