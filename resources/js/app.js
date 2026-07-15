import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import './attendance-face-service';
import './exposure';
import './s-curve-chart';

window.toggleSidebar = function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    if (!sidebar || !sidebarOverlay) {
        return;
    }

    if (window.innerWidth > 992) {
        const shouldExpand = !body.classList.contains('sidebar-is-expanded') && !sidebar.classList.contains('expanded');

        sidebar.classList.toggle('expanded', shouldExpand);
        body.classList.toggle('sidebar-is-expanded', shouldExpand);
        return;
    }

    sidebar.classList.toggle('expanded');
    sidebarOverlay.classList.toggle('active');
    body.style.overflow = sidebar.classList.contains('expanded') ? 'hidden' : '';
};

window.toggleNavDropdown = function (dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    const toggle = dropdown ? dropdown.querySelector('.nav-dropdown-toggle') : null;

    if (!dropdown || !toggle) {
        return;
    }

    dropdown.classList.toggle('open');
    toggle.setAttribute('aria-expanded', dropdown.classList.contains('open') ? 'true' : 'false');
};

const refreshModalScrollLock = () => {
    document.body.style.overflow = document.querySelector('.modal-overlay.active') ? 'hidden' : '';
};

window.openModal = function (modalId, titleText) {
    const modal = document.getElementById(modalId);
    const modalTitle = modal ? modal.querySelector('.modal-header h3') : null;

    if (!modal) {
        return;
    }

    if (titleText && modalTitle) {
        modalTitle.innerText = titleText;
    }

    modal.classList.add('active');
    modal.dispatchEvent(new CustomEvent('modal:opened'));
    refreshModalScrollLock();
};

window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);

    if (!modal) {
        return;
    }

    modal.classList.remove('active');
    refreshModalScrollLock();
};

const initializeTableControls = () => {
    const normalizedPath = window.location.pathname.replace(/\/+$/, '') || '/';

    if (normalizedPath === '/' || document.body.classList.contains('dashboard-index')) {
        return;
    }

    document.querySelectorAll('table.data-table').forEach((table, tableIndex) => {
        const tbody = table.tBodies[0];

        if (!tbody || table.dataset.tableControls === 'ready') {
            return;
        }

        table.dataset.tableControls = 'ready';

        const pageSize = 10;
        const columnCount = table.tHead?.rows[0]?.cells.length || table.rows[0]?.cells.length || 1;
        const tableWrap = table.closest('.table-responsive') || table;
        const host = tableWrap.parentElement;
        const state = {
            page: 1,
            query: '',
        };
        let rows = [];
        let placeholderRows = [];
        let noResultRow = null;
        let refreshFrame = null;

        const tableLabel = table.closest('.card, .profile-card, .exposure-detail-panel')?.querySelector('h2, h3, .card-title')?.textContent?.trim() || `Table ${tableIndex + 1}`;
        const controlId = `tableSearch${tableIndex + 1}`;

        const toolbar = document.createElement('div');
        toolbar.className = 'table-control-bar';
        toolbar.innerHTML = `
            <div class="table-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input id="${controlId}" class="form-control table-search-input" type="search" placeholder="Search " aria-label="Search ">
            </div>
            <div class="table-page-summary" aria-live="polite"></div>
        `;

        const pagination = document.createElement('div');
        pagination.className = 'table-pagination';

        host.insertBefore(toolbar, tableWrap);
        host.insertBefore(pagination, tableWrap.nextSibling);

        const searchInput = toolbar.querySelector('.table-search-input');
        const summary = toolbar.querySelector('.table-page-summary');

        const isPlaceholderRow = (row) => {
            const onlyCell = row.cells.length === 1 ? row.cells[0] : null;

            return Boolean(onlyCell && onlyCell.colSpan >= columnCount && onlyCell.classList.contains('center'));
        };

        const searchableText = (row) => row.textContent.replace(/\s+/g, ' ').trim().toLowerCase();

        const createNoResultRow = () => {
            const row = document.createElement('tr');
            const cell = document.createElement('td');

            cell.colSpan = columnCount;
            cell.className = 'center';
            cell.textContent = 'No matching data found.';
            row.dataset.tableGenerated = 'no-results';
            row.appendChild(cell);

            return row;
        };

        const visiblePageNumbers = (current, total) => {
            const windowSize = 5;
            let start = Math.max(1, current - Math.floor(windowSize / 2));
            const end = Math.min(total, start + windowSize - 1);

            start = Math.max(1, end - windowSize + 1);

            return Array.from({ length: end - start + 1 }, (_, index) => start + index);
        };

        const renderPagination = (totalPages) => {
            pagination.textContent = '';
            pagination.hidden = totalPages <= 1;

            if (totalPages <= 1) {
                return;
            }

            const makeButton = (label, page, iconClass = '') => {
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'table-page-button';
                button.disabled = page === state.page;
                button.setAttribute('aria-label', iconClass ? label : `Page ${label}`);

                if (iconClass) {
                    const icon = document.createElement('i');

                    icon.className = iconClass;
                    button.appendChild(icon);
                } else {
                    button.textContent = label;
                }

                button.addEventListener('click', () => {
                    state.page = page;
                    render();
                });

                return button;
            };

            pagination.appendChild(makeButton('Previous page', Math.max(1, state.page - 1), 'fa-solid fa-chevron-left'));

            visiblePageNumbers(state.page, totalPages).forEach((page) => {
                const button = makeButton(String(page), page);

                button.classList.toggle('active', page === state.page);
                pagination.appendChild(button);
            });

            pagination.appendChild(makeButton('Next page', Math.min(totalPages, state.page + 1), 'fa-solid fa-chevron-right'));
        };

        const render = () => {
            const filteredRows = rows.filter((row) => !state.query || searchableText(row).includes(state.query));
            const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
            const hasRows = rows.length > 0;

            state.page = Math.min(Math.max(state.page, 1), totalPages);

            const startIndex = (state.page - 1) * pageSize;
            const visibleRows = filteredRows.slice(startIndex, startIndex + pageSize);

            rows.forEach((row) => {
                row.hidden = true;
            });

            placeholderRows.forEach((row) => {
                row.hidden = hasRows;
            });

            if (!hasRows) {
                if (noResultRow) {
                    noResultRow.hidden = true;
                }

                toolbar.hidden = true;
                pagination.hidden = true;
                return;
            }

            toolbar.hidden = false;

            if (!noResultRow || !tbody.contains(noResultRow)) {
                noResultRow = createNoResultRow();
                tbody.appendChild(noResultRow);
            }

            noResultRow.hidden = filteredRows.length > 0;
            visibleRows.forEach((row) => {
                row.hidden = false;
            });

            const endIndex = Math.min(startIndex + visibleRows.length, filteredRows.length);
            const fromText = filteredRows.length ? startIndex + 1 : 0;
            const searchSuffix = state.query ? ` from ${rows.length}` : '';

            summary.textContent = `Showing ${fromText}-${endIndex} of ${filteredRows.length}${searchSuffix}`;
            renderPagination(totalPages);
        };

        const refreshRows = () => {
            if (refreshFrame) {
                window.cancelAnimationFrame(refreshFrame);
            }

            refreshFrame = window.requestAnimationFrame(() => {
                const allRows = Array.from(tbody.rows);

                placeholderRows = allRows.filter((row) => row.dataset.tableGenerated !== 'no-results' && isPlaceholderRow(row));
                rows = allRows.filter((row) => row.dataset.tableGenerated !== 'no-results' && !isPlaceholderRow(row));
                state.page = 1;
                render();
            });
        };

        searchInput.addEventListener('input', () => {
            state.query = searchInput.value.trim().toLowerCase();
            state.page = 1;
            render();
        });

        new MutationObserver(refreshRows).observe(tbody, { childList: true });
        refreshRows();
    });
};

