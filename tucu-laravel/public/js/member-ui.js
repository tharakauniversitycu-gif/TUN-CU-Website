/* UI behaviour is independent of authentication and member permissions. */
const navigationIcons = {
  bookshelf: '<path d="M3 4h7v16H3zM14 4h7v16h-7zM3 8h7M14 8h7"/>',
  overview: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  profile: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
  updates: '<path d="M4 5h16v12H8l-4 4V5Z"/><path d="M8 9h8M8 13h5"/>',
  events: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-13 5h2m4 0h2"/>',
  prayers: '<path d="M20 5c-3-3-7-1-8 1-1-2-5-4-8-1-4 4 1 9 8 15 7-6 12-11 8-15Z"/>',
  executive: '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V3h8v4M3 13h18M10 13v3h4v-3"/>',
  associate: '<path d="m2 8 10-5 10 5-10 5L2 8Zm4 2v7c4 3 8 3 12 0v-7m4-2v9"/>',
  newsletter: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>'
};
const navButtons = [...document.querySelectorAll('.ws-tab-btn')];
function openPanel(name, focus = false) {
  const selected = navButtons.find(button => button.dataset.panel === name);
  if (!selected || selected.style.display === 'none') return;
  navButtons.forEach(button => {
    const active = button === selected;
    button.classList.toggle('active', active);
    if (active) button.setAttribute('aria-current', 'page');
    else button.removeAttribute('aria-current');
  });
  document.querySelectorAll('.tab-panel-container').forEach(panel => panel.classList.toggle('active', panel.id === `panel-${name}`));
  if (focus) { const panel = document.getElementById(`panel-${name}`); panel.tabIndex = -1; panel.focus({preventScroll:true}); }
}
navButtons.forEach(button => {
  const icon = button.querySelector('span');
  if (icon) icon.outerHTML = `<svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true">${navigationIcons[button.dataset.panel]}</svg>`;
  button.setAttribute('aria-controls', `panel-${button.dataset.panel}`);
  button.addEventListener('click', () => openPanel(button.dataset.panel));
  button.addEventListener('keydown', event => {
    if (!['ArrowDown','ArrowUp','ArrowRight','ArrowLeft','Home','End'].includes(event.key)) return;
    event.preventDefault();
    const visible = navButtons.filter(b => b.style.display !== 'none');
    let index = visible.indexOf(button);
    index = event.key === 'Home' ? 0 : event.key === 'End' ? visible.length - 1 : (index + (['ArrowDown','ArrowRight'].includes(event.key) ? 1 : -1) + visible.length) % visible.length;
    visible[index].focus();
  });
});
document.querySelectorAll('[data-open-panel]').forEach(button => button.addEventListener('click', () => openPanel(button.dataset.openPanel, true)));
openPanel('overview');
document.getElementById('today-label').textContent = new Date().toLocaleDateString('en-KE',{weekday:'long',day:'numeric',month:'long'});

function renderOverviewUpdates(items) {
  const container = document.getElementById('overview-updates');
  container.replaceChildren();
  if (!items.length) {
    const empty = document.createElement('p'); empty.className = 'overview-empty';
    empty.textContent = 'You’re all caught up. Updates from the CU and your ministries will appear here.';
    container.append(empty); return;
  }
  items.slice(0,3).forEach(item => {
    const button = document.createElement('button'); button.type = 'button'; button.className = 'overview-update';
    const meta = document.createElement('div'); meta.className = 'update-meta';
    const scope = document.createElement('span'); scope.className = 'update-scope';
    scope.textContent = item.ministry?.name || item.docket?.name || (item.target_type === 'associates' ? 'Associates' : 'Union-wide');
    const date = document.createElement('span'); date.textContent = item.published_at ? new Date(item.published_at).toLocaleDateString('en-KE',{month:'short',day:'numeric'}) : '';
    meta.append(scope,date);
    const title = document.createElement('h4'); title.textContent = item.title;
    const content = document.createElement('p'); content.textContent = item.content;
    button.append(meta,title,content); button.addEventListener('click', () => openPanel('updates',true)); container.append(button);
  });
}

document.querySelectorAll('input[type="password"]').forEach(input => {
  const wrapper = document.createElement('div'); wrapper.className = 'password-wrap';
  input.before(wrapper); wrapper.append(input);
  const button = document.createElement('button'); button.type = 'button'; button.className = 'password-toggle'; button.textContent = 'Show';
  button.setAttribute('aria-label', `Show ${document.querySelector(`label[for="${input.id}"]`)?.textContent.toLowerCase() || 'password'}`);
  button.setAttribute('aria-pressed','false');
  button.addEventListener('click', () => { const show = input.type === 'password'; input.type = show ? 'text' : 'password'; button.textContent = show ? 'Hide' : 'Show'; button.setAttribute('aria-pressed',String(show)); });
  wrapper.append(button);
});
document.querySelectorAll('.ministries-picker-panel').forEach(panel => {
  const search = document.createElement('input'); search.type = 'search'; search.className = 'ministry-search'; search.placeholder = 'Find a ministry…'; search.setAttribute('aria-label','Find a ministry');
  panel.before(search);
  search.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
    panel.querySelectorAll('.docket-category-wrap').forEach(group => {
      const docketMatches = group.querySelector('.docket-header-title').textContent.toLowerCase().includes(query);
      group.querySelectorAll('.ministry-option-chip').forEach(option => { option.hidden = !docketMatches && !option.textContent.toLowerCase().includes(query); });
      group.hidden = ![...group.querySelectorAll('.ministry-option-chip')].some(option => !option.hidden);
    });
    panel.querySelector('.ministry-empty')?.remove();
    if (panel.querySelector('.docket-category-wrap') && ![...panel.querySelectorAll('.docket-category-wrap')].some(group => !group.hidden)) {
      const empty = document.createElement('p'); empty.className = 'ministry-empty'; empty.textContent = 'No matching ministries. Try another name.'; panel.append(empty);
    }
  });
});
document.getElementById('retry-portal').addEventListener('click', () => showPortal());
document.getElementById('year-modal-backdrop').setAttribute('role','dialog');
document.getElementById('year-modal-backdrop').setAttribute('aria-modal','true');
document.getElementById('year-modal-backdrop').setAttribute('aria-label','Confirm your year of study');
// Move focus into the confirmation dialog and keep keyboard navigation inside it.
const yearDialog = document.getElementById('year-modal-backdrop');
let previousFocus;
new MutationObserver(() => {
  const open = getComputedStyle(yearDialog).display !== 'none';
  if (open && !yearDialog.contains(document.activeElement)) { previousFocus = document.activeElement; yearDialog.querySelector('button').focus(); }
  if (!open && previousFocus) { previousFocus.focus(); previousFocus = null; }
}).observe(yearDialog,{attributes:true,attributeFilter:['style']});
yearDialog.addEventListener('keydown',event => {
  if (event.key !== 'Tab') return;
  const controls = [...yearDialog.querySelectorAll('button,input,select')].filter(el => el.offsetParent !== null && !el.disabled);
  if (event.shiftKey && document.activeElement === controls[0]) { event.preventDefault(); controls.at(-1).focus(); }
  else if (!event.shiftKey && document.activeElement === controls.at(-1)) { event.preventDefault(); controls[0].focus(); }
});
