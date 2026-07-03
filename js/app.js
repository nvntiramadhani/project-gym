/* ========================================
   FitPro Gym - Main JavaScript (Dynamic Version)
   ======================================== */

document.addEventListener('DOMContentLoaded', function () {

    // ===== LOGIN HANDLER (AJAX) =====
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value.trim();
            const csrfToken = document.getElementById('csrfToken').value;
            const btn = document.getElementById('btnLogin');

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
            btn.disabled = true;

            fetch('api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ email: email, password: password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(function () {
                        window.location.href = 'pages/dashboard.php';
                    }, 1000);
                } else {
                    showToast(data.message, 'danger');
                    document.getElementById('loginPassword').value = '';
                    document.getElementById('loginPassword').focus();
                    btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Masuk';
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error login:', error);
                showToast('Terjadi kesalahan koneksi!', 'danger');
                btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Masuk';
                btn.disabled = false;
            });
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

    // ===== DASHBOARD CHART (DYNAMIC DATA) =====
    const chartCanvas = document.getElementById('memberChart');
    if (chartCanvas && typeof Chart !== 'undefined') {
        const ctx = chartCanvas.getContext('2d');
        
        // Baca data dinamis dari PHP jika diset, jika tidak pakai dummy data
        const labels = (typeof dynamicChartData !== 'undefined') ? dynamicChartData.labels : ['Nov', 'Des', 'Jan', 'Feb', 'Mar', 'Apr'];
        const joins = (typeof dynamicChartData !== 'undefined') ? dynamicChartData.joins : [18, 25, 30, 22, 35, 28];
        const leaves = (typeof dynamicChartData !== 'undefined') ? dynamicChartData.leaves : [5, 8, 4, 7, 6, 3];

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Pendaftaran Baru',
                        data: joins,
                        backgroundColor: 'rgba(67, 97, 238, 0.8)',
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
                        ticks: { font: { family: "'Inter', sans-serif", size: 12 }, color: '#636e72', stepSize: 1 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "'Inter', sans-serif", size: 12 }, color: '#636e72' }
                    }
                }
            }
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

    var searchVal = search.value.toLowerCase().trim();
    var paketVal = filterPaket ? filterPaket.value : '';
    var statusVal = filterStatus ? filterStatus.value : '';

    var table = document.getElementById('memberTable');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    var visibleCount = 0;

    rows.forEach(function (row) {
        if (row.cells.length === 1 && row.cells[0].getAttribute('colspan')) return;

        var nameEl = row.querySelector('.member-name');
        var codeEl = row.cells[0]; // Kolom ID Member
        var nameText = nameEl ? nameEl.textContent.toLowerCase() : '';
        var codeText = codeEl ? codeEl.textContent.toLowerCase() : '';
        
        var rowPaket = row.getAttribute('data-paket') || '';
        var rowStatus = row.getAttribute('data-status') || '';

        var matchSearch = nameText.indexOf(searchVal) > -1 || codeText.indexOf(searchVal) > -1;
        var matchPaket = !paketVal || rowPaket === paketVal;
        var matchStatus = !statusVal || rowStatus === statusVal;

        if (matchSearch && matchPaket && matchStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    var countEl = document.getElementById('displayedCount');
    if (countEl) {
        countEl.textContent = visibleCount;
    }
}

// ===== ATTENDANCE SEARCH =====
function filterAttendance() {
    var search = document.getElementById('searchAttendance');
    if (!search) return;
    var searchVal = search.value.toLowerCase().trim();

    var table = document.getElementById('attendanceTable');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');

    rows.forEach(function (row) {
        if (row.cells.length === 1 && row.cells[0].getAttribute('colspan')) return;

        var nameEl = row.querySelector('.member-name');
        var idEl = row.cells[2]; // Kolom ID Member
        
        var nameText = nameEl ? nameEl.textContent.toLowerCase() : '';
        var idText = idEl ? idEl.textContent.toLowerCase() : '';
        
        var match = nameText.indexOf(searchVal) > -1 || idText.indexOf(searchVal) > -1;
        row.style.display = match ? '' : 'none';
    });
}

// ===== MEMBER MODAL (ADD) =====
function openAddModal() {
    document.getElementById('memberModalTitle').textContent = 'Tambah Anggota Baru';
    document.getElementById('memberId').value = '';
    document.getElementById('memberForm').reset();
    
    // Default Tanggal Join hari ini
    var today = new Date().toISOString().split('T')[0];
    document.getElementById('memberBirthDate').value = '';
    
    // Tampilkan field pilihan paket & status pembayaran
    var pkgGroup = document.getElementById('packageGroup');
    var payGroup = document.getElementById('paymentStatusGroup');
    if (pkgGroup) pkgGroup.classList.remove('d-none');
    if (payGroup) payGroup.classList.remove('d-none');
}

// ===== MEMBER MODAL (EDIT) =====
function openEditModal(id, name, email, pkg, status, phone, address, joinDate, trainerId) {
    document.getElementById('memberModalTitle').textContent = 'Edit Anggota - ' + name;
    document.getElementById('memberId').value = id;
    document.getElementById('memberName').value = name;
    document.getElementById('memberEmail').value = email || '';
    document.getElementById('memberPhone').value = phone || '';
    document.getElementById('memberAddress').value = address || '';
    
    var trainerSelect = document.getElementById('memberTrainer');
    if (trainerSelect) {
        trainerSelect.value = trainerId || '';
    }

    // Sembunyikan field pilihan paket & status pembayaran pada edit (dikelola lewat detail & perpanjangan)
    var pkgGroup = document.getElementById('packageGroup');
    var payGroup = document.getElementById('paymentStatusGroup');
    if (pkgGroup) pkgGroup.classList.add('d-none');
    if (payGroup) payGroup.classList.add('d-none');

    // Tarik biodata tambahan dari database
    var apiPath = window.location.pathname.includes('/pages/') ? '../api/members.php' : 'api/members.php';
    
    // Ambil tanggal lahir dan gender
    // Karena kita tidak mau buat endpoint tambahan, kita bisa kirim request get
    var detailPath = window.location.pathname.includes('/pages/') ? 'member-detail.php' : 'pages/member-detail.php';
    
    // Untuk memudahkan, kita panggil bootstrap modal secara langsung
    var modal = new bootstrap.Modal(document.getElementById('memberModal'));
    modal.show();
}

// ===== SAVE MEMBER (AJAX MULTIPART) =====
function saveMember(isFromDetail) {
    var name = document.getElementById('memberName').value.trim();
    var phone = document.getElementById('memberPhone').value.trim();
    var birthDate = document.getElementById('memberBirthDate').value;
    var gender = document.getElementById('memberGender').value;
    var id = document.getElementById('memberId').value;
    
    if (!name || !phone || !birthDate || !gender) {
        showToast('Mohon lengkapi semua field wajib!', 'warning');
        return;
    }

    var form = document.getElementById('memberForm');
    var formData = new FormData(form);
    
    var action = id ? 'update' : 'create';
    formData.append('action', action);

    var csrfToken = document.getElementById('memberCsrf') ? document.getElementById('memberCsrf').value : '';
    if (!csrfToken) {
        var csrfEl = document.querySelector('[name="csrf_token"]');
        csrfToken = csrfEl ? csrfEl.value : '';
    }

    var apiPath = window.location.pathname.includes('/pages/') ? '../api/members.php' : 'api/members.php';

    fetch(apiPath, {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            var modalEl = document.getElementById('memberModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            
            setTimeout(function () {
                if (isFromDetail) {
                    location.reload();
                } else {
                    location.href = 'members.php';
                }
            }, 1000);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error save member:', error);
        showToast('Gagal menyimpan data!', 'danger');
    });
}

// ===== DELETE MEMBER (AJAX) =====
var deleteTargetId = '';
var deleteTargetName = '';

function confirmDelete(id, name) {
    deleteTargetId = id;
    deleteTargetName = name;
    document.getElementById('deleteMessage').textContent =
        'Apakah Anda yakin ingin menghapus anggota "' + name + '"? Semua data presensi dan program latihan terkait akan dinonaktifkan.';
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function deleteMember() {
    var csrfToken = document.querySelector('[name="csrf_token"]').value;
    var apiPath = window.location.pathname.includes('/pages/') ? '../api/members.php' : 'api/members.php';

    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken
        },
        body: 'action=delete&id=' + deleteTargetId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            var modalEl = document.getElementById('deleteModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            setTimeout(function () { location.reload(); }, 800);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error delete member:', error);
        showToast('Gagal menghapus data!', 'danger');
    });
}

function deleteMemberDirect(id) {
    var csrfEl = document.querySelector('[name="csrf_token"]');
    var csrfToken = csrfEl ? csrfEl.value : '';
    var apiPath = '../api/members.php';

    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken
        },
        body: 'action=delete&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(function () { location.href = 'members.php'; }, 1000);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error delete member:', error);
        showToast('Gagal menghapus data!', 'danger');
    });
}

