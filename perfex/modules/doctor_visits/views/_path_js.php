<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/* علامة بضغطة: بتتحفظ AJAX (بنفس الفورم والـ CSRF بتاع Perfex) — ولو الـ AJAX فشل الفورم بيتبعت عادي */
document.addEventListener('change', function (e) {
  var box = e.target, form = box.closest && box.closest('form.dv-tick');
  if (!form) { return; }
  var fd = new FormData(form); fd.set('checked', box.checked ? 1 : 0); fd.set('ajax', 1);
  box.disabled = true;
  fetch(form.action, {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
    .then(function (r) { return r.json(); })
    .then(function (j) {
      box.disabled = false;
      if (!j.ok) { box.checked = !box.checked; alert(j.error); return; }
      form.querySelector('[name=checked]').value = box.checked ? 0 : 1;
      form.querySelector('label').title = j.by || '';
      var auto = form.querySelector('.dv-auto'); if (auto && !box.checked) { auto.remove(); }
      var wrap = form.closest('.dv-path, .dv-daily') || document;
      var t = wrap.querySelector('.dv-path-txt'); if (t) { t.textContent = j.done + ' / ' + j.total; }
      var b = wrap.querySelector('.dv-path-bar'); if (b) { b.style.width = Math.round(100 * j.done / Math.max(1, j.total)) + '%'; }
      var n = wrap.querySelector('.dv-path-next'); if (n) { n.innerHTML = j.next ? '➡️ <?= _l('dv_path_next'); ?>: <b></b>' : '🎉 <?= _l('dv_path_all_done'); ?>'; if (j.next) { n.querySelector('b').textContent = j.next; } }
      j.steps.forEach(function (s) {
        var d = wrap.querySelector('details[data-step="' + s.key + '"]'); if (!d) { return; }
        var was = d.classList.contains('full');
        d.classList.toggle('full', s.full);
        d.querySelector('.dv-cnt .d').textContent = s.done;
        d.querySelector('.dv-mini i').style.width = Math.round(100 * s.done / s.count) + '%';
        d.querySelector('.dv-num').textContent = s.full ? '✓' : d.dataset.num;
        if (s.full && !was && s.key === box.closest('details').dataset.step) {   // الخطوة خلصت ← اللي بعدها تتفتح
          d.open = false; var nx = wrap.querySelector('details[data-step="' + j.open + '"]'); if (nx) { nx.open = true; }
        }
      });
    })
    .catch(function () { form.submit(); });
});
</script>
