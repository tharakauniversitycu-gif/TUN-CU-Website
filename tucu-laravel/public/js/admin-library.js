(() => {
  const booksNode = document.getElementById('library-admin-books');
  if (!booksNode) return;

  const byId = id => document.getElementById(id);
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[character]);
  const fileSize = bytes => {
    const size = Number(bytes) || 0;
    return size >= 1024 * 1024 ? `${(size / (1024 * 1024)).toFixed(1)} MiB` : `${Math.max(1, Math.round(size / 1024))} KiB`;
  };
  const formatDate = value => value
    ? new Date(value).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' })
    : '—';

  let books = [];
  let categories = [];
  let counts = { all: 0, published: 0, draft: 0, archived: 0 };
  let selectedIds = new Set();
  let status = '';
  let page = 1;
  let lastPage = 1;
  let requestSequence = 0;
  let loaded = false;
  let searchTimer;

  function showMessage(message, isError = false) {
    const node = byId('library-admin-form-message');
    if (!node) return;
    node.textContent = message;
    node.classList.toggle('error', isError);
  }

  function updateUploadProgress(percent, label) {
    const progress = byId('library-admin-upload-progress');
    progress.hidden = false;
    byId('library-admin-progress').value = percent;
    byId('library-admin-progress-percent').textContent = `${percent}%`;
    byId('library-admin-progress-label').textContent = label;
  }

  function hideUploadProgress() {
    byId('library-admin-upload-progress').hidden = true;
  }

  function request(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    headers.set('Authorization', `Bearer ${localStorage.getItem('tucu_admin_token') || ''}`);
    if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');

    return fetch(`${window.location.origin}/api${path}`, { ...options, headers }).then(async response => {
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        const validation = result.errors ? Object.values(result.errors).flat()[0] : null;
        throw new Error(validation || result.message || `Request failed (${response.status}).`);
      }
      return result;
    });
  }

  function upload(path, form, method = 'POST') {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open(method, `${window.location.origin}/api${path}`);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('Authorization', `Bearer ${localStorage.getItem('tucu_admin_token') || ''}`);
      xhr.upload.addEventListener('progress', event => {
        if (!event.lengthComputable) return;
        const percent = Math.min(99, Math.round((event.loaded / event.total) * 100));
        updateUploadProgress(percent, percent === 99 ? 'Upload received · validating PDF…' : 'Uploading files…');
      });
      xhr.addEventListener('load', () => {
        const result = (() => {
          try { return JSON.parse(xhr.responseText); } catch { return {}; }
        })();
        if (xhr.status >= 200 && xhr.status < 300) {
          updateUploadProgress(100, 'Upload complete');
          resolve(result);
          return;
        }
        const validation = result.errors ? Object.values(result.errors).flat()[0] : null;
        reject(new Error(validation || result.message || `Upload failed (${xhr.status}).`));
      });
      xhr.addEventListener('error', () => reject(new Error('The upload was interrupted. Check your connection and try again.')));
      xhr.addEventListener('abort', () => reject(new Error('The upload was cancelled.')));
      xhr.send(form);
    });
  }

  function renderCategories() {
    const filter = byId('library-admin-category-filter');
    const selectedFilter = filter.value;
    filter.innerHTML = '<option value="">All categories</option>' + categories.map(category =>
      `<option value="${category.id}">${escapeHtml(category.name)}</option>`
    ).join('');
    if (categories.some(category => String(category.id) === selectedFilter)) filter.value = selectedFilter;

    const editorSelect = byId('library-category');
    const selectedCategory = editorSelect.value;
    editorSelect.innerHTML = '<option value="">Uncategorized</option>' + categories.map(category =>
      `<option value="${category.id}">${escapeHtml(category.name)}</option>`
    ).join('');
    if (categories.some(category => String(category.id) === selectedCategory)) editorSelect.value = selectedCategory;

    byId('library-admin-category-list').innerHTML = categories.length
      ? categories.map(category => `<span class="library-admin-pill">${escapeHtml(category.name)}</span>`).join('')
      : '<span class="library-admin-state">No categories yet.</span>';
  }

  function renderStats() {
    byId('library-count-all').textContent = counts.all;
    byId('library-count-published').textContent = counts.published;
    byId('library-count-draft').textContent = counts.draft;
    byId('library-count-archived').textContent = counts.archived;
    document.querySelectorAll('[data-library-status]').forEach(button => {
      button.classList.toggle('active', button.dataset.libraryStatus === status);
      button.setAttribute('aria-pressed', String(button.dataset.libraryStatus === status));
    });
  }

  function renderSelection() {
    const visibleIds = books.map(book => String(book.id));
    const selectedOnPage = visibleIds.filter(id => selectedIds.has(id)).length;
    byId('library-admin-bulkbar').hidden = selectedIds.size === 0;
    byId('library-admin-selected-count').textContent = `${selectedIds.size} selected`;
    byId('library-admin-select-page').checked = visibleIds.length > 0 && selectedOnPage === visibleIds.length;
    byId('library-admin-select-page').indeterminate = selectedOnPage > 0 && selectedOnPage < visibleIds.length;
    byId('library-admin-bulk-apply').disabled = selectedIds.size === 0 || !byId('library-admin-bulk-action').value;
    booksNode.querySelectorAll('[data-library-select]').forEach(input => {
      input.checked = selectedIds.has(input.value);
    });
  }

  function renderBooks() {
    if (!books.length) {
      booksNode.innerHTML = `<tr><td colspan="7"><div class="library-admin-state">No titles match these filters. Try a different search or add a book.</div></td></tr>`;
      renderSelection();
      return;
    }

    booksNode.innerHTML = books.map(book => {
      const current = book.current_file;
      const nextStatus = book.status === 'published' ? 'archived' : 'published';
      const actionLabel = book.status === 'published' ? 'Archive' : 'Publish';
      const invalidEdition = nextStatus === 'published'
        && (!current || current.validation_status !== 'valid' || !Number(current.page_count));
      const edition = current
        ? `v${current.version} · ${Number(current.page_count) || '—'} pages · ${fileSize(current.byte_size)}`
        : 'No validated edition';

      return `<tr>
        <td class="library-admin-check-cell"><input type="checkbox" aria-label="Select ${escapeHtml(book.title)}" data-library-select value="${book.id}"/></td>
        <td><div class="library-admin-title-cell"><strong>${escapeHtml(book.title)}</strong><span>${escapeHtml(book.author || 'Author not specified')}${book.is_featured ? ' · Featured' : ''}</span></div></td>
        <td>${escapeHtml(book.category?.name || 'Uncategorized')}</td>
        <td><span class="library-admin-pill status-${escapeHtml(book.status)}">${escapeHtml(book.status)}</span></td>
        <td>${escapeHtml(edition)}${book.versions?.length > 1 ? `<span class="library-admin-edition-count">${book.versions.length} editions</span>` : ''}</td>
        <td>${formatDate(book.updated_at)}</td>
        <td><div class="library-admin-row-actions">
          <button type="button" class="library-admin-button secondary" data-library-action="edit" data-book-id="${book.id}">Edit</button>
          <button type="button" class="library-admin-button ${nextStatus === 'archived' ? 'danger' : 'primary'}" data-library-action="status" data-book-id="${book.id}" data-status="${nextStatus}" ${invalidEdition ? 'disabled title="Upload and validate a PDF edition before publishing"' : ''}>${actionLabel}</button>
        </div></td>
      </tr>`;
    }).join('');
    renderSelection();
  }

  function updatePagination(pagination = {}) {
    page = Number(pagination.current_page) || 1;
    lastPage = Number(pagination.last_page) || 1;
    const total = Number(pagination.total) || 0;
    byId('library-admin-range').textContent = total
      ? `Showing ${pagination.from}–${pagination.to} of ${total} titles`
      : 'No titles found';
    byId('library-admin-page-label').textContent = `Page ${page} of ${lastPage}`;
    byId('library-admin-prev').disabled = page <= 1;
    byId('library-admin-next').disabled = page >= lastPage;
  }

  function setTableMessage(message, withRetry = false) {
    booksNode.innerHTML = `<tr><td colspan="7"><div class="library-admin-state">${escapeHtml(message)}${withRetry ? ' <button type="button" class="library-admin-button secondary" id="library-admin-retry">Try again</button>' : ''}</div></td></tr>`;
    byId('library-admin-retry')?.addEventListener('click', () => loadLibrary(true));
  }

  function resetEditor() {
    byId('library-admin-form').reset();
    byId('library-book-id').value = '';
    byId('library-admin-editor-mode').textContent = 'NEW TITLE';
    byId('library-admin-editor-title').textContent = 'Add a book';
    byId('library-admin-save').textContent = 'Add book to drafts';
    byId('library-pdf').required = true;
    byId('library-pdf-help').textContent = 'Required for a new book · PDF up to 64 MiB';
    byId('library-pdf-file-info').textContent = 'No PDF selected';
    byId('library-cover-file-info').textContent = 'No cover selected';
    byId('library-admin-editor-overlay').hidden = true;
    hideUploadProgress();
    renderCategories();
    showMessage('');
  }

  function openEditor(book = null) {
    resetEditor();
    if (book) {
      byId('library-book-id').value = book.id;
      byId('library-title').value = book.title || '';
      byId('library-author').value = book.author || '';
      byId('library-category').value = book.category_id || '';
      byId('library-description').value = book.description || '';
      byId('library-featured').checked = Boolean(book.is_featured);
      byId('library-admin-editor-mode').textContent = `EDITING · ${book.status.toUpperCase()}`;
      byId('library-admin-editor-title').textContent = 'Update book details';
      byId('library-admin-save').textContent = 'Save book changes';
      byId('library-pdf').required = false;
      byId('library-pdf-help').textContent = book.current_file
        ? `Optional · uploads a new edition · current: v${book.current_file.version}`
        : 'Required to publish · PDF up to 64 MiB';
    }
    byId('library-admin-editor-overlay').hidden = false;
    byId('library-title').focus();
  }

  async function loadLibrary(force = false) {
    if (loaded && !force) return;
    const sequence = ++requestSequence;
    setTableMessage('Loading library catalogue…');
    const params = new URLSearchParams({
      page: String(page),
      per_page: byId('library-admin-per-page').value,
      sort: byId('library-admin-sort').value,
    });
    const query = byId('library-admin-search').value.trim();
    const categoryId = byId('library-admin-category-filter').value;
    if (query) params.set('q', query);
    if (categoryId) params.set('category_id', categoryId);
    if (status) params.set('status', status);

    try {
      const result = await request(`/admin/library?${params}`);
      if (sequence !== requestSequence) return;
      const paginator = result.books || {};
      books = Array.isArray(paginator) ? paginator : (paginator.data || []);
      categories = result.categories || [];
      counts = result.counts || counts;
      renderCategories();
      renderStats();
      updatePagination(paginator);
      renderBooks();
      loaded = true;
    } catch (error) {
      if (sequence !== requestSequence) return;
      setTableMessage(error.message, true);
      loaded = false;
    }
  }

  function metadataFromForm() {
    return {
      title: byId('library-title').value.trim(),
      author: byId('library-author').value.trim() || null,
      description: byId('library-description').value.trim() || null,
      category_id: byId('library-category').value || null,
      is_featured: byId('library-featured').checked,
    };
  }

  function toFormData(values) {
    const form = new FormData();
    Object.entries(values).forEach(([key, value]) => {
      const serialized = typeof value === 'boolean' ? (value ? '1' : '0') : (value ?? '');
      form.append(key, serialized);
    });
    return form;
  }

  window.loadAdminLibrary = loadLibrary;

  byId('library-admin-search').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { page = 1; loadLibrary(true); }, 250);
  });
  byId('library-admin-category-filter').addEventListener('change', () => { page = 1; loadLibrary(true); });
  byId('library-admin-sort').addEventListener('change', () => { page = 1; loadLibrary(true); });
  byId('library-admin-per-page').addEventListener('change', () => { page = 1; loadLibrary(true); });
  byId('library-admin-refresh').addEventListener('click', () => loadLibrary(true));
  byId('library-admin-prev').addEventListener('click', () => {
    if (page > 1) { page -= 1; loadLibrary(true); }
  });
  byId('library-admin-next').addEventListener('click', () => {
    if (page < lastPage) { page += 1; loadLibrary(true); }
  });
  document.querySelectorAll('[data-library-status]').forEach(button => {
    button.addEventListener('click', () => {
      status = button.dataset.libraryStatus === 'all' ? '' : button.dataset.libraryStatus;
      page = 1;
      renderStats();
      loadLibrary(true);
    });
  });
  byId('library-admin-new').addEventListener('click', () => openEditor());
  byId('library-admin-cancel').addEventListener('click', resetEditor);
  byId('library-admin-close').addEventListener('click', resetEditor);
  byId('library-admin-editor-overlay').addEventListener('click', event => {
    if (event.target === event.currentTarget) resetEditor();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !byId('library-admin-editor-overlay').hidden) resetEditor();
  });

  byId('library-pdf').addEventListener('change', event => {
    const file = event.target.files[0];
    byId('library-pdf-file-info').textContent = file ? `${file.name} · ${fileSize(file.size)}` : 'No PDF selected';
  });
  byId('library-cover').addEventListener('change', event => {
    const file = event.target.files[0];
    byId('library-cover-file-info').textContent = file ? `${file.name} · ${fileSize(file.size)}` : 'No cover selected';
  });

  booksNode.addEventListener('change', event => {
    const input = event.target.closest('[data-library-select]');
    if (!input) return;
    if (input.checked) selectedIds.add(input.value);
    else selectedIds.delete(input.value);
    renderSelection();
  });
  byId('library-admin-select-page').addEventListener('change', event => {
    const ids = books.map(book => String(book.id));
    const newIds = event.target.checked ? ids.filter(id => !selectedIds.has(id)) : [];
    if (selectedIds.size + newIds.length > 100) {
      event.target.checked = false;
      window.toast?.('Bulk actions are limited to 100 selected books at a time.', true);
      return;
    }
    if (event.target.checked) ids.forEach(id => selectedIds.add(id));
    else ids.forEach(id => selectedIds.delete(id));
    renderSelection();
  });
  byId('library-admin-bulk-action').addEventListener('change', renderSelection);
  byId('library-admin-clear-selection').addEventListener('click', () => {
    selectedIds.clear();
    renderSelection();
  });
  byId('library-admin-bulk-apply').addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
      const result = await request('/admin/library/books/bulk-status', {
        method: 'PUT',
        body: JSON.stringify({ ids: [...selectedIds].map(Number), status: byId('library-admin-bulk-action').value }),
      });
      selectedIds.clear();
      window.toast?.(`${result.updated} books moved to ${result.status}.`);
      await loadLibrary(true);
    } catch (error) {
      window.toast?.(error.message, true);
      renderSelection();
    }
  });

  booksNode.addEventListener('click', async event => {
    const button = event.target.closest('[data-library-action]');
    if (!button) return;
    const book = books.find(item => String(item.id) === button.dataset.bookId);
    if (!book) return;
    if (button.dataset.libraryAction === 'edit') {
      openEditor(book);
      return;
    }
    button.disabled = true;
    try {
      await request(`/admin/library/books/${book.id}/status`, {
        method: 'PUT',
        body: JSON.stringify({ status: button.dataset.status }),
      });
      window.toast?.(`“${book.title}” is now ${button.dataset.status}.`);
      await loadLibrary(true);
    } catch (error) {
      button.disabled = false;
      window.toast?.(error.message, true);
    }
  });

  byId('library-admin-form').addEventListener('submit', async event => {
    event.preventDefault();
    const button = byId('library-admin-save');
    const bookId = byId('library-book-id').value;
    const pdf = byId('library-pdf').files[0];
    const cover = byId('library-cover').files[0];
    const metadata = metadataFromForm();
    button.disabled = true;
    showMessage(bookId ? 'Saving book details…' : 'Uploading and validating the PDF…');

    try {
      if (!bookId && !pdf) throw new Error('Choose a PDF edition to add this book.');
      if (pdf || (!bookId && cover)) {
        const form = toFormData(metadata);
        if (pdf) form.append('pdf', pdf);
        if (cover) form.append('cover', cover);
        const path = bookId ? `/admin/library/books/${bookId}/editions` : '/admin/library/books';
        updateUploadProgress(0, 'Preparing upload…');
        await upload(path, form);
      } else if (cover) {
        const form = toFormData(metadata);
        form.append('cover', cover);
        form.append('_method', 'PUT');
        updateUploadProgress(0, 'Uploading cover…');
        await upload(`/admin/library/books/${bookId}`, form);
      } else {
        await request(`/admin/library/books/${bookId}`, {
          method: 'PUT',
          body: JSON.stringify(metadata),
        });
      }
      selectedIds.clear();
      resetEditor();
      window.toast?.(bookId ? 'Book details and edition saved.' : 'Book added as a draft.');
      await loadLibrary(true);
    } catch (error) {
      showMessage(error.message, true);
    } finally {
      button.disabled = false;
      hideUploadProgress();
    }
  });

  byId('library-admin-category-form').addEventListener('submit', async event => {
    event.preventDefault();
    const input = byId('library-category-name');
    const button = event.currentTarget.querySelector('button');
    button.disabled = true;
    try {
      const result = await request('/admin/library/categories', {
        method: 'POST',
        body: JSON.stringify({ name: input.value.trim() }),
      });
      categories.push(result.category);
      categories.sort((a, b) => a.name.localeCompare(b.name));
      renderCategories();
      input.value = '';
      window.toast?.('Library category created.');
    } catch (error) {
      window.toast?.(error.message, true);
    } finally {
      button.disabled = false;
    }
  });

  byId('library-admin-per-page').value = '25';
  resetEditor();
})();
