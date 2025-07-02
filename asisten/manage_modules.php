<?php
$pageTitle = 'Manajemen Modul'; // Akan diupdate
$activePage = 'manage_courses'; // Tetap di manage_courses atau buat baru 'manage_modules'
require_once '../config.php';
require_once 'templates/header.php';

// Pastikan asisten sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    header("Location: ../login.php");
    exit();
}

$id_praktikum = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$message = '';
$message_type = '';

if ($id_praktikum <= 0) {
    echo "<div class='container mx-auto p-6'><p class='text-red-500'>ID Mata Praktikum tidak valid.</p> <a href='manage_courses.php' class='text-blue-500 hover:underline'>Kembali ke Manajemen Mata Praktikum</a></div>";
    require_once 'templates/footer.php';
    exit();
}

// Ambil nama mata praktikum untuk judul
$stmt_course_name = $conn->prepare("SELECT nama_praktikum FROM mata_praktikum WHERE id = ?");
$stmt_course_name->bind_param("i", $id_praktikum);
$stmt_course_name->execute();
$course_data = $stmt_course_name->get_result()->fetch_assoc();
if (!$course_data) {
    echo "<div class='container mx-auto p-6'><p class='text-red-500'>Mata Praktikum tidak ditemukan.</p> <a href='manage_courses.php' class='text-blue-500 hover:underline'>Kembali ke Manajemen Mata Praktikum</a></div>";
    require_once 'templates/footer.php';
    exit();
}
$pageTitle = 'Modul: ' . htmlspecialchars($course_data['nama_praktikum']);
// Update judul di header
echo "<script>document.querySelector('header h1').textContent = '" . addslashes($pageTitle) . "'; document.title = 'Panel Asisten - " . addslashes($pageTitle) . "';</script>";
$stmt_course_name->close();


