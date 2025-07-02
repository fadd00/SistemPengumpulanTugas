<?php
$pageTitle = 'Manajemen Pengguna';
$activePage = 'manage_users'; // Akan ditambahkan di navigasi nanti
require_once '../config.php';
require_once 'templates/header.php';

// Pastikan asisten sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    // Sebenarnya header.php sudah melakukan cek ini, tapi untuk keamanan ganda
    header("Location: ../login.php");
    exit();
}

$message = '';
$message_type = ''; // 'success' atau 'error'

// Handle Aksi Pengguna (Tambah, Edit, Hapus)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Tambah Pengguna
    if (isset($_POST['tambah_pengguna'])) {
        $nama = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role']);

        if (empty($nama) || empty($email) || empty($password) || empty($role)) {
            $message = "Semua field (Nama, Email, Password, Role) harus diisi.";
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Format email tidak valid.";
            $message_type = 'error';
        } elseif (!in_array($role, ['mahasiswa', 'asisten'])) {
            $message = "Role tidak valid.";
            $message_type = 'error';
        } else {
            // Cek apakah email sudah ada
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $message = "Email sudah terdaftar. Gunakan email lain.";
                $message_type = 'error';
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt_insert = $conn->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt_insert->bind_param("ssss", $nama, $email, $hashed_password, $role);
                if ($stmt_insert->execute()) {
                    $message = "Pengguna baru berhasil ditambahkan.";
                    $message_type = 'success';
                } else {
                    $message = "Gagal menambahkan pengguna: " . $stmt_insert->error;
                    $message_type = 'error';
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        }
    }
    // Edit Pengguna
    elseif (isset($_POST['edit_pengguna'])) {
        $id_user = (int)$_POST['id_user_edit'];
        $nama = trim($_POST['nama_edit']);
        $email = trim($_POST['email_edit']);
        $password = trim($_POST['password_edit']); // Password baru, kosongkan jika tidak diubah
        $role = trim($_POST['role_edit']);

        if (empty($nama) || empty($email) || empty($role) || $id_user <= 0) {
            $message = "Nama, Email, Role, dan ID Pengguna yang valid harus diisi.";
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Format email tidak valid.";
            $message_type = 'error';
        } elseif (!in_array($role, ['mahasiswa', 'asisten'])) {
            $message = "Role tidak valid.";
            $message_type = 'error';
        } else {
            // Cek apakah email baru (jika diubah) sudah ada untuk pengguna lain
            $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_check_email->bind_param("si", $email, $id_user);
            $stmt_check_email->execute();
            if ($stmt_check_email->get_result()->num_rows > 0) {
                $message = "Email tersebut sudah digunakan oleh pengguna lain.";
                $message_type = 'error';
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt_update = $conn->prepare("UPDATE users SET nama = ?, email = ?, password = ?, role = ? WHERE id = ?");
                    $stmt_update->bind_param("ssssi", $nama, $email, $hashed_password, $role, $id_user);
                } else {
                    // Jangan update password jika field password kosong
                    $stmt_update = $conn->prepare("UPDATE users SET nama = ?, email = ?, role = ? WHERE id = ?");
                    $stmt_update->bind_param("sssi", $nama, $email, $role, $id_user);
                }

                if ($stmt_update->execute()) {
                    $message = "Data pengguna berhasil diperbarui.";
                    $message_type = 'success';
                     // Jika asisten mengedit dirinya sendiri, update session nama
                    if ($id_user == $_SESSION['user_id'] && $_SESSION['nama'] != $nama) {
                        $_SESSION['nama'] = $nama;
                         echo "<script>document.querySelector('aside .text-gray-400').textContent = '".htmlspecialchars(addslashes($nama))."';</script>";
                    }
                } else {
                    $message = "Gagal memperbarui data pengguna: " . $stmt_update->error;
                    $message_type = 'error';
                }
                $stmt_update->close();
            }
            $stmt_check_email->close();
        }
    }
    // Hapus Pengguna
    elseif (isset($_POST['hapus_pengguna'])) {
        $id_user_hapus = (int)$_POST['id_user_hapus'];

        if ($id_user_hapus > 0) {
            if ($id_user_hapus == $_SESSION['user_id']) {
                $message = "Anda tidak dapat menghapus akun Anda sendiri.";
                $message_type = 'error';
            } else {
                // Pertimbangkan konsekuensi penghapusan (misal, data pendaftaran, laporan)
                // ON DELETE CASCADE sudah ada di pendaftaran_praktikum dan laporan_praktikum
                $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt_delete->bind_param("i", $id_user_hapus);
                if ($stmt_delete->execute()) {
                    $message = "Pengguna berhasil dihapus.";
                    $message_type = 'success';
                } else {
                    $message = "Gagal menghapus pengguna: " . $stmt_delete->error . ". Pastikan pengguna tidak memiliki data terkait yang mencegah penghapusan (jika tidak ada ON DELETE CASCADE).";
                    $message_type = 'error';
                }
                $stmt_delete->close();
            }
        }
    }
}

