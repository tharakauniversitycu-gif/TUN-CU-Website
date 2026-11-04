const api = window.CuMember, main = document.getElementById('main');
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let activeBook, memberState, downloadKey;
const bookPath = id => `/library/books/${Number(id)}`;
function notice(message) { const t = document.getElementById('toast'); t.textContent = message; t.style.display = 'block'; setTimeout(() => t.style.display = 'none', 5000); }
function cover(b) {
  return b.cover_url ? `<img class="real-cover" src="${esc(b.cover_url)}" alt="Cover of ${esc(b.title)}">`
    : `<div class="cover"><small>THE CU LIBRARY</small><b>${esc(b.title)}</b><span class="ornament" aria-hidden="true">✦</span><small>${esc(b.author || 'Author not listed')}</small></div>`;
}
function card(b) { return `<article class="book-card"><div class="book-art"><a href="${bookPath(b.id)}" aria-label="View ${esc(b.title)}">${cover(b)}</a></div><span class="tag">${esc(b.category?.name || 'Collection')}</span><h3><a href="${bookPath(b.id)}">${esc(b.title)}</a></h3><div class="author">${esc(b.author || 'Author not listed')}</div><div class="card-bottom"><span>PDF${b.file?.page_count ? ' · ' + b.file.page_count + ' pages' : ''}</span><a href="${bookPath(b.id)}">Explore book ↗</a></div></article>`; }
function errorView(error) {
  main.innerHTML = `<div class="empty"><h1>${error.status === 404 ? 'Book unavailable' : 'Unable to open the library'}</h1><p>${esc(error.message)}</p><a class="button outline" href="/library">Browse library</a> <button class="button" id="retry">Try again</button></div>`;
  document.getElementById('retry').onclick = () => location.reload();
}
async function catalogue() {
  const params = new URLSearchParams(location.search);
  const {data: categories} = await api.request('/library/categories', {authenticated:false});
  main.innerHTML = `<section class="hero"><div class="hero-copy"><div class="eyebrow">THE CU LIBRARY</div><h1>Good books.<br>Deeper roots.</h1><p>A space to explore, reflect, and grow. Discover books for your faith, relationships, and everyday life.</p></div><div class="hero-art"><div class="library-quote">“Let the word of Christ<br>dwell in you richly.”<small>COLOSSIANS 3:16</small></div></div></section>
    <form id="search-form" class="searchbar"><input id="search" name="q" type="search" maxlength="200" aria-label="Search books" placeholder="Search by title or author" value="${esc(params.get('q') || '')}"><button class="button small">Search</button></form>
    <div class="catalogue-layout"><aside class="categories" aria-label="Book categories"><span class="eyebrow">BROWSE BY TOPIC</span><button class="category" data-category="">All books</button>${categories.map(c => `<button class="category" data-category="${esc(c.slug)}">${esc(c.name)}</button>`).join('')}</aside><section><div class="section-top"><h2 id="results-title">Explore the collection</h2><label>Sort <select id="sort"><option value="featured">Featured first</option><option value="title">Title A–Z</option><option value="recent">Newest first</option></select></label></div><div id="results" class="books" aria-live="polite"></div><div id="pagination" class="actions"></div></section></div>`;
  let sequence = 0;
  async function load(page = 1) {
    const current = ++sequence; params.set('page', page); params.set('q', document.getElementById('search').value.trim());
    params.set('sort', document.getElementById('sort').value);
    history.replaceState(null, '', '/library?' + params);
    document.querySelectorAll('[data-category]').forEach(b => {const selected = b.dataset.category === (params.get('category') || ''); b.classList.toggle('active', selected); b.setAttribute('aria-pressed', selected);});
    document.getElementById('results').innerHTML = '<p role="status">Loading books…</p>';
    try {
      const result = await api.request('/library/books?' + params, {authenticated:false}); if (current !== sequence) return;
      document.getElementById('results-title').textContent = `Explore the collection · ${result.total}`;
      document.getElementById('results').innerHTML = result.data.length ? result.data.map(card).join('') : '<div class="empty catalogue-empty"><h2>No books found</h2><p>Try another title or topic. New resources will appear here after they have been reviewed and published.</p></div>';
      const paging = document.getElementById('pagination'); paging.innerHTML = '';
      for (const [label, target] of [['Previous', result.current_page - 1], ['Next', result.current_page + 1]]) {
        if (target < 1 || target > result.last_page) continue;
        const button = document.createElement('button'); button.className = 'button outline'; button.textContent = label; button.onclick = () => load(target); paging.append(button);
      }
    } catch (e) { if (current === sequence) { document.getElementById('results').textContent = e.message; } }
  }
  document.getElementById('sort').value = ['featured','title','recent'].includes(params.get('sort')) ? params.get('sort') : 'featured';
  document.getElementById('search-form').onsubmit = e => {e.preventDefault(); load();};
  document.getElementById('sort').onchange = () => load();
  document.querySelectorAll('[data-category]').forEach(button => button.onclick = () => {params.set('category', button.dataset.category); load();});
  await load(Math.max(1, Number(params.get('page')) || 1));
}
async function details(b) {
  memberState = null;
  if (api.token()) { try { memberState = (await api.request(`/library/me/books/${b.id}`)).data; } catch (e) { if (e.status !== 401) notice(e.message); } }
  main.innerHTML = `<a class="back" href="/library">← Back to the library</a><section class="detail"><div><div class="detail-art">${cover(b)}</div>${b.cover_url ? '' : '<p class="footnote">Cover image unavailable</p>'}</div><div><div class="eyebrow">${esc(b.category?.name || 'Collection')}</div><h1>${esc(b.title)}</h1><p class="author">By ${esc(b.author || 'Author not listed')}</p><p class="description">${esc(b.description || 'No description has been provided yet.')}</p><div class="facts"><div><span>FORMAT</span>PDF</div><div><span>LENGTH</span>${b.file?.page_count || '—'} pages</div><div><span>ACCESS</span>CU members</div></div><div class="actions"><button class="button" data-action="read">${memberState?.progress ? 'Continue reading' : 'Start reading'} →</button><button class="button outline" data-action="download">${new URLSearchParams(location.search).has('download') ? 'Continue download' : 'Download'} ↓</button><button class="button outline" data-action="like" aria-pressed="${!!memberState?.liked}">${memberState?.liked ? '♥ Liked' : '♡ Like book'}</button></div><p class="info" id="action-status" role="status">${api.token() ? 'Your bookshelf and reading position are saved to your CU account.' : 'Browse freely. Sign in with your CU account to read, download, and save books.'}</p></div></section>`;
  document.querySelectorAll('[data-action]').forEach(button => button.onclick = async () => {
    const action = button.dataset.action;
    if (!api.token()) { api.signin(b.id, action); return; }
    button.disabled = true;
    try {
      if (action === 'read') {location.assign(bookPath(b.id) + '/read'); return;}
      if (action === 'like') {
        const result = await api.request(`/library/me/books/${b.id}/like`, {method:memberState?.liked ? 'DELETE' : 'PUT'});
        memberState = {...memberState, liked:result.liked}; button.textContent = result.liked ? '♥ Liked' : '♡ Like book'; button.setAttribute('aria-pressed', result.liked);
        document.getElementById('action-status').textContent = result.liked ? 'Saved to your liked books.' : 'Removed from liked books.';
      } else {
        downloadKey ||= crypto.randomUUID();
        const data = await api.request(`/library/books/${b.id}/download`, {method:'POST', body:{request_key:downloadKey}, binary:true});
        const url = URL.createObjectURL(new Blob([data], {type:'application/pdf'}));
        const link = document.createElement('a'); link.href = url; link.download = `cu-book-${b.id}.pdf`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 10000);
        document.getElementById('action-status').textContent = 'Download requested. Check your browser’s downloads.';
        downloadKey = null;
      }
    } catch (e) { if (e.status === 401) api.signin(b.id, action); else document.getElementById('action-status').textContent = e.message; }
    finally {button.disabled = false;}
  });
}
api.token() && (document.getElementById('account-link').textContent = 'Member dashboard');
window.addEventListener('cu:auth-changed', () => { memberState = null; document.getElementById('account-link').textContent = api.token() ? 'Member dashboard' : 'Sign in'; });
try {
  const match = location.pathname.match(/^\/library\/books\/(\d+)(\/read)?\/?$/);
  if (!match) await catalogue();
  else {
    activeBook = (await api.request('/library/books/' + match[1], {authenticated:false})).data;
    document.title = `${activeBook.title} · CU Library`;
    if (match[2]) {
      if (!api.token()) api.signin(activeBook.id, 'read');
      else { const {openReader} = await import('/js/library-reader.js'); await openReader(activeBook, main); }
    } else await details(activeBook);
  }
} catch (error) { if (error.status === 401 && activeBook) api.signin(activeBook.id, 'read'); else errorView(error); }