// Handle Aksi Modul (Tambah, Edit, Hapus)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $upload_dir_materi = '../uploads/materi/';
    if (!is_dir($upload_dir_materi)) {
        mkdir($upload_dir_materi, 0777, true);
    }

    // Tambah Modul
    if (isset($_POST['tambah_modul'])) {
        $nama_modul = trim($_POST['nama_modul']);
        $nama_file_materi = null;
        $path_file_materi = null;

        if (!empty($nama_modul)) {
            if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == UPLOAD_ERR_OK) {
                $file_tmp_path = $_FILES['file_materi']['tmp_name'];
                $file_name = basename($_FILES['file_materi']['name']);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar'];

                if (in_array($file_ext, $allowed_ext)) {
                    $new_file_name = "materi_" . $id_praktikum . "_" . time() . "_" . $file_name;
                    $dest_path = $upload_dir_materi . $new_file_name;
                    if (move_uploaded_file($file_tmp_path, $dest_path)) {
                        $nama_file_materi = $file_name;
                        $path_file_materi = 'uploads/materi/' . $new_file_name; // Path relatif dari root proyek
                    } else {
                        $message = "Gagal memindahkan file materi.";
                        $message_type = 'error';
                    }
                } else {
                    $message = "Format file materi tidak diizinkan.";
                    $message_type = 'error';
                }
            } elseif ($_FILES['file_materi']['error'] != UPLOAD_ERR_NO_FILE) {
                 $message = "Error saat upload file materi: code " . $_FILES['file_materi']['error'];
                 $message_type = 'error';
            }

            if (empty($message)) { // Lanjutkan jika tidak ada error upload
                $stmt = $conn->prepare("INSERT INTO modul (id_praktikum, nama_modul, nama_file_materi, path_file_materi) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $id_praktikum, $nama_modul, $nama_file_materi, $path_file_materi);
                if ($stmt->execute()) {
                    $message = "Modul berhasil ditambahkan.";
                    $message_type = 'success';
                } else {
                    $message = "Gagal menambahkan modul: " . $stmt->error;
                    $message_type = 'error';
                    if ($path_file_materi && file_exists('../'.$path_file_materi)) unlink('../'.$path_file_materi); // Hapus file jika db gagal
                }
                $stmt->close();
            }
        } else {
            $message = "Nama modul tidak boleh kosong.";
            $message_type = 'error';
        }
    }
    // Edit Modul
    elseif (isset($_POST['edit_modul'])) {
        $id_modul = (int)$_POST['id_modul_edit'];
        $nama_modul = trim($_POST['nama_modul_edit']);
        $path_file_materi_lama = $_POST['path_file_materi_lama_edit'];
        $nama_file_materi_baru = null;
        $path_file_materi_baru = $path_file_materi_lama; // Default ke file lama

        if (!empty($nama_modul) && $id_modul > 0) {
            if (isset($_FILES['file_materi_edit']) && $_FILES['file_materi_edit']['error'] == UPLOAD_ERR_OK) {
                $file_tmp_path = $_FILES['file_materi_edit']['tmp_name'];
                $file_name = basename($_FILES['file_materi_edit']['name']);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar'];

                if (in_array($file_ext, $allowed_ext)) {
                    $new_file_name = "materi_" . $id_praktikum . "_" . time() . "_" . $file_name;
                    $dest_path_new = $upload_dir_materi . $new_file_name;
                    if (move_uploaded_file($file_tmp_path, $dest_path_new)) {
                        // Hapus file lama jika ada dan berbeda
                        if ($path_file_materi_lama && file_exists('../'.$path_file_materi_lama) && $path_file_materi_lama != ('uploads/materi/' . $new_file_name)) {
                            unlink('../'.$path_file_materi_lama);
                        }
                        $nama_file_materi_baru = $file_name;
                        $path_file_materi_baru = 'uploads/materi/' . $new_file_name;
                    } else {
                        $message = "Gagal memindahkan file materi baru.";
                        $message_type = 'error';
                    }
                } else {
                    $message = "Format file materi baru tidak diizinkan.";
                    $message_type = 'error';
                }
            } elseif (isset($_POST['hapus_file_materi_edit_checkbox']) && $_POST['hapus_file_materi_edit_checkbox'] == '1') {
                if ($path_file_materi_lama && file_exists('../'.$path_file_materi_lama)) {
                    unlink('../'.$path_file_materi_lama);
                }
                $nama_file_materi_baru = null;
                $path_file_materi_baru = null;
            } elseif ($_FILES['file_materi_edit']['error'] != UPLOAD_ERR_NO_FILE) {
                $message = "Error saat upload file materi baru: code " . $_FILES['file_materi_edit']['error'];
                $message_type = 'error';
            }


            if (empty($message)) {
                $stmt = $conn->prepare("UPDATE modul SET nama_modul = ?, nama_file_materi = ?, path_file_materi = ? WHERE id = ? AND id_praktikum = ?");
                $stmt->bind_param("sssii", $nama_modul, $nama_file_materi_baru, $path_file_materi_baru, $id_modul, $id_praktikum);
                if ($stmt->execute()) {
                    $message = "Modul berhasil diperbarui.";
                    $message_type = 'success';
                } else {
                    $message = "Gagal memperbarui modul: " . $stmt->error;
                    $message_type = 'error';
                     // Jika path baru dibuat tapi DB gagal, hapus file baru itu
                    if ($path_file_materi_baru != $path_file_materi_lama && $path_file_materi_baru && file_exists('../'.$path_file_materi_baru) ) {
                         unlink('../'.$path_file_materi_baru);
                    }
                }
                $stmt->close();
            }
        } else {
            $message = "Nama modul tidak boleh kosong dan ID valid diperlukan.";
            $message_type = 'error';
        }
    }
    // Hapus Modul
    elseif (isset($_POST['hapus_modul'])) {
        $id_modul = (int)$_POST['id_modul_hapus'];
        $path_file_materi_hapus = $_POST['path_file_materi_hapus'];

        if ($id_modul > 0) {
            $stmt = $conn->prepare("DELETE FROM modul WHERE id = ? AND id_praktikum = ?");
            $stmt->bind_param("ii", $id_modul, $id_praktikum);
            if ($stmt->execute()) {
                if ($path_file_materi_hapus && file_exists('../'.$path_file_materi_hapus)) {
                    unlink('../'.$path_file_materi_hapus);
                }
                $message = "Modul berhasil dihapus.";
                $message_type = 'success';
            } else {
                $message = "Gagal menghapus modul: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Ambil semua modul untuk mata praktikum ini
$modul_list = [];
$stmt_modul = $conn->prepare("SELECT id, nama_modul, nama_file_materi, path_file_materi FROM modul WHERE id_praktikum = ? ORDER BY id ASC");
$stmt_modul->bind_param("i", $id_praktikum);
$stmt_modul->execute();
$result_modul = $stmt_modul->get_result();
if ($result_modul && $result_modul->num_rows > 0) {
    while ($row = $result_modul->fetch_assoc()) {
        $modul_list[] = $row;
    }
}
$stmt_modul->close();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Tombol kembali sudah diatur di header.php, tapi bisa ditambahkan di sini juga jika perlu -->
     <div class="mb-6">
        <a href="manage_courses.php" class="text-blue-600 hover:text-blue-800 hover:underline">&larr; Kembali ke Manajemen Mata Praktikum</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Form Tambah Modul -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Tambah Modul Baru</h2>
        <form action="manage_modules.php?course_id=<?php echo $id_praktikum; ?>" method="post" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="nama_modul" class="block text-sm font-medium text-gray-700 mb-1">Nama Modul <span class="text-red-500">*</span></label>
                <input type="text" name="nama_modul" id="nama_modul" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
            </div>
            <div class="mb-4">
                <label for="file_materi" class="block text-sm font-medium text-gray-700 mb-1">File Materi (Opsional)</label>
                <input type="file" name="file_materi" id="file_materi" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-500 mt-1">Format: PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR.</p>
            </div>
            <div>
                <button type="submit" name="tambah_modul" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Tambah Modul
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Modul -->
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Daftar Modul</h2>
        <?php if (!empty($modul_list)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Modul</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Materi</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($modul_list as $modul): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($modul['nama_modul']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($modul['nama_file_materi'] && $modul['path_file_materi']): ?>
                                        <a href="../<?php echo htmlspecialchars($modul['path_file_materi']); ?>" download="<?php echo htmlspecialchars($modul['nama_file_materi']); ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($modul['nama_file_materi']); ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="openEditModulModal(
                                        <?php echo $modul['id']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($modul['nama_modul'])); ?>',
                                        '<?php echo htmlspecialchars(addslashes($modul['nama_file_materi'] ?? '')); ?>',
                                        '<?php echo htmlspecialchars(addslashes($modul['path_file_materi'] ?? '')); ?>'
                                    )" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <form action="manage_modules.php?course_id=<?php echo $id_praktikum; ?>" method="post" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus modul ini? Semua laporan terkait juga akan terhapus jika ada cascade.');">
                                        <input type="hidden" name="id_modul_hapus" value="<?php echo $modul['id']; ?>">
                                        <input type="hidden" name="path_file_materi_hapus" value="<?php echo htmlspecialchars($modul['path_file_materi'] ?? ''); ?>">
                                        <button type="submit" name="hapus_modul" class="text-red-600 hover:text-red-900">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500">Belum ada modul yang ditambahkan untuk mata praktikum ini.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Edit Modul -->
<div id="editModulModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
      <form action="manage_modules.php?course_id=<?php echo $id_praktikum; ?>" method="post" enctype="multipart/form-data">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title-modul">Edit Modul</h3>
              <div class="mt-2">
                <input type="hidden" name="id_modul_edit" id="edit_id_modul">
                <input type="hidden" name="path_file_materi_lama_edit" id="edit_path_file_materi_lama">
                <div class="mb-4">
                    <label for="edit_nama_modul" class="block text-sm font-medium text-gray-700 mb-1">Nama Modul <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_modul_edit" id="edit_nama_modul" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                </div>
                <div class="mb-4">
                    <label for="file_materi_edit" class="block text-sm font-medium text-gray-700 mb-1">File Materi Baru (Opsional)</label>
                    <input type="file" name="file_materi_edit" id="file_materi_edit" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah file materi. Format: PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR.</p>
                </div>
                 <div class="mb-4" id="current_file_display_edit_container">
                    <p class="text-sm text-gray-600">File saat ini: <a href="#" id="current_file_link_edit" class="text-blue-500 hover:underline" target="_blank"></a></p>
                    <label for="hapus_file_materi_edit_checkbox" class="inline-flex items-center mt-1">
                        <input type="checkbox" name="hapus_file_materi_edit_checkbox" id="hapus_file_materi_edit_checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-600">Hapus file materi saat ini</span>
                    </label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button type="submit" name="edit_modul" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
            Simpan Perubahan
          </button>
          <button type="button" onclick="closeEditModulModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
            Batal
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditModulModal(id, nama, namaFile, pathFile) {
    document.getElementById('edit_id_modul').value = id;
    document.getElementById('edit_nama_modul').value = nama;
    document.getElementById('edit_path_file_materi_lama').value = pathFile;

    const fileLinkElement = document.getElementById('current_file_link_edit');
    const fileDisplayContainer = document.getElementById('current_file_display_edit_container');
    const deleteCheckbox = document.getElementById('hapus_file_materi_edit_checkbox');

    deleteCheckbox.checked = false; // Reset checkbox

    if (namaFile && pathFile) {
        fileLinkElement.textContent = namaFile;
        fileLinkElement.href = '../' + pathFile; // Path relatif dari root
        fileDisplayContainer.classList.remove('hidden');
    } else {
        fileDisplayContainer.classList.add('hidden');
        fileLinkElement.textContent = '';
        fileLinkElement.href = '#';
    }

    document.getElementById('file_materi_edit').value = ''; // Clear file input
    document.getElementById('editModulModal').classList.remove('hidden');
}

function closeEditModulModal() {
    document.getElementById('editModulModal').classList.add('hidden');
}
</script>

<?php
$conn->close();
require_once 'templates/footer.php';
?>
