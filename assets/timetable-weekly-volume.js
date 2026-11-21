(function () {
  'use strict';
  let panel = document.getElementById('ttb-weekly-volume');
  if (!panel) return;
  const storageKey = 'cds:weekly-volume:' + location.pathname + ':' + panel.dataset.workspace;
  let controller, requestId = 0;
  function position() {
    const table = panel.querySelector('[data-volume-table]');
    return {offset: panel.getBoundingClientRect().top, left: table.scrollLeft, top: table.scrollTop};
  }
  function restore(saved, keepRows) {
    const table = panel.querySelector('[data-volume-table]');
    table.scrollLeft = saved.left || 0; table.scrollTop = keepRows ? saved.top || 0 : 0;
    window.scrollBy(0, panel.getBoundingClientRect().top - saved.offset);
  }
  function bind() {
    const filter = panel.querySelector('[data-volume-filter]');
    function applyFilter(event) {
      event.preventDefault();
      const url = new URL(location.pathname, location.origin);
      url.search = new URLSearchParams(new FormData(filter)).toString();
      if (event.target.name === 'scope') url.searchParams.delete('target');
      void load(url, true, event.target.name);
    }
    filter.addEventListener('change', applyFilter); filter.addEventListener('submit', applyFilter);
    panel.querySelectorAll('[data-volume-save]').forEach(form => form.addEventListener('submit', function (event) {
      if (event.defaultPrevented) return;
      try {
        sessionStorage.setItem(storageKey, JSON.stringify({...position(), scope: panel.dataset.scope, target: panel.dataset.target, at: Date.now()}));
      } catch (_) { /* Storage unavailable: the normal save still works. */ }
    }));
  }
  async function load(url, push, focusName) {
    const saved = position(), workspace = panel.dataset.workspace, id = ++requestId;
    if (controller) controller.abort(); controller = new AbortController();
    panel.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url.href, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
      if (!response.ok || new URL(response.url || url.href).origin !== location.origin || new URL(response.url || url.href).pathname !== location.pathname) throw new Error('Filter request failed');
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (id !== requestId) return;
      const next = doc.getElementById('ttb-weekly-volume');
      if (!next || next.dataset.workspace !== workspace) throw new Error('Filter context changed');
      panel.replaceWith(next); panel = next; bind();
      if (push) history.pushState(null, '', url.pathname + url.search);
      restore(saved, false);
      if (focusName) panel.querySelector('[data-volume-filter] [name="' + focusName + '"]')?.focus({preventScroll: true});
    } catch (error) {
      if (id !== requestId || error.name === 'AbortError') return;
      // Progressive fallback keeps the weekly-volume section in view instead of returning to the page top.
      url.hash = 'ttb-weekly-volume'; location.assign(url.href);
    } finally { if (id === requestId) panel.removeAttribute('aria-busy'); }
  }
  bind();
  window.addEventListener('popstate', function () {
    const url = new URL(location.href);
    if (url.pathname === location.pathname && url.searchParams.get('tab') === 'data') void load(url, false);
    else location.reload();
  });
  try {
    const raw = sessionStorage.getItem(storageKey); sessionStorage.removeItem(storageKey);
    const saved = raw ? JSON.parse(raw) : null;
    if (saved && Date.now() - saved.at < 120000 && saved.scope === panel.dataset.scope && saved.target === panel.dataset.target && Number.isFinite(saved.offset)) {
      const apply = () => requestAnimationFrame(() => restore(saved, true));
      if (document.readyState === 'complete') apply(); else window.addEventListener('load', apply, {once: true});
    }
  } catch (_) { /* An invalid/stale record cannot block the page. */ }
})();
