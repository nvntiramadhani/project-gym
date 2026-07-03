<?php
// pages/users.php
$path_prefix = '../';
$page_title = 'Kelola Pengguna';
include '../includes/header.php';
include '../config/database.php';

// Proteksi hak akses role: Owner saja
require_roles('owner', '../');

// Ambil data semua user
$stmt = $pdo->query("
    SELECT u.*, t.specialization, t.phone
    FROM users u
    LEFT JOIN trainers t ON t.user_id = u.id
    ORDER BY u.id ASC
");
$users = $stmt->fetchAll();
?>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Navbar -->
        <?php include '../includes/topbar.php'; ?>

        <div class="content-wrapper">
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchUser" placeholder="Cari nama atau email..." onkeyup="filterUsers()">
                </div>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddUserModal()">
                    <i class="bi bi-plus-lg"></i> Tambah Pengguna
                </button>
            </div>

            <!-- Table -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5>Daftar Pengguna & Staf Internal</h5>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table-custom" id="userTable">
                            <thead>
                                <tr>
                                    <th>No</th><th>Nama</th><th>Email</th><th>Role</th><th>Detail Trainer</th><th>Status</th><th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($users as $u): 
                                    $status_class = $u['status'] === 'aktif' ? 'aktif' : 'expired';
                                    $role_label = $u['role'] === 'owner' ? 'Owner' : ($u['role'] === 'admin' ? 'Admin / Resepsionis' : 'Personal Trainer');
                                ?>
                                    <tr class="user-row" data-search="<?php echo htmlspecialchars(strtolower($u['name'] . ' ' . $u['email'])); ?>">
                                        <td><?php echo $no++; ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($u['name']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $role_label; ?></span></td>
                                        <td>
                                            <?php if ($u['role'] === 'trainer'): ?>
                                                <small class="d-block">Spec: <?php echo htmlspecialchars($u['specialization'] ?? '-'); ?></small>
                                                <small class="text-muted">Telp: <?php echo htmlspecialchars($u['phone'] ?? '-'); ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge-status <?php echo $status_class; ?>">
                                                <?php echo ucfirst(htmlspecialchars($u['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn-action edit" title="Edit" onclick="openEditUserModal(
                                                    '<?php echo htmlspecialchars($u['id']); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($u['name'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($u['email'])); ?>',
                                                    '<?php echo htmlspecialchars($u['role']); ?>',
                                                    '<?php echo htmlspecialchars($u['status']); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($u['specialization'] ?? '')); ?>',
                                                    '<?php echo htmlspecialchars($u['phone'] ?? ''); ?>'
                                                )"><i class="bi bi-pencil"></i></button>
                                                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="toggleUserStatus(<?php echo $u['id']; ?>)">
                                                        <i class="bi bi-power"></i> Toggle Status
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal User -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px">
                    <h5 class="modal-title" id="userModalTitle">Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px">
                    <form id="userForm">
                        <input type="hidden" id="userId" name="id">
                        <input type="hidden" name="csrf_token" id="userCsrf" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="userName">Nama Lengkap *</label>
                                <input type="text" class="form-control" id="userName" name="name" required placeholder="Masukkan nama lengkap">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="userEmail">Email *</label>
                                <input type="email" class="form-control" id="userEmail" name="email" required placeholder="Masukkan email">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="userPassword">Password <span id="pwdRequiredLabel">*</span></label>
                                <input type="password" class="form-control" id="userPassword" name="password" placeholder="Masukkan password">
                                <small class="text-muted d-none" id="pwdHint">Kosongkan jika tidak ingin mengubah password.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="userRole">Role Hak Akses *</label>
                                <select class="form-select" id="userRole" name="role" required onchange="toggleTrainerFields()">
                                    <option value="admin">Admin Resepsionis</option>
                                    <option value="owner">Owner / Manajer</option>
                                    <option value="trainer">Personal Trainer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="userStatus">Status *</label>
                                <select class="form-select" id="userStatus" name="status" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </select>
                            </div>
                            <!-- Form trainer spesifik -->
                            <div class="col-12 trainer-fields d-none">
                                <label class="form-label" for="trainerSpec">Spesialisasi PT *</label>
                                <input type="text" class="form-control" id="trainerSpec" name="specialization" placeholder="Cth: Yoga / Body Building">
                            </div>
                            <div class="col-12 trainer-fields d-none">
                                <label class="form-label" for="trainerPhone">No. Telepon Trainer *</label>
                                <input type="text" class="form-control" id="trainerPhone" name="phone" placeholder="Masukkan nomor telepon">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="saveUser()">Simpan Pengguna</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function filterUsers() {
        var query = document.getElementById('searchUser').value.toLowerCase().trim();
        var rows = document.querySelectorAll('.user-row');
        rows.forEach(function(row) {
            var text = row.getAttribute('data-search');
            if (text.indexOf(query) > -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function toggleTrainerFields() {
        var role = document.getElementById('userRole').value;
        var fields = document.querySelectorAll('.trainer-fields');
        if (role === 'trainer') {
            fields.forEach(f => f.classList.remove('d-none'));
            document.getElementById('trainerSpec').setAttribute('required', 'required');
            document.getElementById('trainerPhone').setAttribute('required', 'required');
        } else {
            fields.forEach(f => f.classList.add('d-none'));
            document.getElementById('trainerSpec').removeAttribute('required');
            document.getElementById('trainerPhone').removeAttribute('required');
        }
    }

    function openAddUserModal() {
        document.getElementById('userModalTitle').textContent = 'Tambah Pengguna Baru';
        document.getElementById('userId').value = '';
        document.getElementById('userForm').reset();
        document.getElementById('pwdRequiredLabel').classList.remove('d-none');
        document.getElementById('userPassword').setAttribute('required', 'required');
        document.getElementById('pwdHint').classList.add('d-none');
        toggleTrainerFields();
    }

    function openEditUserModal(id, name, email, role, status, spec, phone) {
        document.getElementById('userModalTitle').textContent = 'Edit Pengguna';
        document.getElementById('userId').value = id;
        document.getElementById('userName').value = name;
        document.getElementById('userEmail').value = email;
        document.getElementById('userRole').value = role;
        document.getElementById('userStatus').value = status;
        
        document.getElementById('userPassword').removeAttribute('required');
        document.getElementById('pwdRequiredLabel').classList.add('d-none');
        document.getElementById('pwdHint').classList.remove('d-none');
        
        document.getElementById('trainerSpec').value = spec || '';
        document.getElementById('trainerPhone').value = phone || '';
        
        toggleTrainerFields();

        var modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    }

    function saveUser() {
        var name = document.getElementById('userName').value.trim();
        var email = document.getElementById('userEmail').value.trim();
        var role = document.getElementById('userRole').value;
        var status = document.getElementById('userStatus').value;
        var spec = document.getElementById('trainerSpec').value.trim();
        var phone = document.getElementById('trainerPhone').value.trim();
        var password = document.getElementById('userPassword').value;
        
        var id = document.getElementById('userId').value;
        var action = id ? 'update' : 'create';
        
        if (!name || !email || !role) {
            alert('Mohon isi field nama, email, dan role!');
            return;
        }

        if (role === 'trainer' && (!spec || !phone)) {
            alert('Mohon lengkapi data spesialisasi dan nomor telepon Personal Trainer!');
            return;
        }

        if (!id && !password) {
            alert('Password wajib diisi untuk pengguna baru!');
            return;
        }

        var csrfToken = document.getElementById('userCsrf').value;
        var form = document.getElementById('userForm');
        var formData = new FormData(form);
        formData.append('action', action);

        fetch('../api/users.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Gagal menyimpan: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error save user:', error);
            alert('Gagal menghubungi server!');
        });
    }

    function toggleUserStatus(id) {
        if (!confirm('Ubah status aktifasi akun pengguna ini?')) {
            return;
        }

        var csrfToken = document.getElementById('userCsrf').value;

        fetch('../api/users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: 'action=toggle_status&id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Gagal mengubah status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error toggle user:', error);
            alert('Gagal menghubungi server!');
        });
    }
    </script>

<?php
include '../includes/footer.php';
?>
