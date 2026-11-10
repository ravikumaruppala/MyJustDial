(function () {
'use strict';
var body = document.body;
var sidebar = document.getElementById('sidebar');
function isMobile() { return window.matchMedia('(max-width:650px)').matches; }
function isCollapsed() { return body.classList.contains('sidebar-collapsed'); }

/* ---------- sidebar fly-outs (hover shows the sub menu beside the item) ---------- */
var flyouts = [], hideTimer = null, tip = null;
function hideFlyouts() {
 flyouts.forEach(function (f) { f.el.classList.remove('is-visible'); });
}
function hideTip() { if (tip) tip.classList.remove('is-visible'); }
function placeBeside(el, anchor) {
 var r = anchor.getBoundingClientRect();
 el.style.left = (r.right + 4) + 'px';
 el.style.top = '0px';
 el.classList.add('is-visible');
 var h = el.offsetHeight, top = Math.max(8, Math.min(r.top - 8, window.innerHeight - h - 8));
 el.style.top = top + 'px';
}
function scheduleHide() { clearTimeout(hideTimer); hideTimer = setTimeout(hideFlyouts, 160); }
function cancelHide() { clearTimeout(hideTimer); }

document.querySelectorAll('.nav-group').forEach(function (group) {
 var toggle = group.querySelector('.nav-group-toggle'), sub = group.querySelector('.sub-nav');
 if (!toggle || !sub) return;
 var el = document.createElement('div');
 el.className = 'nav-flyout';
 var title = document.createElement('div');
 title.className = 'nav-flyout-title';
 title.textContent = toggle.querySelector('.nav-label').textContent;
 el.appendChild(title);
 sub.querySelectorAll('a').forEach(function (a) { el.appendChild(a.cloneNode(true)); });
 document.body.appendChild(el);
 var entry = { el: el, toggle: toggle, sub: sub };
 flyouts.push(entry);
 function show() {
  if (isMobile()) return;
  if (!isCollapsed() && sub.classList.contains('is-open')) return; // already expanded downward
  cancelHide(); hideFlyouts(); placeBeside(el, toggle);
 }
 entry.show = show;
 toggle.addEventListener('mouseenter', show);
 toggle.addEventListener('mouseleave', scheduleHide);
 toggle.addEventListener('focus', show);
 el.addEventListener('mouseenter', cancelHide);
 el.addEventListener('mouseleave', scheduleHide);
});

/* labels for plain items while the sidebar is collapsed */
tip = document.createElement('div');
tip.className = 'nav-flyout nav-tip';
document.body.appendChild(tip);
document.querySelectorAll('.side-nav > a, .collapse-sidebar').forEach(function (item) {
 item.addEventListener('mouseenter', function () {
  hideFlyouts();
  if (!isCollapsed() || isMobile()) return;
  tip.textContent = item.getAttribute('data-label') || '';
  placeBeside(tip, item);
 });
 item.addEventListener('mouseleave', hideTip);
});
window.addEventListener('resize', function () { hideFlyouts(); hideTip(); });
var nav = document.querySelector('.side-nav');
if (nav) nav.addEventListener('scroll', function () { hideFlyouts(); hideTip(); });

/* placeholder links should not jump the page */
document.addEventListener('click', function (e) {
 var a = e.target.closest('.side-nav a, .nav-flyout a');
 if (a && a.getAttribute('href') === '#') e.preventDefault();
});

/* ---------- collapse / expand ---------- */
var collapseBtn = document.getElementById('collapseSidebar');
function syncCollapseLabel() {
 if (!collapseBtn) return;
 collapseBtn.setAttribute('data-label', isCollapsed() ? 'Expand' : 'Collapse');
 collapseBtn.setAttribute('aria-label', isCollapsed() ? 'Expand sidebar' : 'Collapse sidebar');
}
if (collapseBtn) {
 collapseBtn.addEventListener('click', function () {
  if (isMobile()) { if (sidebar) sidebar.classList.remove('sidebar-open'); return; }
  body.classList.toggle('sidebar-collapsed');
  try { localStorage.setItem('mjd_sidebar', isCollapsed() ? 'collapsed' : 'expanded'); } catch (err) { /* ignore */ }
  hideFlyouts(); hideTip(); syncCollapseLabel();
 });
 syncCollapseLabel();
}

/* ---------- menus: sidebar groups expand downward on click; account / action dropdowns ---------- */
document.addEventListener('click', function (event) {
 var toggle = event.target.closest('[data-menu-toggle]');
 if (toggle) {
  var key = toggle.getAttribute('data-menu-toggle'), menu = document.querySelector('[data-menu="' + key + '"]');
  if (!menu) return;
  if (toggle.classList.contains('nav-group-toggle') && isCollapsed() && !isMobile()) {
   // icons-only mode: a click (touch) opens the fly-out instead of the inline list
   var entry = flyouts.filter(function (f) { return f.toggle === toggle; })[0];
   if (entry) entry.show();
   event.stopPropagation(); return;
  }
  var open = !menu.classList.contains('is-open');
  document.querySelectorAll('.small-dropdown.is-open,.fm-action-dropdown.is-open').forEach(function (el) { el.classList.remove('is-open'); });
  if (toggle.classList.contains('nav-group-toggle')) {
   document.querySelectorAll('.nav-group-toggle').forEach(function (t) {
    if (t !== toggle) { t.setAttribute('aria-expanded', 'false'); var m = document.querySelector('[data-menu="' + t.getAttribute('data-menu-toggle') + '"]'); if (m) m.classList.remove('is-open'); }
   });
   hideFlyouts();
  }
  menu.classList.toggle('is-open', open);
  if (toggle.hasAttribute('aria-expanded')) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  event.stopPropagation(); return;
 }
 if (!event.target.closest('.fm-action-dropdown') && !event.target.closest('.small-dropdown')) {
  document.querySelectorAll('.fm-action-dropdown.is-open,.small-dropdown.is-open').forEach(function (el) { el.classList.remove('is-open'); });
 }
 var copy = event.target.closest('.copy-shortcode');
 if (copy) {
  event.preventDefault();
  var code = copy.getAttribute('data-code') || '';
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(code).then(function () { alert('Shortcode copied: ' + code); }); }
  else { var t = document.createElement('textarea'); t.value = code; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); alert('Shortcode copied: ' + code); }
 }
});

