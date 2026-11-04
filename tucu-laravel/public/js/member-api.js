/* Shared bearer client. Member tokens are never placed in a URL. */
(() => {
  const tokenKey = 'tucu_member_token', intentKey = 'tucu_library_intent';
  const controllers = new Set();
  const token = () => localStorage.getItem(tokenKey);
  function changed() {
    controllers.forEach(c => c.abort()); controllers.clear();
    window.dispatchEvent(new Event('cu:auth-changed'));
  }
  function clear() {
    localStorage.removeItem(tokenKey); localStorage.removeItem('tucu_member_id'); changed();
  }
  async function request(path, {method = 'GET', body, binary = false, authenticated = true, keepalive = false} = {}) {
    const credential = token(), controller = new AbortController(); controllers.add(controller);
    try {
      const response = await fetch('/api' + path, {method, signal: controller.signal, keepalive, cache: 'no-store', credentials: 'omit',
        headers: {Accept: 'application/json', ...(body ? {'Content-Type': 'application/json'} : {}),
          ...(authenticated && credential ? {Authorization: 'Bearer ' + credential} : {})},
        ...(body ? {body: JSON.stringify(body)} : {})});
      if (authenticated && credential !== token()) throw new DOMException('Account changed', 'AbortError');
      if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        if (response.status === 401 && authenticated) clear();
        const error = new Error(data.message || 'The request could not be completed. Please try again.');
        error.status = response.status; error.data = data; throw error;
      }
      const result = binary ? await response.arrayBuffer() : await response.json();
      if (authenticated && credential !== token()) throw new DOMException('Account changed', 'AbortError');
      return result;
    } finally { controllers.delete(controller); }
  }
  function intent() {
    try {
      const value = JSON.parse(sessionStorage.getItem(intentKey));
      if (!value || !Number.isSafeInteger(value.bookId) || value.bookId < 1 || !['read', 'like', 'download'].includes(value.action)
        || typeof value.nonce !== 'string' || !Number.isFinite(value.createdAt) || Date.now() - value.createdAt > 1800000 || value.createdAt > Date.now()) {
        sessionStorage.removeItem(intentKey); return null;
      }
      return value;
    } catch { sessionStorage.removeItem(intentKey); return null; }
  }
  function signin(bookId, action) {
    if (Number.isSafeInteger(bookId) && bookId > 0 && ['read', 'like', 'download'].includes(action)) {
      sessionStorage.setItem(intentKey, JSON.stringify({bookId, action, createdAt: Date.now(), nonce: crypto.randomUUID()}));
    }
    location.assign('/member?library=1');
  }
  async function resumeIntent() {
    const next = intent(); if (!next || !token()) return false;
    await request(`/library/me/books/${next.bookId}`); // Recheck availability and eligibility.
    if (next.action === 'like') await request(`/library/me/books/${next.bookId}/like`, {method: 'PUT'});
    sessionStorage.removeItem(intentKey);
    location.assign(`/library/books/${next.bookId}` + (next.action === 'read' ? '/read' : next.action === 'download' ? '?download=1' : '?liked=1'));
    return true;
  }
  window.addEventListener('storage', e => { if (e.key === tokenKey || e.key === null) changed(); });
  window.CuMember = {token, request, clear, intent, signin, resumeIntent, cancelIntent: () => sessionStorage.removeItem(intentKey)};
})();
