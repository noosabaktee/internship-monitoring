import 'bootstrap/dist/js/bootstrap.bundle.min.js';
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
        sidebar.classList.toggle('expanded');
        body.classList.toggle('sidebar-is-expanded');
        return;
    }

    sidebar.classList.toggle('expanded');
    sidebarOverlay.classList.toggle('active');
    body.style.overflow = sidebar.classList.contains('expanded') ? 'hidden' : '';
};

window.openDatePicker = function () {
    const topbarDate = document.getElementById('topbarDate');

    if (!topbarDate) {
        return;
    }

    if (topbarDate.showPicker) {
        topbarDate.showPicker();
    } else {
        topbarDate.focus();
    }
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

const modalTemplates = {
    intern: `
        <div class="form-group">
            <label>ID</label>
            <input type="text" class="form-control" placeholder="Example: INT-002">
        </div>
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" class="form-control" placeholder="Enter full name">
        </div>
        <div class="form-group">
            <label>Gender</label>
            <select class="form-control">
                <option>Male</option>
                <option>Female</option>
            </select>
        </div>
        <div class="form-group">
            <label>University</label>
            <input type="text" class="form-control" placeholder="Enter university name">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select class="form-control">
                <option>Active</option>
                <option>Inactive</option>
            </select>
        </div>
    `,
    project: `
        <div class="form-group">
            <label>Project Name</label>
            <input type="text" class="form-control" placeholder="Enter project name">
        </div>
        <div class="form-group">
            <label>Type</label>
            <select class="form-control">
                <option>Collaboration</option>
                <option>Main</option>
                <option>Satellite</option>
                <option>Sharing</option>
            </select>
        </div>
        <div class="form-group">
            <label>PIC / Mentor</label>
            <input type="text" class="form-control" placeholder="Enter PIC or mentor name">
        </div>
        <div class="form-group">
            <label>Progress (%)</label>
            <select class="form-control">
                <option>Open - 0%</option>
                <option>Inprogress - 25%</option>
                <option>Project Review - 50%</option>
                <option>Trial/testing - 75%</option>
                <option>Completed - 100%</option>
            </select>
        </div>
    `,
    mentor: `
        <div class="form-group">
            <label>ID</label>
            <input type="text" class="form-control" placeholder="Example: MTR-003">
        </div>
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" class="form-control" placeholder="Enter mentor full name">
        </div>
        <div class="form-group">
            <label>Gender</label>
            <select class="form-control">
                <option>Male</option>
                <option>Female</option>
            </select>
        </div>
        <div class="form-group">
            <label>Department</label>
            <input type="text" class="form-control" placeholder="Enter department">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select class="form-control">
                <option>Active</option>
                <option>Inactive</option>
            </select>
        </div>
    `,
};

window.openCrudModal = function (type, titleText) {
    const modalFields = document.getElementById('modalFields');

    if (!modalFields) {
        return;
    }

    modalFields.innerHTML = modalTemplates[type] || modalTemplates.intern;
    window.openModal('crudModal', titleText);
};

window.openModal = function (modalId, titleText) {
    const modal = document.getElementById(modalId);
    const modalTitle = document.getElementById('modalTitle');

    if (!modal) {
        return;
    }

    if (titleText && modalTitle) {
        modalTitle.innerText = titleText;
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
};

window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);

    if (!modal) {
        return;
    }

    modal.classList.remove('active');
    document.body.style.overflow = '';
};

window.saveData = function () {
    alert('Data has been saved!');
    window.closeModal('crudModal');
};

document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeIcon = themeToggleBtn ? themeToggleBtn.querySelector('i') : null;
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    const topbarDate = document.getElementById('topbarDate');
    const today = new Date();
    const modal = document.getElementById('crudModal');
    const internExtendButton = document.getElementById('internExtendButton');
    const internExtendFields = document.getElementById('internExtendFields');
    const internExtendNote = document.getElementById('internExtendNote');
    const addProjectStageButton = document.getElementById('addProjectStageButton');
    const projectStageList = document.getElementById('projectStageList');
    const projectStageTotal = document.getElementById('projectStageTotal');
    const projectStageWarning = document.getElementById('projectStageWarning');
    let internExtendAddedThisEdit = false;

    if (topbarDate && !topbarDate.value) {
        topbarDate.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    }

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

    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                window.closeModal('crudModal');
            }
        });
    }

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
});
