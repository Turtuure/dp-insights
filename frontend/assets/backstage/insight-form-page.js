/**
 * Insight create/edit sub-page handlers.
 *
 * After the i18n refactor:
 *   - Translations (title, excerpt, content) are owned by the locale-cards
 *     component (left column) — each card POSTs to
 *       /api/v1/backstage/insights/{id}/translations/{locale}
 *     via locale-cards.js. We just mount it.
 *   - Chrome (slug, category, category_label, author, published_date,
 *     featured) lives on the right column. The Save button POSTs chrome
 *     to /api/backstage/insights.php?op=update; on create it sends both
 *     chrome AND a fi_FI seed (so the new row has its first translation).
 *   - Publish: same as Save but auto-fills published_date with NOW if empty.
 *   - Delete: GitHub-style "type the slug to confirm" modal.
 *
 * Reads mode + id from the parent .insight-form-panel data attributes:
 *   data-mode        : 'create' | 'edit'
 *   data-insight-id  : present only when mode === 'edit'
 *
 * Pre-fill state for translations + coverage is bridged via window.DAEMS_INSIGHT_FORM
 * (set by _form.php from the server-side fetch).
 */
(function () {
    'use strict';

    var panel = document.querySelector('.insight-form-panel');
    if (!panel) return;

    var mode      = panel.getAttribute('data-mode') || 'create';
    var insightId = panel.getAttribute('data-insight-id') || '';

    var bridge = window.DAEMS_INSIGHT_FORM || { id: insightId, translations: {}, coverage: {} };

    var slugEl        = document.getElementById('if-slug');
    var categoryEl    = document.getElementById('if-category');
    var categoryLabel = document.getElementById('if-category-label');
    var authorEl      = document.getElementById('if-author');
    var dateBtnEl     = document.getElementById('if-publish-date-btn');
    var dateDisplayEl = document.getElementById('if-publish-date-display');
    var timeBtnEl     = document.getElementById('if-publish-time-btn');
    var timeDisplayEl = document.getElementById('if-publish-time-display');
    var hiddenDtEl    = document.getElementById('if-published-date');
    var featuredEl    = document.getElementById('if-featured');
    var saveBtn       = document.getElementById('if-save');
    var publishBtn    = document.getElementById('if-publish');
    var deleteBtn     = document.getElementById('if-delete');
    var errorEl       = document.getElementById('if-error-mount');

    // Slug as it was when the page loaded — what the delete modal verifies.
    var originalSlug = slugEl ? slugEl.value.trim() : '';

    var labels = {
        save:    saveBtn    ? saveBtn.textContent.trim()    : '',
        publish: publishBtn ? publishBtn.textContent.trim() : '',
    };

    // ── Locale-cards mount ──────────────────────────────────────────────
    function mountLocaleCards() {
        var container = panel.querySelector('.locale-cards-container');
        if (!container || !window.LocaleCards) return;
        window.LocaleCards.mount(container, {
            kind:         'insight',
            entityId:     bridge.id || '',
            translations: bridge.translations || {},
            coverage:     bridge.coverage || {
                fi_FI: { filled: 0, total: 3 },
                en_GB: { filled: 0, total: 3 },
                sw_TZ: { filled: 0, total: 3 }
            }
        });
    }
    if (window.LocaleCards) {
        mountLocaleCards();
    } else {
        // locale-cards.js is loaded with `defer`; if it hasn't parsed yet,
        // defer until DOMContentLoaded / load.
        document.addEventListener('DOMContentLoaded', mountLocaleCards);
        window.addEventListener('load', mountLocaleCards);
    }

    // ── Read fi_FI draft for create-mode seed ───────────────────────────
    function readFiFIDraft() {
        var container = panel.querySelector('.locale-cards-container');
        if (!container) return { title: '', excerpt: '', content: '' };
        // The locale-cards component renders only the ACTIVE locale's fields
        // in the DOM. On a fresh create page the active locale defaults to
        // fi_FI, so reading the inputs gives us the seed we need.
        var draft = { title: '', excerpt: '', content: '' };
        container.querySelectorAll('.locale-cards-fields input, .locale-cards-fields textarea').forEach(function (i) {
            draft[i.name] = i.value;
        });
        return draft;
    }

    // ── Slug auto-generation from fi_FI title ──────────────────────────
    var slugManuallyEdited = mode === 'edit' && originalSlug !== '';
    if (slugEl) {
        slugEl.addEventListener('input', function () {
            slugManuallyEdited = true;
        });
    }
    // Watch the locale-cards title input (fi_FI only) and keep slug in sync.
    document.addEventListener('input', function (e) {
        if (slugManuallyEdited) return;
        if (!(e.target instanceof HTMLInputElement)) return;
        if (e.target.id !== 'lc-insight-title') return;
        var container = panel.querySelector('.locale-cards-container');
        if (!container) return;
        var state = container._localeCardsState;
        if (!state || state.activeLocale !== 'fi_FI') return;
        if (slugEl) slugEl.value = slugify(e.target.value);
    });

    function slugify(s) {
        if (!s) return '';
        var pre = String(s)
            .replace(/ä/g, 'a').replace(/Ä/g, 'A')
            .replace(/ö/g, 'o').replace(/Ö/g, 'O')
            .replace(/å/g, 'a').replace(/Å/g, 'A')
            .replace(/ø/g, 'o').replace(/Ø/g, 'O')
            .replace(/æ/g, 'ae').replace(/Æ/g, 'AE')
            .replace(/ß/g, 'ss');
        var folded = pre.normalize ? pre.normalize('NFD').replace(/[̀-ͯ]/g, '') : pre;
        return folded
            .toLowerCase()
            .replace(/['"]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    // ── Featured toggle helper-label swap ───────────────────────────────
    if (featuredEl) {
        var toggleLabel = panel.querySelector('.toggle-switch__label');
        if (toggleLabel) {
            var onText  = toggleLabel.getAttribute('data-on')  || toggleLabel.textContent;
            var offText = toggleLabel.getAttribute('data-off') || toggleLabel.textContent;
            var syncLabel = function () {
                toggleLabel.textContent = featuredEl.checked ? onText : offText;
            };
            featuredEl.addEventListener('change', syncLabel);
            syncLabel();
        }
    }

    // ── Publish date+time wiring ────────────────────────────────────────
    function pad2(n) { return String(n).padStart(2, '0'); }
    function getDateValue() {
        return dateDisplayEl ? (dateDisplayEl.getAttribute('data-value') || '') : '';
    }
    function setDateValue(yyyymmdd) {
        if (!dateDisplayEl) return;
        if (yyyymmdd) {
            dateDisplayEl.setAttribute('data-value', yyyymmdd);
            dateDisplayEl.textContent = yyyymmdd;
        } else {
            dateDisplayEl.setAttribute('data-value', '');
            dateDisplayEl.textContent = dateDisplayEl.getAttribute('data-empty-text') || '—';
        }
    }
    function hasDateTime() {
        return !!(getDateValue() && timeDisplayEl && timeDisplayEl.textContent !== '--:--');
    }
    function setDateTimeNow() {
        var now = new Date();
        setDateValue(now.getFullYear() + '-' + pad2(now.getMonth() + 1) + '-' + pad2(now.getDate()));
        if (timeDisplayEl) timeDisplayEl.textContent = pad2(now.getHours()) + ':' + pad2(now.getMinutes());
    }
    function syncHiddenDateTime() {
        if (!hiddenDtEl) return;
        var d = getDateValue();
        var t = (timeDisplayEl && timeDisplayEl.textContent !== '--:--') ? timeDisplayEl.textContent : '';
        if (d && t) {
            hiddenDtEl.value = d + ' ' + t + ':00';
        } else if (d) {
            hiddenDtEl.value = d + ' 00:00:00';
        } else {
            hiddenDtEl.value = '';
        }
    }
    if (dateBtnEl) {
        dateBtnEl.addEventListener('click', function () {
            if (!window.DaemsDatePicker) return;
            window.DaemsDatePicker.open(dateBtnEl, {
                initial: getDateValue(),
                onSelect: function (ymd) {
                    setDateValue(ymd);
                    syncHiddenDateTime();
                },
            });
        });
    }
    if (timeBtnEl) {
        timeBtnEl.addEventListener('click', function () {
            if (!window.DaemsTimePicker) return;
            var current = (timeDisplayEl && timeDisplayEl.textContent !== '--:--')
                ? timeDisplayEl.textContent
                : '';
            window.DaemsTimePicker.open(timeBtnEl, {
                initial:  current,
                system24: getTimeFormat() === '24',
                onSelect: function (hhmm) {
                    if (timeDisplayEl) timeDisplayEl.textContent = hhmm;
                    syncHiddenDateTime();
                },
                onSystemChange: function (fmt) { persistTimeFormat(fmt); },
            });
        });
    }

    // ── Submit / Publish / Delete wiring ────────────────────────────────
    if (saveBtn) {
        saveBtn.addEventListener('click', function () { submit({ action: 'save' }); });
    }
    if (publishBtn) {
        publishBtn.addEventListener('click', function () { submit({ action: 'publish' }); });
    }
    if (deleteBtn && mode === 'edit') {
        deleteBtn.addEventListener('click', openDeleteModal);
    }

    function buildChromePayload(action) {
        if (action === 'publish' && !hasDateTime()) {
            setDateTimeNow();
        }
        syncHiddenDateTime();
        return {
            slug:           slugEl        ? slugEl.value          : '',
            category:       categoryEl    ? categoryEl.value      : '',
            category_label: categoryLabel ? categoryLabel.value   : '',
            author:         authorEl      ? authorEl.value        : '',
            published_date: hiddenDtEl    ? (hiddenDtEl.value || null) : null,
            featured:       !!(featuredEl && featuredEl.checked),
            hero_image:     null,
            tags:           [],
        };
    }

    function setAllBusy(busy) {
        [saveBtn, publishBtn, deleteBtn].forEach(function (b) {
            if (b) b.disabled = busy;
        });
    }
    function restoreLabels() {
        if (saveBtn)    saveBtn.textContent    = labels.save;
        if (publishBtn) publishBtn.textContent = labels.publish;
    }

    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.style.display = '';
        errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function clearError() {
        if (!errorEl) return;
        errorEl.textContent = '';
        errorEl.style.display = 'none';
    }

    function submit(opts) {
        opts = opts || {};
        clearError();

        var payload = buildChromePayload(opts.action);
        if (!payload.slug) {
            showError('Slug is required.');
            return;
        }
        if (!payload.category) {
            showError('Category is required.');
            return;
        }

        // On create we ALSO need a fi_FI seed (title + excerpt + content),
        // because the platform CreateInsight expects them. Once the row is
        // in the DB the user can fill en_GB / sw_TZ via the locale cards.
        var draft = (mode === 'create') ? readFiFIDraft() : null;
        if (mode === 'create') {
            if ((draft.title   || '').trim().length < 3)  { showError('Title must be at least 3 characters (enter it under the Suomi card).'); return; }
            if ((draft.excerpt || '').trim() === '')      { showError('Excerpt is required (enter it under the Suomi card).'); return; }
            if ((draft.content || '').trim() === '')      { showError('Body is required (enter it under the Suomi card).'); return; }
            payload.title   = draft.title.trim();
            payload.excerpt = draft.excerpt.trim();
            payload.content = draft.content.trim();
        }

        var url = mode === 'create'
            ? '/api/backstage/insights.php?op=create'
            : '/api/backstage/insights.php?op=update&id=' + encodeURIComponent(insightId);

        var activeBtn = opts.action === 'publish' ? publishBtn : saveBtn;
        var busyText  = opts.action === 'publish' ? 'Publishing…' : 'Saving…';

        setAllBusy(true);
        if (activeBtn) activeBtn.textContent = busyText;

        fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json().catch(function () { return {}; });
            })
            .then(function (res) {
                // Created — redirect to /edit so the user can fill en_GB+sw_TZ.
                if (mode === 'create') {
                    var newId = (res.data && (res.data.id || (res.data.data && res.data.data.id))) || '';
                    if (newId) {
                        window.location.href = '/backstage/insights/edit?id=' + encodeURIComponent(newId);
                        return;
                    }
                }
                window.location.href = '/backstage/insights';
            })
            .catch(function (e) {
                setAllBusy(false);
                restoreLabels();
                showError('Save failed: ' + (e && e.message || 'unknown error'));
            });
    }

    // ── Delete modal — type-slug-to-confirm (GitHub style) ──────────────
    var deleteModal = null;

    function openDeleteModal() {
        if (!insightId) return;
        if (!originalSlug) {
            showError('Cannot delete: original slug is unknown.');
            return;
        }
        if (deleteModal === null) {
            deleteModal = buildDeleteModal();
            document.body.appendChild(deleteModal.backdrop);
        }
        deleteModal.input.value = '';
        deleteModal.confirmBtn.disabled = true;
        deleteModal.backdrop.hidden = false;
        document.addEventListener('keydown', handleEsc);
        setTimeout(function () { deleteModal.input.focus(); }, 30);
    }

    function closeDeleteModal() {
        if (!deleteModal) return;
        deleteModal.backdrop.hidden = true;
        document.removeEventListener('keydown', handleEsc);
        if (deleteBtn) deleteBtn.focus();
    }

    function handleEsc(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeDeleteModal();
        }
    }

    function buildDeleteModal() {
        var backdrop = document.createElement('div');
        backdrop.className = 'delete-modal-backdrop';
        backdrop.hidden = true;
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) closeDeleteModal();
        });

        var modal = document.createElement('div');
        modal.className = 'delete-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'if-delete-modal-title');
        backdrop.appendChild(modal);

        var head = document.createElement('div');
        head.className = 'delete-modal__head';
        head.innerHTML =
            '<span class="delete-modal__icon" aria-hidden="true">' +
            '  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>' +
            '    <line x1="12" y1="9"  x2="12" y2="13"/>' +
            '    <line x1="12" y1="17" x2="12.01" y2="17"/>' +
            '  </svg>' +
            '</span>' +
            '<h2 class="delete-modal__title" id="if-delete-modal-title">Delete insight permanently?</h2>';
        modal.appendChild(head);

        var body = document.createElement('p');
        body.className = 'delete-modal__body';
        body.innerHTML = 'This action cannot be undone. The insight, its content and all related data will be permanently removed.';
        modal.appendChild(body);

        var typeHint = document.createElement('p');
        typeHint.className = 'delete-modal__body';
        typeHint.innerHTML = 'To confirm, type <code></code> below:';
        typeHint.querySelector('code').textContent = originalSlug;
        modal.appendChild(typeHint);

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'delete-modal__input';
        input.placeholder = originalSlug;
        input.autocomplete = 'off';
        input.spellcheck = false;
        modal.appendChild(input);

        var actions = document.createElement('div');
        actions.className = 'delete-modal__actions';
        modal.appendChild(actions);

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn--secondary';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.addEventListener('click', closeDeleteModal);
        actions.appendChild(cancelBtn);

        var confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'btn btn--danger';
        confirmBtn.textContent = 'Delete permanently';
        confirmBtn.disabled = true;
        confirmBtn.addEventListener('click', function () { performDelete(confirmBtn, cancelBtn); });
        actions.appendChild(confirmBtn);

        input.addEventListener('input', function () {
            confirmBtn.disabled = input.value.trim() !== originalSlug;
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !confirmBtn.disabled) {
                e.preventDefault();
                confirmBtn.click();
            }
        });

        return { backdrop: backdrop, input: input, confirmBtn: confirmBtn, cancelBtn: cancelBtn };
    }

    function performDelete(confirmBtn, cancelBtn) {
        confirmBtn.disabled = true;
        cancelBtn.disabled  = true;
        confirmBtn.textContent = 'Deleting…';

        fetch('/api/backstage/insights.php?op=delete&id=' + encodeURIComponent(insightId), { method: 'POST' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                window.location.href = '/backstage/insights';
            })
            .catch(function (e) {
                confirmBtn.textContent = 'Delete permanently';
                cancelBtn.disabled = false;
                if (deleteModal && deleteModal.input.value.trim() === originalSlug) {
                    confirmBtn.disabled = false;
                }
                showError('Delete failed: ' + (e && e.message || 'unknown error'));
                closeDeleteModal();
            });
    }

    // TimePicker / DatePicker wiring helpers (unchanged from pre-i18n).
    function getTimeFormat() {
        var m = document.querySelector('meta[name="daems-time-format"]');
        var v = m && m.getAttribute('content');
        return v === '12' ? '12' : '24';
    }
    function persistTimeFormat(fmt) {
        var m = document.querySelector('meta[name="daems-time-format"]');
        if (m) m.setAttribute('content', fmt);
        fetch('/api/me/time-format', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ time_format: fmt }),
        }).catch(function () { /* silent */ });
    }
})();