// ===== CHECK-IN (AJAX) =====
function doCheckin() {
    var id = document.getElementById('checkinId').value.trim();
    var type = document.getElementById('checkinType').value;

    if (!id) {
        showToast('Mohon masukkan ID Member!', 'warning');
        return;
    }

    var apiPath = window.location.pathname.includes('/pages/') ? '../api/checkin.php' : 'api/checkin.php';

    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            member_id: id,
            type: type
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            var modalEl = document.getElementById('checkinModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            document.getElementById('checkinId').value = '';
            
            setTimeout(function () {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error presensi:', error);
        showToast('Gagal memproses presensi!', 'danger');
    });
}

// ===== PHYSICAL MEASUREMENT (AJAX) =====
function savePhysical() {
    var form = document.getElementById('physicalForm');
    var formData = new FormData(form);
    
    var height = document.getElementById('physHeight').value;
    var weight = document.getElementById('physWeight').value;
    var bodyFat = document.getElementById('physBodyFat').value;
    var date = document.getElementById('physDate').value;

    if (!height || !weight || !bodyFat || !date) {
        showToast('Mohon lengkapi semua field yang wajib diisi!', 'warning');
        return;
    }

    var csrfToken = form.querySelector('[name="csrf_token"]').value;
    var apiPath = '../api/physical.php';

    fetch(apiPath, {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            var modalEl = document.getElementById('physicalModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            
            setTimeout(function () {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error save physical:', error);
        showToast('Gagal menyimpan data pengukuran!', 'danger');
    });
}

// ===== MEMBERSHIP RENEWAL (AJAX) =====
function submitRenewal() {
    var form = document.getElementById('renewForm');
    var packageId = document.getElementById('renewPackage').value;
    
    if (!packageId) {
        showToast('Pilih paket terlebih dahulu!', 'warning');
        return;
    }

    var formData = new FormData(form);
    var csrfToken = form.querySelector('[name="csrf_token"]').value;
    
    fetch('../api/memberships.php', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            var modalEl = document.getElementById('renewModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error renew membership:', error);
        showToast('Gagal memproses perpanjangan!', 'danger');
    });
}

// ===== PERSONAL TRAINER ASSIGNMENT (AJAX) =====
function submitTrainerAssignment() {
    var form = document.getElementById('assignTrainerForm');
    var formData = new FormData(form);
    var csrfToken = form.querySelector('[name="csrf_token"]').value;

    fetch('../api/trainers.php', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            var modalEl = document.getElementById('assignTrainerModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error assigning trainer:', error);
        showToast('Gagal memproses penugasan!', 'danger');
    });
}

// ===== WORKOUT PROGRAM (AJAX) =====
function submitWorkoutProgram() {
    var form = document.getElementById('programForm');
    var title = document.getElementById('programTitle').value.trim();
    var target = document.getElementById('programTarget').value.trim();
    var desc = document.getElementById('programDesc').value.trim();
    
    if (!title || !target || !desc) {
        showToast('Mohon lengkapi data program latihan!', 'warning');
        return;
    }

    var formData = new FormData(form);
    var csrfToken = form.querySelector('[name="csrf_token"]').value;

    fetch('../api/workout_programs.php', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            var modalEl = document.getElementById('programModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error save program:', error);
        showToast('Gagal menyimpan program latihan!', 'danger');
    });
}