// Ambil semua pengguna
$users_list = [];
$result_users = $conn->query("SELECT id, nama, email, role, created_at FROM users ORDER BY nama ASC");
if ($result_users && $result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users_list[] = $row;
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $pageTitle; ?></h1>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Form Tambah Pengguna -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Tambah Pengguna Baru</h2>
        <form action="manage_users.php" method="post" class="space-y-4">
            <div>
                <label for="nama" class="block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" name="nama" id="nama" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="email" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" id="password" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
            </div>
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                <select name="role" id="role" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                    <option value="mahasiswa">Mahasiswa</option>
                    <option value="asisten">Asisten</option>
                </select>
            </div>
            <div>
                <button type="submit" name="tambah_pengguna" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Tambah Pengguna
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Pengguna -->
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Daftar Pengguna Terdaftar</h2>
        <?php if (!empty($users_list)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Daftar</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users_list as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['nama']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['role'] == 'asisten' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="openEditUserModal(
                                        <?php echo $user['id']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($user['nama'])); ?>',
                                        '<?php echo htmlspecialchars(addslashes($user['email'])); ?>',
                                        '<?php echo htmlspecialchars(addslashes($user['role'])); ?>'
                                    )" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>

                                    <?php if ($user['id'] != $_SESSION['user_id']): // Jangan biarkan user hapus diri sendiri ?>
                                    <form action="manage_users.php" method="post" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini? Semua data terkait (pendaftaran, laporan) juga akan terhapus.');">
                                        <input type="hidden" name="id_user_hapus" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="hapus_pengguna" class="text-red-600 hover:text-red-900">Hapus</button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-gray-400 cursor-not-allowed">Hapus</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500">Belum ada pengguna yang terdaftar.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Edit Pengguna -->
<div id="editUserModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
      <form action="manage_users.php" method="post" class="space-y-4 p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title-user">Edit Pengguna</h3>
        <input type="hidden" name="id_user_edit" id="edit_id_user">
        <div>
            <label for="edit_nama" class="block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
            <input type="text" name="nama_edit" id="edit_nama" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
        </div>
        <div>
            <label for="edit_email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email_edit" id="edit_email" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
        </div>
        <div>
            <label for="edit_password" class="block text-sm font-medium text-gray-700">Password Baru (Opsional)</label>
            <input type="password" name="password_edit" id="edit_password" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Kosongkan jika tidak ingin diubah">
        </div>
        <div>
            <label for="edit_role" class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
            <select name="role_edit" id="edit_role" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                <option value="mahasiswa">Mahasiswa</option>
                <option value="asisten">Asisten</option>
            </select>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-0 sm:flex sm:flex-row-reverse -mx-6 -mb-6 mt-6 rounded-b-lg">
          <button type="submit" name="edit_pengguna" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
            Simpan Perubahan
          </button>
          <button type="button" onclick="closeEditUserModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
            Batal
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditUserModal(id, nama, email, role) {
    document.getElementById('edit_id_user').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_password').value = ''; // Kosongkan field password
    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}
</script>

<?php
$conn->close();
require_once 'templates/footer.php';
?>