const initializeLiveMultiselect = () => {
    document.querySelectorAll('select[data-live-multiselect]').forEach((select) => {
        if (select.dataset.liveMultiselectReady === 'ready') {
            return;
        }

        select.dataset.liveMultiselectReady = 'ready';
        select.classList.add('live-multi-select-native');

        const placeholder = select.dataset.placeholder || 'Search';
        const emptyText = select.dataset.emptyText || 'No intern found.';
        const touchInput = select.dataset.touchInput ? document.querySelector(select.dataset.touchInput) : null;
        const multiselect = document.createElement('div');
        const control = document.createElement('div');
        const tags = document.createElement('div');
        const searchInput = document.createElement('input');
        const dropdown = document.createElement('div');

        multiselect.className = 'live-multi-select';
        control.className = 'live-multi-select-control';
        tags.className = 'live-multi-select-tags';
        searchInput.className = 'live-multi-select-input';
        searchInput.type = 'search';
        searchInput.autocomplete = 'off';
        searchInput.placeholder = placeholder;
        dropdown.className = 'live-multi-select-dropdown';
        dropdown.hidden = true;

        control.appendChild(tags);
        control.appendChild(searchInput);
        multiselect.appendChild(control);
        multiselect.appendChild(dropdown);
        select.insertAdjacentElement('afterend', multiselect);

        const allOptions = () => Array.from(select.options).filter((option) => option.value !== '');
        const selectedOptions = () => allOptions().filter((option) => option.selected);
        const matchingOptions = () => {
            const query = searchInput.value.trim().toLowerCase();

            return allOptions().filter((option) => option.textContent.trim().toLowerCase().includes(query));
        };

        const dispatchChange = () => {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const markTouched = () => {
            if (touchInput) {
                touchInput.value = '1';
            }
        };

        const closeDropdown = () => {
            dropdown.hidden = true;
            multiselect.classList.remove('is-open');
        };

        const openDropdown = () => {
            dropdown.hidden = false;
            multiselect.classList.add('is-open');
            renderOptions();
        };

        const toggleOption = (option) => {
            markTouched();
            option.selected = !option.selected;
            searchInput.value = '';
            dispatchChange();
            render();
            openDropdown();
            searchInput.focus();
        };

        const removeOption = (option) => {
            markTouched();
            option.selected = false;
            dispatchChange();
            render();
        };

        const renderTags = () => {
            tags.textContent = '';

            selectedOptions().forEach((option) => {
                const tag = document.createElement('span');
                const label = document.createElement('span');
                const removeButton = document.createElement('button');

                tag.className = 'live-multi-select-tag';
                label.textContent = option.textContent.trim();
                removeButton.type = 'button';
                removeButton.className = 'live-multi-select-remove';
                removeButton.setAttribute('aria-label', `Remove ${label.textContent}`);
                removeButton.textContent = 'x';
                removeButton.addEventListener('click', (event) => {
                    event.stopPropagation();
                    removeOption(option);
                    searchInput.focus();
                });

                tag.appendChild(removeButton);
                tag.appendChild(label);
                tags.appendChild(tag);
            });

            searchInput.placeholder = selectedOptions().length ? 'Search' : placeholder;
        };

        const renderOptions = () => {
            const options = matchingOptions();

            dropdown.textContent = '';

            if (options.length === 0) {
                const emptyOption = document.createElement('div');

                emptyOption.className = 'live-multi-select-empty';
                emptyOption.textContent = emptyText;
                dropdown.appendChild(emptyOption);
                return;
            }

            options.forEach((option) => {
                const optionButton = document.createElement('button');

                optionButton.type = 'button';
                optionButton.className = 'live-multi-select-option';
                optionButton.classList.toggle('is-selected', option.selected);
                optionButton.textContent = option.textContent.trim();
                optionButton.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    toggleOption(option);
                });

                dropdown.appendChild(optionButton);
            });
        };

        const render = () => {
            renderTags();
            renderOptions();
        };

        control.addEventListener('click', () => {
            searchInput.focus();
            openDropdown();
        });

        searchInput.addEventListener('focus', openDropdown);
        searchInput.addEventListener('input', openDropdown);
        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && searchInput.value === '') {
                const lastSelectedOption = selectedOptions().at(-1);

                if (lastSelectedOption) {
                    event.preventDefault();
                    removeOption(lastSelectedOption);
                }
            }

            if (event.key === 'Enter') {
                const firstOption = matchingOptions()[0];

                if (firstOption) {
                    event.preventDefault();
                    toggleOption(firstOption);
                }
            }

            if (event.key === 'Escape') {
                closeDropdown();
            }
        });

        document.addEventListener('click', (event) => {
            if (!multiselect.contains(event.target)) {
                closeDropdown();
            }
        });

        render();
    });
};

const initializeInternExtendControls = (root = document) => {
    const internExtendButton = root.querySelector('#internExtendButton');
    const internExtendFields = root.querySelector('#internExtendFields');
    const internExtendNote = root.querySelector('#internExtendNote');
    let internExtendAddedThisEdit = false;

    if (!internExtendButton || !internExtendFields || internExtendButton.dataset.internExtendReady === 'ready') {
        return;
    }

    internExtendButton.dataset.internExtendReady = 'ready';

    const latestInternEndDateInput = () => {
        const extensionInputs = Array.from(root.querySelectorAll('.intern-extend-date'));

        return extensionInputs.at(-1) || root.querySelector('input[name="dtmEndDate"]');
    };

    const canExtendIntern = () => {
        const latestInput = latestInternEndDateInput();

        if (!latestInput || !latestInput.value) {
            return false;
        }

        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);

        const latestDate = new Date(`${latestInput.value}T00:00:00`);
        const diffDays = Math.ceil((latestDate - todayDate) / 86400000);

        return diffDays <= 14;
    };

    const refreshInternExtendButton = () => {
        if (internExtendAddedThisEdit) {
            internExtendButton.hidden = true;

            if (internExtendNote) {
                internExtendNote.textContent = 'Save changes dulu untuk menambahkan extend berikutnya.';
            }

            return;
        }

        internExtendButton.hidden = false;
        internExtendButton.disabled = !canExtendIntern();

        if (internExtendNote) {
            internExtendNote.textContent = internExtendButton.disabled
                ? 'Extend can be added when the latest end date is within 14 days.'
                : 'Latest end date is eligible for extension.';
        }
    };

    const bindInternExtendInputs = () => {
        root.querySelectorAll('.intern-extend-date, input[name="dtmEndDate"]').forEach((input) => {
            input.removeEventListener('change', refreshInternExtendButton);
            input.addEventListener('change', refreshInternExtendButton);
        });
    };

    bindInternExtendInputs();
    refreshInternExtendButton();

    internExtendButton.addEventListener('click', () => {
        if (!canExtendIntern()) {
            return;
        }

        const nextIndex = internExtendFields.querySelectorAll('.intern-extend-field').length + 1;
        const field = document.createElement('div');

        field.className = 'intern-extend-field';
        field.innerHTML = `
            <label class="form-label">Extend ${nextIndex} End Date</label>
            <input class="form-control intern-extend-date" type="date" name="txtInternExtendEndDates[]">
        `;
        internExtendFields.appendChild(field);
        field.querySelector('input')?.focus();
        bindInternExtendInputs();
        internExtendAddedThisEdit = true;
        refreshInternExtendButton();
    });
};

