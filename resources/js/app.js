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

const modalTemplates = {
    intern: `
        <div class="form-group">
            <label>ID</label>
            <input type="text" class="form-control" placeholder="Contoh: INT-002">
        </div>
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" class="form-control" placeholder="Masukkan nama lengkap">
        </div>
        <div class="form-group">
            <label>Universitas</label>
            <input type="text" class="form-control" placeholder="Masukkan nama universitas">
        </div>
        <div class="form-group">
            <label>Jurusan</label>
            <input type="text" class="form-control" placeholder="Masukkan jurusan">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select class="form-control">
                <option>Aktif</option>
                <option>Nonaktif</option>
            </select>
        </div>
    `,
    project: `
        <div class="form-group">
            <label>Nama Project</label>
            <input type="text" class="form-control" placeholder="Masukkan nama project">
        </div>
        <div class="form-group">
            <label>Tipe</label>
            <select class="form-control">
                <option>Collaboration</option>
                <option>Main</option>
                <option>Satellite</option>
                <option>Sharing</option>
            </select>
        </div>
        <div class="form-group">
            <label>PIC / Mentor</label>
            <input type="text" class="form-control" placeholder="Masukkan nama PIC atau mentor">
        </div>
        <div class="form-group">
            <label>Progress (%)</label>
            <input type="number" class="form-control" min="0" max="100" placeholder="Contoh: 80">
        </div>
    `,
    mentor: `
        <div class="form-group">
            <label>ID</label>
            <input type="text" class="form-control" placeholder="Contoh: MTR-003">
        </div>
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" class="form-control" placeholder="Masukkan nama lengkap mentor">
        </div>
        <div class="form-group">
            <label>Department</label>
            <input type="text" class="form-control" placeholder="Masukkan department">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select class="form-control">
                <option>Aktif</option>
                <option>Nonaktif</option>
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
    alert('Data berhasil disimpan!');
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
});
