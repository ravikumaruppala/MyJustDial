/**
 * MyJustDial website - front-end behaviour.
 * Plain JavaScript, no libraries. Sections:
 *   1 Navigation   2 Pop-up window   3 Shortcode forms   4 Category drop-downs
 *   5 Results view + save + phone   6 Promo slider   7 Auto-submit selects
 * window.MJD.formEndpoint is printed by layout.php (URL of form-submit.php).
 */
(function () {
  'use strict';

  var $ = function (selector, scope) { return (scope || document).querySelector(selector); };
  var $$ = function (selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); };

  /* ================= 1. Navigation (hamburger on small screens) ================= */
  var navToggle = $('.nav-toggle');
  var navList = $('#navList');
  if (navToggle && navList) {
    navToggle.addEventListener('click', function () {
      var open = navList.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });
  }

  /* ================= 2. Pop-up window ================= */
  var modal = $('#mjdModal');
  var modalTitle = $('#mjdModalTitle');
  var modalBody = modal ? $('.mjd-modal-body', modal) : null;
  var lastFocus = null;

  /** First visible input of a container (skips hidden inputs and the spam-trap field). */
  function firstField(scope) {
    return $$('input:not([type=hidden]), select, textarea', scope).filter(function (el) { return !el.closest('.mjd-hp'); })[0] || null;
  }

  /** Show the pop-up with a title and (HTML string or node) content. */
  function openModal(title, content) {
    if (!modal) { return; }
    lastFocus = document.activeElement;
    modalTitle.textContent = title || '';
    modalBody.innerHTML = '';
    if (typeof content === 'string') { modalBody.innerHTML = content; } else { modalBody.appendChild(content); }
    modal.hidden = false;
    document.body.classList.add('modal-open');
    (firstField(modalBody) || $('.mjd-modal-close', modal)).focus();
  }

  function closeModal() {
    if (!modal || modal.hidden) { return; }
    modal.hidden = true;
    document.body.classList.remove('modal-open');
    modalBody.innerHTML = '';
    if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
  }

  if (modal) {
    modal.addEventListener('click', function (event) { if (event.target.closest('[data-close]')) { closeModal(); } });
    document.addEventListener('keydown', function (event) {
      if (modal.hidden) { return; }
      if (event.key === 'Escape') { closeModal(); return; }
      if (event.key === 'Tab') {                       // keep keyboard focus inside the pop-up
        var items = $$('a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select, textarea', modal)
          .filter(function (el) { return el.offsetParent !== null; });
        if (!items.length) { return; }
        var firstItem = items[0], lastItem = items[items.length - 1];
        if (event.shiftKey && document.activeElement === firstItem) { event.preventDefault(); lastItem.focus(); }
        else if (!event.shiftKey && document.activeElement === lastItem) { event.preventDefault(); firstItem.focus(); }
      }
    });
  }

  /** Links with data-modal-form="form_name" load that shortcode form into the pop-up. */
  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-modal-form]');
    if (link && !(event.ctrlKey || event.metaKey || event.shiftKey)) {
      event.preventDefault();
      var url = window.MJD.formEndpoint + '?action=render&form=' + encodeURIComponent(link.getAttribute('data-modal-form'));
      if (link.getAttribute('data-member-id')) { url += '&member=' + encodeURIComponent(link.getAttribute('data-member-id')); }
      if (link.getAttribute('data-plan')) { url += '&plan=' + encodeURIComponent(link.getAttribute('data-plan')); }
      openModal(link.getAttribute('data-modal-title'), '<p class="mjd-loading">Loading&hellip;</p>');
      fetch(url, { credentials: 'same-origin' })
        .then(function (response) { if (!response.ok) { throw new Error('load'); } return response.text(); })
        .then(function (html) { modalBody.innerHTML = html; var f = firstField(modalBody); if (f) { f.focus(); } })
        .catch(function () { modalBody.innerHTML = '<div class="mjd-notice">The form could not be loaded. Please try again.</div>'; });
      return;
    }
    // Static notices (Terms, Privacy, Reset password) come from <template id="tpl-NAME">.
    var info = event.target.closest('[data-modal-static]');
    if (info) {
      event.preventDefault();
      var tpl = $('#tpl-' + info.getAttribute('data-modal-static'));
      openModal(info.textContent.trim(), tpl ? tpl.innerHTML : '');
    }
  });

  /* ================= 3. Shortcode forms (AJAX submit, inline errors) ================= */
  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form.mjd-form');
    if (!form) { return; }
    event.preventDefault();

    // Record the page the visitor is on (the pop-up itself is loaded from form-submit.php).
    if (form.elements.mjd_return) { form.elements.mjd_return.value = window.location.pathname + window.location.search; }

    var button = $('.mjd-submit-btn', form);
    var message = $('.mjd-form-msg', form);
    // clear the previous errors
    $$('.mjd-error', form).forEach(function (el) { el.textContent = ''; });
    $$('.has-error', form).forEach(function (el) { el.classList.remove('has-error'); });
    message.className = 'mjd-form-msg'; message.textContent = '';
    if (button) { button.disabled = true; }

    fetch(window.MJD.formEndpoint, {
      method: 'POST', body: new FormData(form), credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
      .then(function (response) { return response.json(); })
      .then(function (result) {
        if (result.ok) {
          showThanks(form, result.message);
          if (result.reload) { setTimeout(function () { window.location.reload(); }, 700); }
          return;
        }
        var firstBad = null;
        Object.keys(result.errors || {}).forEach(function (name) {
          var holder = $('.mjd-error[data-for="' + name + '"]', form);
          if (holder) {
            holder.textContent = result.errors[name];
            var wrap = holder.closest('.mjd-field');
            if (wrap) { wrap.classList.add('has-error'); }
            firstBad = firstBad || firstField(wrap || form);
          }
        });
        if (result.message) { message.className = 'mjd-form-msg is-error'; message.textContent = result.message; }
        if (firstBad) { firstBad.focus(); }
      })
      .catch(function () {
        message.className = 'mjd-form-msg is-error';
        message.textContent = 'Something went wrong. Please try again.';
      })
      .then(function () { if (button) { button.disabled = false; } });
  });

  /** Replace the form with a thank-you message. */
  function showThanks(form, text) {
    var box = document.createElement('div');
    box.className = 'form-thanks';
    box.innerHTML = '<i class="fa fa-check-circle" aria-hidden="true"></i><p></p>';
    $('p', box).textContent = text || 'Thank you!';
    form.parentNode.replaceChild(box, form);
  }

  /* ================= 4. Category -> sub category drop-downs ================= */
  var category = $('#catSelect'), sub = $('#subSelect');
  if (category && sub) {
    var syncSub = function () {
      var chosen = category.value;
      $$('option[data-profession]', sub).forEach(function (option) {
        var show = chosen !== '' && option.getAttribute('data-profession') === chosen;
        option.hidden = !show; option.disabled = !show;
      });
      if (sub.selectedOptions[0] && sub.selectedOptions[0].disabled) { sub.value = ''; }
    };
    category.addEventListener('change', function () { sub.value = ''; syncSub(); });
    syncSub();
  }

  /* ================= 5. Results: list/grid view, save, phone ================= */
  var results = $('.results');
  var viewButtons = $$('.view-btn');
  var postList = $('.post-list');
  var viewTarget = results || postList;
  function setView(mode) {
    if (viewTarget) { viewTarget.classList.toggle('is-grid', mode === 'grid'); }
    viewButtons.forEach(function (b) {
      var active = b.getAttribute('data-view') === mode;
      b.classList.toggle('is-active', active); b.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    try { localStorage.setItem('mjd_view', mode); } catch (e) { /* storage may be blocked */ }
  }
  if (viewButtons.length) {
    var savedView = 'list';
    try { savedView = localStorage.getItem('mjd_view') || 'list'; } catch (e) { /* ignore */ }
    setView(savedView);
    viewButtons.forEach(function (b) { b.addEventListener('click', function () { setView(b.getAttribute('data-view')); }); });
  }

  // "SAVE" keeps favourite members in this browser (localStorage).
  var saved = [];
  try { saved = JSON.parse(localStorage.getItem('mjd_saved') || '[]'); } catch (e) { saved = []; }
  $$('[data-save-member]').forEach(function (btn) {
    var id = btn.getAttribute('data-save-member');
    var paint = function () { var on = saved.indexOf(id) > -1; btn.classList.toggle('is-saved', on); btn.setAttribute('aria-pressed', on ? 'true' : 'false'); };
    paint();
    btn.addEventListener('click', function () {
      var at = saved.indexOf(id);
      if (at > -1) { saved.splice(at, 1); } else { saved.push(id); }
      try { localStorage.setItem('mjd_saved', JSON.stringify(saved)); } catch (e) { /* ignore */ }
      paint();
    });
  });

  // "See Phone Number" shows the number only after the visitor asks for it.
  $$('[data-reveal-phone]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var phone = btn.getAttribute('data-reveal-phone');
      var link = document.createElement('a');
      link.className = btn.className; link.href = 'tel:' + phone.replace(/[^0-9+]/g, '');
      link.innerHTML = '<i class="fa fa-phone"></i> ';
      link.appendChild(document.createTextNode(phone));
      btn.parentNode.replaceChild(link, btn);
    });
  });

  /* ================= 6. Promo slider (Search Directory page) ================= */
  var promo = $('.promo');
  if (promo) {
    var slides = $$('.promo-slide', promo), dots = $$('.promo-dots button', promo), current = 0, timer = null;
    var show = function (index) {
      current = (index + slides.length) % slides.length;
      slides.forEach(function (s, i) { s.classList.toggle('is-active', i === current); });
      dots.forEach(function (d, i) { d.classList.toggle('is-active', i === current); });
    };
    var start = function () { stop(); timer = setInterval(function () { show(current + 1); }, 6000); };
    var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
    dots.forEach(function (d, i) { d.addEventListener('click', function () { show(i); start(); }); });
    promo.addEventListener('mouseenter', stop);
    promo.addEventListener('mouseleave', start);
    show(0); start();
  }

  /* ================= 7. Selects that submit their form when changed ================= */
  $$('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () { select.form.submit(); });
  });
}());
