<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Digital Solution — الاستوديو والباقات
Description: رصيد الباقات، حجز الاستوديو، السكربتات والنشر، جروبات واتساب العملاء (Evolution API)، وتنبيه العميل اللي مابيصوّرش.
Version: 1.9.8
Requires at least: 3.0.*
Author: Digital Solution
*/

define('DSE_STUDIO_MODULE_NAME', 'dse_studio');

$CI = &get_instance();
require_once __DIR__ . '/helpers/dse_studio_setup.php';
$CI->load->helper(DSE_STUDIO_MODULE_NAME . '/dse_studio');

register_activation_hook(DSE_STUDIO_MODULE_NAME, 'dse_studio_activation_hook');
register_language_files(DSE_STUDIO_MODULE_NAME, [DSE_STUDIO_MODULE_NAME]);
register_cron_task('dse_studio_cron');

hooks()->add_action('admin_init', 'dse_studio_admin_init');
hooks()->add_action('app_admin_footer', 'dse_studio_date_inputs');
hooks()->add_action('app_admin_footer', 'dse_studio_wa_banner');

function dse_studio_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/install.php';
}

function dse_studio_admin_init()
{
    $CI = &get_instance();

    register_staff_capabilities(DSE_STUDIO_MODULE_NAME, [
        'capabilities' => [
            'view'   => _l('dse_cap_view'),
            'create' => _l('dse_cap_create'),
            'edit'   => _l('dse_cap_edit'),
            'delete' => _l('dse_cap_delete'),
            'publish' => _l('dse_cap_publish'),
        ],
    ], _l('dse_studio'));

    $can_publish = staff_can('publish', DSE_STUDIO_MODULE_NAME) || staff_can('create', DSE_STUDIO_MODULE_NAME);
    if (!staff_can('view', DSE_STUDIO_MODULE_NAME) && $can_publish) {
        // المودريشن: صفحة النشر بس
        $CI->app_menu->add_sidebar_menu_item('dse-studio-publish', [
            'name' => _l('dse_menu_publish'), 'href' => admin_url('dse_studio/publishing'), 'position' => 6, 'icon' => 'fa fa-share-square-o',
        ]);
        $CI->app_menu->add_sidebar_menu_item('dse-studio-media', [
            'name' => _l('dse_menu_media'), 'href' => admin_url('dse_studio/media'), 'position' => 6, 'icon' => 'fa fa-calendar',
        ]);
        $CI->app_menu->add_sidebar_menu_item('dse-studio-library', [
            'name' => _l('dse_menu_library'), 'href' => admin_url('dse_studio/library'), 'position' => 6, 'icon' => 'fa fa-fire',
        ]);
    }
    if (staff_can('view', DSE_STUDIO_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_menu_item('dse-studio', [
            'name'     => _l('dse_studio'),
            'href'     => admin_url('dse_studio'),
            'position' => 6,
            'icon'     => 'fa fa-video-camera',
        ]);
        $children = [
            ['dse-studio-home', 'dse_menu_home', 'dse_studio', 1],
            ['dse-studio-calendar', 'dse_menu_calendar', 'dse_studio/calendar', 2],
            ['dse-studio-media', 'dse_menu_media', 'dse_studio/media', 2],
            ['dse-studio-library', 'dse_menu_library', 'dse_studio/library', 2],
            ['dse-studio-publish', 'dse_menu_publish', 'dse_studio/publishing', 2],
            ['dse-studio-alerts', 'dse_menu_alerts', 'dse_studio/alerts', 3],
            ['dse-studio-renewals', 'dse_menu_renewals', 'dse_studio/renewals', 3],
            ['dse-studio-outbox', 'dse_menu_outbox', 'dse_studio/outbox', 4],
        ];
        if (is_admin()) {
            $children[] = ['dse-studio-finance', 'dse_menu_finance', 'dse_studio/finance', 0];
            $children[] = ['dse-studio-messages', 'dse_menu_messages', 'dse_studio/messages', 5];
            $children[] = ['dse-studio-settings', 'dse_menu_settings', 'dse_studio/settings', 6];
        }
        foreach ($children as $c) {
            $CI->app_menu->add_sidebar_children_item('dse-studio', [
                'slug' => $c[0], 'name' => _l($c[1]), 'href' => admin_url($c[2]), 'position' => $c[3],
            ]);
        }

        // تاب في بروفايل العميل (لو النسخة بتدعمها)
        if (isset($CI->app_tabs) && method_exists($CI->app_tabs, 'add_customer_profile_tab')) {
            $CI->app_tabs->add_customer_profile_tab('dse_studio', [
                'name'     => _l('dse_studio'),
                'icon'     => 'fa fa-video-camera',
                'view'     => 'dse_studio/customer_tab',
                'position' => 6,
            ]);
        }
    }
}

/** بيشتغل مع cron بتاع Perfex: الواتساب كل مرة، والتنبيهات مرة في اليوم */
function dse_studio_cron()
{
    $CI = &get_instance();
    dse_studio_ensure_schema();
    $CI->load->model(DSE_STUDIO_MODULE_NAME . '/dse_studio_model');
    $CI->load->library(DSE_STUDIO_MODULE_NAME . '/dse_evolution');
    $CI->dse_studio_model->evo = $CI->dse_evolution;

    try {
        $wa = $CI->dse_studio_model->wa_health_check(); // الرقم خرج / الرسايل بتفشل ← تنبيه للأدمن
        if ($wa) {
            dse_studio_notify_staff($wa);
        }
        $CI->dse_studio_model->resolve_pending_groups(5); // لينكات جروبات اتحفظت ولسه مااتربطتش
        $CI->dse_studio_model->process_outbox(10);
    } catch (Exception $e) {
        log_message('error', 'dse_studio outbox: ' . $e->getMessage());
    }

    if (get_option('dse_last_daily_run') === date('Y-m-d')) {
        return;
    }
    update_option('dse_last_daily_run', date('Y-m-d'));
    try {
        $CI->dse_studio_model->sync_all_packages();
        $new = array_merge($CI->dse_studio_model->check_inactive_clients(), $CI->dse_studio_model->check_all_payments(), $CI->dse_studio_model->check_renewals(),
            $CI->dse_studio_model->check_content_runway(), $CI->dse_studio_model->check_packages_ending(),
            $CI->dse_studio_model->check_installments());
        if ($new) {
            dse_studio_notify_staff($new);
        }
    } catch (Exception $e) {
        log_message('error', 'dse_studio daily: ' . $e->getMessage());
    }
}

/**
 * إشعار جوه Perfex (الجرس) + إيميل للموظفين المسؤولين عن نوع التنبيه:
 * payment_due → dse_payment_notify_staff_ids · الباقي → dse_notify_staff_ids (فاضي = الأدمنز)
 */
function dse_studio_notify_staff(array $alert_ids)
{
    $CI = &get_instance();
    $CI->load->model('emails_model');
    $subjects = [
        'payment_due'     => 'العميل محتاج يدفع الباقي',
        'client_inactive' => 'عميل مابيصوّرش',
        'no_group'        => 'عميل مالوش جروب واتساب',
        'no_content'      => 'عميل محتاج ييجي يصوّر',
        'package_ending'  => 'باقة هتخلص قريب',
        'renewal_due'     => 'باقة خلصت ومجددش',
        'installment_due' => 'ميعاد قسط',
        'wa_down'         => 'واتساب السيستم وقف — الرسايل مش بتتبعت',
        'wa_up'           => 'واتساب السيستم رجع يشتغل',
    ];

    foreach (array_unique($alert_ids) as $aid) {
        $a = $CI->db->query('SELECT * FROM ' . db_prefix() . 'dse_alerts WHERE id = ?', [(int) $aid])->row_array();
        if (!$a) {
            continue;
        }
        $opt   = in_array($a['alert_key'], ['wa_down', 'wa_up'], true) ? 'dse_wa_alert_staff_ids'
            : (in_array($a['alert_key'], ['payment_due', 'installment_due'], true) ? 'dse_payment_notify_staff_ids'
            : (in_array($a['alert_key'], ['client_inactive', 'no_content', 'package_ending', 'renewal_due'], true) ? 'dse_content_notify_staff_ids' : 'dse_notify_staff_ids'));
        $ids   = array_filter(array_map('intval', preg_split('/[\s,;]+/', (string) get_option($opt))));
        $where = $ids ? 'staffid IN (' . implode(',', $ids) . ')' : 'admin = 1';
        $staff = $CI->db->query('SELECT staffid, email FROM ' . db_prefix() . 'staff WHERE active = 1 AND ' . $where)->result_array();
        $subject = ($a['priority'] === 'urgent' ? '⚠️ ' : '') . ($subjects[$a['alert_key']] ?? 'تنبيه من الاستوديو');
        $link    = $a['clientid'] ? 'dse_studio/client/' . $a['clientid'] : 'dse_studio/alerts';

        $notified = [];
        foreach ($staff as $s) {
            if (function_exists('add_notification') && add_notification([
                'description'     => 'dse_notification_alert',
                'touserid'        => $s['staffid'],
                'link'            => $link,
                'additional_data' => serialize([mb_substr($a['message'], 0, 150)]),
            ])) {
                $notified[] = $s['staffid'];
            }
            if (method_exists($CI->emails_model, 'send_simple_email')) {
                $CI->emails_model->send_simple_email($s['email'], $subject,
                    '<div dir="rtl">' . nl2br(html_escape($a['message'])) . '<br><br><a href="' . admin_url($link) . '">افتح في Perfex</a></div>');
            }
        }
        if ($notified && function_exists('pusher_trigger_notification')) {
            pusher_trigger_notification($notified);
        }
    }
}

/**
 * خانات التاريخ يوم/شهر/سنة (مصر) في صفحات الموديول: input[type=date|datetime-local] بتاع المتصفح بيتعرض
 * بلغة الكروم (أمريكي = شهر/يوم/سنة). بنبدله بالـ datetimepicker بتاع Perfex بصيغة d/m/Y والأسبوع بيبدأ السبت،
 * والقيمة اللي بتتبعت للسيرفر فاضلة YYYY-MM-DD (خانة مخفية) — يعني مفيش تغيير في الكود اللي بيستقبل.
 */
function dse_studio_date_inputs()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/dse_studio') === false) {
        return;
    }
    echo <<<'JS'