const initializeProjectStageControls = (root = document) => {
    const addProjectStageButton = root.querySelector('#addProjectStageButton');
    const projectStageList = root.querySelector('#projectStageList');
    const projectStageTotal = root.querySelector('#projectStageTotal');
    const projectStageWarning = root.querySelector('#projectStageWarning');

    if (!addProjectStageButton || !projectStageList || addProjectStageButton.dataset.projectStageReady === 'ready') {
        return;
    }

    addProjectStageButton.dataset.projectStageReady = 'ready';

    const refreshProjectStageNumbers = () => {
        projectStageList.querySelectorAll('.project-stage-row').forEach((row, index) => {
            const number = index + 1;
            const label = row.querySelector('.project-stage-number');
            const stepInput = row.querySelector('.project-stage-step');
            const startInput = row.querySelector('.project-stage-start');
            const endInput = row.querySelector('.project-stage-end');
            const planInput = row.querySelector('.project-stage-plan');
            const actualInput = row.querySelector('.project-stage-actual');

            if (label) {
                label.textContent = `Tahap ${number}`;
            }

            if (stepInput) {
                stepInput.name = `stages[${index}][txtProjectStageStep]`;
            }

            if (startInput) {
                startInput.name = `stages[${index}][dtmProjectStageStartDate]`;
            }

            if (endInput) {
                endInput.name = `stages[${index}][dtmProjectStageEndDate]`;
            }

            if (planInput) {
                planInput.name = `stages[${index}][floatProjectStagePlan]`;
            }

            if (actualInput) {
                actualInput.name = `stages[${index}][floatProjectStageActual]`;
            }
        });
    };

    const refreshProjectStageTotal = () => {
        if (!projectStageTotal) {
            return;
        }

        const total = Array.from(projectStageList.querySelectorAll('.project-stage-plan'))
            .reduce((sum, input) => sum + Number(input.value || 0), 0);
        const roundedTotal = Math.round(total * 100) / 100;
        const overLimit = roundedTotal > 100;
        const isComplete = Math.abs(roundedTotal - 100) < 0.001;

        projectStageTotal.classList.toggle('is-valid', isComplete);
        projectStageTotal.classList.toggle('is-invalid', overLimit || (roundedTotal > 0 && roundedTotal < 100));
        projectStageTotal.textContent = overLimit
            ? `Total Plan: ${roundedTotal}% - melebihi 100%`
            : `Total Plan: ${roundedTotal}%`;

        projectStageList.querySelectorAll('.project-stage-plan').forEach((input) => {
            input.classList.toggle('is-invalid', overLimit);
        });

        let actualOverPlanCount = 0;

        projectStageList.querySelectorAll('.project-stage-row').forEach((row) => {
            const planInput = row.querySelector('.project-stage-plan');
            const actualInput = row.querySelector('.project-stage-actual');
            const actualOverPlan = Number(actualInput?.value || 0) > Number(planInput?.value || 0);

            if (actualOverPlan) {
                actualOverPlanCount += 1;
            }

            actualInput?.classList.toggle('is-invalid', actualOverPlan);
        });

        if (projectStageWarning) {
            projectStageWarning.hidden = !overLimit && actualOverPlanCount === 0;
            projectStageWarning.textContent = overLimit
                ? `Warning: total plan tahap sudah ${roundedTotal}%, kurangi ${Math.round((roundedTotal - 100) * 100) / 100}%.`
                : actualOverPlanCount > 0
                    ? 'Warning: actual tahap tidak boleh lebih besar dari plan.'
                    : '';
        }
    };

    const projectStageRows = () => Array.from(projectStageList.querySelectorAll('.project-stage-row'));
    const requiredStageInputs = (row) => [
        row.querySelector('.project-stage-step'),
        row.querySelector('.project-stage-start'),
        row.querySelector('.project-stage-end'),
        row.querySelector('.project-stage-plan'),
    ];
    const hasMeaningfulStageData = (row) => requiredStageInputs(row).some((input) => String(input?.value || '').trim() !== '');
    const stageRowIsComplete = (row) => requiredStageInputs(row).every((input) => String(input?.value || '').trim() !== '');

    const showProjectStageWarning = (text, focusTarget = addProjectStageButton) => {
        if (projectStageWarning) {
            projectStageWarning.hidden = false;
            projectStageWarning.textContent = text;
        }

        focusTarget?.focus();
    };

    const bindProjectStageRows = () => {
        projectStageList.querySelectorAll('.project-stage-plan, .project-stage-actual').forEach((input) => {
            input.removeEventListener('input', refreshProjectStageTotal);
            input.addEventListener('input', refreshProjectStageTotal);
        });

        projectStageList.querySelectorAll('.project-stage-remove').forEach((button) => {
            if (button.dataset.projectStageRemoveReady === 'ready') {
                return;
            }

            button.dataset.projectStageRemoveReady = 'ready';
            button.addEventListener('click', () => {
                button.closest('.project-stage-row')?.remove();
                refreshProjectStageNumbers();
                refreshProjectStageTotal();
            });
        });
    };

    const form = projectStageList.closest('form');

    if (form && form.dataset.projectStageSubmitReady !== 'ready') {
        form.dataset.projectStageSubmitReady = 'ready';
        form.addEventListener('submit', (event) => {
            const rows = projectStageRows();
            const meaningfulRows = rows.filter(hasMeaningfulStageData);

            if (meaningfulRows.length === 0) {
                event.preventDefault();
                showProjectStageWarning('Isi tahap project terlebih dahulu.');
                return;
            }

            const incompleteRow = meaningfulRows.find((row) => !stageRowIsComplete(row));

            if (incompleteRow) {
                event.preventDefault();
                const emptyInput = requiredStageInputs(incompleteRow).find((input) => String(input?.value || '').trim() === '');

                showProjectStageWarning('Setiap tahap yang diisi harus memiliki step, start date, end date, dan plan.', emptyInput);
                return;
            }

            const totalPlan = meaningfulRows.reduce((sum, row) => {
                const planInput = row.querySelector('.project-stage-plan');

                return sum + Number(planInput?.value || 0);
            }, 0);

            if (Math.abs(totalPlan - 100) >= 0.001) {
                event.preventDefault();
                refreshProjectStageTotal();
                showProjectStageWarning('Total plan tahap project harus tepat 100%.', projectStageTotal || addProjectStageButton);
                return;
            }

            const invalidActualRow = meaningfulRows.find((row) => {
                const planInput = row.querySelector('.project-stage-plan');
                const actualInput = row.querySelector('.project-stage-actual');

                return Number(actualInput?.value || 0) > Number(planInput?.value || 0);
            });

            if (invalidActualRow) {
                event.preventDefault();
                const actualInput = invalidActualRow.querySelector('.project-stage-actual');

                showProjectStageWarning('Actual tahap project tidak boleh lebih besar dari plan.', actualInput);
            }
        });
    }

    bindProjectStageRows();
    refreshProjectStageNumbers();
    refreshProjectStageTotal();

    addProjectStageButton.addEventListener('click', () => {
        const index = projectStageList.querySelectorAll('.project-stage-row').length;
        const row = document.createElement('div');

        row.className = 'project-stage-row';
        row.innerHTML = `
            <div class="project-stage-number">Tahap ${index + 1}</div>
            <div>
                <label class="form-label">Step</label>
                <input class="form-control project-stage-step" name="stages[${index}][txtProjectStageStep]">
            </div>
            <div>
                <label class="form-label">Start</label>
                <input class="form-control project-stage-start" type="date" name="stages[${index}][dtmProjectStageStartDate]">
            </div>
            <div>
                <label class="form-label">End</label>
                <input class="form-control project-stage-end" type="date" name="stages[${index}][dtmProjectStageEndDate]">
            </div>
            <div>
                <label class="form-label">Plan (%)</label>
                <input class="form-control project-stage-plan" type="number" min="0" max="100" step="0.01" name="stages[${index}][floatProjectStagePlan]">
            </div>
            <div>
                <label class="form-label">Actual (%)</label>
                <input class="form-control project-stage-actual" type="number" min="0" max="100" step="0.01" name="stages[${index}][floatProjectStageActual]" value="0">
            </div>
            <button class="btn-icon btn-delete project-stage-remove" type="button" title="Remove stage"><i class="fa-solid fa-trash"></i></button>
        `;
        projectStageList.appendChild(row);
        bindProjectStageRows();
        refreshProjectStageNumbers();
        refreshProjectStageTotal();
        row.querySelector('.project-stage-step')?.focus();
    });
};

const initializeIconChoiceControls = (root = document) => {
    root.querySelectorAll('.icon-choice input[type="radio"]').forEach((input) => {
        if (input.dataset.iconChoiceReady === 'ready') {
            return;
        }

        input.dataset.iconChoiceReady = 'ready';
        input.addEventListener('change', () => {
            const scope = input.closest('form') || root;

            scope.querySelectorAll(`.icon-choice input[name="${input.name}"]`).forEach((radio) => {
                radio.closest('.icon-choice')?.classList.toggle('selected', radio.checked);
            });
        });
    });
};

