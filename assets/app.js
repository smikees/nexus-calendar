(function () {
  'use strict';

  var LS_ENABLED = 'nc_enabled_v1';
  var LS_VIEW = 'nc_view_v1';
  var calendarsById = {};
  var fc = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function getEnabled() {
    try { return JSON.parse(localStorage.getItem(LS_ENABLED)) || null; }
    catch (e) { return null; }
  }
  function setEnabled(set) {
    localStorage.setItem(LS_ENABLED, JSON.stringify(Array.from(set)));
  }

  function enabledSlugs() {
    var stored = getEnabled();
    if (stored === null) {
      // default: everything on
      return new Set(Object.keys(calendarsById));
    }
    return new Set(stored);
  }

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

  function renderSidebar(cals, user) {
    var list = document.getElementById('calendar-list');
    var enabled = enabledSlugs();
    if (!cals.length) {
      list.innerHTML = '<p class="muted small">No calendars yet.</p>';
    } else {
      list.innerHTML = '';
      cals.forEach(function (c) {
        var row = document.createElement('label');
        row.className = 'cal-row';
        row.innerHTML =
          '<input type="checkbox" data-slug="' + esc(c.slug) + '"' +
          (enabled.has(c.slug) ? ' checked' : '') + '>' +
          '<span class="dot" style="background:' + esc(c.color) + '"></span>' +
          '<span class="cal-name">' + (c.icon ? esc(c.icon) + ' ' : '') + esc(c.name) + '</span>';
        list.appendChild(row);
      });
      list.addEventListener('change', function (ev) {
        var cb = ev.target;
        if (!cb.dataset || !cb.dataset.slug) return;
        var set = enabledSlugs();
        if (cb.checked) set.add(cb.dataset.slug); else set.delete(cb.dataset.slug);
        setEnabled(set);
        if (fc) fc.refetchEvents();
      });
    }
    var admin = document.getElementById('admin-tools');
    if (user && user.is_admin) admin.hidden = false;
  }

  function openModal(ev) {
    var p = ev.event.extendedProps || {};
    document.getElementById('modal-title').textContent = ev.event.title;
    document.getElementById('modal-cal').textContent = p.calendarName || '';
    var s = ev.event.start, e = ev.event.end;
    var opts = ev.event.allDay
      ? { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }
      : { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    var when = s ? s.toLocaleString(undefined, opts) : '';
    if (e && !ev.event.allDay) when += ' – ' + e.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    document.getElementById('modal-when').textContent = when;
    document.getElementById('modal-where').textContent = p.location || '';
    document.getElementById('modal-desc').textContent = p.description || '';
    document.getElementById('event-modal').hidden = false;
  }
  function closeModal() { document.getElementById('event-modal').hidden = true; }

  function initCalendar() {
    var el = document.getElementById('calendar');
    var startView = localStorage.getItem(LS_VIEW) || 'dayGridMonth';
    fc = new FullCalendar.Calendar(el, {
      initialView: startView,
      height: 'auto',
      firstDay: 1,
      nowIndicator: true,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      buttonText: { today: 'Today', month: 'Month', week: 'Week', day: 'Day', list: 'Agenda' },
      datesSet: function (info) { localStorage.setItem(LS_VIEW, info.view.type); },
      eventClick: function (info) { info.jsEvent.preventDefault(); openModal(info); },
      events: function (info, success, failure) {
        var slugs = Array.from(enabledSlugs());
        if (!slugs.length) { success([]); return; }
        var url = '/api/events.php?cals=' + encodeURIComponent(slugs.join(',')) +
          '&from=' + encodeURIComponent(info.startStr) + '&to=' + encodeURIComponent(info.endStr);
        fetch(url, { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { success(d.events || []); })
          .catch(failure);
      }
    });
    fc.render();
  }

  document.getElementById('modal-close').addEventListener('click', closeModal);
  document.getElementById('event-modal').addEventListener('click', function (e) {
    if (e.target.id === 'event-modal') closeModal();
  });

  fetch('/api/calendars.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      (d.calendars || []).forEach(function (c) { calendarsById[c.slug] = c; });
      renderHeader(d.user);
      renderSidebar(d.calendars || [], d.user);
      initCalendar();
      if (location.search.indexOf('seeded=1') !== -1) {
        history.replaceState({}, '', '/');
      }
    })
    .catch(function () {
      document.getElementById('calendar-list').innerHTML =
        '<p class="muted small">Could not load calendars.</p>';
    });
})();
