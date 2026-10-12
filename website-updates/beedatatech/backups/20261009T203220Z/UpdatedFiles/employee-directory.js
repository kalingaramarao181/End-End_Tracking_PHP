(function () {
  'use strict';
  const api = window.BeeDataEmployees;
  const byId = id => document.getElementById(id);
  const grid = byId('employeeGrid');
  const search = byId('employeeSearch');
  const role = byId('roleFilter');
  const sort = byId('sortOrder');
  const feedback = byId('feedback');
  const buttons = [...document.querySelectorAll('[data-status]')];
  const state = { employees: [], status: 'all', visible: 12, loaded: false, generation: 0 };
  const dates = new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' });
  const number = value => api.formatNumber(value);
  const safeNumber = value => Number.isSafeInteger(value) && value >= 0 ? value : null;
  const node = (tag, className, text) => { const element = document.createElement(tag); if (className) element.className = className; if (text != null) element.textContent = text; return element; };
  function date(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return 'Not recorded';
    const parsed = new Date(`${value}T12:00:00Z`);
    return Number.isNaN(parsed.getTime()) ? 'Not recorded' : dates.format(parsed);
  }
  function tenure(employee) {
    if (!employee.tenure) return 'Not recorded';
    const parts = ['years', 'months', 'days'].flatMap(unit => {
      const value = safeNumber(employee.tenure[unit]);
      return value ? [`${value} ${value === 1 ? unit.slice(0, -1) : unit}`] : [];
    });
    return parts.join(', ') || (safeNumber(employee.tenure.calendar_days) === 0 ? '0 days' : 'Not recorded');
  }
  function status(employee) { return employee.is_working === true ? 'working' : 'not_working'; }
  function card(employee) {
    const article = node('article', 'employee-card');
    const name = typeof employee.name === 'string' && employee.name.trim() ? employee.name : 'Employee';
    const working = status(employee) === 'working';
    const hasLeft = !working && employee.employment_status === 'left';
    const heading = node('div', 'card-heading');
    const portrait = node('div', 'portrait');
    const initials = name.trim().split(/\s+/).slice(0, 2).map(part => [...part][0] || '').join('').toUpperCase();
    portrait.append(node('span', 'initials', initials));
    const hue = [...name].reduce((hash, char) => (hash + char.codePointAt(0)) % 360, 0);
    portrait.style.setProperty('--avatar-hue', hue);
    const url = api.photoURL(employee.profile_image_url);
    if (url) {
      const image = document.createElement('img');
      image.crossOrigin = 'anonymous'; image.loading = 'lazy'; image.decoding = 'async'; image.alt = ''; image.width = 72; image.height = 72;
      image.addEventListener('error', () => image.remove(), { once: true });
      image.src = url; portrait.append(image);
    }
    heading.append(portrait, node('span', `employment-badge ${working ? 'working' : hasLeft ? 'left' : 'unlinked'}`, working ? 'Working' : hasLeft ? 'Left company' : 'Not working'));
    article.append(heading, node('h3', 'employee-name', name), node('p', 'employee-role', employee.role || 'Role not recorded'), node('span', 'employee-id', employee.employee_id || 'ID not assigned'));
    const facts = node('div', 'card-facts');
    const experience = node('div', 'fact'); experience.append(node('span', '', working ? 'Time with BeeData' : 'Service tenure'), node('strong', '', tenure(employee)));
    const attendance = node('div', 'fact'); const days = safeNumber(employee.recorded_workdays); attendance.append(node('span', '', 'Recorded workdays'), node('strong', '', days == null ? 'Not recorded' : number(days)));
    facts.append(experience, attendance); article.append(facts);
    const details = node('details', 'employee-details'); const summary = node('summary', '', 'Employment details');
    details.append(summary); const list = node('dl', 'details-list');
    [['Joined BeeData', date(employee.date_of_joining)], ['Leaving date', working ? 'Currently working' : date(employee.date_of_leaving)], ['Calendar service days', safeNumber(employee.tenure?.calendar_days) == null ? 'Not recorded' : number(employee.tenure.calendar_days)]].forEach(([label, value]) => { list.append(node('dt', '', label), node('dd', '', value)); });
    details.append(list, node('p', 'details-note', 'Workdays count recorded attendance dates, including half days.')); article.append(details);
    return article;
  }
  function setStatus(value) {
    state.status = value;
    buttons.forEach(button => { const active = button.dataset.status === value; button.classList.toggle('active', active); button.setAttribute('aria-pressed', String(active)); });
    state.visible = 12;
    if (state.loaded) render();
  }
  function filtered() {
    const needle = search.value.trim().normalize('NFKC').toLocaleLowerCase('en-US');
    const employees = state.employees.filter(employee => {
      if (state.status !== 'all' && status(employee) !== state.status) return false;
      if (role.value !== 'all' && (employee.role ? `role:${employee.role}` : 'none') !== role.value) return false;
      return !needle || [employee.name, employee.employee_id, employee.role].some(value => typeof value === 'string' && value.normalize('NFKC').toLocaleLowerCase('en-US').includes(needle));
    });
    const names = (a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'en', { sensitivity: 'base' });
    employees.sort((a, b) => {
      if (sort.value === 'name_desc') return names(b, a);
      if (sort.value === 'newest') return String(b.date_of_joining || '').localeCompare(String(a.date_of_joining || '')) || names(a, b);
      if (sort.value === 'longest') return (safeNumber(b.tenure?.calendar_days) ?? -1) - (safeNumber(a.tenure?.calendar_days) ?? -1) || names(a, b);
      return names(a, b);
    });
    return employees;
  }
  function resetFilters() { search.value = ''; role.value = 'all'; sort.value = 'name'; setStatus('all'); }
  function showFeedback(type, title, message, action) {
    feedback.hidden = false; feedback.className = `feedback ${type}`; feedback.replaceChildren();
    if (type === 'loading') feedback.append(node('span', 'spinner'));
    feedback.append(node('h3', '', title), node('p', '', message));
    if (action) { const button = node('button', 'feedback-button', action.label); button.type = 'button'; button.addEventListener('click', action.run); feedback.append(button); }
  }
  function render() {
    const employees = filtered(); const visible = employees.slice(0, state.visible);
    grid.replaceChildren(...visible.map(card));
    byId('resultCount').textContent = `${number(employees.length)} ${employees.length === 1 ? 'employee' : 'employees'}${state.status === 'all' && !search.value.trim() && role.value === 'all' ? '' : ' found'}`;
    byId('pageNote').textContent = employees.length ? `Showing ${number(visible.length)} of ${number(employees.length)} employees` : '';
    byId('loadMoreButton').hidden = visible.length >= employees.length;
    if (!employees.length) {
      if (!state.employees.length) showFeedback('empty', 'Our team directory is ready for its first chapter.', 'Employee profiles will appear here when they are available.');
      else showFeedback('empty', 'No employees match your filters.', 'Try another name, employee ID, role or status.', { label: 'Clear filters', run: resetFilters });
    } else feedback.hidden = true;
  }
  function busy(value) {
    grid.setAttribute('aria-busy', String(value));
    [search, role, sort, byId('refreshButton'), ...buttons].forEach(element => { element.disabled = value || !state.loaded; });
    byId('refreshButton').disabled = value;
  }
  async function load() {
    const generation = ++state.generation;
    state.loaded = false; busy(true); grid.replaceChildren(); byId('loadMoreButton').hidden = true; byId('pageNote').textContent = ''; byId('dataNote').hidden = true;
    ['totalCount', 'workingCount', 'formerCount'].forEach(id => { byId(id).textContent = '—'; });
    byId('resultCount').textContent = 'Loading the team…';
    showFeedback('loading', 'Getting to know the team…', "We're bringing the latest employee information together.");
    try {
      const result = await api.all();
      if (generation !== state.generation) return;
      state.employees = result.employees; state.loaded = true; state.visible = 12;
      const working = state.employees.filter(employee => status(employee) === 'working').length;
      byId('totalCount').textContent = number(state.employees.length); byId('workingCount').textContent = number(working); byId('formerCount').textContent = number(state.employees.length - working);
      const selected = role.value;
      role.replaceChildren(new Option('All roles', 'all'));
      const roles = [...new Set(state.employees.map(employee => employee.role).filter(value => typeof value === 'string' && value))].sort((a, b) => a.localeCompare(b));
      roles.forEach(value => role.add(new Option(value, `role:${value}`)));
      if (state.employees.some(employee => !employee.role)) role.add(new Option('Role not recorded', 'none'));
      role.value = [...role.options].some(option => option.value === selected) ? selected : 'all';
      const asOf = date(result.asOfDate);
      byId('dataNote').textContent = `${asOf !== 'Not recorded' ? `Information as of ${asOf}. ` : ''}Employment status is based on the employee's linked account. Workdays reflect recorded attendance.`;
      byId('dataNote').hidden = false; render();
    } catch {
      if (generation !== state.generation) return;
      state.employees = []; state.loaded = false;
      ['totalCount', 'workingCount', 'formerCount'].forEach(id => { byId(id).textContent = '\u2014'; });
      byId('resultCount').textContent = 'Team directory unavailable';
      showFeedback('unavailable', 'The team directory will be available soon.', "We can't load the latest employee information right now. Please try again shortly.", { label: 'Try again', run: load });
    } finally { if (generation === state.generation) busy(false); }
  }
  buttons.forEach(button => button.addEventListener('click', () => setStatus(button.dataset.status)));
  [search, role, sort].forEach(element => element.addEventListener(element === search ? 'input' : 'change', () => { state.visible = 12; render(); }));
  byId('refreshButton').addEventListener('click', load);
  byId('loadMoreButton').addEventListener('click', () => { state.visible += 12; render(); });
  byId('copyrightYear').textContent = String(new Date().getFullYear());
  load();
})();