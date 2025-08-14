(function () {
  // Read the latest token at request time (handles token refresh)
  function currentToken() {
    try { return localStorage.getItem('auth_token') || ''; }
    catch (_) { return ''; }
  }

  // Attach token to every POST (x-www-form-urlencoded) request
  if (window.jQuery) {
    $.ajaxSetup({
      beforeSend: function (xhr, settings) {
        try {
          var method = (settings.type || settings.method || 'GET').toUpperCase();
          if (method !== 'POST') return;

          var token = currentToken();

          if (settings.data instanceof FormData) {
            settings.data.append('token', token);
          } else {
            var params = new URLSearchParams(settings.data || '');
            params.set('token', token);
            settings.data = params.toString();
            settings.contentType = 'application/x-www-form-urlencoded; charset=UTF-8';
          }
        } catch (e) {}
      }
    });
  }

  // Helper for fetch-based calls (reads token fresh each time)
  window.apiPost = function apiPost(url, data) {
    data = data || {};
    data.token = currentToken();
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data).toString()
    }).then(r => r.json());
  };
})();