<script>
(function () {
  var $ = window.jQuery;
  if (!$ || !$.fn.datetimepicker) { return; } // من غير الـ plugin: الخانة بتفضل زي ما هي
  try { $.datetimepicker.setLocale('ar'); } catch (e) {}
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var toIso = function (d, time) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + (time ? 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) : ''); };
  var toShow = function (iso, time) {
    var m = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/.exec(iso || '');
    return m ? m[3] + '/' + m[2] + '/' + m[1] + (time && m[4] ? ' ' + m[4] + ':' + m[5] : '') : '';
  };
  var parseShow = function (v, time) {
    var m = /^\s*(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})(?:\s+(\d{1,2}):(\d{2}))?\s*$/.exec(v || '');
    if (!m) { return null; }
    var d = new Date(+m[3], +m[2] - 1, +m[1], m[4] ? +m[4] : 0, m[5] ? +m[5] : 0);
    return d.getDate() === +m[1] ? d : null;
  };
  var bounds = function (v) { return v ? v.slice(0, 10).replace(/-/g, '/') : false; };
  var upgrade = function () {
    $('input[type=date],input[type=datetime-local]').not('[data-dse-date]').each(function () {
      var real = this, time = real.type === 'datetime-local';
      var vis = $('<input type="text" autocomplete="off" dir="ltr">').attr('class', real.className)
        .attr('placeholder', time ? 'يوم/شهر/سنة  ساعة:دقيقة' : 'يوم/شهر/سنة').val(toShow(real.value, time)).css('text-align', 'right');
      if (real.required) { vis.prop('required', true); real.required = false; }
      real.setAttribute('data-dse-date', '1');
      $(real).before(vis);
      real.type = 'hidden';
      var sync = function () {
        var d = parseShow(vis.val(), time);
        real.value = d ? toIso(d, time) : '';
        $(real).trigger('change');
      };
      vis.datetimepicker({
        format: time ? 'd/m/Y H:i' : 'd/m/Y', formatDate: 'Y/m/d', timepicker: time, datepicker: true, step: 15,
        dayOfWeekStart: 6, scrollInput: false, closeOnDateSelect: !time,
        i18n: { ar: { // مصري + الأيام من الأحد (الـ plugin بيبدأ عدّ الأيام من الأحد)
          months: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
          dayOfWeekShort: ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'],
          dayOfWeek: ['الأحد', 'الاتنين', 'التلات', 'الأربع', 'الخميس', 'الجمعة', 'السبت'] } },
        minDate: bounds(real.getAttribute('min')), maxDate: bounds(real.getAttribute('max')),
        onChangeDateTime: function (dp) { real.value = dp ? toIso(dp, time) : ''; $(real).trigger('change'); }
      });
      vis.on('change blur', sync);
    });
  };
  $(upgrade);
})();
</script>
JS;
}