var mobile = document.getElementById('mobileMenu');
if (mobile && sidebar) mobile.addEventListener('click', function () { sidebar.classList.toggle('sidebar-open'); });

/* ---------- Form Manager ---------- */
var all = document.getElementById('selectAll');
if (all) all.addEventListener('change', function () { document.querySelectorAll('.form-row-check').forEach(function (c) { c.checked = all.checked; }); });
var close = document.querySelector('[data-dismiss-notice]');
if (close) close.addEventListener('click', function () { var n = close.closest('.notice'); if (n) n.remove(); });

// every filter / sort option applies as soon as it is chosen
document.querySelectorAll('.fm-filters select').forEach(function (s) {
 s.addEventListener('change', function () { s.form.submit(); });
});

// bulk actions: Enable / Disable / Delete the ticked custom forms
var applyBulk = document.getElementById('applyBulk');
if (applyBulk) applyBulk.addEventListener('click', function () {
 var select = document.getElementById('bulkAction'), op = select ? select.value : '';
 if (!op) { alert('Choose a bulk action first.'); return; }
 var ids = Array.prototype.map.call(document.querySelectorAll('.form-row-check:checked'), function (c) { return c.value; });
 if (!ids.length) { alert('Tick at least one custom form first.'); return; }
 if (op === 'delete' && !window.confirm('Delete ' + ids.length + ' selected form(s) and their fields?')) return;
 window.location.href = applyBulk.getAttribute('data-url') + '?action=bulk&op=' + encodeURIComponent(op) + '&ids=' + ids.join(',') + '&token=' + encodeURIComponent(applyBulk.getAttribute('data-token'));
});

// fit each form's real miniature inside its thumbnail box
function fitMiniForms() {
 document.querySelectorAll('.fm-preview-thumb').forEach(function (thumb) {
  var mini = thumb.querySelector('.fm-mini');
  if (!mini) return;
  mini.style.transform = 'none';
  var w = mini.offsetWidth, h = mini.offsetHeight;
  var s = Math.min((thumb.clientWidth - 4) / w, (thumb.clientHeight - 4) / h);
  mini.style.transform = 'scale(' + s + ')';
  mini.style.left = Math.max(0, (thumb.clientWidth - w * s) / 2) + 'px';
  mini.style.top = '2px';
 });
}
fitMiniForms();
window.addEventListener('load', fitMiniForms);
if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitMiniForms);
}());