const initializeCrudModalControls = (root = document) => {
    initializeLiveMultiselect();
    initializeInternExtendControls(root);
    initializeProjectStageControls(root);
    initializeIconChoiceControls(root);
};

const crudModalRoutePatterns = [
    /^\/projects\/create$/,
    /^\/projects\/\d+\/edit$/,
    /^\/interns\/create$/,
    /^\/interns\/\d+\/edit$/,
    /^\/mentors\/create$/,
    /^\/mentors\/\d+\/edit$/,
    /^\/hrds\/create$/,
    /^\/hrds\/\d+\/edit$/,
    /^\/skill-sets\/create$/,
    /^\/skill-sets\/\d+\/edit$/,
    /^\/project-handles\/create$/,
    /^\/project-handles\/\d+\/edit$/,
    /^\/calendar-sharing\/create$/,
    /^\/calendar-sharing\/\d+\/edit$/,
    /^\/analytics\/create$/,
    /^\/analytics\/\d+\/edit$/,
    /^\/achievements\/create$/,
    /^\/achievements\/\d+\/edit$/,
];

const shouldLoadCrudModal = (link) => {
    if (!link.href || link.target || link.hasAttribute('download')) {
        return false;
    }

    const url = new URL(link.href, window.location.href);

    return url.origin === window.location.origin
        && crudModalRoutePatterns.some((pattern) => pattern.test(url.pathname.replace(/\/+$/, '')));
};

const removeRemoteModal = (modal) => {
    modal?.remove();
    refreshModalScrollLock();
};

