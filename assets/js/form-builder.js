(function () {
  'use strict';

  var list = document.getElementById('fbList');
  if (!list) return;

  var preview = document.getElementById('fbPreview');
  var saveBtn = document.getElementById('fbSave');
  var searchInput = document.getElementById('fbSearch');
  var nicknameInput = document.getElementById('formNickname');
  var variableInput = document.getElementById('formVariable');
  var titleInput = document.getElementById('formTitle');
  var descInput = document.getElementById('formDescription');
  var formName = document.getElementById('fbFormName');
  var shortcodeEl = document.getElementById('shortcodePreview');
  var settingsCard = document.getElementById('formSettingsCard');
  var toast = document.getElementById('fbToast');
  var groups = window.MJD_FIELD_GROUPS || {};
  var lists = window.MJD_FIELD_DATA || {};

  var typeLabels = {};
  Object.keys(groups).forEach(function (g) {
    Object.keys(groups[g]).forEach(function (label) { typeLabels[groups[g][label]] = label; });
  });

  var CHOICE_TYPES = ['select', 'radio', 'checkbox_group'];
  var LIST_TYPES = ['country', 'state', 'top_category', 'years'];
  var TEXT_BLOCK_TYPES = ['html', 'help_alert'];
  var NON_INPUT = ['section', 'html', 'help_alert', 'honeypot', 'captcha', 'submit', 'hidden'];

  var nextId = 1;
  var fields = [];
  var savedFields = [];
  var dirty = false;
  var saving = false;
  var toastTimer = null;

  /* ---------- helpers ---------- */
  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
    });
  }
  function makeId() { return 'new_' + (nextId++); }
  function toVariable(v) {
    return String(v || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/_+/g, '_');
  }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function byId(id) { for (var i = 0; i < fields.length; i++) if (fields[i].id === id) return fields[i]; return null; }
  function indexOfId(id) { for (var i = 0; i < fields.length; i++) if (fields[i].id === id) return i; return -1; }
  function setDirty(v) {
    dirty = v;
    saveBtn.classList.toggle('is-dirty', v);
  }
  function showToast(msg, isError) {
    toast.textContent = msg;
    toast.classList.toggle('is-error', !!isError);
    toast.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.classList.remove('is-visible'); }, isError ? 5000 : 2600);
  }
  function defaultSettings() {
    return { inputView: true, displayView: true, leadPreviews: false, includeEmails: true, tableView: true, adminOnly: false, altInput: '', altDisplay: '', altEmail: '', altTable: '' };
  }
  function newField(type, label, name) {
    return {
      id: makeId(), type: type || 'text', origType: '', label: label == null ? 'New Field' : label,
      name: name == null ? toVariable(label || 'new_field') : name, nameTouched: name != null,
      required: false, placeholder: '', help: '', description: '', options: '', defaultValue: '',
      inputClass: NON_INPUT.indexOf(type) >= 0 && type !== 'hidden' ? '' : 'form-control', displayClass: '', outerId: '',
      settings: defaultSettings(), open: false
    };
  }
  function normalize(f) {
    f.settings = Object.assign(defaultSettings(), f.settings || {});
    f.nameTouched = true;
    f.open = !!f.open;
    return f;
  }
  function optionsOf(f) {
    var o = String(f.options || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
    if (o.length) return o;
    if (f.type === 'country') return lists.countries || [];
    if (f.type === 'state') return lists.states || [];
    if (f.type === 'top_category') return lists.categories || [];
    if (f.type === 'years') { var y = new Date().getFullYear(); return Array.from({ length: 30 }, function (_, i) { return String(y - i); }); }
    return ['Option 1', 'Option 2'];
  }

  /* ---------- left: field widgets ---------- */
  function typeSelect(f) {
    var known = false;
    var html = Object.keys(groups).map(function (g) {
      return '<optgroup label="' + esc(g) + '">' + Object.keys(groups[g]).map(function (label) {
        var v = groups[g][label];
        if (v === f.type) known = true;
        return '<option value="' + esc(v) + '"' + (v === f.type ? ' selected' : '') + '>' + esc(label) + '</option>';
      }).join('') + '</optgroup>';
    }).join('');
    if (!known) html = '<option value="' + esc(f.type) + '" selected>' + esc(f.type) + '</option>' + html;
    return '<select class="fb-ctl" data-p="type" aria-label="Type of Field">' + html + '</select>';
  }
  function toggle(prop, checked, isSetting, label) {
    return '<label class="fb-toggle"><input type="checkbox" ' + (isSetting ? 'data-s="' + prop + '"' : 'data-p="' + prop + '"') +
      (checked ? ' checked' : '') + ' aria-label="' + esc(label) + '"><span class="fb-track"><span class="fb-knob"></span></span></label>';
  }
  function dsItem(label, prop, altProp, f) {
    var s = f.settings;
    return '<div class="fb-ds-item"><label class="fb-lbl">' + label + '</label>' + toggle(prop, !!s[prop], true, label) +
      (altProp ? '<textarea class="fb-ctl" rows="1" data-s="' + altProp + '" placeholder="Alt. Field" aria-label="' + esc(label) + ' alternate field">' + esc(s[altProp]) + '</textarea>' : '') + '</div>';
  }
  function cardHtml(f) {
    var s = f.settings;
    var extra = '';
    if (CHOICE_TYPES.indexOf(f.type) >= 0 || LIST_TYPES.indexOf(f.type) >= 0) {
      extra += '<div class="fb-adv-grid" style="margin-top:14px"><div class="fb-adv-full"><label class="fb-lbl">Field Options (one per line)</label>' +
        '<textarea class="fb-ctl" rows="3" style="overflow:auto" data-p="options" placeholder="Option 1">' + esc(f.options) + '</textarea>' +
        (LIST_TYPES.indexOf(f.type) >= 0 ? '<div class="fb-muted">Leave blank to use the list stored in the database.</div>' : '') + '</div></div>';
    }
    if (TEXT_BLOCK_TYPES.indexOf(f.type) >= 0) {
      extra += '<div class="fb-adv-grid" style="margin-top:14px"><div class="fb-adv-full"><label class="fb-lbl">' + (f.type === 'html' ? 'Custom HTML' : 'Alert Text') + '</label>' +
        '<textarea class="fb-ctl" rows="3" style="overflow:auto" data-p="description">' + esc(f.description) + '</textarea></div></div>';
    }
    return '<div class="fb-strip" tabindex="0" role="button" title="Drag to reorder (or use the Up/Down arrow keys)" aria-label="Reorder field"><i class="fa fa-bars"></i></div>' +
      '<div class="fb-body">' +
      '<div class="fb-row">' +
        '<div class="fb-col"><label>Type of Field</label>' + typeSelect(f) + '</div>' +
        '<div class="fb-col"><label>Required Field?</label>' + toggle('required', f.required, false, 'Required Field?') + '</div>' +
        '<div class="fb-tools">' +
          '<button type="button" class="fb-gear' + (f.open ? ' is-open' : '') + '" data-act="gear" title="Field settings" aria-label="Field settings" aria-expanded="' + (f.open ? 'true' : 'false') + '"><i class="fa fa-cog"></i></button>' +
          '<div class="fb-menu-wrap"><button type="button" class="fb-menu-btn" data-act="menu" aria-haspopup="true">Actions</button>' +
            '<div class="fb-menu"><button type="button" data-act="dup"><i class="fa fa-clone"></i> Duplicate</button><button type="button" data-act="del" class="is-danger"><i class="fa fa-trash-o"></i> Delete</button></div></div>' +
        '</div>' +
      '</div>' +
      '<div class="fb-row">' +
        '<div class="fb-col"><label>Field Label</label><textarea class="fb-ctl" rows="1" data-p="label" placeholder="Field label">' + esc(f.label) + '</textarea></div>' +
        '<div class="fb-col"><label>Database Variable</label><input class="fb-ctl" data-p="name" value="' + esc(f.name) + '" placeholder="database_variable"></div>' +
        '<div class="fb-tools-bottom"><button type="button" class="fb-addfield" data-act="add">Add Field +</button></div>' +
      '</div>' +
      '<div class="fb-adv' + (f.open ? ' is-open' : '') + '">' +
        '<div class="fb-adv-grid">' +
          '<div><label class="fb-lbl">Placeholder Text</label><textarea class="fb-ctl" rows="1" data-p="placeholder" placeholder="Placeholder Text">' + esc(f.placeholder) + '</textarea></div>' +
          '<div><label class="fb-lbl">Field Help Text</label><textarea class="fb-ctl" rows="1" data-p="help" placeholder="Field Instructions">' + esc(f.help) + '</textarea></div>' +
          '<div><label class="fb-lbl">Default Field Value</label><textarea class="fb-ctl" rows="1" data-p="defaultValue" placeholder="Default Value">' + esc(f.defaultValue) + '</textarea></div>' +
        '</div>' +
        '<div class="fb-adv-grid">' +
          '<div><label class="fb-lbl">Addtl. Field CSS Class</label><input class="fb-ctl" data-p="inputClass" value="' + esc(f.inputClass) + '" placeholder="form-control"></div>' +
          '<div><label class="fb-lbl">Outer Element ID</label><input class="fb-ctl" data-p="outerId" maxlength="50" value="' + esc(f.outerId) + '" placeholder="Enter Outer Element ID"></div>' +
        '</div>' + extra +
        '<hr>' +
        '<div class="fb-ds-head"><h4>Display Settings</h4><a href="https://support.brilliantdirectories.com/" target="_blank" rel="noopener">Learn More <i class="fa fa-external-link"></i></a></div>' +
        '<div class="fb-ds">' +
          dsItem('Input View', 'inputView', 'altInput', f) + dsItem('Display View', 'displayView', 'altDisplay', f) +
          dsItem('Lead Previews', 'leadPreviews', null, f) + dsItem('Include In Emails', 'includeEmails', 'altEmail', f) +
          dsItem('Table View', 'tableView', 'altTable', f) +
        '</div>' +
        '<div class="fb-admin"><input type="checkbox" id="adm_' + esc(f.id) + '" data-s="adminOnly"' + (s.adminOnly ? ' checked' : '') + '><label for="adm_' + esc(f.id) + '">Admin Only</label></div>' +
        '<i class="fa fa-question-circle fb-help-icon" title="Only administrators will see this field."></i>' +
      '</div>' +
      '</div>';
  }
  function makeCard(f) {
    var el = document.createElement('div');
    el.className = 'fb-card';
    el.setAttribute('data-id', f.id);
    el.innerHTML = cardHtml(f);
    return el;
  }
  function renderList(keepScroll) {
    var top = list.scrollTop;
    list.innerHTML = '';
    if (!fields.length) {
      list.innerHTML = '<div class="fb-empty"><strong>No fields yet</strong>Add your first field to start building this form.<br><br><button type="button" class="fb-addfield" data-act="add-first">Add Field +</button></div>';
      return;
    }
    fields.forEach(function (f) { list.appendChild(makeCard(f)); });
    applyFilter();
    if (keepScroll) list.scrollTop = top;
  }
  function replaceCard(f) {
    var old = list.querySelector('.fb-card[data-id="' + cssEsc(f.id) + '"]');
    if (!old) return;
    var fresh = makeCard(f);
    old.parentNode.replaceChild(fresh, old);
    applyFilter();
    return fresh;
  }
  function cssEsc(v) { return String(v).replace(/(["\\])/g, '\\$1'); }
  function cardEl(id) { return list.querySelector('.fb-card[data-id="' + cssEsc(id) + '"]'); }

  function applyFilter() {
    var q = (searchInput.value || '').trim().toLowerCase();
    Array.prototype.forEach.call(list.querySelectorAll('.fb-card'), function (card) {
      var f = byId(card.getAttribute('data-id'));
      if (!f) return;
      var hay = (f.label + ' ' + f.name + ' ' + (typeLabels[f.type] || f.type) + ' ' + f.placeholder + ' ' + f.help).toLowerCase();
      card.classList.toggle('is-hidden', q !== '' && hay.indexOf(q) < 0);
    });
  }

  /* ---------- right: preview (rendered from the saved form) ---------- */
  function pvControl(f) {
    var t = f.type, ph = esc(f.placeholder), val = esc(f.defaultValue), opts;
    if (t === 'select' || LIST_TYPES.indexOf(t) >= 0) {
      opts = optionsOf(f);
      return '<select class="pv-control" disabled><option value="">' + (ph || '') + '</option>' + opts.map(function (o) {
        return '<option' + (o === f.defaultValue ? ' selected' : '') + '>' + esc(o) + '</option>';
      }).join('') + '</select>';
    }
    if (t === 'radio' || t === 'checkbox_group' || t === 'yes_no') {
      opts = t === 'yes_no' ? ['Yes', 'No'] : optionsOf(f);
      var type = t === 'checkbox_group' ? 'checkbox' : 'radio';
      return '<div class="pv-choices">' + opts.map(function (o) {
        return '<label><input type="' + type + '" disabled' + (o === f.defaultValue ? ' checked' : '') + '> ' + esc(o) + '</label>';
      }).join('') + '</div>';
    }
    if (t === 'phone_country') {
      return '<div class="pv-phone"><select class="pv-control" disabled><option>+91</option></select><input class="pv-control" type="tel" disabled placeholder="' + ph + '" value="' + val + '"></div>';
    }
    if (t === 'textarea' || t.indexOf('richtext') === 0) {
      return '<textarea class="pv-control" disabled placeholder="' + ph + '">' + val + '</textarea>';
    }
    var map = { email: 'email', tel: 'tel', number: 'number', password: 'password', url: 'url', date: 'date', 'datetime-local': 'datetime-local', file: 'file', price: 'text' };
    var it = map[t] || 'text';
    return '<input class="pv-control" type="' + it + '" disabled placeholder="' + ph + '"' + (it === 'file' ? '' : ' value="' + val + '"') + '>';
  }
  function pvField(f) {
    var s = f.settings || {};
    if (s.inputView === false) return '';
    var t = f.type;
    if (t === 'section') return '<div class="pv-section"><h2>' + esc(f.label) + '</h2></div>';
    if (t === 'honeypot' || t === 'hidden') return '';
    if (t === 'submit') return '<div class="pv-field"><button type="button" class="pv-submit" disabled>' + esc(f.label || 'Submit') + '</button></div>';
    if (t === 'captcha') return '<div class="pv-field"><div class="pv-note"><input type="checkbox" disabled> I\u2019m not a robot (Google reCaptcha)</div></div>';
    if (t === 'html') {
      var txt = f.description || f.label;
      if (/^\s*\[widget=/i.test(txt)) return '<div class="pv-field"><div class="pv-note">' + esc(txt) + '</div></div>';
      return '<div class="pv-field pv-html">' + esc(txt) + '</div>';
    }
    if (t === 'help_alert') return '<div class="pv-field"><div class="pv-alert">' + esc(f.description || f.help || f.label) + '</div></div>';
    var label = '<label class="pv-label">' + (f.required ? '<span class="pv-star">*</span>' : '') + esc(f.label) + (s.adminOnly ? ' <small style="font-weight:400;color:#6c7884">(Admin only)</small>' : '') + '</label>';
    var help = f.help ? '<small class="pv-help">' + esc(f.help) + '</small>' : '';
    return '<div class="pv-field">' + label + pvControl(f) + help + '</div>';
  }
  function renderPreview() {
    var html = savedFields.map(pvField).join('');
    preview.innerHTML = html || '<div class="pv-empty"><strong>Your form preview will appear here</strong>Add fields on the left and press Save Changes.</div>';
  }

  /* ---------- editing ---------- */
  list.addEventListener('input', function (e) { onEdit(e, false); });
  list.addEventListener('change', function (e) { onEdit(e, true); });

  function onEdit(e, isChange) {
    var el = e.target;
    var card = el.closest('.fb-card');
    if (!card) return;
    var f = byId(card.getAttribute('data-id'));
    if (!f) return;
    var p = el.getAttribute('data-p'), s = el.getAttribute('data-s');
    if (p === 'type') {
      if (!isChange) return;
      f.type = el.value;
      if (NON_INPUT.indexOf(f.type) >= 0 && f.type !== 'hidden' && f.inputClass === 'form-control') f.inputClass = '';
      if (NON_INPUT.indexOf(f.type) < 0 && !f.inputClass) f.inputClass = 'form-control';
      var fresh = replaceCard(f);
      var sel = fresh && fresh.querySelector('[data-p="type"]');
      if (sel) sel.focus();
      setDirty(true);
      return;
    }
    if (p) {
      if (el.type === 'checkbox') { if (!isChange) return; f[p] = el.checked; }
      else f[p] = el.value;
      if (p === 'label' && !f.nameTouched) {
        f.name = toVariable(f.label);
        var nameInput = card.querySelector('[data-p="name"]');
        if (nameInput) nameInput.value = f.name;
      }
      if (p === 'name') f.nameTouched = true;
      setDirty(true);
      return;
    }
    if (s) {
      if (el.type === 'checkbox') { if (!isChange) return; f.settings[s] = el.checked; }
      else f.settings[s] = el.value;
      setDirty(true);
    }
  }

  function addAfter(id) {
    var idx = id ? indexOfId(id) : fields.length - 1;
    var f = newField('text', 'New Field');
    fields.splice(idx + 1, 0, f);
    renderList(true);
    setDirty(true);
    var el = cardEl(f.id);
    if (el) {
      el.classList.add('is-flash');
      el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      var lbl = el.querySelector('[data-p="label"]');
      if (lbl) { lbl.focus(); lbl.select(); }
    }
  }
  function closeMenus() {
    Array.prototype.forEach.call(list.querySelectorAll('.fb-menu.is-open'), function (m) { m.classList.remove('is-open'); });
  }
  list.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn) return;
    var act = btn.getAttribute('data-act');
    var card = btn.closest('.fb-card');
    var f = card ? byId(card.getAttribute('data-id')) : null;
    if (act === 'add-first') { addAfter(null); return; }
    if (!f) return;
    if (act === 'gear') {
      f.open = !f.open;
      card.querySelector('.fb-adv').classList.toggle('is-open', f.open);
      btn.classList.toggle('is-open', f.open);
      btn.setAttribute('aria-expanded', f.open ? 'true' : 'false');
    } else if (act === 'menu') {
      var menu = card.querySelector('.fb-menu');
      var wasOpen = menu.classList.contains('is-open');
      closeMenus();
      menu.classList.toggle('is-open', !wasOpen);
      e.stopPropagation();
    } else if (act === 'add') {
      addAfter(f.id);
    } else if (act === 'dup') {
      var copy = clone(f);
      copy.id = makeId(); copy.origType = ''; copy.label = f.label + ' Copy'; copy.name = toVariable(copy.label); copy.nameTouched = false;
      fields.splice(indexOfId(f.id) + 1, 0, copy);
      renderList(true); setDirty(true);
      var c = cardEl(copy.id); if (c) { c.classList.add('is-flash'); c.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
    } else if (act === 'del') {
      if (!window.confirm('Delete the field \u201C' + (f.label || 'Untitled') + '\u201D? This takes effect when you save.')) { closeMenus(); return; }
      fields.splice(indexOfId(f.id), 1);
      renderList(true); setDirty(true);
    }
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.fb-menu-wrap')) closeMenus(); });
  searchInput.addEventListener('input', applyFilter);

  /* ---------- reordering: drag the dark strip (mouse/touch) or use arrow keys on it ---------- */
  var drag = null;
  var dropLine = document.createElement('div');
  dropLine.className = 'fb-drop-line';

  list.addEventListener('pointerdown', function (e) {
    var strip = e.target.closest('.fb-strip');
    if (!strip || (e.pointerType === 'mouse' && e.button !== 0)) return;
    var card = strip.closest('.fb-card');
    e.preventDefault();
    var cards = Array.prototype.filter.call(list.querySelectorAll('.fb-card'), function (c) { return !c.classList.contains('is-hidden'); });
    drag = {
      card: card, id: card.getAttribute('data-id'), strip: strip, pointerId: e.pointerId,
      startY: e.clientY, startScroll: list.scrollTop, lastY: e.clientY, target: null, raf: null,
      rects: cards.map(function (c) { return { id: c.getAttribute('data-id'), top: c.offsetTop, h: c.offsetHeight }; })
    };
    var me = drag.rects.filter(function (r) { return r.id === drag.id; })[0];
    drag.center = me.top + me.h / 2;
    drag.minDy = -me.top;                                   // keep the dragged card inside the list so it
    drag.maxDy = list.scrollHeight - (me.top + me.h);      // cannot extend the scroll area (runaway scrolling)
    try { strip.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
    card.classList.add('is-dragging');
    list.appendChild(dropLine);
    dropLine.style.display = 'none';
    closeMenus();
    loop();
  });
  list.addEventListener('pointermove', function (e) { if (drag && e.pointerId === drag.pointerId) { drag.lastY = e.clientY; update(); } });
  list.addEventListener('pointerup', endDrag);
  list.addEventListener('pointercancel', function (e) { endDrag(e, true); });

  function loop() {
    if (!drag) return;
    var r = list.getBoundingClientRect(), edge = 44, speed = 0;
    if (drag.lastY < r.top + edge) speed = -Math.ceil((r.top + edge - drag.lastY) / 4);
    else if (drag.lastY > r.bottom - edge) speed = Math.ceil((drag.lastY - (r.bottom - edge)) / 4);
    if (speed) { list.scrollTop += Math.max(-24, Math.min(24, speed)); update(); }
    drag.raf = requestAnimationFrame(loop);
  }
  function update() {
    var dy = (drag.lastY - drag.startY) + (list.scrollTop - drag.startScroll);
    dy = Math.max(drag.minDy, Math.min(drag.maxDy, dy));
    drag.card.style.transform = 'translateY(' + dy + 'px)';
    var y = drag.center + dy, others = drag.rects.filter(function (r) { return r.id !== drag.id; });
    var target = { before: null }, lineY;
    for (var i = 0; i < others.length; i++) {
      if (y < others[i].top + others[i].h / 2) { target.before = others[i].id; lineY = others[i].top - 10; break; }
    }
    if (target.before === null) {
      var last = others[others.length - 1];
      lineY = last ? last.top + last.h + 6 : 0;
    }
    drag.target = target;
    dropLine.style.display = 'block';
    dropLine.style.top = Math.max(0, lineY) + 'px';
  }
  function endDrag(e, cancelled) {
    if (!drag || (e && e.pointerId !== drag.pointerId)) return;
    cancelAnimationFrame(drag.raf);
    try { drag.strip.releasePointerCapture(drag.pointerId); } catch (err) { /* ignore */ }
    var moved = false;
    if (!cancelled && drag.target) {
      var from = indexOfId(drag.id), f = fields.splice(from, 1)[0];
      var to = drag.target.before === null ? fields.length : indexOfId(drag.target.before);
      fields.splice(to, 0, f);
      moved = to !== from;
    }
    var id = drag.id;
    drag = null;
    if (dropLine.parentNode) dropLine.parentNode.removeChild(dropLine);
    renderList(true);
    if (moved) { setDirty(true); var c = cardEl(id); if (c) c.classList.add('is-flash'); }
  }
  list.addEventListener('keydown', function (e) {
    var strip = e.target.closest && e.target.closest('.fb-strip');
    if (!strip || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
    e.preventDefault();
    var id = strip.closest('.fb-card').getAttribute('data-id');
    var i = indexOfId(id), j = e.key === 'ArrowUp' ? i - 1 : i + 1;
    if (j < 0 || j >= fields.length) return;
    var f = fields.splice(i, 1)[0];
    fields.splice(j, 0, f);
    renderList(true); setDirty(true);
    var c = cardEl(id); if (c) { var s = c.querySelector('.fb-strip'); if (s) s.focus(); c.scrollIntoView({ block: 'nearest' }); }
  });

  /* ---------- form settings / shortcode ---------- */
  function refreshNickname() {
    var v = toVariable(nicknameInput.value);
    variableInput.value = v;
    shortcodeEl.textContent = '[form=' + (v || 'your_form_variable') + ']';
    formName.textContent = nicknameInput.value.trim() || 'Untitled form';
    setDirty(true);
  }
  nicknameInput.addEventListener('input', refreshNickname);
  titleInput.addEventListener('input', function () { setDirty(true); });
  descInput.addEventListener('input', function () { setDirty(true); });
  document.getElementById('toggleFormSettings').addEventListener('click', function () {
    var collapsed = settingsCard.classList.toggle('is-collapsed');
    this.setAttribute('aria-expanded', String(!collapsed));
  });
  document.getElementById('copyShortcode').addEventListener('click', function () {
    var text = shortcodeEl.textContent, btn = this, original = btn.innerHTML;
    function done() { btn.innerHTML = '<i class="fa fa-check"></i> Copied'; setTimeout(function () { btn.innerHTML = original; }, 1500); }
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copy this shortcode:', text); });
    else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (err) { window.prompt('Copy this shortcode:', text); } t.remove(); }
  });

  /* ---------- save: store in the database, then refresh the preview from what was stored ---------- */
  function save() {
    if (saving) return;
    if (!toVariable(nicknameInput.value)) {
      settingsCard.classList.remove('is-collapsed');
      nicknameInput.focus();
      showToast('Enter a form nickname containing at least one letter or number.', true);
      return;
    }
    saving = true; saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026';
    var form = document.getElementById('formBuilder');
    var body = new FormData();
    body.append('csrf_token', form.elements.csrf_token.value);
    body.append('form_id', form.elements.form_id.value);
    body.append('form_nickname', nicknameInput.value);
    body.append('form_title', titleInput.value);
    body.append('form_desc', descInput.value);
    body.append('fields_json', JSON.stringify(fields.map(function (f) {
      var c = clone(f); delete c.open; delete c.nameTouched; return c;
    })));
    fetch(window.MJD_SAVE_URL, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (err) { throw new Error('The server returned an unexpected response.'); } }); })
      .then(function (res) {
        if (!res.ok) throw new Error(res.message || 'Save failed.');
        var openByIndex = fields.map(function (f) { return f.open; });
        fields = res.fields.map(function (f, i) { f = normalize(f); f.open = !!openByIndex[i]; return f; });
        savedFields = clone(fields);
        if (res.form) {
          variableInput.value = res.form.variable;
          shortcodeEl.textContent = res.form.shortcode;
          if (!titleInput.value.trim()) titleInput.value = res.form.title;
        }
        renderList(true);
        renderPreview();
        setDirty(false);
        showToast('Changes saved \u2014 preview updated.');
      })
      .catch(function (err) { showToast(err.message || 'Save failed.', true); })
      .then(function () { saving = false; saveBtn.disabled = false; saveBtn.textContent = 'Save Changes'; });
  }
  saveBtn.addEventListener('click', save);
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); save(); }
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- start ---------- */
  var initial = Array.isArray(window.MJD_INITIAL_FIELDS) ? window.MJD_INITIAL_FIELDS : [];
  var starting = false;
  if (window.MJD_IS_NEW_FORM && !initial.length) {
    // A brand-new form starts with the standard contact-form fields.
    initial = [
      newField('section', 'Form header title', 'title'), newField('text', 'Enter Name', 'yourname'),
      newField('email', 'Enter Email', 'inquiry_email'), newField('tel', 'Enter Phone', 'phone'),
      newField('textarea', 'Enter Message', 'comments'), newField('submit', 'Submit Message', 'savebutton')
    ];
    initial[5].inputClass = 'btn btn-primary btn-lg btn-block';
    starting = true;
  }
  fields = initial.map(normalize);
  savedFields = clone(fields);
  renderList(false);
  renderPreview();
  setDirty(starting);
}());
