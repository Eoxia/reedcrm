/**
 * @file    js/reedcrm_tickets_kanban.js
 * @ingroup reedcrm
 * @brief   Kanban board interactions: filter, search, drag & drop, view toggle, column resize
 */
(function () {
    'use strict';

    var isMobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent) || window.innerWidth < 768;

    /** @type {Object<string, {label: string, color: string}>} Dolibarr 23 Ticket status map */
    var STATUS_MAP = {
        '0': { label: 'Non lu',         color: '#e74c3c' },
        '1': { label: 'Lu',             color: '#f39c12' },
        '2': { label: 'Assign\u00e9',   color: '#f1c40f' },
        '3': { label: 'En cours',       color: '#3498db' },
        '5': { label: "Besoin d'info",  color: '#e67e22' },
        '7': { label: 'En attente',     color: '#9b59b6' },
        '8': { label: 'Ferm\u00e9',     color: '#2ecc71' },
        '9': { label: 'Annul\u00e9',    color: '#7f8c8d' }
    };

    document.addEventListener('DOMContentLoaded', function () {
        initAssigneeFilter();
        initSearchFilter();
        initColumnToggle();
        initViewToggle();
        initStatusFilter();
        initAssigneePicker();
        if (!isMobile) {
            initDragAndDrop();
            initColumnResize();
        } else {
            document.querySelectorAll('.ticket-card-kanban').forEach(function (card) {
                card.setAttribute('draggable', 'false');
                card.style.cursor = 'default';
            });
        }
    });

    // ── VIEW TOGGLE (Kanban / List) ───────────────────────────────────
    /**
     * @description Toggle between Kanban board and list view
     */
    function initViewToggle() {
        var toggleBtns = document.querySelectorAll('.view-toggle-btn');
        var board = document.getElementById('kanban-board');
        if (!board || !toggleBtns.length) return;

        toggleBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                toggleBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                var viewMode = btn.getAttribute('data-view');
                if (viewMode === 'list') {
                    board.classList.add('kanban-list-mode');
                } else {
                    board.classList.remove('kanban-list-mode');
                }
            });
        });
    }

    // ── STATUS FILTER (Notion-like column visibility toggles) ─────────
    /**
     * @description Creates status filter chips below the assignee filter to show/hide columns
     */
    function initStatusFilter() {
        var filtersContainer = document.getElementById('kanban-filters');
        if (!filtersContainer) return;

        var statusRow = document.createElement('div');
        statusRow.className = 'kanban-status-filters';
        statusRow.id = 'kanban-status-filters';

        // Label
        var label = document.createElement('span');
        label.className = 'status-filter-label';
        label.innerHTML = '<i class="fas fa-filter"></i> Statuts';
        statusRow.appendChild(label);

        // One toggle per status column
        document.querySelectorAll('.kanban-column').forEach(function (col) {
            var st = col.getAttribute('data-status');
            var info = STATUS_MAP[st];
            if (!info) return;

            var btn = document.createElement('button');
            btn.className = 'status-filter-chip active';
            btn.setAttribute('data-status-filter', st);
            btn.title = 'Afficher/masquer ' + info.label;
            btn.innerHTML = '<span class="sf-dot" style="background:' + info.color + '"></span>'
                + '<span class="sf-label">' + info.label + '</span>'
                + '<span class="sf-count" id="sf-count-' + st + '">' + (col.querySelectorAll('.ticket-card-kanban').length) + '</span>';

            btn.addEventListener('click', function () {
                btn.classList.toggle('active');
                var isVisible = btn.classList.contains('active');
                col.style.display = isVisible ? '' : 'none';
            });

            statusRow.appendChild(btn);
        });

        filtersContainer.appendChild(statusRow);
    }

    // ── COLUMN TOGGLE (click header to collapse) ──────────────────────
    /**
     * @description All column headers are clickable to toggle collapse
     */
    function initColumnToggle() {
        document.querySelectorAll('.kanban-column-header').forEach(function (header) {
            // The toggle button itself
            var toggleBtn = header.querySelector('.kanban-column-toggle');

            // Make the entire header clickable (except toggle btn which has its own handler)
            header.style.cursor = 'pointer';
            header.addEventListener('click', function (e) {
                // Don't double-trigger if clicking the toggle button
                if (toggleBtn && (e.target === toggleBtn || toggleBtn.contains(e.target))) return;
                var col = header.closest('.kanban-column');
                if (col) {
                    col.classList.toggle('collapsed');
                    // Update chevron
                    var icon = header.querySelector('.kanban-column-toggle i');
                    if (icon) {
                        // Icon rotation is handled by CSS
                    }
                }
            });

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var col = toggleBtn.closest('.kanban-column');
                    if (col) col.classList.toggle('collapsed');
                });
            }
        });
    }

    // ── COLUMN RESIZE (Notion-like drag to resize) ────────────────────
    /**
     * @description Adds resize handles to Kanban columns for width adjustment
     */
    function initColumnResize() {
        var columns = document.querySelectorAll('.kanban-column');
        columns.forEach(function (col) {
            var handle = document.createElement('div');
            handle.className = 'kanban-resize-handle';
            handle.title = 'Ajuster la largeur';
            col.appendChild(handle);

            var startX, startWidth;

            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                startX = e.clientX;
                startWidth = col.offsetWidth;
                col.classList.add('resizing');
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';

                function onMouseMove(ev) {
                    var diff = ev.clientX - startX;
                    var newWidth = Math.max(200, Math.min(600, startWidth + diff));
                    col.style.flex = '0 0 ' + newWidth + 'px';
                    col.style.minWidth = newWidth + 'px';
                    col.style.maxWidth = newWidth + 'px';
                }

                function onMouseUp() {
                    col.classList.remove('resizing');
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                }

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        });
    }

    // ── ASSIGNEE FILTER ───────────────────────────────────────────────
    /**
     * @description Filter tickets by assigned user via chip buttons
     */
    function initAssigneeFilter() {
        var chips = document.querySelectorAll('.kanban-chip[data-filter-user]');
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                applyFilters();
            });
        });
    }

    // ── SEARCH FILTER ─────────────────────────────────────────────────
    /**
     * @description Real-time text search across ticket cards
     */
    function initSearchFilter() {
        var input = document.getElementById('kanban-search-input');
        if (!input) return;

        var debounceTimer = null;
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                applyFilters();
            }, 200);
        });
    }

    // ── APPLY ALL FILTERS ─────────────────────────────────────────────
    /**
     * @description Combined filter: assignee + text search. Updates counts.
     */
    function applyFilters() {
        var activeChip = document.querySelector('.kanban-chip.active');
        var filterUser = activeChip ? activeChip.getAttribute('data-filter-user') : 'all';
        var searchInput = document.getElementById('kanban-search-input');
        var searchTerm = (searchInput ? searchInput.value : '').toLowerCase().trim();

        var allCards = document.querySelectorAll('.ticket-card-kanban');
        var visibleByCol = {};
        var totalVisible = 0;

        allCards.forEach(function (card) {
            var cardAssignee = card.getAttribute('data-assignee') || '0';
            var cardSearch = card.getAttribute('data-search') || '';
            var cardStatus = card.getAttribute('data-status') || '0';

            var matchUser = (filterUser === 'all') || (cardAssignee === filterUser);
            var matchText = !searchTerm || cardSearch.indexOf(searchTerm) >= 0;

            if (matchUser && matchText) {
                card.classList.remove('hidden-by-filter');
                totalVisible++;
                visibleByCol[cardStatus] = (visibleByCol[cardStatus] || 0) + 1;
            } else {
                card.classList.add('hidden-by-filter');
            }
        });

        // Update column counters + status filter counters
        document.querySelectorAll('.kanban-column').forEach(function (col) {
            var st = col.getAttribute('data-status');
            var count = visibleByCol[st] || 0;
            var countEl = document.getElementById('col-count-' + st);
            if (countEl) countEl.textContent = count;
            // Update status filter chip count
            var sfCount = document.getElementById('sf-count-' + st);
            if (sfCount) sfCount.textContent = count;

            // Show/hide empty state
            var body = col.querySelector('.kanban-column-body');
            var cards = body.querySelectorAll('.ticket-card-kanban:not(.hidden-by-filter)');
            var emptyDiv = body.querySelector('.kanban-empty-col');
            if (cards.length === 0 && !emptyDiv) {
                var empty = document.createElement('div');
                empty.className = 'kanban-empty-col';
                empty.innerHTML = '<i class="fas fa-inbox"></i><span>Aucun ticket</span>';
                body.appendChild(empty);
            } else if (cards.length > 0 && emptyDiv) {
                emptyDiv.remove();
            }
        });

        // Update assignee chip counts based on text filter only
        if (searchTerm) {
            var countsByUser = { 'all': 0, '0': 0 };
            allCards.forEach(function (card) {
                var cs = card.getAttribute('data-search') || '';
                if (cs.indexOf(searchTerm) >= 0) {
                    countsByUser['all']++;
                    var uid = card.getAttribute('data-assignee') || '0';
                    countsByUser[uid] = (countsByUser[uid] || 0) + 1;
                }
            });
            document.querySelectorAll('.kanban-chip').forEach(function (chip) {
                var uid = chip.getAttribute('data-filter-user');
                var cntEl = chip.querySelector('.chip-count');
                if (cntEl) cntEl.textContent = countsByUser[uid] || 0;
            });
        }
    }

    // ── DRAG & DROP (Desktop only) ────────────────────────────────────
    /**
     * @description Drag tickets between columns to change status
     */
    function initDragAndDrop() {
        var draggedCard = null;
        var placeholder = null;

        document.querySelectorAll('.ticket-card-kanban').forEach(function (card) {
            card.addEventListener('dragstart', function (e) {
                draggedCard = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.getAttribute('data-ticket-id'));

                placeholder = document.createElement('div');
                placeholder.className = 'kanban-drop-placeholder';
                placeholder.style.height = card.offsetHeight + 'px';

                setTimeout(function () {
                    card.style.display = 'none';
                }, 0);
            });

            card.addEventListener('dragend', function () {
                card.classList.remove('dragging');
                card.style.display = '';
                draggedCard = null;
                document.querySelectorAll('.kanban-column').forEach(function (col) {
                    col.classList.remove('drag-over');
                });
                if (placeholder && placeholder.parentNode) {
                    placeholder.remove();
                }
                placeholder = null;
            });
        });

        document.querySelectorAll('.kanban-column-body').forEach(function (body) {
            body.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                var col = body.closest('.kanban-column');
                if (col) col.classList.add('drag-over');

                if (placeholder) {
                    var afterElement = getDragAfterElement(body, e.clientY);
                    if (afterElement) {
                        body.insertBefore(placeholder, afterElement);
                    } else {
                        body.appendChild(placeholder);
                    }
                }
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
                var ticketId  = draggedCard.getAttribute('data-ticket-id');

                if (placeholder && placeholder.parentNode) {
                    body.insertBefore(draggedCard, placeholder);
                    placeholder.remove();
                } else {
                    body.appendChild(draggedCard);
                }
                draggedCard.style.display = '';

                var emptyDiv = body.querySelector('.kanban-empty-col');
                if (emptyDiv) emptyDiv.remove();

                // Add empty state to old column if needed
                var oldCol = document.getElementById('kanban-body-' + oldStatus);
                if (oldCol) {
                    var remaining = oldCol.querySelectorAll('.ticket-card-kanban:not(.hidden-by-filter)');
                    if (remaining.length === 0 && !oldCol.querySelector('.kanban-empty-col')) {
                        var emptyEl = document.createElement('div');
                        emptyEl.className = 'kanban-empty-col';
                        emptyEl.innerHTML = '<i class="fas fa-inbox"></i><span>Aucun ticket</span>';
                        oldCol.appendChild(emptyEl);
                    }
                }

                if (newStatus !== oldStatus) {
                    draggedCard.setAttribute('data-status', newStatus);
                    updateTicketStatusCard(draggedCard, newStatus);
                    saveTicketStatus(ticketId, newStatus, draggedCard);
                }

                updateColumnCounts();
            });
        });
    }

    /**
     * @description Find the element after which to insert the dragged card
     * @param {Element} container The column body
     * @param {number} y Mouse Y position
     * @returns {Element|null}
     */
    function getDragAfterElement(container, y) {
        var draggableElements = Array.from(
            container.querySelectorAll('.ticket-card-kanban:not(.dragging):not(.hidden-by-filter)')
        );
        var result = null;
        var closestOffset = Number.NEGATIVE_INFINITY;
        draggableElements.forEach(function (child) {
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closestOffset) {
                closestOffset = offset;
                result = child;
            }
        });
        return result;
    }

    /**
     * @description Update card status badge after drag
     * @param {Element} card The ticket card
     * @param {string} newStatus Status code
     */
    function updateTicketStatusCard(card, newStatus) {
        var info = STATUS_MAP[newStatus];
        if (!info) return;
        var dot = card.querySelector('.tc-status-dot');
        var label = card.querySelector('.tc-status-label');
        if (dot) dot.style.backgroundColor = info.color;
        if (label) label.textContent = info.label;
    }

    /**
     * @description AJAX call to save new ticket status via setStatut()
     * @param {string} ticketId Ticket row ID
     * @param {string} newStatus New status code
     * @param {Element} card The ticket card element
     */
    function saveTicketStatus(ticketId, newStatus, card) {
        card.classList.add('kanban-card-loading');
        card.style.position = 'relative';

        var token = '';
        var tokenInput = document.querySelector('input[name="token"]');
        if (tokenInput) token = tokenInput.value;

        var baseUrl = window.location.pathname;
        var params = 'action=updateticketstatus'
            + '&token=' + encodeURIComponent(token)
            + '&ticketid=' + encodeURIComponent(ticketId)
            + '&newstatus=' + encodeURIComponent(newStatus);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', baseUrl + '?' + params, true);
        xhr.onload = function () {
            card.classList.remove('kanban-card-loading');
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    card.style.borderColor = '#22c55e';
                    card.style.boxShadow = '0 0 0 2px rgba(34,197,94,0.3)';
                    setTimeout(function () {
                        card.style.borderColor = '';
                        card.style.boxShadow = '';
                    }, 1500);
                } else {
                    card.style.borderColor = '#ef4444';
                    card.style.boxShadow = '0 0 0 2px rgba(239,68,68,0.3)';
                    console.error('Ticket status update failed:', res.error);
                    setTimeout(function () {
                        card.style.borderColor = '';
                        card.style.boxShadow = '';
                    }, 3000);
                }
            } catch (e) {
                console.error('Invalid JSON response:', xhr.responseText);
            }
        };
        xhr.onerror = function () {
            card.classList.remove('kanban-card-loading');
            console.error('Network error during ticket status update');
        };
        xhr.send();
    }

    /**
     * @description Recalculate all column counters
     */
    function updateColumnCounts() {
        document.querySelectorAll('.kanban-column').forEach(function (col) {
            var st = col.getAttribute('data-status');
            var visibleCards = col.querySelectorAll('.ticket-card-kanban:not(.hidden-by-filter)');
            var count = visibleCards.length;
            var countEl = document.getElementById('col-count-' + st);
            if (countEl) countEl.textContent = count;
            var sfCount = document.getElementById('sf-count-' + st);
            if (sfCount) sfCount.textContent = count;
        });
    }

    // ── ASSIGNEE PICKER DROPDOWN ──────────────────────────────────────
    /**
     * @description Click on assignee avatar to show a dropdown of all users
     */
    function initAssigneePicker() {
        var users = window.KANBAN_USERS || [];
        var activeDropdown = null;

        // Close dropdown on outside click
        document.addEventListener('click', function (e) {
            if (activeDropdown && !activeDropdown.contains(e.target) && !e.target.closest('.tc-assignee-picker')) {
                closeDropdown();
            }
        });

        document.querySelectorAll('.tc-assignee-picker').forEach(function (picker) {
            picker.addEventListener('click', function (e) {
                e.stopPropagation();
                e.preventDefault();

                // Close existing dropdown
                if (activeDropdown) {
                    var wasOnSame = activeDropdown.parentElement === picker;
                    closeDropdown();
                    if (wasOnSame) return;
                }

                var ticketId = picker.getAttribute('data-ticket-id');
                var currentAssignee = parseInt(picker.getAttribute('data-current-assignee') || '0', 10);

                // Build dropdown
                var dd = document.createElement('div');
                dd.className = 'assignee-dropdown';

                // "Non assigné" option
                var unassignItem = createDropdownItem(0, 'Non assigné', '?', '', currentAssignee === 0);
                unassignItem.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    selectAssignee(ticketId, 0, picker);
                });
                dd.appendChild(unassignItem);

                // Separator
                var sep = document.createElement('div');
                sep.className = 'assignee-dropdown-separator';
                dd.appendChild(sep);

                // All users
                users.forEach(function (u) {
                    var item = createDropdownItem(u.id, u.name, u.initials, u.photo, currentAssignee === u.id);
                    item.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        selectAssignee(ticketId, u.id, picker);
                    });
                    dd.appendChild(item);
                });

                picker.appendChild(dd);
                activeDropdown = dd;
            });
        });

        /**
         * @description Create a single dropdown item element
         * @param {number} userId
         * @param {string} name
         * @param {string} initials
         * @param {string} photoUrl
         * @param {boolean} isSelected
         * @returns {HTMLElement}
         */
        function createDropdownItem(userId, name, initials, photoUrl, isSelected) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'assignee-dropdown-item' + (isSelected ? ' selected' : '');
            btn.setAttribute('data-user-id', userId);

            var avatarHtml = '';
            if (photoUrl) {
                avatarHtml = '<div class="dd-avatar"><img src="' + photoUrl + '" alt="' + initials + '"></div>';
            } else {
                avatarHtml = '<div class="dd-avatar">' + (initials || '?') + '</div>';
            }

            btn.innerHTML = avatarHtml
                + '<span>' + escapeHtml(name) + '</span>'
                + (isSelected ? '<i class="fas fa-check dd-check"></i>' : '');

            return btn;
        }

        /**
         * @description AJAX call to assign a user to a ticket
         * @param {string} ticketId
         * @param {number} newAssigneeId
         * @param {Element} pickerEl
         */
        function selectAssignee(ticketId, newAssigneeId, pickerEl) {
            closeDropdown();

            var card = pickerEl.closest('.ticket-card-kanban');
            if (!card) return;

            card.classList.add('kanban-card-loading');
            card.style.position = 'relative';

            var token = '';
            var tokenInput = document.querySelector('input[name="token"]');
            if (tokenInput) token = tokenInput.value;

            var baseUrl = window.location.pathname;
            var params = 'action=updateticketassignee'
                + '&token=' + encodeURIComponent(token)
                + '&ticketid=' + encodeURIComponent(ticketId)
                + '&assigneeid=' + encodeURIComponent(newAssigneeId);

            var xhr = new XMLHttpRequest();
            xhr.open('GET', baseUrl + '?' + params, true);
            xhr.onload = function () {
                card.classList.remove('kanban-card-loading');
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        // Update card data
                        card.setAttribute('data-assignee', String(newAssigneeId));
                        pickerEl.setAttribute('data-current-assignee', String(newAssigneeId));

                        // Update avatar display
                        updatePickerAvatar(pickerEl, newAssigneeId);

                        // Update status if changed by assignUser()
                        if (typeof res.new_status !== 'undefined') {
                            var newSt = String(res.new_status);
                            var oldSt = card.getAttribute('data-status');
                            if (newSt !== oldSt) {
                                card.setAttribute('data-status', newSt);
                                updateTicketStatusCard(card, newSt);
                                // Move card to correct column
                                var targetBody = document.getElementById('kanban-body-' + newSt);
                                if (targetBody) {
                                    targetBody.appendChild(card);
                                    // Remove empty state from target
                                    var emptyEl = targetBody.querySelector('.kanban-empty-col');
                                    if (emptyEl) emptyEl.remove();
                                }
                                updateColumnCounts();
                            }
                        }

                        // Success flash
                        card.style.borderColor = '#22c55e';
                        card.style.boxShadow = '0 0 0 2px rgba(34,197,94,0.3)';
                        setTimeout(function () {
                            card.style.borderColor = '';
                            card.style.boxShadow = '';
                        }, 1500);
                    } else {
                        console.error('Assignee update failed:', res.error);
                        card.style.borderColor = '#ef4444';
                        card.style.boxShadow = '0 0 0 2px rgba(239,68,68,0.3)';
                        setTimeout(function () {
                            card.style.borderColor = '';
                            card.style.boxShadow = '';
                        }, 3000);
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', xhr.responseText);
                }
            };
            xhr.onerror = function () {
                card.classList.remove('kanban-card-loading');
                console.error('Network error during assignee update');
            };
            xhr.send();
        }

        /**
         * @description Update the avatar display in the picker after reassignment
         * @param {Element} pickerEl
         * @param {number} userId
         */
        function updatePickerAvatar(pickerEl, userId) {
            // Remove existing content except the caret
            var caret = pickerEl.querySelector('.tc-assignee-caret');
            pickerEl.textContent = '';

            if (userId > 0) {
                var user = null;
                for (var i = 0; i < users.length; i++) {
                    if (users[i].id === userId) { user = users[i]; break; }
                }
                if (user) {
                    if (user.photo) {
                        var img = document.createElement('img');
                        img.src = user.photo;
                        img.alt = user.initials;
                        img.className = 'tc-assignee-img';
                        pickerEl.appendChild(img);
                    } else {
                        pickerEl.appendChild(document.createTextNode(user.initials || '?'));
                    }
                } else {
                    pickerEl.appendChild(document.createTextNode('?'));
                }
            } else {
                pickerEl.appendChild(document.createTextNode('?'));
            }

            // Re-add caret
            if (caret) {
                pickerEl.appendChild(caret);
            } else {
                var newCaret = document.createElement('i');
                newCaret.className = 'fas fa-caret-down tc-assignee-caret';
                pickerEl.appendChild(newCaret);
            }
        }

        function closeDropdown() {
            if (activeDropdown) {
                activeDropdown.remove();
                activeDropdown = null;
            }
        }

        /**
         * @description Escape HTML characters
         * @param {string} str
         * @returns {string}
         */
        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    }

})();

