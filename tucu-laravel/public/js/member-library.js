(() => {
  const api = window.CuMember;
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let collection = 'in_progress', sequence = 0;
  function row(book) {
    const progress = book.member_state?.progress;
    const info = collection === 'downloads' ? 'Download requested · Offline reading is not tracked' : progress ? `Page ${progress.current_page} of ${book.file?.page_count || '—'}` : 'Saved to your liked books';
    return `<article class="member-book"><div class="member-book-mark" aria-hidden="true">▤</div><div><h3>${esc(book.title)}</h3><p>${esc(book.author || 'Author not listed')}</p><small>${esc(info)}</small></div><a class="text-action" href="/library/books/${book.id}/read">${progress ? 'Continue reading' : 'Start reading'} →</a></article>`;
  }
  async function load(page = 1) {
    const current = ++sequence, target = document.getElementById('member-books');
    if (!api.token()) {target.replaceChildren(); return;}
    target.textContent = 'Loading your bookshelf…';
    try {
      const data = await api.request(`/library/me/books?collection=${collection}&page=${page}`);
      if (current !== sequence) return;
      target.innerHTML = data.data.length ? data.data.map(row).join('') : '<p class="overview-empty">This shelf is waiting for your next book. <a href="/library">Explore the library →</a></p>';
      const pagination = document.getElementById('member-books-pages'); pagination.replaceChildren();
      for (const [label, next] of [['Previous', data.current_page - 1], ['Next', data.current_page + 1]]) {
        if (next < 1 || next > data.last_page) continue;
        const button = document.createElement('button'); button.className = 'text-action'; button.textContent = label; button.onclick = () => load(next); pagination.append(button);
      }
    } catch (e) { if (current === sequence) target.textContent = e.message; }
  }
  async function overview() {
    const target = document.getElementById('reading-overview');
    try {
      const data = await api.request('/library/me/books?collection=in_progress');
      target.replaceChildren();
      if (!data.data.length) { target.innerHTML = '<p class="overview-empty">Open a book from the library and your reading place will appear here.</p>'; return; }
      for (const book of data.data.slice(0, 2)) {
        const a = document.createElement('a'); a.className = 'member-reading-link'; a.href = `/library/books/${book.id}/read`;
        a.textContent = `${book.title} — Resume page ${book.member_state.progress.current_page}`; target.append(a);
      }
    } catch (e) {target.textContent = e.message;}
  }
  async function loadPreview() {
    const target = document.getElementById('lib-preview');
    target.setAttribute('aria-busy', 'true');
    target.replaceChildren(Object.assign(document.createElement('p'), {textContent:'Loading books…'}));
    try {
      const result = await api.request('/library/books?sort=featured', {authenticated:false});
      target.replaceChildren();
      if (!result.data.length) {
        const empty = document.createElement('p');
        empty.className = 'library-preview-message';
        empty.textContent = 'The collection is being prepared. Please check back soon.';
        target.append(empty);
        return;
      }
      result.data.slice(0, 4).forEach(book => {
        const link = document.createElement('a');
        link.className = 'library-preview-book';
        link.href = `/library/books/${encodeURIComponent(book.id)}`;
        const cover = document.createElement('div');
        cover.className = 'library-preview-cover';
        if (book.cover_url) {
          const image = document.createElement('img');
          image.src = book.cover_url;
          image.alt = `Cover of ${book.title}`;
          image.loading = 'lazy';
          image.onerror = () => {
            const fallback = document.createElement('span');
            fallback.setAttribute('aria-hidden', 'true');
            fallback.textContent = '▤';
            cover.replaceChildren(fallback);
          };
          cover.append(image);
        } else {
          const fallback = document.createElement('span');
          fallback.setAttribute('aria-hidden', 'true');
          fallback.textContent = '▤';
          cover.append(fallback);
        }
        const category = document.createElement('span');
        category.className = 'library-preview-category';
        category.textContent = book.category?.name || 'CU Library';
        const title = document.createElement('strong');
        title.textContent = book.title;
        const author = document.createElement('span');
        author.className = 'library-preview-author';
        author.textContent = book.author || 'Author not listed';
        link.append(cover, category, title, author);
        target.append(link);
      });
    } catch (error) {
      const message = document.createElement('p');
      message.className = 'library-preview-message';
      message.textContent = `Books could not be loaded: ${error.message}`;
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'text-action';
      retry.textContent = 'Try again';
      retry.addEventListener('click', loadPreview, {once:true});
      target.replaceChildren(message, retry);
    } finally {
      target.removeAttribute('aria-busy');
    }
  }
  document.querySelectorAll('[data-shelf]').forEach(button => button.onclick = () => {
    collection = button.dataset.shelf;
    document.querySelectorAll('[data-shelf]').forEach(b => b.setAttribute('aria-pressed', b === button)); load();
  });
  document.getElementById('refresh-books').onclick = () => {load(); overview();};
  window.addEventListener('cu:portal-ready', () => {load(); overview(); loadPreview(); if (location.hash === '#bookshelf') openPanel('bookshelf');});
  window.addEventListener('cu:auth-changed', () => {sequence++; document.getElementById('member-books').replaceChildren(); document.getElementById('reading-overview').replaceChildren(); document.getElementById('member-books-pages').replaceChildren();});
  const next = api.intent();
  if (next) {
    const context = document.createElement('p'); context.className = 'library-auth-context';
    context.textContent = `Sign in or register to ${next.action} your selected library book. You’ll return to it automatically. `;
    const cancel = document.createElement('a'); cancel.href = '/library'; cancel.textContent = 'Cancel and browse'; cancel.onclick = () => api.cancelIntent();
    context.append(cancel); document.getElementById('login-form').before(context);
  }
})();
