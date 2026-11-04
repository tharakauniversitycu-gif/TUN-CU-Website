
  const API = `${window.location.origin}/api`;

  let TOKEN = localStorage.getItem('tucu_member_token');
  let MEMBER_ID = localStorage.getItem('tucu_member_id');
  let memberData = null;
  let allDockets = [];
  let allAnnouncements = [];

  // ── TOAST NOTIFICATIONS ──
  function toast(msg, type = 'ok') {
    const existing = document.querySelector('.toast-capsule');
    if (existing) existing.remove();
    const t = document.createElement('div');
    t.className = 'toast-capsule' + (type === 'error' ? ' err' : '');
    t.innerHTML = `<span>${type === 'error' ? '❌' : '✨'}</span><span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => {
      t.style.opacity = '0';
      t.style.transition = 'opacity 0.3s';
      setTimeout(() => t.remove(), 300);
    }, 4000);
  }

  // ── AUTH TAB SWITCHER ──
  const tabLoginBtn = document.getElementById('tab-login-btn');
  const tabRegisterBtn = document.getElementById('tab-register-btn');
  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');

  tabLoginBtn.addEventListener('click', () => {
    tabLoginBtn.classList.add('active');
    tabRegisterBtn.classList.remove('active');
    loginForm.classList.add('active');
    registerForm.classList.remove('active');
  });

  tabRegisterBtn.addEventListener('click', () => {
    tabRegisterBtn.classList.add('active');
    tabLoginBtn.classList.remove('active');
    registerForm.classList.add('active');
    loginForm.classList.remove('active');
  });

  // Toggle year selection if Associate is chosen at registration
  document.getElementById('reg-category').addEventListener('change', (e) => {
    const isAssoc = e.target.value === 'associate';
    document.getElementById('reg-year-wrap').style.display = isAssoc ? 'none' : 'block';
  });

  // ── LOAD DOCKETS & MINISTRIES ──
  async function loadDockets() {
    try {
      const res = await fetch(`${API}/dockets`);
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error('Ministries unavailable');
      if (data.success) {
        allDockets = data.dockets;
        renderMinistriesPicker('reg-ministries-box', 'reg-selected-count', []);
      }
    } catch (e) {
      document.getElementById('reg-ministries-box').textContent = 'Ministries are unavailable right now. You can choose them from your profile later.';
    }
  }

  function renderMinistriesPicker(containerId, counterId, selectedIds = []) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let html = '';
    allDockets.forEach(docket => {
      html += `
        <div class="docket-category-wrap">
          <div class="docket-header-title">
            <span>${docket.icon || '📂'}</span>
            <span>${docket.name}</span>
          </div>
          <div>
      `;
      (docket.ministries || []).forEach(m => {
        const isChecked = selectedIds.includes(m.id);
        html += `
          <label class="ministry-option-chip">
            <span class="ministry-chip-label">
              <input type="checkbox" value="${m.id}" data-picker="${containerId}" ${isChecked ? 'checked' : ''}/>
              <span>${m.name}</span>
            </span>
            ${m.registration_fee > 0 ? `<span class="ministry-chip-fee">KES ${m.registration_fee}</span>` : ''}
          </label>
        `;
      });
      html += `</div></div>`;
    });

    container.innerHTML = html;
    updatePickerState(containerId, counterId);

    container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', () => updatePickerState(containerId, counterId));
    });
  }

  function updatePickerState(containerId, counterId) {
    const container = document.getElementById(containerId);
    const counter = document.getElementById(counterId);
    if (!container || !counter) return;

    const checked = Array.from(container.querySelectorAll('input[type="checkbox"]:checked'));
    counter.textContent = checked.length;

    const unChecked = Array.from(container.querySelectorAll('input[type="checkbox"]:not(:checked)'));
    if (checked.length >= 3) {
      unChecked.forEach(cb => {
        cb.disabled = true;
        cb.closest('.ministry-option-chip').style.opacity = '0.5';
      });
    } else {
      unChecked.forEach(cb => {
        cb.disabled = false;
        cb.closest('.ministry-option-chip').style.opacity = '1';
      });
    }
  }

  function getSelectedMinistryIds(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return [];
    return Array.from(container.querySelectorAll('input[type="checkbox"]:checked')).map(cb => parseInt(cb.value));
  }

  // ── AUTH: LOGIN ──
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;
    const btn = document.getElementById('login-submit-btn');
    const errEl = document.getElementById('login-error');
    errEl.style.display = 'none';

    btn.disabled = true;
    btn.innerHTML = '<span>Verifying credentials...</span>';

    try {
      const res = await fetch(`${API}/members/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      const data = await res.json().catch(() => null);
      if (res.ok && data) {
        TOKEN = data.token;
        MEMBER_ID = data.member.id;
        localStorage.setItem('tucu_member_token', TOKEN);
        localStorage.setItem('tucu_member_id', MEMBER_ID);
        showPortal();
      } else {
        errEl.textContent = data?.errors ? Object.values(data.errors)[0][0] : (data?.message || 'Invalid email or password.');
        errEl.style.display = 'block';
      }
    } catch (err) {
      errEl.textContent = 'Cannot reach server. Please check your connection.';
      errEl.style.display = 'block';
    }
    btn.disabled = false;
    btn.innerHTML = '<span>Sign in</span><span>→</span>';
  });

  // ── AUTH: REGISTER ──
  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errEl = document.getElementById('register-error');
    const okEl = document.getElementById('register-success');
    errEl.style.display = 'none';
    okEl.style.display = 'none';

    const password = document.getElementById('reg-password').value;
    const passwordConfirm = document.getElementById('reg-password-confirm').value;

    if (password !== passwordConfirm) {
      errEl.textContent = 'Passwords do not match.';
      errEl.style.display = 'block';
      return;
    }

    const ministry_ids = getSelectedMinistryIds('reg-ministries-box');
    const category = document.getElementById('reg-category').value;
    const year_of_study = category === 'associate' ? 0 : parseInt(document.getElementById('reg-year').value);

    const payload = {
      full_name: document.getElementById('reg-name').value.trim(),
      email: document.getElementById('reg-email').value.trim(),
      phone: document.getElementById('reg-phone').value.trim(),
      reg_number: document.getElementById('reg-regno').value.trim(),
      membership_category: category,
      course: document.getElementById('reg-course').value.trim(),
      course_duration: parseInt(document.getElementById('reg-duration').value),
      year_of_study,
      ministry_ids,
      password,
      password_confirmation: passwordConfirm
    };

    const btn = document.getElementById('register-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<span>Creating your credential...</span>';

    try {
      const res = await fetch(`${API}/members/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (res.ok) {
        TOKEN = data.token;
        MEMBER_ID = data.member.id;
        localStorage.setItem('tucu_member_token', TOKEN);
        localStorage.setItem('tucu_member_id', MEMBER_ID);
        okEl.textContent = 'Welcome to TUCU! Opening your credential suite...';
        okEl.style.display = 'block';
        setTimeout(showPortal, 900);
      } else {
        errEl.textContent = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Registration failed.');
        errEl.style.display = 'block';
      }
    } catch {
      errEl.textContent = 'Cannot reach server. Please check your connection.';
      errEl.style.display = 'block';
    }
    btn.disabled = false;
    btn.innerHTML = '<span>Complete Registration</span><span>✓</span>';
  });

  // ── AUTH: LOGOUT ──
  document.getElementById('logout-btn').addEventListener('click', async () => {
    try {
      await fetch(`${API}/members/logout`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${TOKEN}` }
      });
    } catch {}
    CuMember.clear();
    TOKEN = null; MEMBER_ID = null;
    document.getElementById('portal').style.display = 'none';
    document.getElementById('auth-screen').style.display = 'flex';
  });

  // ── API GET WRAPPER ──
  async function apiGet(path) {
    try { return await CuMember.request(path); }
    catch (error) {
      if (error.status === 401) {
        TOKEN = null; MEMBER_ID = null;
        document.getElementById('portal').style.display = 'none';
        document.getElementById('auth-screen').style.display = 'flex';
        throw new Error('Unauthorized');
      }
      throw error;
    }
  }

  // ── SHOW PORTAL ──
  async function showPortal() {
    try { if (await CuMember.resumeIntent()) return; }
    catch (error) { toast(error.status === 403 ? 'Library access is unavailable for this account. Contact CU leadership.' : 'The selected book could not be opened. You can retry from the library.', 'error'); CuMember.cancelIntent(); }
    document.getElementById('auth-screen').style.display = 'none';
    document.getElementById('portal').style.display = 'block';
    if (!allDockets.length) await loadDockets();
    await loadProfile();
    await loadAnnouncements();
    await loadEvents();
    await loadPrayers();
    if (CuMember.token()) window.dispatchEvent(new Event('cu:portal-ready'));
  }

  // ── LOAD PROFILE ──
  async function loadProfile() {
    try {
      const data = await apiGet(`/members/${MEMBER_ID}/profile`);
      if (!data.member) throw new Error('Profile unavailable');
      document.getElementById('portal-load-error').hidden = true;
      memberData = data.member;
      const m = memberData;

      // Spiritual Year & Academic Year Masthead
      if (data.calendars) {
        document.getElementById('s-year').textContent = data.calendars.spiritual_year || '2025/2026';
        document.getElementById('s-theme').textContent = data.calendars.spiritual_theme || 'Come now, let us reason together';
        if (data.calendars.academic_year) {
          document.getElementById('seal-academic-year').textContent = `Academic Cycle ${data.calendars.academic_year}`;
        }
      }

      // Greetings & Identity
      document.getElementById('welcome-name').textContent = m.full_name.split(' ')[0];
      document.getElementById('avatar-initials').textContent = m.full_name.split(' ').map(n => n[0]).slice(0,2).join('').toUpperCase();
      document.getElementById('profile-fullname').textContent = m.full_name;
      document.getElementById('profile-emailcourse').textContent = `${m.email}${m.course ? ' · ' + m.course : ''}`;
      document.getElementById('profile-regno-display').textContent = m.reg_number || 'REG: N/A';

      // Status Badges
      const badgeYear = document.getElementById('badge-year');
      if (m.is_associate) {
        badgeYear.textContent = '🎓 Associate';
        badgeYear.className = 'chip-badge gold';
        document.getElementById('portal-hero-sub').textContent = 'Always part of the family. Stay connected, wherever life takes you.';
      } else {
        badgeYear.textContent = `Year ${m.year_of_study} of ${m.course_duration || 4}`;
        badgeYear.className = 'chip-badge gold';
      }

      document.getElementById('badge-category').textContent = (m.membership_category || 'full').toUpperCase() + ' MEMBER';
      document.getElementById('badge-status').textContent = m.status === 'active' ? '● Active' : (m.status === 'associate' ? '● Associate' : '○ ' + m.status);

      const badgeRole = document.getElementById('badge-role');
      if (m.is_executive) {
        badgeRole.style.display = 'inline-flex';
        badgeRole.textContent = `👑 ${m.ec_position || 'Executive Leader'}`;
        badgeRole.className = 'chip-badge gold';
      } else {
        badgeRole.style.display = 'none';
      }

      // Render Affiliated Ministries Strip in Passport
      const strip = document.getElementById('member-ministries-strip');
      if (m.ministries && m.ministries.length) {
        strip.innerHTML = '<span class="passport-deck-label">Active Ministries:</span>' +
          m.ministries.map(min => {
            const docketName = min.docket ? min.docket.name : '';
            return `<span class="passport-tag-pill"><span>${min.docket?.icon || '📂'}</span><strong>${min.name}</strong> <small>(${docketName})</small></span>`;
          }).join('');
      } else {
        strip.innerHTML = '<span class="passport-deck-label">Active Ministries:</span><span style="font-size:0.85rem; color:#CBD5E1;">No ministry chosen yet. Explore ministries from My profile.</span>';
      }

      // Quick Metrics
      document.getElementById('stat-ministries').textContent = m.ministries ? m.ministries.length : 0;
      document.getElementById('stat-events').textContent = data.summary.events_count;
      document.getElementById('stat-prayers').textContent = data.summary.prayers_count;

      const sub = data.newsletter.is_subscribed;
      document.getElementById('stat-newsletter').textContent = sub ? 'Active' : 'Paused';
      document.getElementById('newsletter-toggle').checked = sub;
      document.getElementById('newsletter-since').textContent = data.newsletter.subscribed_at
        ? new Date(data.newsletter.subscribed_at).toLocaleDateString('en-KE', { year:'numeric', month:'long', day:'numeric' })
        : 'Not Subscribed';

      // Profile Form inputs
      document.getElementById('p-fullname').value = m.full_name || '';
      document.getElementById('p-email').value = m.email || '';
      document.getElementById('p-phone').value = m.phone || '';
      document.getElementById('p-regno').value = m.reg_number || '—';
      document.getElementById('p-course').value = m.course || '';
      document.getElementById('p-duration').value = m.course_duration || 4;
      document.getElementById('p-year-display').value = m.is_associate ? 'Associate Member (Alumni)' : `Year ${m.year_of_study}`;
      document.getElementById('p-joined').value = m.joined_at ? new Date(m.joined_at).toLocaleDateString('en-KE', { year:'numeric', month:'long', day:'numeric' }) : '—';

      if (m.admin_notes) {
        document.getElementById('admin-notes-wrap').style.display = 'block';
        document.getElementById('p-notes').value = m.admin_notes;
      }

      // Render profile ministries picker
      const currentMinistryIds = (m.ministries || []).map(min => min.id);
      renderMinistriesPicker('p-ministries-box', 'p-selected-count', currentMinistryIds);

      // Render Progression History List
      renderProgressionHistory(m.year_history || []);

      // Conditional Tabs setup
      // A. Executive Desk Tab
      const ecTab = document.getElementById('tab-executive');
      if (m.is_executive) {
        ecTab.style.display = 'flex';
        document.getElementById('ec-desk-title').textContent = `👑 Leadership desk: ${m.ec_position || 'Executive Leader'}`;
        setupExecutiveDesk(m);
      } else {
        ecTab.style.display = 'none';
      }

      // B. Associates Hub Tab
      const assocTab = document.getElementById('tab-associate');
      if (m.is_associate) {
        assocTab.style.display = 'flex';
        document.getElementById('filter-associates-btn').style.display = 'inline-block';
        loadAssociateHub();
      } else {
        assocTab.style.display = 'none';
      }

      // Check Academic Year Confirmation Modal
      if (m.is_year_confirmed === false) {
        showYearConfirmationModal(m, data.calendars?.academic_year);
      }

    } catch (e) {
      if (e.message !== 'Unauthorized') document.getElementById('portal-load-error').hidden = false;
    }
  }

  function renderProgressionHistory(history) {
    const list = document.getElementById('progression-history-list');
    if (!history.length) {
      list.innerHTML = '<p style="color:var(--text-muted); font-size:0.85rem;">No academic progression entries on record yet.</p>';
      return;
    }
    list.innerHTML = history.map(h => `
      <div style="padding:10px 0; border-bottom:1px solid var(--surface-border); display:flex; justify-content:space-between; align-items:center;">
        <div>
          <strong style="color:var(--navy-900);">${h.year_of_study === 0 ? '🎓 Associate Status' : 'Year ' + h.year_of_study}</strong>
          <span style="color:var(--text-muted); font-size:0.78rem; margin-left:8px;">(${h.academic_year ? h.academic_year.label : 'Academic Cycle'})</span>
          <div style="color:#475569; font-size:0.8rem; margin-top:2px;">${h.notes || h.action}</div>
        </div>
        <span style="color:var(--text-muted); font-size:0.78rem;">${new Date(h.confirmed_at).toLocaleDateString('en-KE')}</span>
      </div>
    `).join('');
  }

  // ── YEAR CONFIRMATION MODAL ──
  function showYearConfirmationModal(m, academicYearLabel) {
    const modal = document.getElementById('year-modal-backdrop');
    const msg = document.getElementById('year-modal-msg');
    const quickBtn = document.getElementById('btn-quick-confirm-year');

    const yearText = m.year_of_study === 0 ? 'Associate of the Union (Graduated)' : `Year ${m.year_of_study}`;
    msg.innerHTML = `
      Welcome to academic cycle <strong>${academicYearLabel || ''}</strong>!<br/>
      The union records indicate you are progressing to <strong>${yearText}</strong>.<br/>
      Please confirm that this is accurate or submit an adjustment below.
    `;
    quickBtn.textContent = `Confirm as ${yearText}`;

    quickBtn.onclick = async () => {
      quickBtn.disabled = true; quickBtn.textContent = 'Verifying status...';
      try {
        const res = await fetch(`${API}/members/${MEMBER_ID}/confirm-year`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
          body: JSON.stringify({ action: 'confirm', year_of_study: m.year_of_study })
        });
        const data = await res.json();
        if (res.ok) {
          toast(data.message);
          modal.style.display = 'none';
          loadProfile();
        } else {
          toast(data.message || 'Verification failed.', 'error');
        }
      } catch {
        toast('Server communication error.', 'error');
      }
      quickBtn.disabled = false;
    };

    modal.style.display = 'flex';
  }

  document.getElementById('btn-toggle-year-options').addEventListener('click', () => {
    const form = document.getElementById('year-custom-form');
    form.style.display = form.style.display === 'none' || !form.style.display ? 'block' : 'none';
  });

  document.getElementById('year-custom-submit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const selectVal = document.getElementById('modal-select-year').value;
    const notes = document.getElementById('modal-year-notes').value.trim();

    const isGrad = selectVal === 'graduate';
    const payload = {
      action: isGrad ? 'graduate' : 'update',
      year_of_study: isGrad ? 0 : parseInt(selectVal),
      notes: notes || (isGrad ? 'Completed studies' : 'Updated year by member')
    };

    try {
      const res = await fetch(`${API}/members/${MEMBER_ID}/confirm-year`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (res.ok) {
        toast(data.message);
        document.getElementById('year-modal-backdrop').style.display = 'none';
        loadProfile();
      } else {
        toast(data.message || 'Could not update year.', 'error');
      }
    } catch {
      toast('Server communication error.', 'error');
    }
  });

  // ── PROFILE UPDATE ──
  document.getElementById('profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const ministry_ids = getSelectedMinistryIds('p-ministries-box');
    const payload = {
      full_name: document.getElementById('p-fullname').value.trim(),
      phone: document.getElementById('p-phone').value.trim(),
      course: document.getElementById('p-course').value.trim(),
      course_duration: parseInt(document.getElementById('p-duration').value),
      ministry_ids,
    };
    try {
      const res = await fetch(`${API}/members/${MEMBER_ID}/profile`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (res.ok) {
        toast('Profile & Ministries updated successfully!');
        loadProfile();
        loadAnnouncements();
      } else {
        toast(data.message || 'Update failed.', 'error');
      }
    } catch { toast('Server connection error.', 'error'); }
  });

  // ── PASSWORD UPDATE ──
  document.getElementById('password-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const newpass = document.getElementById('p-newpass').value;
    const confirm = document.getElementById('p-newpass-confirm').value;
    if (!newpass) return toast('Please enter a new password.', 'error');
    if (newpass !== confirm) return toast('Passwords do not match.', 'error');
    if (newpass.length < 6) return toast('Password must be at least 6 characters.', 'error');

    try {
      const res = await fetch(`${API}/members/${MEMBER_ID}/profile`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
        body: JSON.stringify({ password: newpass, password_confirmation: confirm })
      });
      const data = await res.json();
      if (res.ok) {
        toast('Password updated successfully!');
        document.getElementById('p-newpass').value = '';
        document.getElementById('p-newpass-confirm').value = '';
      } else toast(data.message || 'Update failed.', 'error');
    } catch { toast('Server communication error.', 'error'); }
  });

  // ── ANNOUNCEMENTS / MINISTRY BULLETINS ──
  async function loadAnnouncements() {
    try {
      const data = await apiGet(`/members/${MEMBER_ID}/announcements`);
      allAnnouncements = data.announcements || [];
      document.getElementById('announcements-total').textContent = `${allAnnouncements.length} bulletins on file`;

      const badge = document.getElementById('updates-count');
      if (allAnnouncements.length > 0) {
        badge.textContent = allAnnouncements.length;
        badge.style.display = 'inline-block';
      } else {
        badge.style.display = 'none';
      }

      renderAnnouncements(allAnnouncements);
      renderOverviewUpdates(allAnnouncements);
    } catch (e) {
      if (e.message !== 'Unauthorized') { toast('Could not load announcements.', 'error'); document.getElementById('overview-updates').textContent = 'Updates are unavailable right now. Please try again shortly.'; }
    }
  }

  function renderAnnouncements(items) {
    const list = document.getElementById('announcements-list');
    if (!items.length) {
      list.innerHTML = `
        <div class="empty-state-view">
          <div class="empty-state-icon">📢</div>
          <p>No announcements found for your selected filter.<br/>Check back shortly for upcoming ministry briefings.</p>
        </div>
      `;
      return;
    }

    list.innerHTML = items.map(a => {
      let tagClass = 'union';
      let scopeLabel = '🌐 Union-Wide';

      if (a.target_type === 'docket') {
        tagClass = 'docket';
        scopeLabel = `📂 ${a.docket?.name || 'Docket'}`;
      } else if (a.target_type === 'ministry') {
        tagClass = 'ministry';
        scopeLabel = `👥 ${a.ministry?.name || 'Ministry'}`;
      } else if (a.target_type === 'associates') {
        tagClass = 'associates';
        scopeLabel = '🎓 Associates';
      }

      return `
        <div class="announcement-item-card ${a.is_pinned ? 'pinned-style' : ''}">
          <div class="announcement-header-meta">
            <span class="announcement-tag ${tagClass}">${scopeLabel}</span>
            ${a.is_pinned ? '<span class="announcement-tag pinned">📌 Pinned Bulletin</span>' : ''}
            <span style="font-size:0.76rem; color:var(--text-muted); margin-left:auto;">
              ${new Date(a.published_at).toLocaleDateString('en-KE', { day:'2-digit', month:'short', year:'numeric' })}
            </span>
          </div>
          <div class="announcement-title-text">${a.title}</div>
          <div class="announcement-body-copy">${a.content.replace(/\n/g, '<br/>')}</div>
          <div class="announcement-author-meta">
            <span>Official Post by: <strong>${a.author_name || 'Leadership'}</strong></span>
            ${a.author_role_label ? `<span>(${a.author_role_label})</span>` : ''}
          </div>
        </div>
      `;
    }).join('');
  }

  // Filter announcements
  document.querySelectorAll('#announcement-filters .filter-chip-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#announcement-filters .filter-chip-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.filter;
      if (filter === 'all') renderAnnouncements(allAnnouncements);
      else if (filter === 'union') renderAnnouncements(allAnnouncements.filter(a => a.target_type === 'all'));
      else if (filter === 'docket') renderAnnouncements(allAnnouncements.filter(a => a.target_type === 'docket'));
      else if (filter === 'ministry') renderAnnouncements(allAnnouncements.filter(a => a.target_type === 'ministry'));
      else if (filter === 'associates') renderAnnouncements(allAnnouncements.filter(a => a.target_type === 'associates'));
    });
  });

  // ── EXECUTIVE COMMITTEE CABINET DESK ──
  function setupExecutiveDesk(member) {
    const ministrySelect = document.getElementById('ec-target-ministry');
    const targetTypeSelect = document.getElementById('ec-target-type');
    const ministryField = document.getElementById('ec-ministry-field');

    // Populate ministries for posting
    let options = '';
    (member.ministries || []).forEach(m => {
      options += `<option value="${m.id}">${m.name}</option>`;
    });
    ministrySelect.innerHTML = options;

    targetTypeSelect.onchange = () => {
      ministryField.style.display = targetTypeSelect.value === 'ministry' ? 'block' : 'none';
    };

    document.getElementById('ec-announcement-form').onsubmit = async (e) => {
      e.preventDefault();
      const targetType = targetTypeSelect.value;
      const payload = {
        title: document.getElementById('ec-title').value.trim(),
        content: document.getElementById('ec-content').value.trim(),
        target_type: targetType,
        is_pinned: document.getElementById('ec-pinned').checked,
        docket_id: targetType === 'docket' ? (member.dockets && member.dockets[0] ? member.dockets[0].id : null) : null,
        ministry_id: targetType === 'ministry' ? parseInt(ministrySelect.value) : null,
      };

      const btn = document.getElementById('ec-post-btn');
      btn.innerHTML = '<span>Broadcasting...</span>';
      btn.disabled = true;

      try {
        const res = await fetch(`${API}/members/${MEMBER_ID}/announcements`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok) {
          toast('Announcement published to feeds successfully!');
          document.getElementById('ec-title').value = '';
          document.getElementById('ec-content').value = '';
          loadAnnouncements();
        } else {
          toast(data.message || 'Could not publish bulletin.', 'error');
        }
      } catch {
        toast('Server communication error.', 'error');
      }
      btn.innerHTML = '<span>Publish announcement</span><span>🚀</span>';
      btn.disabled = false;
    };
  }

  // ── ASSOCIATES FELLOWSHIP HUB ──
  async function loadAssociateHub() {
    try {
      const data = await apiGet(`/members/${MEMBER_ID}/associate-hub`);
      const timeline = document.getElementById('associate-timeline');

      let html = '';
      if (data.journey && data.journey.length) {
        data.journey.forEach(j => {
          html += `
            <div class="timeline-milestone-node">
              <div class="timeline-milestone-title">${j.year_of_study === 0 ? '🎓 Associate of TUCU' : 'Year ' + j.year_of_study}</div>
              <div class="timeline-milestone-sub">${j.academic_year ? j.academic_year.label : ''} · ${new Date(j.confirmed_at).toLocaleDateString('en-KE', { month:'short', year:'numeric' })}</div>
              <div class="timeline-milestone-note">${j.notes || j.action}</div>
            </div>
          `;
        });
      } else {
        html = '<p style="color:var(--text-muted); font-size:0.85rem;">Journey milestone history is being compiled.</p>';
      }

      timeline.innerHTML = html;
    } catch (e) {
      console.warn('Could not load associate hub:', e);
    }
  }

  // ── EVENTS REGISTER ──
  async function loadEvents() {
    try {
      const data = await apiGet(`/members/${MEMBER_ID}/events`);
      const list = document.getElementById('events-list');
      document.getElementById('events-total').textContent = `${data.count} registered`;
      if (!data.events.length) {
        list.innerHTML = `
          <div class="empty-state-view">
            <div class="empty-state-icon">📅</div>
            <p>You have not registered for any upcoming union conferences or fellowships.<br/><a href="/#events">Explore Upcoming Events & Retreats →</a></p>
          </div>
        `;
        return;
      }
      list.innerHTML = data.events.map(ev => `
        <div class="item-list-row">
          <div class="item-row-left">
            <h4>${ev.event_name}</h4>
            <p>Registered on ${new Date(ev.registered_at).toLocaleDateString('en-KE', { day:'2-digit', month:'short', year:'numeric' })}</p>
          </div>
          <span class="status-capsule ${ev.status}">${ev.status}</span>
        </div>
      `).join('');
    } catch (e) {
      if (e.message !== 'Unauthorized') toast('Could not load events.', 'error');
    }
  }

  // ── INTERCESSION & PRAYER ──
  async function loadPrayers() {
    try {
      const data = await apiGet(`/members/${MEMBER_ID}/prayers`);
      const list = document.getElementById('prayers-list');
      document.getElementById('prayers-total').textContent = `${data.count} petitions`;
      if (!data.prayers.length) {
        list.innerHTML = `
          <div class="empty-state-view">
            <div class="empty-state-icon">🙏</div>
            <p>You have not submitted any prayer petitions yet.<br/><a href="/#prayer">Submit a confidential prayer request →</a></p>
          </div>
        `;
        return;
      }
      list.innerHTML = data.prayers.map(p => `
        <div class="item-list-row">
          <div class="item-row-left">
            <h4>${p.category}</h4>
            <p>Submitted ${new Date(p.submitted_at).toLocaleDateString('en-KE', { day:'2-digit', month:'short', year:'numeric' })}${p.is_anonymous ? ' · Anonymous' : ''}</p>
            <div class="prayer-message-bubble">"${p.request}"</div>
          </div>
          <span class="status-capsule ${p.status}">${p.status}</span>
        </div>
      `).join('');
    } catch (e) {
      if (e.message !== 'Unauthorized') toast('Could not load prayers.', 'error');
    }
  }

  // ── NEWSLETTER TOGGLE ──
  document.getElementById('newsletter-toggle').addEventListener('change', async (e) => {
    const is_subscribed = e.target.checked;
    try {
      const res = await fetch(`${API}/members/${MEMBER_ID}/newsletter`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
        body: JSON.stringify({ is_subscribed })
      });
      const data = await res.json();
      if (res.ok) {
        toast(data.message);
        document.getElementById('stat-newsletter').textContent = is_subscribed ? 'Active' : 'Paused';
        loadProfile();
      } else {
        toast('Could not update subscription.', 'error');
        e.target.checked = !is_subscribed;
      }
    } catch {
      toast('Server communication error.', 'error');
      e.target.checked = !is_subscribed;
    }
  });

  window.addEventListener('cu:auth-changed', () => {
    TOKEN = CuMember.token(); MEMBER_ID = localStorage.getItem('tucu_member_id'); memberData = null;
    document.getElementById('portal').style.display = 'none';
    document.getElementById('auth-screen').style.display = 'flex';
    if (TOKEN && MEMBER_ID) location.reload();
  });
  // ── APP BOOTSTRAP ──
  loadDockets();
  if (TOKEN && MEMBER_ID) {
    showPortal();
  }
