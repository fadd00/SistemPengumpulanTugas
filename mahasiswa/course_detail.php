<?php
$pageTitle = 'Detail Praktikum'; // Akan diupdate dengan nama praktikum
$activePage = 'my_courses'; // Tetap aktif di 'Praktikum Saya'
require_once '../config.php';
require_once 'templates/header_mahasiswa.php';

// Pastikan mahasiswa sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

$id_mahasiswa = $_SESSION['user_id'];
$id_praktikum = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_praktikum <= 0) {
    echo "<div class='container mx-auto p-6'><p class='text-red-500'>ID Praktikum tidak valid.</p></div>";
    require_once 'templates/footer_mahasiswa.php';
    exit();
}

// 1. Verifikasi apakah mahasiswa terdaftar di praktikum ini
$stmt_check_enrollment = $conn->prepare("SELECT id FROM pendaftaran_praktikum WHERE id_mahasiswa = ? AND id_praktikum = ?");
$stmt_check_enrollment->bind_param("ii", $id_mahasiswa, $id_praktikum);
$stmt_check_enrollment->execute();
$result_check_enrollment = $stmt_check_enrollment->get_result();
if ($result_check_enrollment->num_rows == 0) {
    echo "<div class='container mx-auto p-6'><p class='text-red-500'>Anda tidak terdaftar pada mata praktikum ini atau praktikum tidak ditemukan.</p></div>";
    require_once 'templates/footer_mahasiswa.php';
    $stmt_check_enrollment->close();
    $conn->close();
    exit();
}
$stmt_check_enrollment->close();

// 2. Ambil detail mata praktikum
$stmt_praktikum = $conn->prepare("SELECT nama_praktikum, deskripsi FROM mata_praktikum WHERE id = ?");
$stmt_praktikum->bind_param("i", $id_praktikum);
$stmt_praktikum->execute();
$praktikum = $stmt_praktikum->get_result()->fetch_assoc();
$stmt_praktikum->close();

if (!$praktikum) {
    echo "<div class='container mx-auto p-6'><p class='text-red-500'>Mata praktikum tidak ditemukan.</p></div>";
    require_once 'templates/footer_mahasiswa.php';
    $conn->close();
    exit();
}
$pageTitle = htmlspecialchars($praktikum['nama_praktikum']); // Update page title

// 3. Ambil modul untuk praktikum ini beserta status pengumpulan mahasiswa
$sql_modul = "SELECT m.id as id_modul, m.nama_modul, m.nama_file_materi, m.path_file_materi,
              lp.id as id_laporan, lp.nama_file_laporan, lp.path_file_laporan, lp.nilai, lp.feedback, lp.tanggal_kumpul, lp.tanggal_dinilai
              FROM modul m
              LEFT JOIN laporan_praktikum lp ON m.id = lp.id_modul AND lp.id_mahasiswa = ?
              WHERE m.id_praktikum = ?
              ORDER BY m.id ASC";
$stmt_modul = $conn->prepare($sql_modul);
$stmt_modul->bind_param("ii", $id_mahasiswa, $id_praktikum);
$stmt_modul->execute();
$result_modul = $stmt_modul->get_result();
$modul_list = [];
if ($result_modul->num_rows > 0) {
    while ($row = $result_modul->fetch_assoc()) {
        $modul_list[] = $row;
    }
}
$stmt_modul->close();

