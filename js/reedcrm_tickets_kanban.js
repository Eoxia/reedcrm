/**
 * @file    js/reedcrm_tickets_kanban.js
 * @ingroup reedcrm
 * @brief   Kanban + Notion table: inline editing, filters, drag & drop
 */
(function () {
    'use strict';

    var isMobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent) || window.innerWidth < 768;
    var STATUS_MAP = {
        '0': { label: 'Non lu', color: '#e74c3c' },
        '1': { label: 'Lu', color: '#f39c12' },
        '2': { label: 'Assign\u00e9', color: '#f1c40f' },
        '3': { label: 'En cours', color: '#3498db' },
        '5': { label: "Besoin d'info", color: '#e67e22' },
        '7': { label: 'En attente', color: '#9b59b6' },
        '8': { label: 'Ferm\u00e9', color: '#2ecc71' },
        '9': { label: 'Annul\u00e9', color: '#7f8c8d' }
    };

    document.addEventListener('DOMContentLoaded', function () {
        initViewToggle();
        initNotionTableSearch();
        initInlineEditing();
        initKanbanColumnToggle();
        initKanbanAssigneeFilter();
        if (!isMobile) {
            initKanbanDragAndDrop();
            initKanbanAssigneePicker();
        }
    });

    // ═══════════════════════════════════════════════════════════════════
    // VIEW TOGGLE
    // ═══════════════════════════════════════════════════════════════════

    /** @description Toggle between Kanban and Notion table views */
    function initViewToggle() {
        var btns = document.querySelectorAll('.view-toggle-btn');
        var tableWrap = document.getElementById('notion-table-wrapper');
        var kanbanWrap = document.getElementById('kanban-view-section');
        if (!btns.length) return;

        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                btns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                var mode = btn.getAttribute('data-view');
                if (tableWrap) tableWrap.style.display = (mode === 'table') ? '' : 'none';
                if (kanbanWrap) kanbanWrap.style.display = (mode === 'kanban') ? '' : 'none';
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // NOTION TABLE — COLUMN SEARCH (auto-submit on change/enter)
    // ═══════════════════════════════════════════════════════════════════

    /** @description Auto-submit search form on select change or Enter in inputs */
    function initNotionTableSearch() {
        var form = document.getElementById('notion-search-form');
        if (!form) return;

        // Selects: auto submit on change
        form.querySelectorAll('.nt-search-select').forEach(function (sel) {
            sel.addEventListener('change', function () { form.submit(); });
        });

        // Text inputs: submit on Enter
        form.querySelectorAll('.nt-search-input').forEach(function (inp) {
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    form.submit();
                }
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // NOTION TABLE — INLINE EDITING
    // ═══════════════════════════════════════════════════════════════════

    var activeDropdown = null;

    /** @description Initialize inline editing on all editable cells */
    function initInlineEditing() {
        // Close dropdowns on outside click
        document.addEventListener('click', function (e) {
            if (activeDropdown && !activeDropdown.contains(e.target) && !e.target.closest('.nt-editable.nt-editing')) {
                closeActiveDropdown();
            }
        });

        document.querySelectorAll('.nt-editable').forEach(function (cell) {
            cell.addEventListener('click', function (e) {
                if (cell.classList.contains('nt-editing')) return;
                e.stopPropagation();
                var editType = cell.getAttribute('data-edit-type');
                var field = cell.getAttribute('data-field');
                var currentVal = cell.getAttribute('data-value') || '';
                var row = cell.closest('.nt-data-row');
                var ticketId = row ? row.getAttribute('data-ticket-id') : '';

                closeActiveDropdown();

                switch (editType) {
                    case 'text':
                        startTextEdit(cell, field, currentVal, ticketId);
                        break;
                    case 'select':
                        startSelectEdit(cell, field, currentVal, ticketId);
                        break;
                    case 'user':
                        startUserEdit(cell, field, currentVal, ticketId);
                        break;
                    case 'tags':
                        startTagsEdit(cell, field, currentVal, ticketId);
                        break;
                }
            });
        });
    }

    // ── TEXT EDIT ──────────────────────────────────────────────────────

    /**
     * @description Start inline text editing
     * @param {Element} cell
     * @param {string} field
     * @param {string} currentVal
     * @param {string} ticketId
     */
    function startTextEdit(cell, field, currentVal, ticketId) {
        cell.classList.add('nt-editing');
        var origHtml = cell.innerHTML;
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'nt-inline-input';
        input.value = currentVal;
        cell.textContent = '';
        cell.appendChild(input);
        input.focus();
        input.select();

        function save() {
            var newVal = input.value.trim();
            if (newVal === currentVal) {
                cancel();
                return;
            }
            cell.classList.remove('nt-editing');
            cell.classList.add('nt-cell-saving');
            ajaxSaveField(ticketId, field, newVal, function (ok) {
                cell.classList.remove('nt-cell-saving');
                if (ok) {
                    cell.setAttribute('data-value', newVal);
                    cell.textContent = '';
                    var span = document.createElement('span');
                    span.className = 'nt-cell-text';
                    span.textContent = newVal || 'Sans titre';
                    cell.appendChild(span);
                    flashCell(cell, true);
                } else {
                    cell.innerHTML = origHtml;
                    flashCell(cell, false);
                }
            });
        }

        function cancel() {
            cell.classList.remove('nt-editing');
            cell.innerHTML = origHtml;
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); save(); }
            if (e.key === 'Escape') { e.preventDefault(); cancel(); }
        });
        input.addEventListener('blur', function () {
            setTimeout(save, 100);
        });
    }

    // ── SELECT EDIT (severity, status) ────────────────────────────────

    /**
     * @description Start inline select editing
     * @param {Element} cell
     * @param {string} field
     * @param {string} currentVal
     * @param {string} ticketId
     */
    function startSelectEdit(cell, field, currentVal, ticketId) {
        cell.classList.add('nt-editing');
        var origHtml = cell.innerHTML;
        var options = [];

        if (field === 'severity') {
            options = (window.KANBAN_SEVERITIES || []).map(function (s) {
                return { value: s.code, label: s.label };
            });
        } else if (field === 'status') {
            options = (window.KANBAN_STATUSES || []).map(function (s) {
                return { value: String(s.code), label: s.label };
            });
        }

        var select = document.createElement('select');
        select.className = 'nt-inline-select';
        options.forEach(function (opt) {
            var o = document.createElement('option');
            o.value = opt.value;
            o.textContent = opt.label;
            if (opt.value === currentVal) o.selected = true;
            select.appendChild(o);
        });

        cell.textContent = '';
        cell.appendChild(select);
        select.focus();

        function save() {
            var newVal = select.value;
            if (newVal === currentVal) { cancel(); return; }
            cell.classList.remove('nt-editing');
            cell.classList.add('nt-cell-saving');
            ajaxSaveField(ticketId, field, newVal, function (ok) {
                cell.classList.remove('nt-cell-saving');
                if (ok) {
                    cell.setAttribute('data-value', newVal);
                    cell.textContent = '';
                    if (field === 'severity') {
                        var badge = document.createElement('span');
                        badge.className = 'nt-severity-badge nt-severity-' + newVal.toLowerCase();
                        var lbl = options.find(function (o) { return o.value === newVal; });
                        badge.textContent = lbl ? lbl.label : newVal;
                        cell.appendChild(badge);
                    } else if (field === 'status') {
                        var info = STATUS_MAP[newVal] || { label: '?', color: '#ccc' };
                        var sb = document.createElement('span');
                        sb.className = 'nt-status-badge';
                        sb.style.setProperty('--st-color', info.color);
                        var dot = document.createElement('span');
                        dot.className = 'nt-status-dot';
                        dot.style.background = info.color;
                        sb.appendChild(dot);
                        sb.appendChild(document.createTextNode(' ' + info.label));
                        cell.appendChild(sb);
                    }
                    flashCell(cell, true);
                } else {
                    cell.innerHTML = origHtml;
                    flashCell(cell, false);
                }
            });
        }

        function cancel() {
            cell.classList.remove('nt-editing');
            cell.innerHTML = origHtml;
        }

        select.addEventListener('change', save);
        select.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') cancel();
        });
        select.addEventListener('blur', function () { setTimeout(function () { if (cell.classList.contains('nt-editing')) cancel(); }, 150); });
    }

    // ── USER EDIT ─────────────────────────────────────────────────────

    /**
     * @description Start user picker dropdown
     * @param {Element} cell
     * @param {string} field
     * @param {string} currentVal
     * @param {string} ticketId
     */
    function startUserEdit(cell, field, currentVal, ticketId) {
        cell.classList.add('nt-editing');
        var origHtml = cell.innerHTML;
        var users = window.KANBAN_USERS || [];
        var currentId = parseInt(currentVal, 10) || 0;

        var dd = document.createElement('div');
        dd.className = 'nt-user-picker';

        // Non assigné
        dd.appendChild(createUserItem(0, 'Non assigné', '?', '', currentId === 0));
        var sep = document.createElement('div');
        sep.className = 'nt-user-picker-sep';
        dd.appendChild(sep);

        users.forEach(function (u) {
            dd.appendChild(createUserItem(u.id, u.name, u.initials, u.photo, currentId === u.id));
        });

        cell.appendChild(dd);
        activeDropdown = dd;

        function createUserItem(id, name, initials, photo, selected) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'nt-user-picker-item' + (selected ? ' selected' : '');
            var avatar = document.createElement('div');
            avatar.className = 'nt-user-picker-avatar';
            if (photo) {
                var img = document.createElement('img');
                img.src = photo;
                img.alt = initials;
                avatar.appendChild(img);
            } else {
                avatar.textContent = initials || '?';
            }
            btn.appendChild(avatar);
            var nameSpan = document.createElement('span');
            nameSpan.textContent = name;
            btn.appendChild(nameSpan);
            if (selected) {
                var check = document.createElement('i');
                check.className = 'fas fa-check nt-user-picker-check';
                btn.appendChild(check);
            }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                selectUser(id, name, initials, photo);
            });
            return btn;
        }

        function selectUser(id, name, initials, photo) {
            closeActiveDropdown();
            cell.classList.remove('nt-editing');
            cell.classList.add('nt-cell-saving');
            ajaxSaveField(ticketId, field, String(id), function (ok) {
                cell.classList.remove('nt-cell-saving');
                if (ok) {
                    cell.setAttribute('data-value', String(id));
                    cell.textContent = '';
                    if (id > 0) {
                        var chip = document.createElement('div');
                        chip.className = 'nt-user-chip';
                        if (photo) {
                            var img = document.createElement('img');
                            img.src = photo;
                            img.className = 'nt-user-avatar';
                            chip.appendChild(img);
                        } else {
                            var av = document.createElement('span');
                            av.className = 'nt-user-avatar nt-user-initials';
                            av.textContent = initials;
                            chip.appendChild(av);
                        }
                        var nm = document.createElement('span');
                        nm.className = 'nt-user-name';
                        nm.textContent = name;
                        chip.appendChild(nm);
                        cell.appendChild(chip);
                    } else {
                        var empty = document.createElement('span');
                        empty.className = 'nt-empty-value';
                        empty.textContent = '-';
                        cell.appendChild(empty);
                    }
                    flashCell(cell, true);
                } else {
                    cell.innerHTML = origHtml;
                    flashCell(cell, false);
                }
            });
        }
    }

    // ── TAGS EDIT ─────────────────────────────────────────────────────

    /**
     * @description Start multi-select tag picker
     * @param {Element} cell
     * @param {string} field
     * @param {string} currentVal comma-separated IDs
     * @param {string} ticketId
     */
    function startTagsEdit(cell, field, currentVal, ticketId) {
        cell.classList.add('nt-editing');
        var origHtml = cell.innerHTML;
        var allTags = window.KANBAN_TAGS || [];
        var selectedIds = currentVal ? currentVal.split(',').map(Number).filter(Boolean) : [];

        var dd = document.createElement('div');
        dd.className = 'nt-tag-picker';

        allTags.forEach(function (tag) {
            var isSelected = selectedIds.indexOf(tag.id) >= 0;
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'nt-tag-picker-item' + (isSelected ? ' selected' : '');
            item.setAttribute('data-tag-id', tag.id);

            var dot = document.createElement('span');
            dot.className = 'nt-tag-picker-dot';
            dot.style.background = '#' + tag.color;
            item.appendChild(dot);

            var lbl = document.createElement('span');
            lbl.textContent = tag.label;
            item.appendChild(lbl);

            if (isSelected) {
                var check = document.createElement('i');
                check.className = 'fas fa-check nt-tag-picker-check';
                item.appendChild(check);
            }

            item.addEventListener('click', function (e) {
                e.stopPropagation();
                var idx = selectedIds.indexOf(tag.id);
                if (idx >= 0) {
                    selectedIds.splice(idx, 1);
                    item.classList.remove('selected');
                    var existCheck = item.querySelector('.nt-tag-picker-check');
                    if (existCheck) existCheck.remove();
                } else {
                    selectedIds.push(tag.id);
                    item.classList.add('selected');
                    var c = document.createElement('i');
                    c.className = 'fas fa-check nt-tag-picker-check';
                    item.appendChild(c);
                }
                // Save immediately on each toggle
                saveTags();
            });

            dd.appendChild(item);
        });

        cell.appendChild(dd);
        activeDropdown = dd;

        function saveTags() {
            var val = selectedIds.join(',');
            ajaxSaveField(ticketId, field, val, function (ok) {
                if (ok) {
                    cell.setAttribute('data-value', val);
                    // Rebuild tag chips (keep dropdown open)
                    var existing = cell.querySelectorAll('.nt-tag-chip, .nt-empty-value');
                    existing.forEach(function (el) { el.remove(); });
                    if (selectedIds.length === 0) {
                        var empty = document.createElement('span');
                        empty.className = 'nt-empty-value';
                        empty.textContent = '-';
                        cell.insertBefore(empty, dd);
                    } else {
                        selectedIds.forEach(function (id) {
                            var tagInfo = allTags.find(function (t) { return t.id === id; });
                            if (tagInfo) {
                                var chip = document.createElement('span');
                                chip.className = 'nt-tag-chip';
                                chip.style.setProperty('--tag-color', '#' + tagInfo.color);
                                chip.setAttribute('data-tag-id', id);
                                chip.textContent = tagInfo.label;
                                cell.insertBefore(chip, dd);
                            }
                        });
                    }
                }
            });
        }
    }

    // ── AJAX SAVE ─────────────────────────────────────────────────────

    /**
     * @description Generic AJAX field save
     * @param {string} ticketId
     * @param {string} field
     * @param {string} value
     * @param {function} callback (success: boolean)
     */
    function ajaxSaveField(ticketId, field, value, callback) {
        var token = '';
        var tokenInput = document.querySelector('input[name="token"]');
        if (tokenInput) token = tokenInput.value;

        var baseUrl = window.location.pathname;
        var params = 'action=updateticketfield'
            + '&token=' + encodeURIComponent(token)
            + '&ticketid=' + encodeURIComponent(ticketId)
            + '&field=' + encodeURIComponent(field)
            + '&value=' + encodeURIComponent(value);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', baseUrl + '?' + params, true);
        xhr.onload = function () {
            try {
                var res = JSON.parse(xhr.responseText);
                callback(!!res.success);
            } catch (e) {
                console.error('Invalid JSON:', xhr.responseText);
                callback(false);
            }
        };
        xhr.onerror = function () { callback(false); };
        xhr.send();
    }

    /** @description Flash green or red on a cell after save */
    function flashCell(cell, success) {
        var cls = success ? 'nt-cell-success' : 'nt-cell-error';
        cell.classList.add(cls);
        setTimeout(function () { cell.classList.remove(cls); }, 1000);
    }

    /** @description Close active dropdown */
    function closeActiveDropdown() {
        if (activeDropdown) {
            var parent = activeDropdown.closest('.nt-editing');
            activeDropdown.remove();
            activeDropdown = null;
            if (parent) parent.classList.remove('nt-editing');
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // KANBAN — COLUMN TOGGLE
    // ═══════════════════════════════════════════════════════════════════

    function initKanbanColumnToggle() {
        document.querySelectorAll('.kanban-column-header').forEach(function (header) {
            header.addEventListener('click', function (e) {
                if (e.target.closest('.kanban-column-toggle')) return;
                var col = header.closest('.kanban-column');
                if (col) col.classList.toggle('collapsed');
            });
            var btn = header.querySelector('.kanban-column-toggle');
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var col = btn.closest('.kanban-column');
                    if (col) col.classList.toggle('collapsed');
                });
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // KANBAN — ASSIGNEE FILTER
    // ═══════════════════════════════════════════════════════════════════

    function initKanbanAssigneeFilter() {
        var chips = document.querySelectorAll('.kanban-chip[data-filter-user]');
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                applyKanbanFilter();
            });
        });
    }

    function applyKanbanFilter() {
        var active = document.querySelector('.kanban-chip.active');
        var filterUser = active ? active.getAttribute('data-filter-user') : 'all';
        document.querySelectorAll('.ticket-card-kanban').forEach(function (card) {
            var uid = card.getAttribute('data-assignee') || '0';
            var match = (filterUser === 'all') || (uid === filterUser);
            card.classList.toggle('hidden-by-filter', !match);
        });
        // Update counts
        document.querySelectorAll('.kanban-column').forEach(function (col) {
            var st = col.getAttribute('data-status');
            var visible = col.querySelectorAll('.ticket-card-kanban:not(.hidden-by-filter)');
            var countEl = document.getElementById('col-count-' + st);
            if (countEl) countEl.textContent = visible.length;
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // KANBAN — DRAG & DROP
    // ═══════════════════════════════════════════════════════════════════

    function initKanbanDragAndDrop() {
        var draggedCard = null;

        document.querySelectorAll('.ticket-card-kanban').forEach(function (card) {
            card.addEventListener('dragstart', function (e) {
                draggedCard = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.getAttribute('data-ticket-id'));
                setTimeout(function () { card.style.display = 'none'; }, 0);
            });
            card.addEventListener('dragend', function () {
                card.classList.remove('dragging');
                card.style.display = '';
                draggedCard = null;
                document.querySelectorAll('.kanban-column').forEach(function (c) { c.classList.remove('drag-over'); });
            });
        });

        document.querySelectorAll('.kanban-column-body').forEach(function (body) {
            body.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                var col = body.closest('.kanban-column');
                if (col) col.classList.add('drag-over');
            });
            body.addEventListener('dragleave', function (e) {
                if (!body.contains(e.relatedTarget)) {
                    var col = body.closest('.kanban-column');
                    if (col) col.classList.remove('drag-over');
                }
            });
            body.addEventListener('drop', function (e) {
                e.preventDefault();
                var col = body.closest('.kanban-column');
                if (col) col.classList.remove('drag-over');
                if (!draggedCard) return;

                var newStatus = col.getAttribute('data-status');
                var oldStatus = draggedCard.getAttribute('data-status');
                var ticketId = draggedCard.getAttribute('data-ticket-id');

                body.appendChild(draggedCard);
                draggedCard.style.display = '';

                // Remove empty state
                var emptyDiv = body.querySelector('.kanban-empty-col');
                if (emptyDiv) emptyDiv.remove();

                if (newStatus !== oldStatus) {
                    draggedCard.setAttribute('data-status', newStatus);
                    ajaxSaveField(ticketId, 'status', newStatus, function (ok) {
                        if (ok) {
                            draggedCard.style.borderColor = '#22c55e';
                            setTimeout(function () { draggedCard.style.borderColor = ''; }, 1500);
                        }
                    });
                }
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // KANBAN — ASSIGNEE PICKER (on card avatar)
    // ═══════════════════════════════════════════════════════════════════

    function initKanbanAssigneePicker() {
        var users = window.KANBAN_USERS || [];
        var kanbanDD = null;

        document.addEventListener('click', function (e) {
            if (kanbanDD && !kanbanDD.contains(e.target) && !e.target.closest('.tc-assignee-picker')) {
                if (kanbanDD.parentNode) kanbanDD.remove();
                kanbanDD = null;
            }
        });

        document.querySelectorAll('.tc-assignee-picker').forEach(function (picker) {
            picker.addEventListener('click', function (e) {
                e.stopPropagation();
                if (kanbanDD) { kanbanDD.remove(); kanbanDD = null; return; }

                var ticketId = picker.getAttribute('data-ticket-id');
                var currentId = parseInt(picker.getAttribute('data-current-assignee') || '0', 10);

                var dd = document.createElement('div');
                dd.className = 'assignee-dropdown';

                // Non assigné
                var unItem = document.createElement('button');
                unItem.type = 'button';
                unItem.className = 'assignee-dropdown-item' + (currentId === 0 ? ' selected' : '');
                unItem.textContent = 'Non assigné';
                unItem.addEventListener('click', function (ev) { ev.stopPropagation(); pickUser(0); });
                dd.appendChild(unItem);

                var sep = document.createElement('div');
                sep.className = 'assignee-dropdown-separator';
                dd.appendChild(sep);

                users.forEach(function (u) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'assignee-dropdown-item' + (currentId === u.id ? ' selected' : '');
                    var av = document.createElement('div');
                    av.className = 'dd-avatar';
                    if (u.photo) {
                        var img = document.createElement('img');
                        img.src = u.photo;
                        av.appendChild(img);
                    } else {
                        av.textContent = u.initials || '?';
                    }
                    item.appendChild(av);
                    var nm = document.createElement('span');
                    nm.textContent = u.name;
                    item.appendChild(nm);
                    item.addEventListener('click', function (ev) { ev.stopPropagation(); pickUser(u.id); });
                    dd.appendChild(item);
                });

                picker.appendChild(dd);
                kanbanDD = dd;

                function pickUser(userId) {
                    dd.remove();
                    kanbanDD = null;
                    ajaxSaveField(ticketId, 'assignee', String(userId), function (ok) {
                        if (ok) {
                            picker.setAttribute('data-current-assignee', String(userId));
                            // Update avatar
                            var caret = picker.querySelector('.tc-assignee-caret');
                            picker.textContent = '';
                            var u = users.find(function (x) { return x.id === userId; });
                            if (u && u.photo) {
                                var img = document.createElement('img');
                                img.src = u.photo;
                                img.className = 'tc-assignee-img';
                                picker.appendChild(img);
                            } else {
                                picker.appendChild(document.createTextNode(u ? u.initials : '?'));
                            }
                            if (caret) picker.appendChild(caret);
                            else {
                                var nc = document.createElement('i');
                                nc.className = 'fas fa-caret-down tc-assignee-caret';
                                picker.appendChild(nc);
                            }
                        }
                    });
                }
            });
        });
    }

})();
