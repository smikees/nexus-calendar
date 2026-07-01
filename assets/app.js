(function () {
  'use strict';

  var APP_VERSION = '0.8.1';
  var K = {
    enabled: 'nc_enabled_v1', view: 'nc_view_v1', order: 'nc_order_v1',
    colors: 'nc_colors_v1', mode: 'nc_mode_v1', theme: 'nc_theme_v1',
    sidebar: 'nc_sidebar_w_v1'
  };
  var EMOJIS = [
    { e: '📅', k: 'calendar date schedule' }, { e: '📆', k: 'calendar date tearoff' },
    { e: '🗓️', k: 'calendar spiral planner' }, { e: '⏰', k: 'alarm clock time reminder' },
    { e: '⌚', k: 'watch time' }, { e: '⭐', k: 'star favorite important' },
    { e: '🌟', k: 'star sparkle special' }, { e: '🔥', k: 'fire hot streak trending' },
    { e: '💼', k: 'work business briefcase job' }, { e: '🏠', k: 'home house' },
    { e: '🏢', k: 'office building work company' }, { e: '🎉', k: 'party celebration event' },
    { e: '🎊', k: 'party confetti celebration' }, { e: '🎂', k: 'birthday cake' },
    { e: '🎁', k: 'gift present birthday' }, { e: '🎓', k: 'graduation school education study' },
    { e: '✈️', k: 'travel flight plane trip vacation' }, { e: '🏖️', k: 'beach vacation holiday travel' },
    { e: '🏝️', k: 'island vacation travel' }, { e: '🗺️', k: 'map travel trip' },
    { e: '🚗', k: 'car drive commute travel' }, { e: '🚆', k: 'train commute travel' },
    { e: '🍽️', k: 'food dinner restaurant meal eat' }, { e: '☕', k: 'coffee break meeting cafe' },
    { e: '🍺', k: 'beer drinks social happy hour' }, { e: '🍷', k: 'wine drinks dinner' },
    { e: '🍕', k: 'pizza food lunch' }, { e: '🏃', k: 'run running exercise fitness sport' },
    { e: '🏋️', k: 'gym workout fitness exercise weights' }, { e: '🧘', k: 'yoga meditation wellness health' },
    { e: '⚽', k: 'soccer football sport' }, { e: '🏀', k: 'basketball sport' },
    { e: '🏈', k: 'football sport' }, { e: '⚾', k: 'baseball sport' },
    { e: '🎾', k: 'tennis sport' }, { e: '🏊', k: 'swimming sport pool' },
    { e: '🚴', k: 'cycling bike sport' }, { e: '⛳', k: 'golf sport' },
    { e: '🎮', k: 'gaming games play' }, { e: '🎲', k: 'games board dice' },
    { e: '🎸', k: 'music guitar band concert' }, { e: '🎵', k: 'music note song' },
    { e: '🎤', k: 'music sing karaoke concert' }, { e: '🎧', k: 'music headphones podcast' },
    { e: '🎬', k: 'movie film cinema' }, { e: '🎨', k: 'art paint creative design' },
    { e: '📷', k: 'photo camera picture' }, { e: '📚', k: 'books study reading learn' },
    { e: '📖', k: 'book reading study' }, { e: '✏️', k: 'pencil write note edit' },
    { e: '📝', k: 'note memo write task' }, { e: '💡', k: 'idea light tip lightbulb' },
    { e: '💻', k: 'computer laptop work code' }, { e: '🖥️', k: 'computer desktop work' },
    { e: '🔧', k: 'tools fix maintenance repair' }, { e: '🔨', k: 'hammer build tools' },
    { e: '⚙️', k: 'settings gear config' }, { e: '🩺', k: 'doctor health medical appointment' },
    { e: '💊', k: 'medicine pills health meds' }, { e: '🏥', k: 'hospital health medical' },
    { e: '🦷', k: 'dentist teeth health' }, { e: '💰', k: 'money finance budget cash' },
    { e: '💵', k: 'money cash dollar finance' }, { e: '💳', k: 'card payment finance money' },
    { e: '📈', k: 'chart growth finance stocks up trending' }, { e: '📉', k: 'chart finance down loss stocks' },
    { e: '📊', k: 'chart data analytics report' }, { e: '🧾', k: 'receipt bill invoice finance' },
    { e: '🛒', k: 'shopping cart groceries buy' }, { e: '🛍️', k: 'shopping bags buy' },
    { e: '🐶', k: 'dog pet animal' }, { e: '🐱', k: 'cat pet animal' },
    { e: '🐾', k: 'pet paw animal vet' }, { e: '🌱', k: 'plant grow garden nature' },
    { e: '🌳', k: 'tree nature outdoors' }, { e: '🌍', k: 'world earth global travel' },
    { e: '☀️', k: 'sun weather sunny day' }, { e: '🌙', k: 'moon night' },
    { e: '⛅', k: 'weather cloud partly' }, { e: '🌧️', k: 'rain weather' },
    { e: '❄️', k: 'snow winter cold weather' }, { e: '❤️', k: 'love heart red favorite' },
    { e: '💙', k: 'blue heart love' }, { e: '💚', k: 'green heart love' },
    { e: '💜', k: 'purple heart love' }, { e: '🧡', k: 'orange heart love' },
    { e: '💛', k: 'yellow heart love' }, { e: '✅', k: 'check done complete task ok' },
    { e: '❗', k: 'important exclamation alert' }, { e: '❓', k: 'question help unknown' },
    { e: '📌', k: 'pin important' }, { e: '📍', k: 'location pin place map' },
    { e: '🔔', k: 'bell reminder notification alert' }, { e: '🎯', k: 'target goal focus objective' },
    { e: '🚀', k: 'launch rocket startup project' }, { e: '🏆', k: 'trophy win award achievement' },
    { e: '🎄', k: 'christmas holiday tree' }, { e: '🎃', k: 'halloween pumpkin holiday' },
    { e: '💍', k: 'wedding ring engagement anniversary' }, { e: '👶', k: 'baby kids family' }
  ];
  var calendarsById = {};
  var fc = null;
  var eeId = null;          // event id being edited (null = creating)
  var eeSaving = false;     // in-flight save guard (prevents duplicate events)
  var eeScope = null;       // null | 'series' | 'occurrence' (recurring edits)
  var eeSeriesId = null;    // series row id when editing a single occurrence
  var eeRecurrenceId = null;// original occurrence start (ISO/date) for occurrence edits
  var ceId = null;          // calendar id being edited (null = creating)
  var ceShareCalId = null;  // calendar id whose shares are shown
  var ceFeedCalId = null;   // calendar id of the feed subscription being managed

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function readJSON(k, fb) { try { var v = JSON.parse(localStorage.getItem(k)); return v == null ? fb : v; } catch (e) { return fb; } }
  function writeJSON(k, v) { localStorage.setItem(k, JSON.stringify(v)); }
  function $(id) { return document.getElementById(id); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toLocalInput(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  function toDateInput(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function showErr(id, msg) { var el = $(id); el.textContent = msg || ''; el.hidden = !msg; }

  /* ---------- API helper (sends CSRF header on writes) ---------- */
  function api(method, path, body) {
    var opts = { method: method, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } };
    if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(path, opts).then(function (r) {
      return r.json().then(
        function (j) { return { ok: r.ok, status: r.status, data: j }; },
        function () { return { ok: r.ok, status: r.status, data: {} }; }
      );
    });
  }

  /* ---------- theme ---------- */
  function initTheme() {
    var root = document.documentElement;
    var btn = $('mode-toggle');
    var sel = $('theme-select');
    sel.value = root.dataset.theme || 'slate';
    btn.addEventListener('click', function () {
      var m = (root.dataset.mode === 'dark') ? 'light' : 'dark';
      root.dataset.mode = m; localStorage.setItem(K.mode, m);
    });
    sel.addEventListener('change', function () {
      root.dataset.theme = sel.value; localStorage.setItem(K.theme, sel.value);
    });
  }

  /* ---------- ordering / colors / enabled ---------- */
  function orderedSlugs() {
    var def = Object.keys(calendarsById);
    var stored = readJSON(K.order, null);
    if (!stored) return def.slice();
    var out = stored.filter(function (s) { return calendarsById[s]; });
    def.forEach(function (s) { if (out.indexOf(s) < 0) out.push(s); });
    return out;
  }
  function orderIndex(slug) { var i = orderedSlugs().indexOf(slug); return i < 0 ? 999 : i; }
  // Single source of truth: the calendar's own color (managed in the edit window).
  function colorFor(slug) {
    return (calendarsById[slug] && calendarsById[slug].color) || '#888888';
  }
  function enabledSet() {
    var stored = readJSON(K.enabled, null);
    if (stored === null) return new Set(Object.keys(calendarsById));
    return new Set(stored);
  }
  function editableCalendars() {
    return orderedSlugs().map(function (s) { return calendarsById[s]; })
      .filter(function (c) { return c && c.canEdit; });
  }

  /* ---------- sidebar ---------- */
  function renderSidebar(user) {
    var list = $('calendar-list');
    var slugs = orderedSlugs();
    var enabled = enabledSet();
    if (!slugs.length) { list.innerHTML = '<p class="muted small">No calendars yet.</p>'; }
    else {
      list.innerHTML = '';
      slugs.forEach(function (slug) {
        var c = calendarsById[slug];
        var row = document.createElement('div');
        row.className = 'cal-row'; row.dataset.slug = slug; row.draggable = true;
        row.innerHTML =
          '<span class="drag-handle" title="Drag to reorder" aria-label="Drag to reorder">⠿</span>' +
          '<label class="cbx" style="--cal-color:' + esc(colorFor(slug)) + '" title="Show / hide">' +
            '<input type="checkbox" data-act="toggle"' + (enabled.has(slug) ? ' checked' : '') + '>' +
            '<span class="cbx-box"></span>' +
          '</label>' +
          '<span class="cal-name">' + (c.icon ? esc(c.icon) + ' ' : '') + esc(c.name) + '</span>' +
          (c.canManage ? '<button class="robtn manage" data-act="manage" title="Edit calendar" aria-label="Edit calendar">✎</button>' : '');
        list.appendChild(row);
      });
    }
    $('user-tools').hidden = !user;
  }

  function onSidebarClick(ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-act]') : null;
    if (!btn) return;
    var row = ev.target.closest('.cal-row'); if (!row) return;
    var slug = row.dataset.slug;
    var act = btn.dataset.act;
    if (act === 'manage') {
      openCalEditor(calendarsById[slug]);
    }
  }

  /* ---------- drag & drop reordering ---------- */
  function calRowAfter(list, y) {
    var rows = Array.prototype.slice.call(list.querySelectorAll('.cal-row:not(.dragging)'));
    var closest = { offset: -Infinity, el: null };
    rows.forEach(function (r) {
      var box = r.getBoundingClientRect();
      var off = y - box.top - box.height / 2;
      if (off < 0 && off > closest.offset) closest = { offset: off, el: r };
    });
    return closest.el;
  }
  function onSidebarDragStart(ev) {
    var row = ev.target.closest ? ev.target.closest('.cal-row') : null;
    if (!row) return;
    row.classList.add('dragging');
    ev.dataTransfer.effectAllowed = 'move';
    try { ev.dataTransfer.setData('text/plain', row.dataset.slug); } catch (e) {}
  }
  function onSidebarDragOver(ev) {
    var list = $('calendar-list');
    var dragging = list.querySelector('.cal-row.dragging');
    if (!dragging) return;
    ev.preventDefault();
    ev.dataTransfer.dropEffect = 'move';
    var after = calRowAfter(list, ev.clientY);
    if (after == null) list.appendChild(dragging);
    else if (after !== dragging) list.insertBefore(dragging, after);
  }
  function onSidebarDragEnd() {
    var list = $('calendar-list');
    var dragging = list.querySelector('.cal-row.dragging');
    if (dragging) dragging.classList.remove('dragging');
    var order = Array.prototype.map.call(list.querySelectorAll('.cal-row'), function (r) { return r.dataset.slug; });
    if (order.length) { writeJSON(K.order, order); if (fc) fc.refetchEvents(); }
  }
  function onSidebarChange(ev) {
    var t = ev.target; var row = t.closest('.cal-row'); if (!row) return;
    var slug = row.dataset.slug;
    if (t.dataset.act === 'toggle') {
      var set = enabledSet();
      if (t.checked) set.add(slug); else set.delete(slug);
      writeJSON(K.enabled, Array.from(set));
      if (fc) fc.refetchEvents();
    }
  }

  /* ---------- header auth ---------- */
  function renderHeader(user) {
    var el = $('auth');
    if (user) {
      var admin = user.is_admin ? ' <span class="muted small">· admin</span>' : '';
      var avatar = user.avatar ? '<img class="avatar" src="' + esc(user.avatar) + '" alt="" referrerpolicy="no-referrer" onerror="this.style.display=&#39;none&#39;">' : '';
      var name = user.name || user.email;
      el.innerHTML = avatar + '<span class="user">' + esc(name) + '</span>' + admin +
        ' <a class="btn small" href="/auth/logout.php">Log out</a>';
    } else {
      el.innerHTML = '<a class="btn primary small" href="/auth/login.php">Sign in with Google</a>';
    }
  }

  /* ---------- event detail modal ---------- */
  /* ---------- tiny, safe Markdown renderer ----------
     Dependency-free (no build step on this host). HTML is fully escaped FIRST,
     then only a fixed whitelist of tags we generate ourselves is introduced, so
     there is no injection surface. Link hrefs are validated to http/https/mailto. */
  function mdEscape(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function mdUnescape(s) {
    return s.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
  }
  function mdSafeUrl(url) {
    var u = mdUnescape(String(url)).trim();
    return /^(https?:\/\/|mailto:)/i.test(u) ? u : '';
  }
  function mdInline(text) {
    // `text` is already HTML-escaped. Protect inline code spans from further formatting.
    var codes = [];
    var out = text.replace(/`([^`]+)`/g, function (_, c) {
      codes.push(c); return '' + (codes.length - 1) + '';
    });
    out = out.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (_, t, u) {
      var url = mdSafeUrl(u);
      if (!url) return t;
      return '<a href="' + mdEscape(url) + '" target="_blank" rel="noopener noreferrer">' + t + '</a>';
    });
    out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
             .replace(/__([^_]+)__/g, '<strong>$1</strong>')
             .replace(/(^|[^*])\*([^*\s][^*]*?)\*/g, '$1<em>$2</em>')
             .replace(/(^|[^_])_([^_\s][^_]*?)_/g, '$1<em>$2</em>');
    return out.replace(/(\d+)/g, function (_, i) { return '<code>' + codes[i] + '</code>'; });
  }
  function renderMarkdown(src) {
    var lines = mdEscape(src).replace(/\r\n?/g, '\n').split('\n');
    var html = [], para = [], listType = null;
    function closeList() { if (listType) { html.push('</' + listType + '>'); listType = null; } }
    function flushPara() { if (para.length) { html.push('<p>' + mdInline(para.join('<br>')) + '</p>'); para = []; } }
    for (var i = 0; i < lines.length; i++) {
      var raw = lines[i].replace(/\s+$/, '');
      if (!raw.trim()) { flushPara(); closeList(); continue; }
      var h = raw.match(/^(#{1,6})\s+(.*)$/);
      if (h) { flushPara(); closeList(); html.push('<h' + h[1].length + '>' + mdInline(h[2]) + '</h' + h[1].length + '>'); continue; }
      var bq = raw.match(/^>\s?(.*)$/);
      if (bq) { flushPara(); closeList(); html.push('<blockquote>' + mdInline(bq[1]) + '</blockquote>'); continue; }
      var ul = raw.match(/^[-*+]\s+(.*)$/), ol = raw.match(/^\d+\.\s+(.*)$/);
      if (ul || ol) {
        flushPara();
        var t = ul ? 'ul' : 'ol';
        if (listType && listType !== t) closeList();
        if (!listType) { listType = t; html.push('<' + t + '>'); }
        html.push('<li>' + mdInline(ul ? ul[1] : ol[1]) + '</li>');
        continue;
      }
      closeList();
      para.push(raw);
    }
    flushPara(); closeList();
    return html.join('');
  }

  /* ---------- Markdown formatting toolbar (textarea helpers) ---------- */
  function mdWrap(ta, before, after, placeholder) {
    var s = ta.selectionStart, e = ta.selectionEnd, val = ta.value;
    var sel = val.slice(s, e) || placeholder || '';
    ta.value = val.slice(0, s) + before + sel + after + val.slice(e);
    ta.selectionStart = s + before.length;
    ta.selectionEnd = s + before.length + sel.length;
    ta.focus();
  }
  function mdLinePrefix(ta, prefix) {
    var s = ta.selectionStart, e = ta.selectionEnd, val = ta.value;
    var ls = val.lastIndexOf('\n', s - 1) + 1;
    var le = val.indexOf('\n', e); if (le === -1) le = val.length;
    var rep = val.slice(ls, le).split('\n').map(function (ln) {
      return ln ? prefix + ln : ln;
    }).join('\n');
    ta.value = val.slice(0, ls) + rep + val.slice(le);
    ta.selectionStart = ls;
    ta.selectionEnd = ls + rep.length;
    ta.focus();
  }
  function mdLink(ta) {
    var s = ta.selectionStart, e = ta.selectionEnd, val = ta.value;
    var sel = val.slice(s, e) || 'text';
    ta.value = val.slice(0, s) + '[' + sel + '](url)' + val.slice(e);
    var us = s + sel.length + 3; // after '[' + sel + ']('
    ta.selectionStart = us;
    ta.selectionEnd = us + 3; // selects 'url'
    ta.focus();
  }
  function applyMd(ta, type) {
    if (!ta) return;
    switch (type) {
      case 'bold': return mdWrap(ta, '**', '**', 'bold');
      case 'italic': return mdWrap(ta, '*', '*', 'italic');
      case 'code': return mdWrap(ta, '`', '`', 'code');
      case 'heading': return mdLinePrefix(ta, '## ');
      case 'ul': return mdLinePrefix(ta, '- ');
      case 'link': return mdLink(ta);
    }
  }

  function openModal(info) {
    var ev = info.event;
    var p = ev.extendedProps || {};
    window._ncCurrent = ev;
    $('modal-title').textContent = ev.title;
    var calLine = p.calendarName || '';
    if (p.recurring) calLine += ' · ↻ repeats';
    $('modal-cal').textContent = calLine;
    var s = ev.start, e = ev.end;
    var opts = ev.allDay
      ? { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }
      : { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    var when = s ? s.toLocaleString(undefined, opts) : '';
    if (e && !ev.allDay) when += ' – ' + e.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    $('modal-when').textContent = when;
    $('modal-where').textContent = p.location || '';
    $('modal-desc').innerHTML = renderMarkdown(p.description || '');
    // Calendar-color accent + faded icon for quick visual identification.
    var color = colorFor(p.calendar) || ev.backgroundColor || 'var(--border)';
    var cal = calendarsById[p.calendar];
    var icon = p.icon || (cal && cal.icon) || '';
    var card = $('event-modal').querySelector('.modal-card');
    if (card) card.style.setProperty('--modal-accent', color);
    $('modal-icon').textContent = icon;
    // Recurring instances AND moved-occurrence overrides both belong to a series,
    // so offer both scopes right here instead of a single generic Edit.
    var seriesRelated = !!(p.recurring || p.partOfSeries);
    $('modal-edit').hidden = seriesRelated;
    $('modal-edit-occ').hidden = !seriesRelated;
    $('modal-edit-series').hidden = !seriesRelated;
    $('modal-actions').hidden = !p.canEdit;
    $('event-modal').hidden = false;
  }
  function closeModal() { $('event-modal').hidden = true; }

  /* ---------- event editor ---------- */
  function normFromEvent(ev, scope) {
    var p = ev.extendedProps || {};
    var span = ev.allDay ? 86400000 : 3600000;
    var occ = (scope === 'occurrence');
    var start, endEx, recurrenceId = null;
    if (p.recurring && !occ && p.startUtc) {
      // Edit the whole series from its base start, not the clicked occurrence.
      start = ev.allDay ? new Date(String(p.startUtc).substr(0, 10) + 'T00:00:00') : new Date(p.startUtc);
      endEx = ev.allDay ? new Date(String(p.endUtc).substr(0, 10) + 'T00:00:00') : new Date(p.endUtc);
    } else {
      // This occurrence (or a plain one-off): use the clicked instance's times.
      start = ev.start;
      endEx = ev.end || new Date(ev.start.getTime() + span);
    }
    if (p.recurring && occ) {
      recurrenceId = ev.allDay ? String(ev.startStr).substr(0, 10) : ev.start.toISOString();
    }
    return {
      id: parseInt(ev.id, 10),
      seriesId: parseInt(ev.id, 10),
      scope: p.recurring ? (occ ? 'occurrence' : 'series') : null,
      recurrenceId: recurrenceId,
      calendarId: p.calendarId,
      title: ev.title,
      allDay: ev.allDay,
      start: start,
      endExclusive: endEx,
      location: p.location,
      description: p.description,
      icon: p.icon || '',
      rrule: (p.recurring && !occ) ? (p.rruleBody || '') : ''
    };
  }
  function toggleAllDayFields(on) {
    $('ee-timed').hidden = on;
    $('ee-allday-fields').hidden = !on;
  }
  // Tint the closed <select> to match the chosen calendar's color.
  function paintCalSelect() {
    var sel = $('ee-cal');
    var opt = sel.options[sel.selectedIndex];
    sel.style.color = opt ? opt.style.color : '';
  }
  function openEventEditor(norm) {
    eeId = norm.id || null;
    eeScope = norm.scope || null;
    eeSeriesId = norm.seriesId || null;
    eeRecurrenceId = norm.recurrenceId || null;
    var isOcc = (eeScope === 'occurrence');
    $('ee-heading').textContent = eeId
      ? (isOcc ? 'Edit this occurrence' : (eeScope === 'series' ? 'Edit series' : 'Edit event'))
      : 'New event';
    // A single occurrence (or a detached one) can't change the repeat rule.
    $('ee-repeat-row').hidden = isOcc || !!norm.hideRepeat;
    $('ee-title-input').value = norm.title || '';
    var sel = $('ee-cal'); sel.innerHTML = '';
    editableCalendars().forEach(function (c) {
      var o = document.createElement('option');
      o.value = c.id;
      o.textContent = '● ' + (c.icon ? c.icon + ' ' : '') + c.name;
      o.style.color = colorFor(c.slug);           // colored swatch + label (Chrome/Edge)
      sel.appendChild(o);
    });
    if (norm.calendarId) sel.value = String(norm.calendarId);
    paintCalSelect();
    var allday = !!norm.allDay;
    $('ee-allday').checked = allday;
    var start = norm.start || new Date();
    var endEx = norm.endExclusive || new Date(start.getTime() + 3600000);
    $('ee-start').value = toLocalInput(start);
    $('ee-end').value = toLocalInput(endEx);
    $('ee-start-date').value = toDateInput(start);
    var endIncl = new Date(endEx.getTime()); endIncl.setDate(endIncl.getDate() - 1);
    if (endIncl < start) endIncl = new Date(start.getTime());
    $('ee-end-date').value = toDateInput(endIncl);
    $('ee-location').value = norm.location || '';
    $('ee-desc').value = norm.description || '';
    setIcon('ee', norm.icon || '');
    $('ee-emoji-pop').hidden = true;
    $('ee-delete').hidden = !eeId;
    toggleAllDayFields(allday);
    parseRRULE(norm.rrule || '');
    showErr('ee-error', '');
    $('event-editor').hidden = false;
    $('ee-title-input').focus();
  }
  function closeEditor() { $('event-editor').hidden = true; }

  function saveEvent(e) {
    e.preventDefault();
    var title = $('ee-title-input').value.trim();
    var calId = parseInt($('ee-cal').value, 10);
    if (!title) { showErr('ee-error', 'Title is required.'); return; }
    if (!calId) { showErr('ee-error', 'Pick a calendar.'); return; }
    var allday = $('ee-allday').checked;
    var body = {
      title: title, calendar_id: calId, all_day: allday,
      location: $('ee-location').value.trim(), description: $('ee-desc').value.trim(),
      icon: $('ee-icon').value, rrule: buildRRULE()
    };
    if (allday) {
      var sd = $('ee-start-date').value;
      var ed = $('ee-end-date').value || sd;
      if (!sd) { showErr('ee-error', 'Start date is required.'); return; }
      body.start = sd;
      var d = new Date(ed + 'T00:00:00'); d.setDate(d.getDate() + 1); // store exclusive end
      body.end = toDateInput(d);
    } else {
      var sv = $('ee-start').value, ev2 = $('ee-end').value;
      if (!sv) { showErr('ee-error', 'Start is required.'); return; }
      body.start = new Date(sv).toISOString();
      body.end = ev2 ? new Date(ev2).toISOString() : '';
    }
    if (eeScope === 'occurrence') {
      // Edit just this instance: detach it from the series (server adds an EXDATE).
      delete body.rrule;
      body.scope = 'occurrence';
      body.recurrence_id = eeRecurrenceId;
    }
    if (eeSaving) return;          // guard against accidental double-submit
    eeSaving = true;
    var saveBtn = $('ee-form').querySelector('button[type="submit"]');
    if (saveBtn) saveBtn.disabled = true;
    var req = eeId
      ? api('PATCH', '/api/event.php', Object.assign({ id: eeId }, body))
      : api('POST', '/api/event.php', body);
    req.then(function (res) {
      eeSaving = false;
      if (saveBtn) saveBtn.disabled = false;
      if (!res.ok) { showErr('ee-error', (res.data && res.data.message) || 'Could not save event.'); return; }
      closeEditor();
      if (fc) fc.refetchEvents();
    });
  }

  function deleteEvent(id) {
    if (!id || !confirm('Delete this event?')) return;
    api('DELETE', '/api/event.php', { id: id }).then(function (res) {
      if (res.ok) { closeEditor(); closeModal(); if (fc) fc.refetchEvents(); }
      else alert((res.data && res.data.message) || 'Could not delete event.');
    });
  }

  // Delete a single occurrence of a series (server EXDATEs it + drops any override).
  function deleteOccurrence(seriesId, recurrenceId) {
    if (!seriesId || !recurrenceId) return;
    api('DELETE', '/api/event.php', { id: seriesId, scope: 'occurrence', recurrence_id: recurrenceId })
      .then(function (res) {
        if (res.ok) { closeEditor(); closeModal(); if (fc) fc.refetchEvents(); }
        else alert((res.data && res.data.message) || 'Could not delete occurrence.');
      });
  }

  // Delete a whole series by id (server also purges its detached overrides).
  function deleteSeries(seriesId) {
    api('DELETE', '/api/event.php', { id: seriesId }).then(function (res) {
      if (res.ok) { closeEditor(); closeModal(); if (fc) fc.refetchEvents(); }
      else alert((res.data && res.data.message) || 'Could not delete series.');
    });
  }

  // Delete from the detail modal, honouring the chosen scope for series events.
  function deleteRecurring(ev, scope) {
    var p = ev.extendedProps || {};
    if (scope === 'series') {
      var seriesId = p.recurring ? parseInt(ev.id, 10) : (p.series && p.series.id);
      if (!seriesId) return;
      if (!confirm('Delete the entire series?')) return;
      deleteSeries(seriesId);
      return;
    }
    // scope === 'occurrence'
    if (p.recurring) {
      var rid = ev.allDay ? String(ev.startStr).substr(0, 10) : ev.start.toISOString();
      if (!confirm('Delete this occurrence?')) return;
      deleteOccurrence(parseInt(ev.id, 10), rid);
    } else {
      // A moved occurrence is its own row; deleting it removes just this event
      // (the series keeps the EXDATE, so the slot stays empty).
      deleteEvent(parseInt(ev.id, 10));
    }
  }

  // Build an editor "norm" for a series from the snapshot attached to an override.
  function normFromSeries(s) {
    var allDay = !!s.allDay;
    var start = allDay ? new Date(String(s.startUtc).substr(0, 10) + 'T00:00:00') : new Date(s.startUtc);
    var endEx = allDay ? new Date(String(s.endUtc).substr(0, 10) + 'T00:00:00') : new Date(s.endUtc);
    return {
      id: s.id, seriesId: s.id, scope: 'series', recurrenceId: null,
      calendarId: s.calendarId, title: s.title, allDay: allDay,
      start: start, endExclusive: endEx, location: s.location,
      description: s.description, icon: s.icon || '', rrule: s.rruleBody || ''
    };
  }

  // Outlook-style "this occurrence / entire series" chooser for recurring events.
  function askScope(action, cb) {
    window._ncScopeCb = cb;
    $('rs-title').textContent = (action === 'delete') ? 'Delete recurring event' : 'Edit recurring event';
    $('rs-msg').textContent = (action === 'delete')
      ? 'Delete just this occurrence, or the entire series?'
      : 'Edit just this occurrence, or the entire series?';
    $('recur-scope').hidden = false;
  }
  function closeScope() { $('recur-scope').hidden = true; window._ncScopeCb = null; }

  /* ---------- drag / resize ---------- */
  function patchTimes(info) {
    var ev = info.event;
    var p = ev.extendedProps || {};
    var body = { id: parseInt(ev.id, 10), all_day: ev.allDay };
    if (ev.allDay) {
      body.start = ev.startStr.substr(0, 10);
      if (ev.endStr) { body.end = ev.endStr.substr(0, 10); }
      else { var d = new Date(ev.start.getTime()); d.setDate(d.getDate() + 1); body.end = toDateInput(d); }
    } else {
      body.start = ev.start.toISOString();
      body.end = (ev.end || new Date(ev.start.getTime() + 3600000)).toISOString();
    }
    if (p.recurring) {
      // Dragging a repeating event moves only this occurrence (Outlook default).
      var old = info.oldEvent;
      body.scope = 'occurrence';
      body.recurrence_id = ev.allDay ? String(old.startStr).substr(0, 10) : old.start.toISOString();
    }
    api('PATCH', '/api/event.php', body).then(function (res) {
      if (!res.ok) { info.revert(); alert((res.data && res.data.message) || 'Could not move event.'); return; }
      // A recurring occurrence became an EXDATE + a detached override — repaint.
      if (p.recurring && fc) { fc.refetchEvents(); }
    });
  }

  /* ---------- calendar editor + sharing ---------- */
  function openCalEditor(cal) {
    ceId = cal ? cal.id : null;
    $('ce-heading').textContent = cal ? 'Edit calendar' : 'New calendar';
    $('ce-name').value = cal ? cal.name : '';
    $('ce-color').value = (cal && cal.color) ? cal.color : '#5b9dd9';
    setCalIcon(cal ? (cal.icon || '') : '📅'); // new calendars default to 📅
    $('ce-emoji-pop').hidden = true;
    $('ce-delete').hidden = !(cal && cal.owned);
    showErr('ce-error', '');
    var share = $('ce-share');
    if (cal && cal.canManage && cal.visibility === 'private') {
      share.hidden = false; ceShareCalId = cal.id; loadShares(cal.id);
    } else {
      share.hidden = true; ceShareCalId = null;
    }
    var feed = $('ce-feed');
    if (cal && cal.isFeed) {
      feed.hidden = false;
      ceFeedCalId = cal.id;
      $('ce-feed-url').textContent = cal.feedUrl || '';
      $('ce-feed-synced').textContent = cal.feedLastSynced ? ('Last synced ' + relTime(cal.feedLastSynced)) : 'Not synced yet';
      showErr('ce-feed-error', '');
    } else {
      feed.hidden = true; ceFeedCalId = null;
    }
    var exp = $('ce-export');
    if (cal && cal.canManage) {
      exp.hidden = false;
      $('ce-download').href = '/api/export.php?calendar_id=' + cal.id;
      setLinkState(cal.feedToken || null);
      showErr('ce-link-error', '');
    } else {
      exp.hidden = true;
    }
    $('cal-editor').hidden = false;
    $('ce-name').focus();
  }
  function closeCalEditor() { $('cal-editor').hidden = true; }

  /* ---------- import / subscribe ---------- */
  function relTime(s) {
    try {
      var d = new Date(String(s).replace(' ', 'T') + 'Z');
      var sec = Math.round((Date.now() - d.getTime()) / 1000);
      if (sec < 60) return 'just now';
      var m = Math.round(sec / 60); if (m < 60) return m + ' min ago';
      var h = Math.round(m / 60); if (h < 24) return h + 'h ago';
      return Math.round(h / 24) + 'd ago';
    } catch (e) { return ''; }
  }
  function setImportMode(mode) {
    Array.prototype.forEach.call($('im-seg').querySelectorAll('.seg-btn'), function (b) {
      b.classList.toggle('on', b.dataset.mode === mode);
    });
    $('im-url-form').hidden = (mode !== 'url');
    $('im-file-form').hidden = (mode !== 'file');
  }
  function toggleImportFileName() { $('im-file-name-wrap').hidden = ($('im-file-target').value !== '__new__'); }
  function openImportModal() {
    setImportMode('url');
    $('im-url').value = ''; $('im-url-name').value = ''; $('im-url-color').value = '#5b9dd9';
    $('im-file').value = ''; $('im-file-name').value = '';
    showErr('im-url-error', ''); showErr('im-file-error', '');
    var sel = $('im-file-target'); sel.innerHTML = '';
    var optNew = document.createElement('option'); optNew.value = '__new__'; optNew.textContent = '＋ New calendar…'; sel.appendChild(optNew);
    editableCalendars().forEach(function (c) {
      var o = document.createElement('option'); o.value = c.id; o.textContent = (c.icon ? c.icon + ' ' : '') + c.name; sel.appendChild(o);
    });
    toggleImportFileName();
    $('import-modal').hidden = false;
    setTimeout(function () { $('im-url').focus(); }, 0);
  }
  function closeImportModal() { $('import-modal').hidden = true; }

  // After a successful import/subscribe: reload calendars, enable + show the new one.
  function afterImport(slug, msg) {
    return reloadCalendars().then(function () {
      if (slug) { var set = enabledSet(); set.add(slug); writeJSON(K.enabled, Array.from(set)); renderSidebar(window._ncUser); }
      if (fc) fc.refetchEvents();
      closeImportModal();
      if (msg) alert(msg);
    });
  }
  function submitSubscribe(e) {
    e.preventDefault();
    var url = $('im-url').value.trim();
    if (!url) { showErr('im-url-error', 'Enter a feed URL.'); return; }
    var btn = $('im-url-submit'); btn.disabled = true; showErr('im-url-error', '');
    api('POST', '/api/subscribe.php', { feed_url: url, name: $('im-url-name').value.trim(), color: $('im-url-color').value })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) { showErr('im-url-error', (res.data && res.data.message) || 'Could not subscribe to that feed.'); return; }
        afterImport(res.data && res.data.slug, 'Subscribed — imported ' + (res.data && res.data.imported) + ' events.');
      });
  }
  function submitFileImport(e) {
    e.preventDefault();
    var file = $('im-file').files[0];
    if (!file) { showErr('im-file-error', 'Choose an .ics file.'); return; }
    var fd = new FormData(); fd.append('file', file);
    var target = $('im-file-target').value;
    if (target === '__new__') {
      var nm = $('im-file-name').value.trim();
      if (!nm) { showErr('im-file-error', 'Enter a name for the new calendar.'); return; }
      fd.append('name', nm);
    } else { fd.append('calendar_id', target); }
    var btn = $('im-file-submit'); btn.disabled = true; showErr('im-file-error', '');
    // Note: no Content-Type header — the browser sets the multipart boundary.
    fetch('/api/import.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, body: fd })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }, function () { return { ok: r.ok, data: {} }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) { showErr('im-file-error', (res.data && res.data.message) || 'Could not import that file.'); return; }
        afterImport(res.data && res.data.slug, 'Imported ' + (res.data && res.data.imported) + ' events.');
      });
  }
  function refreshFeed() {
    if (!ceFeedCalId) return;
    var btn = $('ce-feed-refresh'); btn.disabled = true; showErr('ce-feed-error', '');
    api('POST', '/api/feed_sync.php', { calendar_id: ceFeedCalId }).then(function (res) {
      btn.disabled = false;
      if (!res.ok) { showErr('ce-feed-error', (res.data && res.data.message) || 'Refresh failed.'); return; }
      $('ce-feed-synced').textContent = 'Last synced just now';
      reloadCalendars().then(function () { if (fc) fc.refetchEvents(); });
    });
  }
  /* ---------- export / subscription link ---------- */
  function feedUrlFor(token) { return location.origin + '/api/export.php?token=' + token; }
  function setLinkState(token) {
    if (token) {
      $('ce-link-on').hidden = false; $('ce-link-off').hidden = true;
      $('ce-link-url').value = feedUrlFor(token);
    } else {
      $('ce-link-on').hidden = true; $('ce-link-off').hidden = false;
      $('ce-link-url').value = '';
    }
  }
  function feedTokenAction(action) {
    if (!ceId) return;
    showErr('ce-link-error', '');
    api('POST', '/api/feed_token.php', { calendar_id: ceId, action: action }).then(function (res) {
      if (!res.ok) { showErr('ce-link-error', (res.data && res.data.message) || 'Could not update the link.'); return; }
      var token = (res.data && res.data.token) || null;
      setLinkState(token);
      // keep local state in sync so reopening the editor reflects it
      Object.keys(calendarsById).forEach(function (s) { if (calendarsById[s].id === ceId) calendarsById[s].feedToken = token; });
    });
  }
  function copyLink() {
    var inp = $('ce-link-url'); if (!inp.value) return;
    inp.focus(); inp.select();
    var btn = $('ce-link-copy');
    var flash = function () { var t = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function () { btn.textContent = t; }, 1200); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(inp.value).then(flash, function () { try { document.execCommand('copy'); flash(); } catch (e) {} });
    } else { try { document.execCommand('copy'); flash(); } catch (e) {} }
  }

  // Lazily re-sync subscribed calendars whose feed has gone stale (no host cron needed).
  function syncStaleFeeds() {
    var due = Array.from(enabledSet())
      .map(function (s) { return calendarsById[s]; })
      .filter(function (c) { return c && c.isFeed && c.feedStale && c.owned; });
    if (!due.length) return;
    var done = 0;
    due.forEach(function (c) {
      api('POST', '/api/feed_sync.php', { calendar_id: c.id }).then(function () {
        if (++done === due.length && fc) fc.refetchEvents();
      }).catch(function () {});
    });
  }

  function saveCal(e) {
    e.preventDefault();
    var name = $('ce-name').value.trim();
    if (!name) { showErr('ce-error', 'Name is required.'); return; }
    var body = { name: name, color: $('ce-color').value, icon: $('ce-icon').value.trim() };
    var creating = !ceId;
    var req = creating
      ? api('POST', '/api/calendar.php', body)
      : api('PATCH', '/api/calendar.php', Object.assign({ id: ceId }, body));
    req.then(function (res) {
      if (!res.ok) { showErr('ce-error', (res.data && res.data.message) || 'Could not save calendar.'); return; }
      var newCal = res.data && res.data.calendar;
      if (creating && newCal) {
        var set = enabledSet(); set.add(newCal.slug); writeJSON(K.enabled, Array.from(set));
      }
      reloadCalendars().then(function () {
        if (fc) fc.refetchEvents();
        if (creating && newCal && calendarsById[newCal.slug]) {
          openCalEditor(calendarsById[newCal.slug]); // reopen so user can share immediately
        } else {
          closeCalEditor();
        }
      });
    });
  }

  function deleteCal(id) {
    if (!id || !confirm('Delete this calendar and all of its events? This cannot be undone.')) return;
    api('DELETE', '/api/calendar.php', { id: id }).then(function (res) {
      if (res.ok) { closeCalEditor(); reloadCalendars().then(function () { if (fc) fc.refetchEvents(); }); }
      else alert((res.data && res.data.message) || 'Could not delete calendar.');
    });
  }

  function loadShares(calId) {
    var list = $('ce-share-list');
    list.innerHTML = '<p class="muted small">Loading…</p>';
    api('GET', '/api/shares.php?calendar_id=' + calId).then(function (res) {
      if (!res.ok) { list.innerHTML = '<p class="muted small">' + esc((res.data && res.data.message) || 'Could not load shares.') + '</p>'; return; }
      var shares = (res.data && res.data.shares) || [];
      if (!shares.length) { list.innerHTML = '<p class="muted small">Not shared with anyone yet.</p>'; return; }
      list.innerHTML = '';
      shares.forEach(function (s) {
        var row = document.createElement('div');
        row.className = 'share-row';
        row.innerHTML =
          '<span class="share-email">' + esc(s.email) + '</span>' +
          '<span class="muted small">' + esc(s.role) + (s.accepted ? '' : ' · pending') + '</span>' +
          '<button class="robtn" data-share-id="' + s.id + '" title="Remove access">✕</button>';
        list.appendChild(row);
      });
    });
  }

  /* ---------- data load ---------- */
  function reloadCalendars() {
    return fetch('/api/calendars.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        calendarsById = {};
        (d.calendars || []).forEach(function (c) { calendarsById[c.slug] = c; });
        window._ncUser = d.user;
        renderHeader(d.user);
        renderSidebar(d.user);
      });
  }

  /* ---------- sticky toolbar offset ----------
     The toolbar is position:sticky at the top of the scroll area; the grid's own
     sticky date header (stickyHeaderDates) must sit just below it. We publish the
     toolbar's measured height as a CSS var the header offsets against. */
  function syncStickyToolbar() {
    var tb = document.querySelector('.fc .fc-header-toolbar');
    var wrap = document.querySelector('.calendar-wrap');
    if (!tb || !wrap) return;
    wrap.style.setProperty('--nc-toolbar-h', tb.offsetHeight + 'px');
  }

  /* ---------- calendar ---------- */
  function initCalendar() {
    var el = $('calendar');
    fc = new FullCalendar.Calendar(el, {
      initialView: localStorage.getItem(K.view) || 'dayGridMonth',
      height: 'auto',
      firstDay: 1,
      nowIndicator: true,
      dayMaxEvents: 3,
      allDaySlot: true,
      allDayText: 'all-day',
      slotEventOverlap: false,   // overlapping timed events tile side-by-side (Outlook-style)
      eventDisplay: 'block',     // month view: all events as full-bg bars (not dots), consistent style
      stickyHeaderDates: true,
      editable: true,
      selectable: true,
      selectMirror: true,
      eventClassNames: function (arg) {
        var s = arg.event.extendedProps.calendar;
        return s ? ['evt-' + s] : [];
      },
      headerToolbar: {
        left: 'prev,next today', center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      buttonText: { today: 'Today', month: 'Month', week: 'Week', day: 'Day', list: 'Agenda' },
      eventOrder: 'order,start',
      datesSet: function (info) {
        localStorage.setItem(K.view, info.view.type);
        requestAnimationFrame(syncStickyToolbar);
      },
      viewDidMount: function () { requestAnimationFrame(syncStickyToolbar); },
      eventClick: function (info) { info.jsEvent.preventDefault(); openModal(info); },
      select: function (sel) {
        var cals = editableCalendars();
        if (!cals.length) { fc.unselect(); return; }
        openEventEditor({ allDay: sel.allDay, start: sel.start, endExclusive: sel.end, calendarId: cals[0].id });
        fc.unselect();
      },
      eventDrop: patchTimes,
      eventResize: patchTimes,
      eventDidMount: function (info) {
        var p = info.event.extendedProps || {};
        var isList = info.view.type.indexOf('list') === 0;
        var titleEl = info.el.querySelector('.fc-event-title') || info.el.querySelector('.fc-list-event-title');
        if ((p.recurring || p.partOfSeries) && titleEl && titleEl.textContent.indexOf('↻') !== 0) {
          var span = document.createElement('span');
          span.textContent = '↻ '; span.className = 'recur-mark';
          titleEl.insertBefore(span, titleEl.firstChild);
        }
        // Faded icon motif: the event's own icon overrides its calendar's icon.
        var cal = calendarsById[p.calendar];
        var icon = p.icon || (cal && cal.icon) || '';
        if (icon) {
          if (isList) {
            if (titleEl) {
              var s = document.createElement('span'); s.className = 'evt-icon-prefix';
              s.textContent = icon + ' '; titleEl.insertBefore(s, titleEl.firstChild);
            }
          } else {
            info.el.classList.add('nc-icon');
            info.el.style.setProperty('--nc-icon', '"' + icon + '"');
          }
        }
      },
      events: function (info, success, failure) {
        var slugs = Array.from(enabledSet());
        if (!slugs.length) { success([]); return; }
        var url = '/api/events.php?cals=' + encodeURIComponent(slugs.join(',')) +
          '&from=' + encodeURIComponent(info.startStr) + '&to=' + encodeURIComponent(info.endStr) +
          '&_=' + Date.now();   // cache-bust so an updated calendar never shows stale
        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            (d.events || []).forEach(function (e) {
              e.extendedProps = e.extendedProps || {};
              e.extendedProps.order = orderIndex(e.extendedProps.calendar);
            });
            success(d.events || []);
          })
          .catch(failure);
      }
    });
    window.__nccal = fc;
    fc.render();
    requestAnimationFrame(function () { try { fc.updateSize(); } catch (e) {} syncStickyToolbar(); });
    window.addEventListener('resize', function () { requestAnimationFrame(syncStickyToolbar); });
    // rAF is paused in background tabs; recompute the offset the moment the tab is shown.
    // Also pull fresh events so returning after an update (e.g. a re-seed) shows current data.
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) { syncStickyToolbar(); if (fc) fc.refetchEvents(); }
    });
  }

  /* ---------- emoji picker (shared by calendar 'ce' and event 'ee' editors) ---------- */
  function setIcon(prefix, e) {
    $(prefix + '-icon').value = e || '';
    var btn = $(prefix + '-icon-btn');
    if (e) { btn.textContent = e; btn.classList.remove('empty'); }
    else if (prefix === 'ce') { btn.textContent = '📅'; btn.classList.remove('empty'); }
    else { btn.textContent = '＋'; btn.classList.add('empty'); }
  }
  function setCalIcon(e) { setIcon('ce', e); } // back-compat for calendar editor
  function renderEmojiGrid(prefix, filter) {
    var grid = $(prefix + '-emoji-grid');
    var q = (filter || '').trim().toLowerCase();
    var items = EMOJIS.filter(function (it) { return !q || it.k.indexOf(q) >= 0 || it.e === q; });
    grid.innerHTML = '';
    if (prefix === 'ee') { // events may clear their icon
      var none = document.createElement('button');
      none.type = 'button'; none.className = 'emoji-none'; none.textContent = '∅'; none.title = 'No icon';
      none.addEventListener('click', function () { setIcon('ee', ''); $('ee-emoji-pop').hidden = true; });
      grid.appendChild(none);
    }
    if (!items.length && prefix !== 'ee') { grid.innerHTML = '<div class="none">No emojis match.</div>'; return; }
    items.forEach(function (it) {
      var b = document.createElement('button');
      b.type = 'button'; b.textContent = it.e; b.title = it.k;
      b.addEventListener('click', function () { setIcon(prefix, it.e); $(prefix + '-emoji-pop').hidden = true; });
      grid.appendChild(b);
    });
  }
  function toggleEmojiPicker(prefix) {
    var pop = $(prefix + '-emoji-pop');
    var willShow = pop.hidden;
    pop.hidden = !willShow;
    if (willShow) {
      $(prefix + '-emoji-search').value = '';
      renderEmojiGrid(prefix, '');
      setTimeout(function () { $(prefix + '-emoji-search').focus(); }, 0);
    }
  }

  /* ---------- recurrence (RRULE build / parse) ---------- */
  var WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
  var WD_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  var ORDINALS = ['first', 'second', 'third', 'fourth', 'fifth'];
  function freqUnit(freq) {
    return { DAILY: 'days', WEEKLY: 'weeks', MONTHLY: 'months', YEARLY: 'years' }[freq] || '';
  }
  function daysInMonth(y, m) { return new Date(y, m + 1, 0).getDate(); }
  // The start instant currently in the editor (timed or all-day).
  function editorStartDate() {
    var d = $('ee-allday').checked ? new Date(($('ee-start-date').value || '') + 'T00:00:00')
                                   : new Date($('ee-start').value);
    return isNaN(d.getTime()) ? new Date() : d;
  }
  // Build the "monthly on…" options from the start date (Google-style): on day N,
  // on the Nth weekday, and (when applicable) on the last weekday. Values encode BYDAY.
  function buildMonthlyOptions() {
    var sel = $('ee-monthly-mode');
    var cur = sel.value;
    var d = editorStartDate();
    var dom = d.getDate();
    var wdIdx = (d.getDay() + 6) % 7;     // JS Sun=0 -> our MO=0
    var wd = WEEKDAYS[wdIdx];
    var n = Math.ceil(dom / 7);           // 1..5
    var isLast = (dom + 7) > daysInMonth(d.getFullYear(), d.getMonth());
    var opts = [{ v: '', t: 'day ' + dom }];
    if (n <= ORDINALS.length) opts.push({ v: n + wd, t: 'the ' + ORDINALS[n - 1] + ' ' + WD_NAMES[wdIdx] });
    if (isLast) opts.push({ v: '-1' + wd, t: 'the last ' + WD_NAMES[wdIdx] });
    sel.innerHTML = '';
    opts.forEach(function (o) {
      var e = document.createElement('option'); e.value = o.v; e.textContent = o.t; sel.appendChild(e);
    });
    if (opts.some(function (o) { return o.v === cur; })) sel.value = cur;
  }
  function syncEndType() {
    var t = $('ee-end-type').value;
    $('ee-count').hidden = (t !== 'count');
    $('ee-count-unit').hidden = (t !== 'count');
    $('ee-until').hidden = (t !== 'until');
  }
  function syncRecurUI() {
    var freq = $('ee-freq').value;
    $('ee-recur-opts').hidden = !freq;
    $('ee-interval-unit').textContent = freqUnit(freq);
    $('ee-byday').hidden = (freq !== 'WEEKLY');
    $('ee-monthly').hidden = (freq !== 'MONTHLY');
    if (freq === 'MONTHLY') buildMonthlyOptions();
    if (freq === 'WEEKLY') {
      // default to the start day's weekday if nothing selected
      var anyOn = $('ee-byday').querySelector('button.on');
      if (!anyOn) {
        var idx = (editorStartDate().getDay() + 6) % 7;
        var btn = $('ee-byday').querySelector('button[data-d="' + WEEKDAYS[idx] + '"]');
        if (btn) btn.classList.add('on');
      }
    }
  }
  function buildRRULE() {
    var freq = $('ee-freq').value;
    if (!freq) return '';
    var parts = ['FREQ=' + freq];
    var iv = parseInt($('ee-interval').value, 10);
    if (iv > 1) parts.push('INTERVAL=' + iv);
    if (freq === 'WEEKLY') {
      var days = Array.prototype.map.call($('ee-byday').querySelectorAll('button.on'), function (b) { return b.dataset.d; });
      if (days.length) parts.push('BYDAY=' + days.join(','));
    } else if (freq === 'MONTHLY') {
      var mv = $('ee-monthly-mode').value;          // e.g. '3WE' or '-1FR'; '' => on start day-of-month
      if (mv) parts.push('BYDAY=' + mv);
    }
    var end = $('ee-end-type').value;
    if (end === 'count') {
      var n = parseInt($('ee-count').value, 10); if (n > 0) parts.push('COUNT=' + n);
    } else if (end === 'until') {
      var u = $('ee-until').value;
      if (u) parts.push('UNTIL=' + u.replace(/-/g, '') + 'T235959Z');
    }
    return parts.join(';');
  }
  function parseRRULE(body) {
    // reset
    $('ee-freq').value = '';
    $('ee-interval').value = '1';
    $('ee-count').value = '10';
    $('ee-until').value = '';
    $('ee-end-type').value = 'never';
    Array.prototype.forEach.call($('ee-byday').querySelectorAll('button'), function (b) { b.classList.remove('on'); });
    var map = {};
    if (body) {
      body.split(';').forEach(function (kv) { var p = kv.split('='); if (p[0]) map[p[0].toUpperCase()] = p[1] || ''; });
      if (map.FREQ) $('ee-freq').value = map.FREQ;
      if (map.INTERVAL) $('ee-interval').value = map.INTERVAL;
      if (map.FREQ === 'WEEKLY' && map.BYDAY) {
        map.BYDAY.split(',').forEach(function (d) {
          var btn = $('ee-byday').querySelector('button[data-d="' + d + '"]'); if (btn) btn.classList.add('on');
        });
      }
      if (map.COUNT) { $('ee-end-type').value = 'count'; $('ee-count').value = map.COUNT; }
      else if (map.UNTIL) {
        $('ee-end-type').value = 'until';
        var m = map.UNTIL.match(/^(\d{4})(\d{2})(\d{2})/);
        if (m) $('ee-until').value = m[1] + '-' + m[2] + '-' + m[3];
      }
    }
    syncRecurUI();                                    // builds monthly options from start date
    if (map.FREQ === 'MONTHLY' && map.BYDAY) $('ee-monthly-mode').value = map.BYDAY;
    syncEndType();
  }

  /* ---------- resizable sidebar ---------- */
  function initSidebarResizer() {
    var sidebar = document.querySelector('.sidebar');
    var handle = $('sidebar-resizer');
    if (!sidebar || !handle) return;
    var saved = parseInt(localStorage.getItem(K.sidebar), 10);
    if (saved >= 180 && saved <= 560) sidebar.style.width = saved + 'px';
    var dragging = false;
    function onMove(e) {
      if (!dragging) return;
      var x = (e.touches ? e.touches[0].clientX : e.clientX);
      var w = Math.min(560, Math.max(180, x - sidebar.getBoundingClientRect().left));
      sidebar.style.width = w + 'px';
      if (fc) { try { fc.updateSize(); } catch (_) {} }
    }
    function stop() {
      if (!dragging) return;
      dragging = false;
      handle.classList.remove('dragging');
      document.body.classList.remove('resizing-sidebar');
      localStorage.setItem(K.sidebar, String(parseInt(sidebar.style.width, 10) || 250));
    }
    function start(e) {
      dragging = true;
      handle.classList.add('dragging');
      document.body.classList.add('resizing-sidebar');
      e.preventDefault();
    }
    handle.addEventListener('mousedown', start);
    handle.addEventListener('touchstart', start, { passive: false });
    document.addEventListener('mousemove', onMove);
    document.addEventListener('touchmove', onMove, { passive: false });
    document.addEventListener('mouseup', stop);
    document.addEventListener('touchend', stop);
  }

  /* ---------- wiring ---------- */
  function wireModals() {
    $('modal-close').addEventListener('click', closeModal);
    $('event-modal').addEventListener('click', function (e) { if (e.target.id === 'event-modal') closeModal(); });
    $('modal-edit').addEventListener('click', function () {
      var ev = window._ncCurrent; if (!ev) return;
      closeModal(); openEventEditor(normFromEvent(ev));
    });
    $('modal-edit-occ').addEventListener('click', function () {
      var ev = window._ncCurrent; if (!ev) return;
      var p = ev.extendedProps || {};
      closeModal();
      if (p.recurring) { openEventEditor(normFromEvent(ev, 'occurrence')); }
      else { var n = normFromEvent(ev); n.hideRepeat = true; openEventEditor(n); } // moved occurrence: edit its own row
    });
    $('modal-edit-series').addEventListener('click', function () {
      var ev = window._ncCurrent; if (!ev) return;
      var p = ev.extendedProps || {};
      closeModal();
      if (p.recurring) { openEventEditor(normFromEvent(ev, 'series')); }
      else if (p.series) { openEventEditor(normFromSeries(p.series)); }
    });
    $('modal-delete').addEventListener('click', function () {
      var ev = window._ncCurrent; if (!ev) return;
      var p = ev.extendedProps || {};
      if (p.recurring || p.partOfSeries) {
        askScope('delete', function (scope) { deleteRecurring(ev, scope); });
      } else {
        deleteEvent(parseInt(ev.id, 10));
      }
    });
    $('rs-close').addEventListener('click', closeScope);
    $('rs-cancel').addEventListener('click', closeScope);
    $('recur-scope').addEventListener('click', function (e) { if (e.target.id === 'recur-scope') closeScope(); });
    $('rs-occurrence').addEventListener('click', function () { var cb = window._ncScopeCb; closeScope(); if (cb) cb('occurrence'); });
    $('rs-series').addEventListener('click', function () { var cb = window._ncScopeCb; closeScope(); if (cb) cb('series'); });

    $('ee-close').addEventListener('click', closeEditor);
    $('ee-cancel').addEventListener('click', closeEditor);
    $('event-editor').addEventListener('click', function (e) { if (e.target.id === 'event-editor') closeEditor(); });
    $('ee-form').addEventListener('submit', saveEvent);
    $('ee-allday').addEventListener('change', function () { toggleAllDayFields(this.checked); if (!$('ee-recur-opts').hidden) syncRecurUI(); });
    $('ee-cal').addEventListener('change', paintCalSelect);
    $('ee-delete').addEventListener('click', function () {
      if (eeScope === 'occurrence') { deleteOccurrence(eeSeriesId, eeRecurrenceId); }
      else { deleteEvent(eeId); }
    });

    // Markdown formatting toolbar: keep the textarea selection on mousedown,
    // then insert syntax on click (delegated so it works for any .md-toolbar).
    document.addEventListener('mousedown', function (e) {
      if (e.target.closest && e.target.closest('.md-btn')) e.preventDefault();
    });
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('.md-btn');
      if (!btn) return;
      var bar = btn.closest('.md-toolbar');
      applyMd($(bar.getAttribute('data-target')), btn.getAttribute('data-md'));
    });
    $('ee-desc').addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey) {
        var k = e.key.toLowerCase();
        if (k === 'b') { e.preventDefault(); applyMd(this, 'bold'); }
        else if (k === 'i') { e.preventDefault(); applyMd(this, 'italic'); }
      }
    });

    $('ce-close').addEventListener('click', closeCalEditor);
    $('ce-cancel').addEventListener('click', closeCalEditor);
    $('cal-editor').addEventListener('click', function (e) { if (e.target.id === 'cal-editor') closeCalEditor(); });
    $('ce-form').addEventListener('submit', saveCal);
    $('ce-delete').addEventListener('click', function () { deleteCal(ceId); });
    $('ce-icon-btn').addEventListener('click', function () { toggleEmojiPicker('ce'); });
    $('ce-emoji-search').addEventListener('input', function () { renderEmojiGrid('ce', this.value); });
    $('ee-icon-btn').addEventListener('click', function () { toggleEmojiPicker('ee'); });
    $('ee-emoji-search').addEventListener('input', function () { renderEmojiGrid('ee', this.value); });

    $('ee-freq').addEventListener('change', syncRecurUI);
    $('ee-end-type').addEventListener('change', syncEndType);
    $('ee-byday').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-d]'); if (b) b.classList.toggle('on');
    });
    // Keep monthly "first Tuesday"-style options / weekly default in step with the start date.
    function reSyncRecur() { if (!$('ee-recur-opts').hidden) syncRecurUI(); }
    $('ee-start').addEventListener('change', reSyncRecur);
    $('ee-start-date').addEventListener('change', reSyncRecur);
    $('ce-share-list').addEventListener('click', function (e) {
      var b = e.target.closest('[data-share-id]'); if (!b) return;
      api('DELETE', '/api/shares.php', { id: parseInt(b.dataset.shareId, 10) }).then(function (res) {
        if (res.ok && ceShareCalId) loadShares(ceShareCalId);
      });
    });
    $('ce-share-form').addEventListener('submit', function (e) {
      e.preventDefault();
      if (!ceShareCalId) return;
      var email = $('ce-share-email').value.trim();
      var role = $('ce-share-role').value;
      api('POST', '/api/shares.php', { calendar_id: ceShareCalId, email: email, role: role }).then(function (res) {
        if (!res.ok) { showErr('ce-share-error', (res.data && res.data.message) || 'Could not add.'); return; }
        $('ce-share-email').value = ''; showErr('ce-share-error', ''); loadShares(ceShareCalId);
      });
    });

    $('new-event-btn').addEventListener('click', function () {
      var cals = editableCalendars();
      if (!cals.length) { alert('Create a calendar first, then add events to it.'); return; }
      var s = new Date(); s.setMinutes(0, 0, 0); s.setHours(s.getHours() + 1);
      openEventEditor({ start: s, endExclusive: new Date(s.getTime() + 3600000), calendarId: cals[0].id, allDay: false });
    });
    $('new-cal-btn').addEventListener('click', function () { openCalEditor(null); });

    $('import-btn').addEventListener('click', openImportModal);
    $('im-close').addEventListener('click', closeImportModal);
    $('import-modal').addEventListener('click', function (e) { if (e.target.id === 'import-modal') closeImportModal(); });
    $('im-seg').addEventListener('click', function (e) { var b = e.target.closest('.seg-btn'); if (b) setImportMode(b.dataset.mode); });
    $('im-url-form').addEventListener('submit', submitSubscribe);
    $('im-file-form').addEventListener('submit', submitFileImport);
    $('im-file-target').addEventListener('change', toggleImportFileName);
    $('ce-feed-refresh').addEventListener('click', refreshFeed);
    $('ce-link-create').addEventListener('click', function () { feedTokenAction('enable'); });
    $('ce-link-regen').addEventListener('click', function () { feedTokenAction('regenerate'); });
    $('ce-link-disable').addEventListener('click', function () {
      if (confirm('Disable the subscription link? Anyone using it will lose access.')) feedTokenAction('disable');
    });
    $('ce-link-copy').addEventListener('click', copyLink);

    var brand = $('brand-today');
    function goToday() { if (fc) { fc.changeView('timeGridDay'); fc.today(); } }
    brand.addEventListener('click', goToday);
    brand.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); goToday(); }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closeModal(); closeEditor(); closeCalEditor(); closeImportModal(); $('ce-emoji-pop').hidden = true; $('ee-emoji-pop').hidden = true; }
    });
  }

  /* ---------- boot ---------- */
  function boot() {
    initTheme();
    wireModals();
    initSidebarResizer();
    $('app-version').textContent = 'v' + APP_VERSION;
    var listEl = $('calendar-list');
    listEl.addEventListener('click', onSidebarClick);
    listEl.addEventListener('change', onSidebarChange);
    listEl.addEventListener('dragstart', onSidebarDragStart);
    listEl.addEventListener('dragover', onSidebarDragOver);
    listEl.addEventListener('drop', function (e) { e.preventDefault(); });
    listEl.addEventListener('dragend', onSidebarDragEnd);

    reloadCalendars()
      .then(function () {
        initCalendar();
        syncStaleFeeds();
        if (location.search.indexOf('seeded=1') !== -1) history.replaceState({}, '', '/');
      })
      .catch(function () {
        $('calendar-list').innerHTML = '<p class="muted small">Could not load calendars.</p>';
      });
  }
  if (document.readyState === 'complete') boot();
  else window.addEventListener('load', boot);
})();