// Handle upload laporan
$upload_message = '';
$upload_message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kumpul_laporan'])) {
    $id_modul_laporan = (int)$_POST['id_modul'];

    if (isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] == UPLOAD_ERR_OK) {
        $upload_dir_laporan = '../uploads/laporan/';
        if (!is_dir($upload_dir_laporan)) {
            mkdir($upload_dir_laporan, 0777, true);
        }

        $file_tmp_path = $_FILES['file_laporan']['tmp_name'];
        $file_name = basename($_FILES['file_laporan']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'doc', 'docx', 'zip', 'rar'];

        if (in_array($file_ext, $allowed_ext)) {
            // Buat nama file unik untuk menghindari overwrite
            $new_file_name = "laporan_" . $id_mahasiswa . "_" . $id_modul_laporan . "_" . time() . "." . $file_ext;
            $dest_path = $upload_dir_laporan . $new_file_name;

            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                // Simpan ke database
                // Cek dulu apakah sudah ada laporan, jika ada, update. Jika tidak, insert.
                $stmt_check_laporan = $conn->prepare("SELECT id, path_file_laporan FROM laporan_praktikum WHERE id_modul = ? AND id_mahasiswa = ?");
                $stmt_check_laporan->bind_param("ii", $id_modul_laporan, $id_mahasiswa);
                $stmt_check_laporan->execute();
                $existing_laporan = $stmt_check_laporan->get_result()->fetch_assoc();
                $stmt_check_laporan->close();

                if ($existing_laporan) {
                    // Hapus file lama jika ada
                    if ($existing_laporan['path_file_laporan'] && file_exists($existing_laporan['path_file_laporan'])) {
                        unlink($existing_laporan['path_file_laporan']);
                    }
                    $stmt_update_laporan = $conn->prepare("UPDATE laporan_praktikum SET nama_file_laporan = ?, path_file_laporan = ?, tanggal_kumpul = NOW(), nilai = NULL, feedback = NULL, tanggal_dinilai = NULL WHERE id = ?");
                    $stmt_update_laporan->bind_param("ssi", $file_name, $dest_path, $existing_laporan['id']);
                    if ($stmt_update_laporan->execute()) {
                        $upload_message = "Laporan berhasil diperbarui.";
                        $upload_message_type = 'success';
                    } else {
                        $upload_message = "Gagal memperbarui laporan di database: " . $stmt_update_laporan->error;
                        $upload_message_type = 'error';
                        if (file_exists($dest_path)) unlink($dest_path); // Hapus file jika db gagal
                    }
                    $stmt_update_laporan->close();
                } else {
                    $stmt_insert_laporan = $conn->prepare("INSERT INTO laporan_praktikum (id_modul, id_mahasiswa, nama_file_laporan, path_file_laporan, tanggal_kumpul) VALUES (?, ?, ?, ?, NOW())");
                    $stmt_insert_laporan->bind_param("iiss", $id_modul_laporan, $id_mahasiswa, $file_name, $dest_path);
                    if ($stmt_insert_laporan->execute()) {
                        $upload_message = "Laporan berhasil dikumpulkan.";
                        $upload_message_type = 'success';
                    } else {
                        $upload_message = "Gagal menyimpan laporan ke database: " . $stmt_insert_laporan->error;
                        $upload_message_type = 'error';
                         if (file_exists($dest_path)) unlink($dest_path); // Hapus file jika db gagal
                    }
                    $stmt_insert_laporan->close();
                }
                 // Refresh modul list
                $stmt_modul_refresh = $conn->prepare($sql_modul);
                $stmt_modul_refresh->bind_param("ii", $id_mahasiswa, $id_praktikum);
                $stmt_modul_refresh->execute();
                $result_modul_refresh = $stmt_modul_refresh->get_result();
                $modul_list = [];
                if ($result_modul_refresh->num_rows > 0) {
                    while ($row = $result_modul_refresh->fetch_assoc()) {
                        $modul_list[] = $row;
                    }
                }
                $stmt_modul_refresh->close();

            } else {
                $upload_message = "Gagal memindahkan file yang diunggah.";
                $upload_message_type = 'error';
            }
        } else {
            $upload_message = "Format file tidak diizinkan. Hanya PDF, DOC, DOCX, ZIP, RAR yang diperbolehkan.";
            $upload_message_type = 'error';
        }
    } else {
        $upload_message = "Terjadi kesalahan saat mengunggah file atau tidak ada file yang dipilih. Error code: ".$_FILES['file_laporan']['error'];
        $upload_message_type = 'error';
    }
}

