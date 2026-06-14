(function () {
  'use strict';

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function renderAuth(d) {
    var el = document.getElementById('auth');
    var status = document.getElementById('status');
    if (d && d.authenticated && d.user) {
      var admin = d.user.is_admin ? ' <span class="muted small">· admin</span>' : '';
      el.innerHTML =
        '<span class="user">' + escapeHtml(d.user.name || d.user.email) + '</span>' + admin +
        ' <a class="btn" href="/auth/logout.php">Log out</a>';
      if (status) status.textContent = 'Signed in as ' + (d.user.email || '');
    } else {
      el.innerHTML = '<a class="btn primary" href="/auth/login.php">Sign in with Google</a>';
      if (status) status.textContent = 'Browsing as a guest. Sign in to add private calendars.';
    }
  }

  fetch('/api/me.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(renderAuth)
    .catch(function () { renderAuth({ authenticated: false }); });
})();