/** بانر أحمر فوق صفحات الموديول لو الواتساب واقف (أو لسه بيبعت المتراكم بالراحة) */
function dse_studio_wa_banner()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/dse_studio') === false || !is_staff_logged_in()) {
        return;
    }
    $CI = &get_instance();
    $CI->load->model(DSE_STUDIO_MODULE_NAME . '/dse_studio_model');
    $h = $CI->dse_studio_model->wa_health();
    if (!$h) {
        return;
    }
    $html = $h['down']
        ? '<b>📵 واتساب السيستم مش بيبعت رسايل</b> من ' . html_escape(date('d/m/Y H:i', strtotime($h['since']))) . ' — ' . html_escape($h['error'])
          . ' · ' . (int) $h['queued'] . ' رسالة مستنية. <a href="' . admin_url('dse_studio/settings') . '">الإعدادات</a>'
        : '<b>✅ الواتساب رجع</b> — ' . (int) $h['queued'] . ' رسالة مستنية بتتبعت واحدة كل ' . (int) $h['gap'] . ' دقيقة.';
    $style = $h['down'] ? 'background:#fef2f2;border:1px solid #fca5a5;color:#991b1b' : 'background:#f0fdf4;border:1px solid #86efac;color:#166534';
    echo '<script>(function(){var c=document.querySelector("#wrapper .content");if(!c){return;}var d=document.createElement("div");d.setAttribute("style",'
        . json_encode($style . ';border-radius:8px;padding:10px 14px;margin-bottom:12px;direction:rtl') . ');d.innerHTML=' . json_encode($html, JSON_UNESCAPED_UNICODE)
        . ';c.insertBefore(d,c.firstChild);})();</script>';
}