// Update page title in header since it might have changed
echo "<script>document.title = 'Panel Mahasiswa - " . htmlspecialchars($praktikum['nama_praktikum']) . "';</script>";
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-lg rounded-lg p-6 mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></h1>
        <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($praktikum['deskripsi'] ?? 'Tidak ada deskripsi.')); ?></p>
    </div>

    <?php if (!empty($upload_message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $upload_message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($upload_message); ?>
        </div>
    <?php endif; ?>

    <h2 class="text-2xl font-semibold text-gray-700 mb-6">Modul Praktikum</h2>

    <?php if (!empty($modul_list)): ?>
        <div class="space-y-6">
            <?php foreach ($modul_list as $index => $modul): ?>
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                        <h3 class="text-xl font-semibold text-blue-600"><?php echo ($index + 1) . ". " . htmlspecialchars($modul['nama_modul']); ?></h3>
                        <?php if ($modul['nama_file_materi'] && $modul['path_file_materi']): ?>
                            <a href="../<?php echo htmlspecialchars($modul['path_file_materi']); ?>" download="<?php echo htmlspecialchars($modul['nama_file_materi']); ?>" class="mt-2 md:mt-0 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md text-sm inline-flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Unduh Materi
                            </a>
                        <?php else: ?>
                            <span class="mt-2 md:mt-0 text-sm text-gray-400">Materi belum tersedia</span>
                        <?php endif; ?>
                    </div>

                    <hr class="my-4">

                    <div>
                        <h4 class="text-md font-semibold text-gray-700 mb-2">Pengumpulan Laporan:</h4>
                        <?php if ($modul['id_laporan']): // Jika sudah ada laporan ?>
                            <div class="bg-gray-50 p-4 rounded-md mb-3">
                                <p class="text-sm text-gray-600">
                                    Anda telah mengumpulkan:
                                    <a href="../<?php echo htmlspecialchars($modul['path_file_laporan']); ?>" download="<?php echo htmlspecialchars($modul['nama_file_laporan']); ?>" class="text-blue-500 hover:underline font-medium">
                                        <?php echo htmlspecialchars($modul['nama_file_laporan']); ?>
                                    </a>
                                    (pada <?php echo date('d M Y, H:i', strtotime($modul['tanggal_kumpul'])); ?>).
                                </p>
                                <?php if ($modul['tanggal_dinilai']): ?>
                                    <div class="mt-2 p-3 bg-green-50 border border-green-200 rounded-md">
                                        <p class="text-sm font-semibold text-green-700">Nilai: <?php echo htmlspecialchars($modul['nilai']); ?></p>
                                        <?php if ($modul['feedback']): ?>
                                            <p class="text-sm text-gray-600 mt-1"><strong>Feedback:</strong> <?php echo nl2br(htmlspecialchars($modul['feedback'])); ?></p>
                                        <?php endif; ?>
                                         <p class="text-xs text-gray-500 mt-1">Dinilai pada: <?php echo date('d M Y, H:i', strtotime($modul['tanggal_dinilai'])); ?></p>
                                    </div>
                                <?php else: ?>
                                    <p class="text-sm text-yellow-600 mt-1">Laporan Anda sedang menunggu penilaian.</p>
                                <?php endif; ?>
                            </div>
                            <form action="course_detail.php?id=<?php echo $id_praktikum; ?>" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="id_modul" value="<?php echo $modul['id_modul']; ?>">
                                <label for="file_laporan_<?php echo $modul['id_modul']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Unggah Ulang Laporan (jika perlu):</label>
                                <div class="flex items-center space-x-2">
                                    <input type="file" name="file_laporan" id="file_laporan_<?php echo $modul['id_modul']; ?>" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                                    <button type="submit" name="kumpul_laporan" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-md text-sm">Kirim Ulang</button>
                                </div>
                            </form>
                        <?php else: // Jika belum ada laporan ?>
                            <form action="course_detail.php?id=<?php echo $id_praktikum; ?>" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="id_modul" value="<?php echo $modul['id_modul']; ?>">
                                <label for="file_laporan_<?php echo $modul['id_modul']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Unggah File Laporan Anda:</label>
                                <div class="flex items-center space-x-2">
                                    <input type="file" name="file_laporan" id="file_laporan_<?php echo $modul['id_modul']; ?>" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                                    <button type="submit" name="kumpul_laporan" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md text-sm">Kumpulkan</button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Format yang diizinkan: PDF, DOC, DOCX, ZIP, RAR.</p>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
            <p class="font-bold">Informasi</p>
            <p>Belum ada modul yang ditambahkan untuk mata praktikum ini.</p>
        </div>
    <?php endif; ?>

    <div class="mt-8">
        <a href="my_courses.php" class="text-blue-600 hover:text-blue-800 hover:underline">&larr; Kembali ke Praktikum Saya</a>
    </div>
</div>

<?php
$conn->close();
require_once 'templates/footer_mahasiswa.php';
?>
