(function () {
  'use strict';
  const endpoint = document.querySelector('meta[name="beedata-employees-api"]')?.content || 'https://e2e.beedatatech.com/public/employees';
  const api = new URL(endpoint);
  const number = new Intl.NumberFormat('en-US');
  async function page(params = {}) {
    const url = new URL(api);
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, String(value)));
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(url, { mode: 'cors', credentials: 'omit', signal: controller.signal, headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Directory unavailable');
      const result = await response.json();
      if (result.success !== true || !Array.isArray(result.data) || !Number.isSafeInteger(result.total) || result.total < 0 || !Number.isSafeInteger(result.total_pages) || result.total_pages < 0) throw new Error('Invalid directory response');
      return result;
    } finally { clearTimeout(timeout); }
  }
  async function all() {
    const first = await page({ limit: 100, page: 1 });
    if (first.total_pages > 500) throw new Error('Directory too large');
    const pages = [first.data];
    for (let next = 2; next <= first.total_pages; next += 4) {
      const batch = await Promise.all(Array.from({ length: Math.min(4, first.total_pages - next + 1) }, (_, offset) => page({ limit: 100, page: next + offset })));
      for (const result of batch) {
        if (result.total !== first.total) throw new Error('Directory changed during loading');
        pages.push(result.data);
      }
    }
    const employees = pages.flat();
    if (employees.length !== first.total) throw new Error('Incomplete directory');
    return { employees, asOfDate: first.as_of_date, timezone: first.timezone };
  }
  function photoURL(path) {
    if (typeof path !== 'string') return null;
    try {
      const url = new URL(path, api);
      if (url.origin !== api.origin || !/\/public\/employees\/[1-9][0-9]*\/photo$/.test(url.pathname) || url.search || url.hash || url.username || url.password) return null;
      return url.href;
    } catch { return null; }
  }
  async function initializeCounter() {
    const count = document.getElementById('employeesCount');
    const item = count?.closest('.counter-item');
    if (!item || item.dataset.employeeDirectory === 'true') return;
    item.dataset.employeeDirectory = 'true';
    const link = document.createElement('a');
    link.href = '/employees.html'; link.className = 'employee-counter-link';
    link.setAttribute('aria-label', 'View the BeeData employee directory');
    while (item.firstChild) link.append(item.firstChild);
    const caption = document.createElement('span'); caption.className = 'employee-counter-caption'; caption.textContent = 'Meet our team ↗';
    link.append(caption); item.append(link);
    count.textContent = '—';
    try {
      const result = await page({ limit: 1 });
      count.textContent = number.format(result.total);
      link.setAttribute('aria-label', `View all ${number.format(result.total)} BeeData employees`);
    } catch {
      count.textContent = '—';
      count.setAttribute('aria-label', 'Employee count temporarily unavailable');
    }
  }
  window.BeeDataEmployees = Object.freeze({ page, all, photoURL, initializeCounter, formatNumber: value => number.format(value) });
})();