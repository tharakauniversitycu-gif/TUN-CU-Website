import {getDocument, GlobalWorkerOptions, TextLayer} from '/vendor/pdfjs/build/pdf.mjs';
GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/build/pdf.worker.mjs';

export async function openReader(book, main) {
  const api = window.CuMember;
  const {data: state} = await api.request(`/library/me/books/${book.id}`);
  if (!book.file) throw new Error('This book is not ready for reading.');
  main.innerHTML = `<div class="reader-top"><a class="back" href="/library/books/${book.id}">← Book details</a><h1 id="reader-title"></h1><a class="back" href="/member#bookshelf">My bookshelf ↗</a></div>
    <div id="version-note" class="info" hidden></div>
    <div class="reader-toolbar"><form id="page-form" class="reader-controls"><button type="button" id="prev" aria-label="Previous page">←</button><label>Page <input id="page" type="number" min="1" max="${book.file.page_count}" required></label><span>of ${book.file.page_count}</span><button>Go</button><button type="button" id="next" aria-label="Next page">→</button></form><div class="reader-controls"><label>Zoom <select id="zoom"><option value="fit">Fit width</option><option value="1">100%</option><option value="1.25">125%</option><option value="1.5">150%</option></select></label></div><span id="save-status" role="status">Loading book…</span></div>
    <div id="reader-error" class="info" role="alert" hidden></div><div id="conflict" class="info" hidden><p>Your reading position changed in another session. Choose which position to keep.</p><button class="button outline" id="resume-saved">Resume saved position</button> <button class="button" id="keep-position">Continue here</button></div>
    <div class="reading-area" id="reading-area" tabindex="0" aria-label="Book page. Use left and right arrow keys to change pages."><div id="pdf-page"><canvas id="pdf-canvas" aria-label="PDF page"></canvas><div class="textLayer" id="pdf-text"></div></div></div>
    <div class="reader-bottom"><span id="page-status" role="status"></span><button class="button outline" id="retry-save" hidden>Retry save</button><button class="button outline" id="complete">Mark as completed</button></div>`;
  const el = id => document.getElementById(id);
  el('reader-title').textContent = book.title;
  if (state.previous_version && !state.progress) {el('version-note').hidden = false; el('version-note').textContent = 'This is a new edition. Your previous reading history is preserved; this edition starts at page 1.';}
  let pageNumber = state.progress?.current_page || 1, revision = state.progress?.revision || 0;
  let status = state.progress?.status || 'in_progress', dirty = false, savedGeneration = 0, generation = 0;
  let pdf, loadingTask, renderTask, textLayer, destroyed = false, rendering = false, saving = null, timer, conflict = null;
  const credential = api.token();
  function validSession() { return !destroyed && api.token() === credential; }
  function report(error) {
    if (destroyed) return;
    el('reader-error').hidden = false; el('reader-error').textContent = error.message;
    if (error.status === 401) api.signin(book.id, 'read');
  }
  function updateControls() {
    el('page').value = pageNumber;
    el('prev').disabled = rendering || pageNumber <= 1; el('next').disabled = rendering || pageNumber >= book.file.page_count;
    el('complete').textContent = status === 'completed' ? 'Mark as in progress' : 'Mark as completed';
    el('complete').disabled = rendering;
  }
  function scheduleSave() {
    dirty = true; generation++; el('save-status').textContent = 'Unsaved changes…';
    clearTimeout(timer); timer = setTimeout(() => save(), 2000);
  }
  async function save(keepalive = false) {
    if (!validSession() || !dirty || conflict) return;
    if (saving) {await saving; return;}
    const captured = generation, position = pageNumber, completion = status;
    el('save-status').textContent = 'Saving…'; el('retry-save').hidden = true;
    saving = (async () => {
      try {
        const result = await api.request(`/library/me/books/${book.id}/progress`, {method:'PUT', keepalive, body:{file_id:book.file.id, expected_revision:revision, current_page:position, page_offset:0, status:completion}});
        if (!validSession()) return;
        revision = result.progress.revision; savedGeneration = captured; dirty = savedGeneration !== generation;
        el('save-status').textContent = dirty ? 'Unsaved changes…' : '✓ Saved to your account';
      } catch (e) {
        if (!validSession()) return;
        el('save-status').textContent = 'Not saved';
        if (e.status === 409 && e.data?.progress) {conflict = e.data.progress; el('conflict').hidden = false;}
        else {el('retry-save').hidden = false; report(e);}
      }
    })();
    await saving; saving = null;
    if (dirty && savedGeneration === captured && !conflict && validSession()) {clearTimeout(timer); timer = setTimeout(() => save(), 500);}
  }
  async function render(target, record = true) {
    if (!validSession() || rendering || !Number.isInteger(target) || target < 1 || target > book.file.page_count) return;
    rendering = true; updateControls();
    try {
      const page = await pdf.getPage(target);
      if (!validSession()) return;
      const base = page.getViewport({scale:1});
      const scale = el('zoom').value === 'fit' ? Math.min(1.6, Math.max(0.25, (el('reading-area').clientWidth - 30) / base.width)) : Number(el('zoom').value);
      const viewport = page.getViewport({scale}), canvas = el('pdf-canvas'), dpr = Math.min(window.devicePixelRatio || 1, 2);
      textLayer?.cancel();
      canvas.width = Math.floor(viewport.width * dpr); canvas.height = Math.floor(viewport.height * dpr);
      canvas.style.width = viewport.width + 'px'; canvas.style.height = viewport.height + 'px';
      const wrapper = el('pdf-page'); wrapper.style.width = viewport.width + 'px'; wrapper.style.height = viewport.height + 'px';
      wrapper.style.setProperty('--scale-factor', scale); wrapper.style.setProperty('--total-scale-factor', scale);
      renderTask = page.render({canvasContext:canvas.getContext('2d'), viewport, transform:dpr === 1 ? null : [dpr,0,0,dpr,0,0]});
      await renderTask.promise; if (!validSession()) return;
      el('pdf-text').replaceChildren();
      textLayer = new TextLayer({textContentSource:page.streamTextContent(), container:el('pdf-text'), viewport});
      await textLayer.render(); if (!validSession()) return;
      pageNumber = target; el('page-status').textContent = `Page ${pageNumber} of ${pdf.numPages}`;
      el('reader-error').hidden = true;
      if (record) scheduleSave();
      page.cleanup();
    } catch (e) {report(e);} finally {rendering = false; if (!destroyed) updateControls();}
  }
  el('page-form').onsubmit = e => {e.preventDefault(); render(Number(el('page').value));};
  el('prev').onclick = () => render(pageNumber - 1); el('next').onclick = () => render(pageNumber + 1);
  el('zoom').onchange = () => render(pageNumber, false);
  el('reading-area').onkeydown = e => {if (['ArrowLeft','ArrowRight'].includes(e.key)) {e.preventDefault(); render(pageNumber + (e.key === 'ArrowRight' ? 1 : -1));}};
  el('complete').onclick = () => {status = status === 'completed' ? 'in_progress' : 'completed'; updateControls(); scheduleSave(); save();};
  el('retry-save').onclick = () => save();
  el('resume-saved').onclick = async () => {revision = conflict.revision; status = conflict.status; const target = conflict.current_page; conflict = null; dirty = false; clearTimeout(timer); el('conflict').hidden = true; await render(target, false); el('save-status').textContent = '✓ Resumed saved position';};
  el('keep-position').onclick = () => {revision = conflict.revision; conflict = null; el('conflict').hidden = true; save();};
  window.addEventListener('cu:auth-changed', () => {
    destroyed = true; clearTimeout(timer); loadingTask?.destroy(); renderTask?.cancel(); textLayer?.cancel(); pdf?.destroy();
    main.innerHTML = '<div class="empty"><h1>Your session changed</h1><p>Sign in again to continue reading.</p><a class="button" href="/member">Sign in</a></div>';
  }, {once:true});
  document.addEventListener('visibilitychange', () => {if (document.visibilityState === 'hidden') save(true);});
  window.addEventListener('pagehide', () => {clearTimeout(timer); save(true);});
  window.addEventListener('beforeunload', e => {if (dirty && validSession()) {save(true); e.preventDefault(); e.returnValue = '';}});
  // Await an ordinary save before following in-app links; preserve failures for an explicit choice.
  document.addEventListener('click', async e => {
    const a = e.target.closest('a[href]'); if (!a || !dirty || destroyed || e.ctrlKey || e.metaKey || a.hash === '#main') return;
    e.preventDefault(); await save();
    if (!dirty && validSession()) location.assign(a.href);
    else report(new Error('Your position is not saved. Retry saving or resolve the reading-position conflict before leaving.'));
  });
  loadingTask = getDocument({url:`/api/library/books/${book.id}/content?version=${book.file.id}`,
    httpHeaders:{Authorization:'Bearer '+credential}, rangeChunkSize:1024*1024, disableStream:true,
    disableAutoFetch:true, isEvalSupported:false, cMapUrl:'/vendor/pdfjs/cmaps/', cMapPacked:true,
    standardFontDataUrl:'/vendor/pdfjs/standard_fonts/', wasmUrl:'/vendor/pdfjs/wasm/'});
  loadingTask.onPassword = () => {loadingTask.destroy(); report(new Error('This PDF requires a password. Please contact the library team.'));};
  pdf = await loadingTask.promise;
  if (!validSession()) {await pdf.destroy(); return;}
  if (pdf.numPages !== book.file.page_count) {await pdf.destroy(); throw new Error('The file does not match its catalogue page count. Please contact the library team.');}
  await render(pageNumber); await save();
}
