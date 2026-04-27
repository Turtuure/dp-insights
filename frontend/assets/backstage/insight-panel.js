/**
 * Insights backstage list page — list, KPIs (sparklines), row delete (confirm-dialog).
 *
 * Create / edit live on dedicated sub-pages now (see new/index.php and
 * edit/index.php) — this script no longer opens a SlidePanel; the toolbar
 * + Add button is a plain link, and the row pencil icon navigates to the
 * edit sub-page.
 *
 * Endpoints (via daem-society proxy):
 *   GET  /api/backstage/insights.php?op=list
 *   GET  /api/backstage/insights.php?op=stats
 *   POST /api/backstage/insights.php?op=delete&id=...
 */
(function () {
  'use strict';

  var KPI_COLORS = {
    published: '#16a34a',
    scheduled: '#3b82f6',
    featured:  '#8b5cf6',
  };

  // Per-card series labels surfaced in the sparkline tooltip.
  var KPI_NAMES = {
    published: 'Published',
    scheduled: 'Scheduled',
    featured:  'Featured',
  };

  // Featured KPI is bi-temporal: past = published-featured (green),
  // future = scheduled-featured (blue). Matches published/scheduled hues so
  // the timeline reads as "what's been highlighted" + "what's queued".
  var FEATURED_SERIES_COLORS = ['#16a34a', '#3b82f6'];
  var FEATURED_SERIES_NAMES  = ['Featured (published)', 'Featured (scheduled)'];

  var els = {
    tbody:       document.getElementById('insights-tbody'),
    filterInput: document.getElementById('insight-filter-input'),
    seg:         document.getElementById('insight-status-filter'),
    emptyMount:  document.getElementById('insights-empty-mount'),
    errorMount:  document.getElementById('insights-error-mount'),
  };
  if (!els.tbody) return;

  var state = {
    rows:    [],
    filter:  '',
    status:  'all',  // all | published | scheduled
  };

  // ── Bootstrap: parallel load list + stats ────────────────────────────────
  function bootstrap() {
    renderSkeletonRows(5);
    Promise.all([fetchList(), fetchStats()])
      .then(function (results) {
        state.rows = results[0] || [];
        renderTable();
        renderKpis(results[1]);
      })
      .catch(function (err) { renderError(err); });
  }

  function fetchList() {
    return fetch('/api/backstage/insights.php?op=list')
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (j) { return (j && j.data) || []; });
  }
  function fetchStats() {
    return fetch('/api/backstage/insights.php?op=stats')
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (j) { return (j && j.data) || null; });
  }

  // ── KPI rendering ────────────────────────────────────────────────────────
  function renderKpis(stats) {
    if (!stats) return;
    document.querySelectorAll('.kpi-card').forEach(function (el) { el.classList.remove('is-loading'); });

    setKpi('published', stats.published);
    setKpi('scheduled', stats.scheduled);
    setKpi('featured',  stats.featured);

    initSpark('published', stats.published.sparkline);
    initSpark('scheduled', stats.scheduled.sparkline);
    initFeaturedSpark(stats.featured);
  }
  function setKpi(id, payload) {
    var el = document.querySelector('.kpi-card[data-kpi="' + id + '"] .kpi-card__value');
    if (el) el.textContent = String(payload.value);
  }
  function initSpark(id, points) {
    var el = document.getElementById('spark-' + id);
    if (el && window.Sparkline) window.Sparkline.init(el, points || [], KPI_COLORS[id], KPI_NAMES[id]);
  }
  function initFeaturedSpark(featured) {
    var el = document.getElementById('spark-featured');
    if (!el || !window.Sparkline) return;
    window.Sparkline.init(
      el,
      [featured.sparkline || [], featured.sparkline_scheduled || []],
      FEATURED_SERIES_COLORS,
      FEATURED_SERIES_NAMES
    );
  }

  // ── Table rendering ──────────────────────────────────────────────────────
  function renderSkeletonRows(n) {
    var html = '';
    for (var i = 0; i < n; i++) {
      html += '<tr class="data-explorer__skeleton">' +
              '  <td><span class="skeleton--text" style="width:60%"></span></td>' +
              '  <td><span class="skeleton--text" style="width:80%"></span></td>' +
              '  <td><span class="skeleton--text" style="width:40%"></span></td>' +
              '  <td><span class="skeleton--pill"></span></td>' +
              '  <td><span class="skeleton--text" style="width:30%"></span></td>' +
              '  <td></td>' +
              '</tr>';
    }
    els.tbody.innerHTML = html;
    if (els.emptyMount) els.emptyMount.style.display = 'none';
    if (els.errorMount) els.errorMount.style.display = 'none';
  }

  function filteredRows() {
    // ISO local 'Y-m-d H:i:s' so it lexicographically compares against the
    // datetime strings the API now returns (DATETIME column).
    var nowISO = currentDbTimestamp();
    return state.rows.filter(function (r) {
      if (state.filter && (r.title || '').toLowerCase().indexOf(state.filter) === -1) return false;
      var isDraft = !r.published_date;
      if (state.status === 'draft'     && !isDraft) return false;
      if (state.status === 'published' && (isDraft || r.published_date >  nowISO)) return false;
      if (state.status === 'scheduled' && (isDraft || r.published_date <= nowISO)) return false;
      return true;
    });
  }

  function currentDbTimestamp() {
    var d = new Date();
    var p = function (n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) +
           ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }

  function formatPublishedCell(raw) {
    if (!raw) return '<span class="text-muted">—</span>';
    // Drop seconds for display: '2026-04-26 14:30:00' → '2026-04-26 14:30'
    return esc(String(raw).slice(0, 16));
  }

  function renderTable() {
    var rows  = filteredRows();

    if (rows.length === 0) {
      els.tbody.innerHTML = '';
      if (els.emptyMount) els.emptyMount.style.display = '';
      return;
    }
    if (els.emptyMount) els.emptyMount.style.display = 'none';

    var nowISO = currentDbTimestamp();
    els.tbody.innerHTML = rows.map(function (i) {
      var isDraft   = !i.published_date;
      var pillClass = isDraft
        ? 'pill--draft'
        : (i.published_date > nowISO ? 'pill--scheduled' : 'pill--published');
      var pillText  = isDraft
        ? 'Draft'
        : (i.published_date > nowISO ? 'Scheduled' : 'Published');
      var dateCell  = formatPublishedCell(i.published_date);
      var feat      = i.featured ? '<span class="pill pill--featured" style="margin-left:6px;">Featured</span>' : '';
      var editHref  = '/backstage/insights/edit?id=' + encodeURIComponent(i.id);
      return '<tr class="row" data-id="' + esc(i.id) + '">' +
             '  <td><a class="js-row-link" href="' + editHref + '"><strong>' + esc(i.title) + '</strong></a></td>' +
             '  <td>' + esc(i.category_label || i.category) + '</td>' +
             '  <td>' + esc(i.author) + '</td>' +
             '  <td><span class="pill ' + pillClass + '">' + pillText + '</span>' + feat + '</td>' +
             '  <td>' + dateCell + '</td>' +
             '  <td class="data-explorer__actions">' +
             '    <a class="btn btn--icon" href="' + editHref + '" title="Edit" aria-label="Edit"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21a1 1 0 0 0 0-1.42l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.82z" fill="currentColor"/></svg></a>' +
             '    <button class="btn btn--icon js-del" title="Delete" aria-label="Delete"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 7h12M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-7 0v13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7"/></svg></button>' +
             '  </td>' +
             '</tr>';
    }).join('');

    Array.from(els.tbody.querySelectorAll('.js-del')).forEach(function (b) {
      b.addEventListener('click', function () { confirmDelete(rowIdFor(b)); });
    });
  }
  function rowIdFor(btn) { return btn.closest('tr').getAttribute('data-id'); }

  // ── Error rendering ──────────────────────────────────────────────────────
  function renderError(err) {
    els.tbody.innerHTML = '';
    if (els.errorMount) {
      els.errorMount.style.display = '';
      els.errorMount.innerHTML =
        '<div class="error-state" role="alert">' +
        '  <svg class="error-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">' +
        '    <path d="M12 9v4M12 17h.01M3.6 18l8.4-14 8.4 14H3.6z"/></svg>' +
        '  <div>' +
        '    <div class="error-state__title">Could not load insights</div>' +
        '    <div class="error-state__message">' + esc(err && err.message || 'Network error') + '</div>' +
        '  </div>' +
        '  <div class="error-state__actions">' +
        '    <button class="btn btn--primary" id="insights-retry-btn">Retry</button>' +
        '  </div>' +
        '</div>';
      var btn = document.getElementById('insights-retry-btn');
      if (btn) btn.addEventListener('click', bootstrap);
    }
  }

  // ── Delete (confirm) ─────────────────────────────────────────────────────
  function confirmDelete(id) {
    if (!id) return;
    var go = function () {
      fetch('/api/backstage/insights.php?op=delete&id=' + encodeURIComponent(id), { method: 'POST' })
        .then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          bootstrap();
        })
        .catch(function (e) { alert('Delete failed: ' + e.message); });
    };
    if (window.ConfirmDialog && typeof window.ConfirmDialog.open === 'function') {
      window.ConfirmDialog.open({
        title:        'Delete insight?',
        body:         'This action cannot be undone.',
        danger:       true,
        confirmLabel: 'Delete',
      }).then(function (ok) { if (ok) go(); });
    } else if (window.confirm('Delete insight? This action cannot be undone.')) {
      go();
    }
  }

  // ── Toolbar wiring ───────────────────────────────────────────────────────
  if (els.filterInput) {
    els.filterInput.addEventListener('input', function () {
      state.filter = (els.filterInput.value || '').toLowerCase();
      renderTable();
    });
  }
  if (els.seg) {
    els.seg.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-status]');
      if (!btn) return;
      Array.from(els.seg.querySelectorAll('[data-status]')).forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      state.status = btn.getAttribute('data-status');
      renderTable();
    });
  }

  // ── Helpers ──────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Boot ─────────────────────────────────────────────────────────────────
  bootstrap();
})();
