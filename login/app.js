// /login/app.js
// React controller that wires your existing login form without changing the design.
// Robust path detection for auth_api.php + clearer diagnostics.

console.log('[login] controller loaded');

const e = React.createElement;

// ---- resolve AUTH_URL robustly (../, ./, or /) ----
const AUTH_CANDIDATES = [
  '../auth_api.php',   // common when /login is the docroot
  './auth_api.php',    // if auth_api.php is in the same folder
  '/auth_api.php'      // when project root is the docroot
];

let AUTH_URL = null;
async function pickAuthUrl() {
  if (AUTH_URL) return AUTH_URL;
  for (const u of AUTH_CANDIDATES) {
    try {
      // lightweight HEAD: many PHP servers won’t allow HEAD, so use GET with a ping param
      const r = await fetch(u + (u.includes('?') ? '&' : '?') + 'ping=1', { method: 'GET' });
      if (r.ok) {
        AUTH_URL = u;
        console.log('[login] AUTH_URL resolved to', AUTH_URL);
        return AUTH_URL;
      }
    } catch (_) { }
  }
  // last resort: default to relative
  AUTH_URL = './auth_api.php';
  console.warn('[login] AUTH_URL fallback to', AUTH_URL);
  return AUTH_URL;
}

async function postForm(action, data) {
  const url = await pickAuthUrl();
  const body = new URLSearchParams(Object.assign({ action }, data || {})).toString();
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  });
  const txt = await res.text();
  // Log for diagnostics
  console.log('[login] POST ->', url, 'status:', res.status, 'raw:', txt.slice(0, 200));

  // Try to parse JSON
  try {
    return JSON.parse(txt);
  } catch {
    // Give a friendly hint if we got HTML/404 etc.
    if (!res.ok || /^<!doctype html>|^<html/i.test(txt)) {
      throw new Error(`Server returned ${res.status}. Check auth_api.php path or PHP error output.`);
    }
    throw new Error('Server returned non-JSON. Check PHP warnings/notices.');
  }
}

function rememberToken(token) {
  try { localStorage.setItem('auth_token', token); } catch (_) { }
  document.cookie = 'auth_token=' + encodeURIComponent(token) + '; Path=/; SameSite=Lax';
}

// ---- React controller (no UI rendering) ----
function Controller() {
  React.useEffect(() => {
    const form = document.getElementById('loginForm');
    const input = document.getElementById('userName');
    const errEl = document.getElementById('emailError'); // matches your HTML
    const button = form ? form.querySelector('.comfort-button') : null;
    const btnText = button ? button.querySelector('.button-text') : null;
    const loader = button ? button.querySelector('.button-loader') : null;
    const success = document.getElementById('successMessage');

    if (!form || !input || !button) {
      console.error('[login] missing required nodes (#loginForm, #userName, .comfort-button)');
      return;
    }

    const setError = (msg) => {
      if (!errEl) return;
      if (msg) {
        errEl.textContent = msg;
        errEl.classList.add('show');
        const field = input.closest('.soft-field');
        if (field) {
          field.classList.add('error');
          field.style.animation = 'none';
          field.style.transform = 'translateX(2px)';
          setTimeout(() => field.style.transform = 'translateX(-2px)', 100);
          setTimeout(() => field.style.transform = 'translateX(0)', 200);
        }
      } else {
        errEl.classList.remove('show');
        errEl.textContent = '';
        const field = input.closest('.soft-field');
        if (field) field.classList.remove('error');
      }
    };

    const setLoading = (v) => {
      button.disabled = !!v;
      button.classList.toggle('loading', !!v);
      if (loader) loader.style.display = v ? 'block' : '';
    };

    const tempButtonHint = (text, dur = 1600) => {
      if (!btnText) return;
      const original = btnText.textContent;
      btnText.textContent = text;
      setTimeout(() => { btnText.textContent = original; }, dur);
    };

    const showSuccessAndMaybeRedirect = (doRedirect) => {
      if (success) success.classList.add('show');
      if (doRedirect) setTimeout(() => (redirectToApp()), 600);
    };

    function redirectToApp() {
      const proto = location.protocol;      
      const port = location.port ? ':' + location.port : '';
      window.location.replace(window.location.origin + '/');
    }

    const onSubmit = async (ev) => {
      ev.preventDefault();
      const u = (input.value || '').trim();
      if (!u) { setError('Please enter your username'); return; }
      setError('');
      setLoading(true);

      try {
        // 1) request OTP
        const res = await postForm('otp_request', { username: u, hp: '' });
        if (res && res.ok) {
          if (res.dev === true) {
            // DEV MODE: directly verify and go in
            const v = await postForm('otp_verify', { username: u });
            if (v && v.ok && v.token) {
              rememberToken(v.token);
              showSuccessAndMaybeRedirect(true);
              return;
            }
            setError(v?.error || 'DEV verify failed');
            return;
          }
          // Normal mode: show hint only
          tempButtonHint('OTP sent ✓');
          return;
        }
        setError(res?.error || 'OTP request failed');
      } catch (err) {
        console.error('[login] otp_request error:', err);
        setError(err.message || 'OTP request failed');
      } finally {
        setLoading(false);
      }
    };

    form.addEventListener('submit', onSubmit);
    return () => form.removeEventListener('submit', onSubmit);
  }, []);

  return null; // no UI changes; design stays exactly as in HTML
}

// Mount the controller invisibly
(function mount() {
  function start() {
    const mountNode = document.getElementById('root');
    if (!mountNode) { console.error('[login] #root not found'); return; }
    if (mountNode.__reactRoot) return;
    const root = ReactDOM.createRoot(mountNode);
    mountNode.__reactRoot = root;
    root.render(e(Controller));
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
