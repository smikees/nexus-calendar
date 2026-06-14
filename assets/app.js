(function () {
  'use strict';

  var K = {
    enabled: 'nc_enabled_v1', view: 'nc_view_v1', order: 'nc_order_v1',
    colors: 'nc_colors_v1', mode: 'nc_mode_v1', theme: 'nc_theme_v1'
  };
  var calendarsById = {};
  var fc = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function readJSON(k, fb) { try { var v = JSON.parse(localStorage.getItem(k)); return v == null ? fb : v; } catch (e) { return fb; } }
  function writeJSON(k, v) { localStorage.setItem(k, JSON.stringify(v)); }

  /* ---------- theme ---------- */
  function initTheme() {
    var root = document.documentElement;
    var btn = document.getElementById('mode-toggle');
    var sel = document.getElementById('theme-select');
    sel.value = root.dataset.theme || 'sky';
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
    var def = Object.keys(calendarsById);              // server priority order
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

  /* ---------- sidebar ---------- */
  function renderSidebar(user) {
    var list = document.getElementById('calendar-list');
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
          '<input type="color" class="color-input" hidden value="' + esc(colorFor(slug)) + '">';
        list.appendChild(row);
      });
    }
    var admin = document.getElementById('admin-tools');
    if (user && user.is_admin) admin.hidden = false;
  }

  function onSidebarClick(ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-act]') : null;
    if (!btn) return;
    var row = ev.target.closest('.cal-row'); if (!row) return;
    var slug = row.dataset.slug;
    var act = btn.dataset.act;
    if (act === 'color') {
      row.querySelector('.color-input').click();
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
    var el = document.getElementById('auth');
    if (user) {
      var admin = user.is_admin ? ' <span class="muted small">· admin</span>' : '';
      el.innerHTML = '<span class="user">' + esc(user.email) + '</span>' + admin +
        ' <a class="btn small" href="/auth/logout.php">Log out</a>';
    } else {
      el.innerHTML = '<a class="btn primary small" href="/auth/login.php">Sign in with Google</a>';
    }
  }

  /* ---------- modal ---------- */
  function openModal(info) {
    var p = info.event.extendedProps || {};
    document.getElementById('modal-title').textContent = info.event.title;
    var calLine = p.calendarName || '';
    if (p.recurring) calLine += ' · ↻ repeats';
    document.getElementById('modal-cal').textContent = calLine;
    var s = info.event.start, e = info.event.end;
    var opts = info.event.allDay
      ? { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }
      : { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    var when = s ? s.toLocaleString(undefined, opts) : '';
    if (e && !info.event.allDay) when += ' – ' + e.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    document.getElementById('modal-when').textContent = when;
    document.getElementById('modal-where').textContent = p.location || '';
    document.getElementById('modal-desc').textContent = p.description || '';
    document.getElementById('event-modal').hidden = false;
  }
  function closeModal() { document.getElementById('event-modal').hidden = true; }

  /* ---------- calendar ---------- */
  function initCalendar() {
    var el = document.getElementById('calendar');
    fc = new FullCalendar.Calendar(el, {
      initialView: localStorage.getItem(K.view) || 'dayGridMonth',
      height: 'auto',
      firstDay: 1,
      nowIndicator: true,
      dayMaxEvents: 3,
      headerToolbar: {
        left: 'prev,next today', center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      buttonText: { today: 'Today', month: 'Month', week: 'Week', day: 'Day', list: 'Agenda' },
      eventOrder: function (a, b) {
        var oa = orderIndex(a.extendedProps.calendar), ob = orderIndex(b.extendedProps.calendar);
        if (oa !== ob) return oa - ob;
        return (a.start ? a.start.getTime() : 0) - (b.start ? b.start.getTime() : 0);
      },
      datesSet: function (info) { localStorage.setItem(K.view, info.view.type); },
      eventClick: function (info) { info.jsEvent.preventDefault(); openModal(info); },
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
              var slug = e.extendedProps && e.extendedProps.calendar;
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

  /* ---------- boot ---------- */
  function boot() {
  initTheme();
  document.getElementById('modal-close').addEventListener('click', closeModal);
  document.getElementById('event-modal').addEventListener('click', function (e) {
    if (e.target.id === 'event-modal') closeModal();
  });
  var listEl = document.getElementById('calendar-list');
  listEl.addEventListener('click', onSidebarClick);
  listEl.addEventListener('change', onSidebarChange);

  fetch('/api/calendars.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      (d.calendars || []).forEach(function (c) { calendarsById[c.slug] = c; });
      window._ncUser = d.user;
      renderHeader(d.user);
      renderSidebar(d.user);
      initCalendar();
      if (location.search.indexOf('seeded=1') !== -1) history.replaceState({}, '', '/');
    })
    .catch(function () {
      document.getElementById('calendar-list').innerHTML = '<p class="muted small">Could not load calendars.</p>';
    });
  }
  if (document.readyState === 'complete') boot();
  else window.addEventListener('load', boot);
})();
