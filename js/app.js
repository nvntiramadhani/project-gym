/* ========================================
   FitPro Gym - Main JavaScript
   ======================================== */

document.addEventListener('DOMContentLoaded', function () {

    // ===== LOGIN HANDLER =====
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const username = document.getElementById('loginUsername').value.trim();
            const password = document.getElementById('loginPassword').value.trim();

            if (username === 'admin' && password === 'admin123') {
                const btn = document.getElementById('btnLogin');
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
                btn.disabled = true;
                setTimeout(function () {
                    window.location.href = 'dashboard.html';
                }, 1200);
            } else {
                showToast('Username atau password salah!', 'danger');
                document.getElementById('loginPassword').value = '';
                document.getElementById('loginPassword').focus();
            }
        });
    }

    // ===== SIDEBAR TOGGLE (MOBILE) =====
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('show');
        });
    }
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }

    // ===== DASHBOARD CHART =====
    const chartCanvas = document.getElementById('memberChart');
    if (chartCanvas && typeof Chart !== 'undefined') {
        const ctx = chartCanvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Nov', 'Des', 'Jan', 'Feb', 'Mar', 'Apr'],
                datasets: [
                    {
                        label: 'Anggota Baru',
                        data: [18, 25, 30, 22, 35, 28],
                        backgroundColor: 'rgba(67, 97, 238, 0.8)',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.5
                    },
                    {
                        label: 'Anggota Keluar',
                        data: [5, 8, 4, 7, 6, 3],
                        backgroundColor: 'rgba(255, 71, 87, 0.8)',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            font: { family: "'Inter', sans-serif", size: 12 }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                        ticks: { font: { family: "'Inter', sans-serif", size: 12 }, color: '#636e72' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "'Inter', sans-serif", size: 12 }, color: '#636e72' }
                    }
                }
            }
        });
    }

    // ===== CHART FILTER =====
    const chartFilter = document.getElementById('chartFilter');
    if (chartFilter) {
        chartFilter.addEventListener('change', function () {
            showToast('Filter chart diperbarui (dummy)', 'info');
        });
    }

});

// ===== TOAST NOTIFICATION =====
function showToast(message, type) {
    type = type || 'success';
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = {
        success: 'bi-check-circle-fill',
        danger: 'bi-x-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info: 'bi-info-circle-fill'
    };

    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-bg-' + type + ' border-0 show';
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML =
        '<div class="d-flex">' +
            '<div class="toast-body">' +
                '<i class="bi ' + (icons[type] || icons.info) + ' me-2"></i>' + message +
            '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
        '</div>';

    container.appendChild(toastEl);

    // Auto-close button
    toastEl.querySelector('.btn-close').addEventListener('click', function () {
        toastEl.remove();
    });

    setTimeout(function () {
        toastEl.style.opacity = '0';
        toastEl.style.transition = 'opacity 0.3s';
        setTimeout(function () { toastEl.remove(); }, 300);
    }, 3000);
}

// ===== MEMBER SEARCH & FILTER =====
function filterMembers() {
    var search = document.getElementById('searchMember');
    var filterPaket = document.getElementById('filterPaket');
    var filterStatus = document.getElementById('filterStatus');
    if (!search) return;

    var searchVal = search.value.toLowerCase();
    var paketVal = filterPaket ? filterPaket.value : '';
    var statusVal = filterStatus ? filterStatus.value : '';

    var table = document.getElementById('memberTable');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');

    rows.forEach(function (row) {
        var name = row.querySelector('.member-name');
        var nameText = name ? name.textContent.toLowerCase() : '';
        var rowPaket = row.getAttribute('data-paket') || '';
        var rowStatus = row.getAttribute('data-status') || '';

        var matchSearch = nameText.indexOf(searchVal) > -1;
        var matchPaket = !paketVal || rowPaket === paketVal;
        var matchStatus = !statusVal || rowStatus === statusVal;

        row.style.display = (matchSearch && matchPaket && matchStatus) ? '' : 'none';
    });
}

// ===== ATTENDANCE SEARCH =====
function filterAttendance() {
    var search = document.getElementById('searchAttendance');
    if (!search) return;
    var searchVal = search.value.toLowerCase();

    var table = document.getElementById('attendanceTable');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');

    rows.forEach(function (row) {
        var name = row.querySelector('.member-name');
        var nameText = name ? name.textContent.toLowerCase() : '';
        row.style.display = nameText.indexOf(searchVal) > -1 ? '' : 'none';
    });
}

// ===== MEMBER MODAL (ADD) =====
function openAddModal() {
    document.getElementById('memberModalTitle').textContent = 'Tambah Anggota Baru';
    document.getElementById('memberId').value = '';
    document.getElementById('memberForm').reset();
}

// ===== MEMBER MODAL (EDIT) =====
function openEditModal(id, name, email, pkg, status, phone, address) {
    document.getElementById('memberModalTitle').textContent = 'Edit Anggota - ' + name;
    document.getElementById('memberId').value = id;
    document.getElementById('memberName').value = name;
    document.getElementById('memberEmail').value = email;
    document.getElementById('memberPackage').value = pkg;
    document.getElementById('memberStatus').value = status;
    document.getElementById('memberPhone').value = phone || '';
    document.getElementById('memberAddress').value = address || '';

    var modal = new bootstrap.Modal(document.getElementById('memberModal'));
    modal.show();
}

// ===== SAVE MEMBER =====
function saveMember() {
    var name = document.getElementById('memberName').value.trim();
    var email = document.getElementById('memberEmail').value.trim();
    var pkg = document.getElementById('memberPackage').value;
    var phone = document.getElementById('memberPhone').value.trim();

    if (!name || !email || !pkg || !phone) {
        showToast('Mohon lengkapi semua field yang wajib diisi!', 'warning');
        return;
    }

    var id = document.getElementById('memberId').value;
    if (id) {
        showToast('Data anggota ' + name + ' berhasil diperbarui!', 'success');
    } else {
        showToast('Anggota baru ' + name + ' berhasil ditambahkan!', 'success');
    }

    var modal = bootstrap.Modal.getInstance(document.getElementById('memberModal'));
    if (modal) modal.hide();
}

// ===== DELETE MEMBER =====
var deleteTargetId = '';
var deleteTargetName = '';

function confirmDelete(id, name) {
    deleteTargetId = id;
    deleteTargetName = name;
    document.getElementById('deleteMessage').textContent =
        'Apakah Anda yakin ingin menghapus anggota "' + name + '"?';
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function deleteMember() {
    showToast('Anggota "' + deleteTargetName + '" berhasil dihapus!', 'success');
    var modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
    if (modal) modal.hide();

    // Remove row from table (visual only)
    var table = document.getElementById('memberTable');
    if (table) {
        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function (row) {
            if (row.querySelector('td') && row.querySelector('td').textContent.indexOf(deleteTargetId) > -1) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function () { row.remove(); }, 300);
            }
        });
    }
}

// ===== CHECK-IN =====
function doCheckin() {
    var id = document.getElementById('checkinId').value.trim();
    var type = document.getElementById('checkinType').value;

    if (!id) {
        showToast('Mohon masukkan ID Member!', 'warning');
        return;
    }

    var action = type === 'in' ? 'Check-in' : 'Check-out';
    showToast(action + ' berhasil untuk member #' + id + '!', 'success');

    var modal = bootstrap.Modal.getInstance(document.getElementById('checkinModal'));
    if (modal) modal.hide();
    document.getElementById('checkinId').value = '';
}