const loadCrudModal = async (url, trigger = null) => {
    const previousHtml = trigger?.innerHTML;

    try {
        trigger?.setAttribute('aria-busy', 'true');
        trigger?.classList.add('is-loading');

        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html',
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to load modal: ${response.status}`);
        }

        const html = await response.text();
        const documentFromResponse = new DOMParser().parseFromString(html, 'text/html');
        const modal = documentFromResponse.querySelector('.crud-modal-overlay.active, .crud-modal-overlay');

        if (!modal) {
            window.location.href = url;
            return;
        }

        const existingModal = document.getElementById(modal.id);

        if (existingModal) {
            existingModal.remove();
        }

        modal.dataset.remoteModal = 'true';
        document.body.appendChild(modal);
        initializeCrudModalControls(modal);
        window.openModal(modal.id);
        modal.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus();
    } catch (error) {
        window.location.href = url;
    } finally {
        if (trigger) {
            trigger.removeAttribute('aria-busy');
            trigger.classList.remove('is-loading');

            if (previousHtml !== undefined) {
                trigger.innerHTML = previousHtml;
            }
        }
    }
};

const initializeAuthPage = () => {
    const authPage = document.querySelector('[data-auth-page]');

    if (!authPage) {
        return;
    }

    const tabButtons = Array.from(authPage.querySelectorAll('[data-auth-tab]'));
    const previewCards = Array.from(authPage.querySelectorAll('[data-auth-card]'));
    const cycleButton = authPage.querySelector('[data-auth-cycle]');
    const passwordToggle = authPage.querySelector('[data-password-toggle]');
    const passwordInput = authPage.querySelector('#txtPassword');
    const authForm = authPage.querySelector('[data-auth-form]');
    const submitButton = authPage.querySelector('[data-auth-submit]');
    const skillTiles = Array.from(authPage.querySelectorAll('.auth-skill-tile'));
    const tabOrder = ['progress', 'performer', 'sessions'];
    let activeIndex = 0;

    const setActiveTab = (tabName) => {
        activeIndex = Math.max(0, tabOrder.indexOf(tabName));

        tabButtons.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.authTab === tabName);
        });

        previewCards.forEach((card) => {
            card.classList.toggle('is-focused', card.dataset.authCard === tabName);
        });
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setActiveTab(button.dataset.authTab);
        });
    });

    if (cycleButton) {
        cycleButton.addEventListener('click', () => {
            activeIndex = (activeIndex + 1) % tabOrder.length;
            setActiveTab(tabOrder[activeIndex]);
        });
    }

    skillTiles.forEach((tile) => {
        tile.addEventListener('click', () => {
            skillTiles.forEach((item) => item.classList.toggle('is-active', item === tile));
        });
    });

    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', () => {
            const shouldShow = passwordInput.type === 'password';
            const icon = passwordToggle.querySelector('i');

            passwordInput.type = shouldShow ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');

            if (icon) {
                icon.classList.toggle('fa-eye', !shouldShow);
                icon.classList.toggle('fa-eye-slash', shouldShow);
            }
        });
    }

    if (authForm && submitButton) {
        authForm.addEventListener('submit', () => {
            submitButton.classList.add('is-loading');
            submitButton.querySelector('span').textContent = 'Logging in...';
        });
    }
};

const initializeAttendancePage = () => {
    const page = document.querySelector('[data-attendance-page]');

    if (!page) {
        return;
    }

    if (page.dataset.attendanceMode === 'python') {
        return;
    }

    const video = page.querySelector('[data-attendance-video]');
    const canvas = page.querySelector('[data-attendance-canvas]');
    const cameraButton = page.querySelector('[data-attendance-camera]');
    const message = page.querySelector('[data-attendance-message]');
    const messageText = message?.querySelector('span');
    const progressBar = page.querySelector('[data-attendance-progress]');
    const enrollForm = page.querySelector('[data-face-enrollment-form]');
    const enrollButton = page.querySelector('[data-face-enroll]');
    const enrollmentDescriptorInput = page.querySelector('[data-face-enrollment-descriptor]');
    const enrollmentSampleInput = page.querySelector('[data-face-enrollment-sample-count]');
    const enrollmentQualityInput = page.querySelector('[data-face-enrollment-quality]');
    const attendanceForm = page.querySelector('[data-attendance-form]');
    const attendanceButton = page.querySelector('[data-attendance-submit]');
    const capturedDescriptorInput = page.querySelector('[data-attendance-captured-descriptor]');
    const latitudeInput = page.querySelector('[data-attendance-latitude]');
    const longitudeInput = page.querySelector('[data-attendance-longitude]');
    const accuracyInput = page.querySelector('[data-attendance-accuracy]');
    const deviceInput = page.querySelector('[data-attendance-device]');
    const descriptorScript = document.getElementById('attendanceEnrollmentDescriptor');
    const faceThreshold = Number(page.dataset.faceThreshold || 0.38);
    const descriptorSize = 12;
    let stream = null;
    let detector = null;
    let useManualFaceFrame = false;
    let enrolledDescriptor = [];

    try {
        enrolledDescriptor = JSON.parse(descriptorScript?.textContent || '[]');
    } catch (error) {
        enrolledDescriptor = [];
    }

    const wait = (duration) => new Promise((resolve) => {
        window.setTimeout(resolve, duration);
    });

    const setMessage = (text, isError = false) => {
        if (messageText) {
            messageText.textContent = text;
        }

        message?.classList.toggle('is-error', isError);
    };

    const setProgress = (value) => {
        if (!progressBar) {
            return;
        }

        const safeValue = Math.max(0, Math.min(1, value));

        progressBar.style.width = `${safeValue * 100}%`;
    };

    const setBusy = (button, busy, busyText = 'Memproses...') => {
        if (!button) {
            return;
        }

        const label = button.querySelector('span');

        if (!button.dataset.originalText && label) {
            button.dataset.originalText = label.textContent;
        }

        button.disabled = busy;

        if (label) {
            label.textContent = busy ? busyText : button.dataset.originalText;
        }
    };

    const ensureFaceDetector = () => {
        if (!('FaceDetector' in window)) {
            useManualFaceFrame = true;
            return null;
        }

        if (!detector) {
            try {
                detector = new window.FaceDetector({
                    fastMode: true,
                    maxDetectedFaces: 1,
                });
            } catch (error) {
                useManualFaceFrame = true;
                return null;
            }
        }

        return detector;
    };

    const manualFaceBox = () => {
        const videoWidth = video.videoWidth || 480;
        const videoHeight = video.videoHeight || 360;
        const width = videoWidth * 0.48;
        const height = videoHeight * 0.58;

        return {
            x: (videoWidth - width) / 2,
            y: (videoHeight - height) / 2,
            width,
            height,
        };
    };

    const ensureVideoReady = () => new Promise((resolve) => {
        if (video.readyState >= 2 && video.videoWidth > 0) {
            resolve();
            return;
        }

        video.addEventListener('loadedmetadata', resolve, { once: true });
    });

    const startCamera = async () => {
        ensureFaceDetector();

        if (stream) {
            await ensureVideoReady();
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Kamera tidak tersedia di browser ini.');
        }

        stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: 'user',
                width: { ideal: 720 },
                height: { ideal: 540 },
            },
        });

        video.srcObject = stream;
        await video.play();
        await ensureVideoReady();
        setMessage(useManualFaceFrame ? 'Kamera aktif. Posisikan wajah di area oval.' : 'Kamera aktif.');
    };

    const detectFace = async () => {
        const faceDetector = ensureFaceDetector();

        if (useManualFaceFrame || !faceDetector) {
            return manualFaceBox();
        }

        let faces = [];

        try {
            faces = await faceDetector.detect(video);
        } catch (error) {
            useManualFaceFrame = true;
            setMessage('Deteksi wajah native tidak tersedia, memakai scan area wajah manual.');
            return manualFaceBox();
        }

        if (faces.length === 0) {
            throw new Error('Wajah belum terdeteksi.');
        }

        if (faces.length > 1) {
            throw new Error('Hanya satu wajah yang boleh berada di kamera.');
        }

        const box = faces[0].boundingBox || faces[0];
        const x = Number(box.x ?? box.left ?? 0);
        const y = Number(box.y ?? box.top ?? 0);
        const width = Number(box.width ?? 0);
        const height = Number(box.height ?? 0);

        if (!width || !height) {
            throw new Error('Area wajah tidak terbaca.');
        }

        return { x, y, width, height };
    };

    const cropFromFace = (faceBox) => {
        const videoWidth = video.videoWidth;
        const videoHeight = video.videoHeight;
        const side = Math.max(faceBox.width, faceBox.height) * 1.35;
        const centerX = faceBox.x + (faceBox.width / 2);
        const centerY = faceBox.y + (faceBox.height / 2);
        const cropWidth = Math.min(side, videoWidth);
        const cropHeight = Math.min(side, videoHeight);
        const x = Math.max(0, Math.min(videoWidth - cropWidth, centerX - (cropWidth / 2)));
        const y = Math.max(0, Math.min(videoHeight - cropHeight, centerY - (cropHeight / 2)));

        return { x, y, width: cropWidth, height: cropHeight };
    };

    const descriptorFromFace = (faceBox) => {
        const context = canvas.getContext('2d', { willReadFrequently: true });
        const crop = cropFromFace(faceBox);

        canvas.width = descriptorSize;
        canvas.height = descriptorSize;
        context.clearRect(0, 0, descriptorSize, descriptorSize);
        context.drawImage(
            video,
            crop.x,
            crop.y,
            crop.width,
            crop.height,
            0,
            0,
            descriptorSize,
            descriptorSize,
        );

        const pixels = context.getImageData(0, 0, descriptorSize, descriptorSize).data;
        const gray = [];

        for (let index = 0; index < pixels.length; index += 4) {
            gray.push(((pixels[index] * 0.299) + (pixels[index + 1] * 0.587) + (pixels[index + 2] * 0.114)) / 255);
        }

        const mean = gray.reduce((sum, value) => sum + value, 0) / gray.length;
        const variance = gray.reduce((sum, value) => sum + ((value - mean) ** 2), 0) / gray.length;
        const standardDeviation = Math.sqrt(variance) || 1;
        const descriptor = gray.map((value) => {
            const normalized = (value - mean) / standardDeviation;

            return Number(Math.max(-2.5, Math.min(2.5, normalized)).toFixed(4));
        });
        const areaRatio = (faceBox.width * faceBox.height) / (video.videoWidth * video.videoHeight);
        const centerX = faceBox.x + (faceBox.width / 2);
        const centerY = faceBox.y + (faceBox.height / 2);
        const distanceFromCenter = Math.hypot(
            (centerX - (video.videoWidth / 2)) / video.videoWidth,
            (centerY - (video.videoHeight / 2)) / video.videoHeight,
        );
        const areaScore = Math.min(1, areaRatio * 11);
        const centerScore = Math.max(0, 1 - (distanceFromCenter * 2.2));
        const contrastScore = Math.min(1, standardDeviation * 8);
        const brightnessScore = mean > 0.18 && mean < 0.86 ? 1 : 0.45;
        const quality = Math.max(0, Math.min(1, (areaScore * 0.34) + (centerScore * 0.34) + (contrastScore * 0.22) + (brightnessScore * 0.1)));

        return { descriptor, quality };
    };

    const averageDescriptors = (samples) => samples[0].map((_, descriptorIndex) => {
        const average = samples.reduce((sum, descriptor) => sum + descriptor[descriptorIndex], 0) / samples.length;

        return Number(average.toFixed(4));
    });

    const descriptorDistance = (first, second) => {
        if (!Array.isArray(first) || !Array.isArray(second) || first.length !== second.length || first.length === 0) {
            return Number.POSITIVE_INFINITY;
        }

        const sum = first.reduce((total, value, index) => total + ((value - second[index]) ** 2), 0);

        return Math.sqrt(sum / first.length);
    };

    const captureDescriptor = async (sampleCount) => {
        await startCamera();
        setProgress(0);

        const samples = [];
        let qualityTotal = 0;
        let lastError = '';
        let attempts = 0;
        const maxAttempts = sampleCount * 10;

        while (samples.length < sampleCount && attempts < maxAttempts) {
            attempts += 1;
            await wait(170);

            try {
                const faceBox = await detectFace();
                const sample = descriptorFromFace(faceBox);

                if (sample.quality < 0.22) {
                    lastError = 'Wajah belum stabil di area kamera.';
                    setMessage(lastError);
                    continue;
                }

                samples.push(sample.descriptor);
                qualityTotal += sample.quality;
                setProgress(samples.length / sampleCount);
                setMessage(`Sampel wajah ${samples.length}/${sampleCount}`);
            } catch (error) {
                lastError = error.message;
                setMessage(lastError);
            }
        }

        if (samples.length < sampleCount) {
            throw new Error(lastError || 'Wajah belum berhasil dipindai.');
        }

        return {
            descriptor: averageDescriptors(samples),
            quality: qualityTotal / samples.length,
        };
    };

    const locationErrorMessage = (error, permissionState = '') => {
        const suffix = permissionState ? ` Status browser: ${permissionState}.` : '';

        if (error?.code === 1) {
            return `Lokasi ditolak oleh browser atau sistem operasi.${suffix} Pastikan site permission dan Location Services perangkat aktif.`;
        }

        if (error?.code === 2) {
            return `Lokasi belum bisa ditemukan.${suffix} Aktifkan GPS/Wi-Fi lalu coba lagi.`;
        }

        if (error?.code === 3) {
            return `Pengambilan lokasi timeout.${suffix} Coba ulang di area dengan sinyal lokasi lebih stabil.`;
        }

        return error?.message || 'Lokasi belum bisa dibaca.';
    };

    const getLocationPermissionState = async () => {
        if (!navigator.permissions?.query) {
            return '';
        }

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });

            return result.state || '';
        } catch (error) {
            return '';
        }
    };

    const getLocation = async () => {
        if (!navigator.geolocation) {
            throw new Error('Geolocation tidak tersedia di browser ini.');
        }

        const permissionState = await getLocationPermissionState();

        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, (error) => {
                error.readableMessage = locationErrorMessage(error, permissionState);
                reject(error);
            }, {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0,
            });
        });
    };

    cameraButton?.addEventListener('click', async () => {
        setBusy(cameraButton, true, '');

        try {
            await startCamera();
            setMessage(useManualFaceFrame ? 'Kamera aktif. Posisikan wajah di area oval.' : 'Kamera aktif.');
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            setBusy(cameraButton, false);
        }
    });

    enrollButton?.addEventListener('click', async () => {
        setBusy(enrollButton, true, 'Memindai...');

        try {
            const capture = await captureDescriptor(5);

            enrollmentDescriptorInput.value = JSON.stringify(capture.descriptor);
            enrollmentSampleInput.value = '5';
            enrollmentQualityInput.value = capture.quality.toFixed(4);
            setMessage('Face ID siap disimpan.');
            enrollForm.requestSubmit();
        } catch (error) {
            setMessage(error.message, true);
            setProgress(0);
            setBusy(enrollButton, false);
        }
    });

    attendanceButton?.addEventListener('click', async () => {
        if (attendanceButton.disabled) {
            setMessage(attendanceButton.dataset.disabledReason || 'Absensi belum bisa dilakukan.', true);
            return;
        }

        setBusy(attendanceButton, true, 'Memproses...');

        try {
            setMessage('Meminta lokasi...');
            const position = await getLocation();

            latitudeInput.value = position.coords.latitude;
            longitudeInput.value = position.coords.longitude;
            accuracyInput.value = position.coords.accuracy || '';
            deviceInput.value = `${navigator.platform || 'Device'} | ${navigator.userAgent || 'Browser'}`.slice(0, 500);
            setMessage('Memindai wajah...');

            const capture = await captureDescriptor(3);
            const distance = descriptorDistance(capture.descriptor, enrolledDescriptor);

            if (distance > faceThreshold) {
                throw new Error('Wajah tidak cocok dengan Face ID terdaftar.');
            }

            capturedDescriptorInput.value = JSON.stringify(capture.descriptor);
            setMessage('Absensi siap dikirim.');
            attendanceForm.requestSubmit();
        } catch (error) {
            const readableMessage = error.readableMessage || error.message;

            setMessage(readableMessage, true);
            setProgress(0);
            setBusy(attendanceButton, false);
        }
    });

    if (!('FaceDetector' in window)) {
        useManualFaceFrame = true;
        setMessage('FaceDetector native belum tersedia, memakai scan area wajah manual.');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeIcon = themeToggleBtn ? themeToggleBtn.querySelector('i') : null;
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    const internExtendButton = document.getElementById('internExtendButton');
    const internExtendFields = document.getElementById('internExtendFields');
    const internExtendNote = document.getElementById('internExtendNote');
    const addProjectStageButton = document.getElementById('addProjectStageButton');
    const projectStageList = document.getElementById('projectStageList');
    const projectStageTotal = document.getElementById('projectStageTotal');
    const projectStageWarning = document.getElementById('projectStageWarning');
    let internExtendAddedThisEdit = false;

    if (localStorage.getItem('theme') === 'dark' && themeIcon) {
        document.body.classList.add('dark-mode');
        themeIcon.classList.replace('fa-moon', 'fa-sun');
    }

    if (themeToggleBtn && themeIcon) {
        themeToggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');

            if (document.body.classList.contains('dark-mode')) {
                themeIcon.classList.replace('fa-moon', 'fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                themeIcon.classList.replace('fa-sun', 'fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });
    }

    if (profileTrigger && profileDropdown) {
        profileTrigger.addEventListener('click', (event) => {
            event.stopPropagation();
            profileDropdown.classList.toggle('active');
            profileTrigger.setAttribute('aria-expanded', profileDropdown.classList.contains('active'));
        });

        document.addEventListener('click', (event) => {
            if (!profileDropdown.contains(event.target) && !profileTrigger.contains(event.target)) {
                profileDropdown.classList.remove('active');
                profileTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('click', (event) => {
        const dismissButton = event.target.closest('[data-modal-dismiss]');

        if (dismissButton) {
            window.closeModal(dismissButton.dataset.modalDismiss);
            return;
        }

        const remoteCancel = event.target.closest('.modal-overlay[data-remote-modal="true"] .btn-close[href], .modal-overlay[data-remote-modal="true"] .btn-cancel[href]');

        if (remoteCancel) {
            event.preventDefault();
            removeRemoteModal(remoteCancel.closest('.modal-overlay'));
            return;
        }

        if (event.target.classList.contains('modal-overlay')) {
            const modal = event.target;

            if (modal.dataset.remoteModal === 'true') {
                removeRemoteModal(modal);
                return;
            }

            if (modal.dataset.closeUrl) {
                window.location.href = modal.dataset.closeUrl;
                return;
            }

            window.closeModal(modal.id);
            return;
        }

        const deleteButton = event.target.closest('[data-delete-modal-trigger]');

        if (deleteButton) {
            const form = document.getElementById('deleteConfirmForm');
            const title = document.getElementById('deleteConfirmTitle');
            const message = document.getElementById('deleteConfirmMessage');
            const submit = document.getElementById('deleteConfirmSubmit');

            if (!form) {
                return;
            }

            form.action = deleteButton.dataset.deleteAction || '';

            if (title && deleteButton.dataset.deleteTitle) {
                title.textContent = deleteButton.dataset.deleteTitle;
            }

            if (message && deleteButton.dataset.deleteMessage) {
                message.textContent = deleteButton.dataset.deleteMessage;
            }

            if (submit && deleteButton.dataset.deleteSubmit) {
                submit.textContent = deleteButton.dataset.deleteSubmit;
            }

            window.openModal('deleteConfirmModal');
            return;
        }

        const modalLink = event.target.closest('a');

        if (modalLink && shouldLoadCrudModal(modalLink)) {
            event.preventDefault();
            loadCrudModal(modalLink.href, modalLink);
        }
    });

    refreshModalScrollLock();

    initializeLiveMultiselect();

    const latestInternEndDateInput = () => {
        const extensionInputs = Array.from(document.querySelectorAll('.intern-extend-date'));

        return extensionInputs.at(-1) || document.querySelector('input[name="dtmEndDate"]');
    };

    const canExtendIntern = () => {
        const latestInput = latestInternEndDateInput();

        if (!latestInput || !latestInput.value) {
            return false;
        }

        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);

        const latestDate = new Date(`${latestInput.value}T00:00:00`);
        const diffDays = Math.ceil((latestDate - todayDate) / 86400000);

        return diffDays <= 14;
    };

    const refreshInternExtendButton = () => {
        if (!internExtendButton) {
            return;
        }

        if (internExtendAddedThisEdit) {
            internExtendButton.hidden = true;

            if (internExtendNote) {
                internExtendNote.textContent = 'Save changes dulu untuk menambahkan extend berikutnya.';
            }

            return;
        }

        internExtendButton.hidden = false;
        internExtendButton.disabled = !canExtendIntern();

        if (internExtendNote) {
            internExtendNote.textContent = internExtendButton.disabled
                ? 'Extend can be added when the latest end date is within 14 days.'
                : 'Latest end date is eligible for extension.';
        }
    };

    const bindInternExtendInputs = () => {
        document.querySelectorAll('.intern-extend-date, input[name="dtmEndDate"]').forEach((input) => {
            input.removeEventListener('change', refreshInternExtendButton);
            input.addEventListener('change', refreshInternExtendButton);
        });
    };

    if (internExtendButton && internExtendFields) {
        bindInternExtendInputs();
        refreshInternExtendButton();

        internExtendButton.addEventListener('click', () => {
            if (!canExtendIntern()) {
                return;
            }

            const nextIndex = internExtendFields.querySelectorAll('.intern-extend-field').length + 1;
            const field = document.createElement('div');

            field.className = 'intern-extend-field';
            field.innerHTML = `
                <label class="form-label">Extend ${nextIndex} End Date</label>
                <input class="form-control intern-extend-date" type="date" name="txtInternExtendEndDates[]">
            `;
            internExtendFields.appendChild(field);
            field.querySelector('input')?.focus();
            bindInternExtendInputs();
            internExtendAddedThisEdit = true;
            refreshInternExtendButton();
        });
    }

    const refreshProjectStageNumbers = () => {
        if (!projectStageList) {
            return;
        }

        projectStageList.querySelectorAll('.project-stage-row').forEach((row, index) => {
            const number = index + 1;
            const label = row.querySelector('.project-stage-number');
            const stepInput = row.querySelector('.project-stage-step');
            const startInput = row.querySelector('.project-stage-start');
            const endInput = row.querySelector('.project-stage-end');
            const planInput = row.querySelector('.project-stage-plan');
            const actualInput = row.querySelector('.project-stage-actual');

            if (label) {
                label.textContent = `Tahap ${number}`;
            }

            if (stepInput) {
                stepInput.name = `stages[${index}][txtProjectStageStep]`;
            }

            if (startInput) {
                startInput.name = `stages[${index}][dtmProjectStageStartDate]`;
            }

            if (endInput) {
                endInput.name = `stages[${index}][dtmProjectStageEndDate]`;
            }

            if (planInput) {
                planInput.name = `stages[${index}][floatProjectStagePlan]`;
            }

            if (actualInput) {
                actualInput.name = `stages[${index}][floatProjectStageActual]`;
            }
        });
    };

    const refreshProjectStageTotal = () => {
        if (!projectStageList || !projectStageTotal) {
            return;
        }

        const total = Array.from(projectStageList.querySelectorAll('.project-stage-plan'))
            .reduce((sum, input) => sum + Number(input.value || 0), 0);
        const roundedTotal = Math.round(total * 100) / 100;
        const overLimit = roundedTotal > 100;
        const isComplete = Math.abs(roundedTotal - 100) < 0.001;

        projectStageTotal.classList.toggle('is-valid', isComplete);
        projectStageTotal.classList.toggle('is-invalid', overLimit || (roundedTotal > 0 && roundedTotal < 100));
        projectStageTotal.textContent = overLimit
            ? `Total Plan: ${roundedTotal}% - melebihi 100%`
            : `Total Plan: ${roundedTotal}%`;

        projectStageList.querySelectorAll('.project-stage-plan').forEach((input) => {
            input.classList.toggle('is-invalid', overLimit);
        });

        let actualOverPlanCount = 0;

        projectStageList.querySelectorAll('.project-stage-row').forEach((row) => {
            const planInput = row.querySelector('.project-stage-plan');
            const actualInput = row.querySelector('.project-stage-actual');
            const actualOverPlan = Number(actualInput?.value || 0) > Number(planInput?.value || 0);

            if (actualOverPlan) {
                actualOverPlanCount += 1;
            }

            actualInput?.classList.toggle('is-invalid', actualOverPlan);
        });

        if (projectStageWarning) {
            projectStageWarning.hidden = !overLimit && actualOverPlanCount === 0;
            projectStageWarning.textContent = overLimit
                ? `Warning: total plan tahap sudah ${roundedTotal}%, kurangi ${Math.round((roundedTotal - 100) * 100) / 100}%.`
                : actualOverPlanCount > 0
                    ? 'Warning: actual tahap tidak boleh lebih besar dari plan.'
                    : '';
        }
    };

    const bindProjectStageRows = () => {
        if (!projectStageList) {
            return;
        }

        projectStageList.querySelectorAll('.project-stage-plan, .project-stage-actual').forEach((input) => {
            input.removeEventListener('input', refreshProjectStageTotal);
            input.addEventListener('input', refreshProjectStageTotal);
        });

        projectStageList.querySelectorAll('.project-stage-remove').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('.project-stage-row')?.remove();
                refreshProjectStageNumbers();
                refreshProjectStageTotal();
            }, { once: true });
        });
    };

    if (addProjectStageButton && projectStageList) {
        bindProjectStageRows();
        refreshProjectStageNumbers();
        refreshProjectStageTotal();

        addProjectStageButton.addEventListener('click', () => {
            const index = projectStageList.querySelectorAll('.project-stage-row').length;
            const row = document.createElement('div');

            row.className = 'project-stage-row';
            row.innerHTML = `
                <div class="project-stage-number">Tahap ${index + 1}</div>
                <div>
                    <label class="form-label">Step</label>
                    <input class="form-control project-stage-step" name="stages[${index}][txtProjectStageStep]">
                </div>
                <div>
                    <label class="form-label">Start</label>
                    <input class="form-control project-stage-start" type="date" name="stages[${index}][dtmProjectStageStartDate]">
                </div>
                <div>
                    <label class="form-label">End</label>
                    <input class="form-control project-stage-end" type="date" name="stages[${index}][dtmProjectStageEndDate]">
                </div>
                <div>
                    <label class="form-label">Plan (%)</label>
                    <input class="form-control project-stage-plan" type="number" min="0" max="100" step="0.01" name="stages[${index}][floatProjectStagePlan]">
                </div>
                <div>
                    <label class="form-label">Actual (%)</label>
                    <input class="form-control project-stage-actual" type="number" min="0" max="100" step="0.01" name="stages[${index}][floatProjectStageActual]" value="0">
                </div>
                <button class="btn-icon btn-delete project-stage-remove" type="button" title="Remove stage"><i class="fa-solid fa-trash"></i></button>
            `;
            projectStageList.appendChild(row);
            bindProjectStageRows();
            refreshProjectStageNumbers();
            refreshProjectStageTotal();
            row.querySelector('.project-stage-step')?.focus();
        });
    }

    document.querySelectorAll('.icon-choice input[type="radio"]').forEach((input) => {
        input.addEventListener('change', () => {
            document.querySelectorAll(`.icon-choice input[name="${input.name}"]`).forEach((radio) => {
                radio.closest('.icon-choice')?.classList.toggle('selected', radio.checked);
            });
        });
    });

    const canvas = document.getElementById('lineChart');
    if (canvas && window.Chart) {
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 150);

        gradient.addColorStop(0, 'rgba(140, 198, 63, 0.4)');
        gradient.addColorStop(1, 'rgba(140, 198, 63, 0)');

        new window.Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Exposure Score',
                    data: [20, 35, 50, 45, 70, 85],
                    borderColor: '#8CC63F',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#006838',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    tension: 0.3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                    y: { display: false, min: 0, max: 100 },
                },
            },
        });
    }

    const skillSetPieCanvas = document.getElementById('skillSetPieChart');
    const skillSetPieData = document.getElementById('skillSetPieData');

    if (skillSetPieCanvas && skillSetPieData && window.Chart) {
        const parsedData = JSON.parse(skillSetPieData.textContent || '{"labels":[],"values":[]}');

        new window.Chart(skillSetPieCanvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: parsedData.labels,
                datasets: [{
                    data: parsedData.values,
                    backgroundColor: ['#006838', '#8CC63F', '#F59E0B', '#2563EB', '#7C3AED', '#ED1C24', '#14B8A6'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            font: { size: 11, weight: '600' },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.label}: ${context.parsed} project`,
                        },
                    },
                },
            },
        });
    }

    const setLocationFieldValue = (field, value) => {
        if (!field) return;
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const reverseGeocodeLocation = async (latitude, longitude) => {
        const url = new URL('https://nominatim.openstreetmap.org/reverse');
        url.searchParams.set('format', 'jsonv2');
        url.searchParams.set('lat', String(latitude));
        url.searchParams.set('lon', String(longitude));
        url.searchParams.set('accept-language', 'id');

        const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
        if (!response.ok) return '';

        const data = await response.json();
        return data.display_name || '';
    };

    const updateLocationAddress = async ({ latitude, longitude, address, message, accuracy = null }) => {
        if (message) message.textContent = 'Mengambil alamat dari koordinat...';

        try {
            const resolvedAddress = await reverseGeocodeLocation(latitude, longitude);
            setLocationFieldValue(address, resolvedAddress || `Koordinat ${latitude.toFixed(7)}, ${longitude.toFixed(7)}`);
            if (message) {
                const suffix = accuracy === null ? '' : ` (akurasi ±${Math.round(accuracy || 0)} m)`;
                message.textContent = `Alamat dan koordinat berhasil terisi${suffix}.`;
            }
        } catch {
            setLocationFieldValue(address, `Koordinat ${latitude.toFixed(7)}, ${longitude.toFixed(7)}`);
            if (message) message.textContent = 'Alamat belum dapat dibaca, tetapi koordinat berhasil terisi.';
        }
    };

    document.querySelectorAll('[data-use-current-location]').forEach((button) => {
        button.addEventListener('click', () => {
            const latitude = document.querySelector(button.dataset.latTarget);
            const longitude = document.querySelector(button.dataset.lngTarget);
            const address = document.querySelector(button.dataset.addressTarget);
            const message = button.closest('form')?.querySelector('[data-location-message]');
            const picker = button.closest('form')?.querySelector('[data-location-picker]');

            if (!navigator.geolocation || !latitude || !longitude) {
                if (message) message.textContent = 'Geolocation tidak tersedia di browser ini.';
                return;
            }

            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengambil lokasi';
            if (message) message.textContent = 'Mengambil koordinat perangkat...';

            navigator.geolocation.getCurrentPosition(async (position) => {
                const nextLatitude = position.coords.latitude;
                const nextLongitude = position.coords.longitude;
                setLocationFieldValue(latitude, nextLatitude.toFixed(7));
                setLocationFieldValue(longitude, nextLongitude.toFixed(7));
                picker?.setMapLocation?.(nextLatitude, nextLongitude, 17);
                await updateLocationAddress({
                    latitude: nextLatitude,
                    longitude: nextLongitude,
                    address,
                    message,
                    accuracy: position.coords.accuracy,
                });
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-check"></i> Lokasi Terisi';
                window.setTimeout(() => { button.innerHTML = original; }, 1800);
            }, (error) => {
                button.disabled = false;
                button.innerHTML = original;
                if (message) message.textContent = error.code === 1
                    ? 'Izin lokasi ditolak. Aktifkan permission lokasi lalu coba lagi.'
                    : 'Lokasi gagal dibaca. Pastikan GPS perangkat aktif.';
            }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
        });
    });

    document.querySelectorAll('[data-location-picker]').forEach((picker) => {
        const overlay = picker.closest('[data-modal-overlay]');
        let initialized = false;

        const initialize = () => {
            if (initialized || !window.L) return;

            const form = picker.closest('form');
            const mapElement = document.getElementById(picker.dataset.mapId);
            const latitude = document.querySelector(picker.dataset.latTarget);
            const longitude = document.querySelector(picker.dataset.lngTarget);
            const address = document.querySelector(picker.dataset.addressTarget);
            const message = form?.querySelector('[data-location-message]');
            const queryInput = picker.querySelector('[data-location-map-query]');
            const searchButton = picker.querySelector('[data-location-map-search]');
            const googleMapsLink = picker.querySelector('[data-google-maps-link]');

            if (!mapElement || !latitude || !longitude) return;
            if (picker.getClientRects().length === 0 || mapElement.clientWidth === 0) return;

            const initialLatitude = Number.parseFloat(latitude.value || mapElement.dataset.initialLat || '');
            const initialLongitude = Number.parseFloat(longitude.value || mapElement.dataset.initialLng || '');
            const hasInitialCoordinates = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);
            const startLatitude = hasInitialCoordinates ? initialLatitude : -6.2088;
            const startLongitude = hasInitialCoordinates ? initialLongitude : 106.8456;
            const map = window.L.map(mapElement, { scrollWheelZoom: false }).setView([startLatitude, startLongitude], hasInitialCoordinates ? 16 : 11);
            const marker = window.L.marker([startLatitude, startLongitude], { draggable: true }).addTo(map);

            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            const setMapLocation = (nextLatitude, nextLongitude, zoom = 16) => {
                marker.setLatLng([nextLatitude, nextLongitude]);
                map.setView([nextLatitude, nextLongitude], zoom);
                if (googleMapsLink) googleMapsLink.href = `https://www.google.com/maps?q=${nextLatitude},${nextLongitude}`;
            };

            const chooseLocation = async (nextLatitude, nextLongitude, zoom = 16) => {
                setMapLocation(nextLatitude, nextLongitude, zoom);
                setLocationFieldValue(latitude, nextLatitude.toFixed(7));
                setLocationFieldValue(longitude, nextLongitude.toFixed(7));
                await updateLocationAddress({ latitude: nextLatitude, longitude: nextLongitude, address, message });
            };

            picker.setMapLocation = setMapLocation;
            picker.locationMap = map;
            setMapLocation(startLatitude, startLongitude, hasInitialCoordinates ? 16 : 11);
            const syncMapFromCoordinateFields = () => {
                const nextLatitude = Number.parseFloat(latitude.value);
                const nextLongitude = Number.parseFloat(longitude.value);

                if (Number.isFinite(nextLatitude) && Number.isFinite(nextLongitude)) {
                    setMapLocation(nextLatitude, nextLongitude, 16);
                }
            };

            latitude.addEventListener('change', syncMapFromCoordinateFields);
            longitude.addEventListener('change', syncMapFromCoordinateFields);
            map.on('click', (event) => chooseLocation(event.latlng.lat, event.latlng.lng));
            marker.on('dragend', () => {
                const position = marker.getLatLng();
                chooseLocation(position.lat, position.lng);
            });
            searchButton?.addEventListener('click', async () => {
                const query = (queryInput?.value || '').trim();
                if (!query) {
                    if (message) message.textContent = 'Masukkan alamat atau nama lokasi terlebih dahulu.';
                    return;
                }

                searchButton.disabled = true;
                if (message) message.textContent = 'Mencari lokasi di maps...';

                try {
                    const url = new URL('https://nominatim.openstreetmap.org/search');
                    url.searchParams.set('format', 'jsonv2');
                    url.searchParams.set('limit', '1');
                    url.searchParams.set('accept-language', 'id');
                    url.searchParams.set('q', query);
                    const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
                    const [result] = response.ok ? await response.json() : [];

                    if (!result) {
                        if (message) message.textContent = 'Lokasi tidak ditemukan. Coba kata kunci yang lebih lengkap.';
                        return;
                    }

                    await chooseLocation(Number.parseFloat(result.lat), Number.parseFloat(result.lon), 17);
                } catch {
                    if (message) message.textContent = 'Pencarian belum tersedia. Klik titik pada peta secara manual.';
                } finally {
                    searchButton.disabled = false;
                }
            });
            queryInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchButton?.click();
                }
            });

            initialized = true;
            window.setTimeout(() => map.invalidateSize(), 80);
        };

        const refresh = () => {
            initialize();
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => picker.locationMap?.invalidateSize({ pan: false }));
            });
        };

        if (overlay) {
            overlay.addEventListener('modal:opened', () => {
                refresh();
            });
            if (overlay.classList.contains('active')) refresh();
        }

        document.addEventListener('attendance:tab-activated', (event) => {
            if (event.detail?.tab === 'settings') refresh();
        });

        if ('ResizeObserver' in window) {
            const resizeObserver = new ResizeObserver(() => {
                if (picker.getClientRects().length > 0) refresh();
            });
            resizeObserver.observe(picker);
        }

        refresh();
    });

    document.querySelectorAll('[data-attendance-admin-tabs]').forEach((tabs) => {
        const buttons = Array.from(tabs.querySelectorAll('[data-attendance-admin-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-attendance-admin-panel]'));

        const activate = (tab) => {
            buttons.forEach((button) => {
                const active = button.dataset.attendanceAdminTab === tab;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach((panel) => { panel.hidden = panel.dataset.attendanceAdminPanel !== tab; });
            document.dispatchEvent(new CustomEvent('attendance:tab-activated', { detail: { tab } }));

            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        };

        buttons.forEach((button) => button.addEventListener('click', () => activate(button.dataset.attendanceAdminTab)));
        activate(tabs.dataset.defaultTab || 'today');
    });

    initializeAuthPage();
    initializeAttendancePage();
    initializeTableControls();
});
