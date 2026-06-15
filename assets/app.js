(function () {
  'use strict';

  var APP_VERSION = '0.4.0';
  var K = {
    enabled: 'nc_enabled_v1', view: 'nc_view_v1', order: 'nc_order_v1',
    colors: 'nc_colors_v1', mode: 'nc_mode_v1', theme: 'nc_theme_v1',
    sidebar: 'nc_sidebar_w_v1'
  };
  var EMOJIS = ('📅 📆 🗓️ ⏰ ⭐ 🔥 💼 🏠 🎉 🎂 🎓 ✈️ 🏖️ 🍽️ ☕ 🏃 🏋️ ⚽ 🏀 🎾 ' +
    '🎮 🎸 🎬 🎨 📚 💡 💻 🔧 🩺 💊 💰 📈 📉 🧾 🛒 🐶 🐱 🌱 🌍 ☀️ ' +
    '🌙 ❤️ 💙 💚 💜 🧡 💛 ✅ ❗ ❓ 📌 🔔 🎵 🍺 🍕 🎄').split(' ');
  var calendarsById = {};
  var fc = null;
  var eeId = null;          // event id being edited (null = creating)
  var ceId = null;          // calendar id being edited (null = creating)
  var ceShareCalId = null;  // calendar id whose shares are shown

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
  function colorFor(slug) {
    var c = readJSON(K.colors, {});
    return c[slug] || (calendarsById[slug] && calendarsById[slug].color) || '#888888';
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
        row.className = 'cal-row'; row.dataset.slug = slug;
        row.innerHTML =
          '<span class="reorder">' +
            '<button class="robtn" data-act="up" title="Move up">▲</button>' +
            '<button class="robtn" data-act="down" title="Move down">▼</button>' +
          '</span>' +
          '<input type="checkbox" data-act="toggle"' + (enabled.has(slug) ? ' checked' : '') + '>' +
          '<button class="dot" data-act="color" style="background:' + esc(colorFor(slug)) + '" title="Change color"></button>' +
          '<span class="cal-name">' + (c.icon ? esc(c.icon) + ' ' : '') + esc(c.name) + '</span>' +
          (c.canManage ? '<button class="robtn manage" data-act="manage" title="Manage / share">⚙</button>' : '') +
          '<input type="color" class="color-input" hidden value="' + esc(colorFor(slug)) + '">';
        list.appendChild(row);
      });
    }
    $('user-tools').hidden = !user;
    $('admin-tools').hidden = !(user && user.is_admin);
  }

  function onSidebarClick(ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-act]') : null;
    if (!btn) return;
    var row = ev.target.closest('.cal-row'); if (!row) return;
    var slug = row.dataset.slug;
    var act = btn.dataset.act;
    if (act === 'color') {
      row.querySelector('.color-input').click();
    } else if (act === 'manage') {
      openCalEditor(calendarsById[slug]);
    } else if (act === 'up' || act === 'down') {
      var order = orderedSlugs(); var i = order.indexOf(slug);
      var j = act === 'up' ? i - 1 : i + 1;
      if (j < 0 || j >= order.length) return;
      var tmp = order[i]; order[i] = order[j]; order[j] = tmp;
      writeJSON(K.order, order);
      renderSidebar(window._ncUser);
      if (fc) fc.refetchEvents();
    }
  }
  function onSidebarChange(ev) {
    var t = ev.target; var row = t.closest('.cal-row'); if (!row) return;
    var slug = row.dataset.slug;
    if (t.dataset.act === 'toggle') {
      var set = enabledSet();
      if (t.checked) set.add(slug); else set.delete(slug);
      writeJSON(K.enabled, Array.from(set));
      if (fc) fc.refetchEvents();
    } else if (t.classList.contains('color-input')) {
      var colors = readJSON(K.colors, {}); colors[slug] = t.value; writeJSON(K.colors, colors);
      row.querySelector('.dot').style.background = t.value;
      if (fc) fc.refetchEvents();
    }
  }

  /* ---------- header auth ---------- */
  function renderHeader(user) {
    var el = $('auth');
    if (user) {
      var admin = user.is_admin ? ' <span class="muted small">· admin</span>' : '';
      var avatar = user.avatar ? '<img class="avatar" src="' + esc(user.avatar) + '" alt="" referrerpolicy="no-referrer">' : '';
      var name = user.name || user.email;
      el.innerHTML = avatar + '<span class="user">' + esc(name) + '</span>' + admin +
        ' <a class="btn small" href="/auth/logout.php">Log out</a>';
    } else {
      el.innerHTML = '<a class="btn primary small" href="/auth/login.php">Sign in with Google</a>';
    }
  }

  /* ---------- event detail modal ---------- */
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
    $('modal-desc').textContent = p.description || '';
    $('modal-actions').hidden = !(p.canEdit && !p.recurring);
    $('event-modal').hidden = false;
  }
  function closeModal() { $('event-modal').hidden = true; }

  /* ---------- event editor ---------- */
  function normFromEvent(ev) {
    var span = ev.allDay ? 86400000 : 3600000;
    return {
      id: parseInt(ev.id, 10),
      calendarId: ev.extendedProps.calendarId,
      title: ev.title,
      allDay: ev.allDay,
      start: ev.start,
      endExclusive: ev.end || new Date(ev.start.getTime() + span),
      location: ev.extendedProps.location,
      description: ev.extendedProps.description
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
    $('ee-heading').textContent = eeId ? 'Edit event' : 'New event';
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
    $('ee-delete').hidden = !eeId;
    toggleAllDayFields(allday);
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
      location: $('ee-location').value.trim(), description: $('ee-desc').value.trim()
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
    var req = eeId
      ? api('PATCH', '/api/event.php', Object.assign({ id: eeId }, body))
      : api('POST', '/api/event.php', body);
    req.then(function (res) {
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

  /* ---------- drag / resize ---------- */
  function patchTimes(info) {
    var ev = info.event;
    var body = { id: parseInt(ev.id, 10), all_day: ev.allDay };
    if (ev.allDay) {
      body.start = ev.startStr.substr(0, 10);
      if (ev.endStr) { body.end = ev.endStr.substr(0, 10); }
      else { var d = new Date(ev.start.getTime()); d.setDate(d.getDate() + 1); body.end = toDateInput(d); }
    } else {
      body.start = ev.start.toISOString();
      body.end = (ev.end || new Date(ev.start.getTime() + 3600000)).toISOString();
    }
    api('PATCH', '/api/event.php', body).then(function (res) {
      if (!res.ok) { info.revert(); alert((res.data && res.data.message) || 'Could not move event.'); }
    });
  }

  /* ---------- calendar editor + sharing ---------- */
  function openCalEditor(cal) {
    ceId = cal ? cal.id : null;
    $('ce-heading').textContent = cal ? 'Edit calendar' : 'New calendar';
    $('ce-name').value = cal ? cal.name : '';
    $('ce-color').value = (cal && cal.color) ? cal.color : '#5b9dd9';
    $('ce-icon').value = (cal && cal.icon) ? cal.icon : '';
    $('ce-emoji-pop').hidden = true;
    $('ce-delete').hidden = !(cal && cal.owned);
    showErr('ce-error', '');
    var share = $('ce-share');
    if (cal && cal.canManage && cal.visibility === 'private') {
      share.hidden = false; ceShareCalId = cal.id; loadShares(cal.id);
    } else {
      share.hidden = true; ceShareCalId = null;
    }
    $('cal-editor').hidden = false;
    $('ce-name').focus();
  }
  function closeCalEditor() { $('cal-editor').hidden = true; }

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
      datesSet: function (info) { localStorage.setItem(K.view, info.view.type); },
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
        if (info.event.extendedProps.recurring) {
          var t = info.el.querySelector('.fc-event-title') || info.el.querySelector('.fc-list-event-title');
          if (t && t.textContent.indexOf('↻') !== 0) {
            var span = document.createElement('span');
            span.textContent = '↻ '; span.className = 'recur-mark';
            t.insertBefore(span, t.firstChild);
          }
        }
      },
      events: function (info, success, failure) {
        var slugs = Array.from(enabledSet());
        if (!slugs.length) { success([]); return; }
        var url = '/api/events.php?cals=' + encodeURIComponent(slugs.join(',')) +
          '&from=' + encodeURIComponent(info.startStr) + '&to=' + encodeURIComponent(info.endStr);
        fetch(url, { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            var colors = readJSON(K.colors, {});
            (d.events || []).forEach(function (e) {
              e.extendedProps = e.extendedProps || {};
              var slug = e.extendedProps.calendar;
              e.extendedProps.order = orderIndex(slug);
              if (slug && colors[slug]) e.color = colors[slug];
            });
            success(d.events || []);
          })
          .catch(failure);
      }
    });
    window.__nccal = fc;
    fc.render();
    requestAnimationFrame(function () { try { fc.updateSize(); } catch (e) {} });
  }

  /* ---------- emoji picker ---------- */
  function buildEmojiPicker() {
    var pop = $('ce-emoji-pop');
    if (pop.childElementCount) return;
    EMOJIS.forEach(function (e) {
      var b = document.createElement('button');
      b.type = 'button'; b.textContent = e;
      b.addEventListener('click', function () {
        $('ce-icon').value = e; pop.hidden = true;
      });
      pop.appendChild(b);
    });
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
      if (window._ncCurrent) { closeModal(); openEventEditor(normFromEvent(window._ncCurrent)); }
    });
    $('modal-delete').addEventListener('click', function () {
      if (window._ncCurrent) deleteEvent(parseInt(window._ncCurrent.id, 10));
    });

    $('ee-close').addEventListener('click', closeEditor);
    $('ee-cancel').addEventListener('click', closeEditor);
    $('event-editor').addEventListener('click', function (e) { if (e.target.id === 'event-editor') closeEditor(); });
    $('ee-form').addEventListener('submit', saveEvent);
    $('ee-allday').addEventListener('change', function () { toggleAllDayFields(this.checked); });
    $('ee-cal').addEventListener('change', paintCalSelect);
    $('ee-delete').addEventListener('click', function () { deleteEvent(eeId); });

    $('ce-close').addEventListener('click', closeCalEditor);
    $('ce-cancel').addEventListener('click', closeCalEditor);
    $('cal-editor').addEventListener('click', function (e) { if (e.target.id === 'cal-editor') closeCalEditor(); });
    $('ce-form').addEventListener('submit', saveCal);
    $('ce-delete').addEventListener('click', function () { deleteCal(ceId); });
    $('ce-emoji-btn').addEventListener('click', function () {
      buildEmojiPicker();
      var p = $('ce-emoji-pop'); p.hidden = !p.hidden;
    });
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

    var brand = $('brand-today');
    function goToday() { if (fc) fc.today(); }
    brand.addEventListener('click', goToday);
    brand.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); goToday(); }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closeModal(); closeEditor(); closeCalEditor(); $('ce-emoji-pop').hidden = true; }
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

    reloadCalendars()
      .then(function () {
        initCalendar();
        if (location.search.indexOf('seeded=1') !== -1) history.replaceState({}, '', '/');
      })
      .catch(function () {
        $('calendar-list').innerHTML = '<p class="muted small">Could not load calendars.</p>';
      });
  }
  if (document.readyState === 'complete') boot();
  else window.addEventListener('load', boot);
})();
