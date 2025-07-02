<?php
$pageTitle = 'Beri Nilai Laporan'; // Akan diupdate
$activePage = 'laporan';
require_once '../config.php';
require_once 'templates/header.php';

// Pastikan asisten sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    header("Location: ../login.php");
    exit();
}

$id_laporan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$message_type = '';

if ($id_laporan <= 0) {
    echo "<div class='container mx-auto p-6'><p class='text-red-500'>ID Laporan tidak valid.</p> <a href='submitted_reports.php' class='text-blue-500 hover:underline'>Kembali ke Daftar Laporan</a></div>";
    require_once 'templates/footer.php';
    exit();
}

// Ambil detail laporan
$sql_laporan = "SELECT lp.*, u.nama as nama_mahasiswa, u.email as email_mahasiswa,
                       m.nama_modul, mp.nama_praktikum
                FROM laporan_praktikum lp
                JOIN users u ON lp.id_mahasiswa = u.id
                JOIN modul m ON lp.id_modul = m.id
                JOIN mata_praktikum mp ON m.id_praktikum = mp.id
                WHERE lp.id = ?";
$stmt_laporan = $conn->prepare($sql_laporan);
$stmt_laporan->bind_param("i", $id_laporan);
$stmt_laporan->execute();
$laporan = $stmt_laporan->get_result()->fetch_assoc();
$stmt_laporan->close();

if (!$laporan) {
    echo "<div class='container mx-auto p-6'><p class='text-red-500'>Laporan tidak ditemukan.</p> <a href='submitted_reports.php' class='text-blue-500 hover:underline'>Kembali ke Daftar Laporan</a></div>";
    require_once 'templates/footer.php';
    $conn->close();
    exit();
}

$pageTitle = 'Nilai: ' . htmlspecialchars($laporan['nama_modul']) . ' - ' . htmlspecialchars($laporan['nama_mahasiswa']);
// Update judul di header
echo "<script>document.querySelector('header h1').textContent = '" . addslashes($pageTitle) . "'; document.title = 'Panel Asisten - " . addslashes($pageTitle) . "';</script>";


// Handle Simpan Nilai
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_nilai'])) {
    $nilai = isset($_POST['nilai']) ? trim($_POST['nilai']) : null; // Bisa kosong jika ingin menghapus nilai
    $feedback = trim($_POST['feedback']);

    // Validasi nilai: harus angka antara 0-100 atau kosong
    if ($nilai !== '' && $nilai !== null && (!is_numeric($nilai) || $nilai < 0 || $nilai > 100)) {
        $message = "Nilai harus berupa angka antara 0 dan 100, atau kosongkan jika belum dinilai.";
        $message_type = 'error';
    } else {
        // Jika nilai dikosongkan, set tanggal_dinilai jadi NULL juga
        $tanggal_dinilai = ($nilai !== '' && $nilai !== null) ? date("Y-m-d H:i:s") : null;
        if ($nilai === '') $nilai = null; // Simpan NULL ke DB jika string kosong

        $stmt_update = $conn->prepare("UPDATE laporan_praktikum SET nilai = ?, feedback = ?, tanggal_dinilai = ? WHERE id = ?");
        $stmt_update->bind_param("issi", $nilai, $feedback, $tanggal_dinilai, $id_laporan);

        if ($stmt_update->execute()) {
            $message = "Nilai dan feedback berhasil disimpan.";
            $message_type = 'success';
            // Refresh data laporan
            $stmt_laporan_refresh = $conn->prepare($sql_laporan);
            $stmt_laporan_refresh->bind_param("i", $id_laporan);
            $stmt_laporan_refresh->execute();
            $laporan = $stmt_laporan_refresh->get_result()->fetch_assoc();
            $stmt_laporan_refresh->close();
        } else {
            $message = "Gagal menyimpan nilai: " . $stmt_update->error;
            $message_type = 'error';
        }
        $stmt_update->close();
    }
}

?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="submitted_reports.php" class="text-blue-600 hover:text-blue-800 hover:underline">&larr; Kembali ke Daftar Laporan</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-lg rounded-lg p-6 md:p-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Mata Praktikum</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($laporan['nama_praktikum']); ?></p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500">Modul</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($laporan['nama_modul']); ?></p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500">Mahasiswa</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($laporan['nama_mahasiswa']); ?></p>
                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($laporan['email_mahasiswa']); ?></p>
            </div>
        </div>

        <div class="border-t border-gray-200 pt-6">
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-500">Tanggal Kumpul</h3>
                <p class="mt-1 text-md text-gray-900"><?php echo date('d M Y, H:i:s', strtotime($laporan['tanggal_kumpul'])); ?></p>
            </div>

            <?php if ($laporan['nama_file_laporan'] && $laporan['path_file_laporan']): ?>
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-500 mb-1">File Laporan</h3>
                <a href="../<?php echo htmlspecialchars($laporan['path_file_laporan']); ?>" download="<?php echo htmlspecialchars($laporan['nama_file_laporan']); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Unduh Laporan (<?php echo htmlspecialchars($laporan['nama_file_laporan']); ?>)
                </a>
            </div>
            <?php else: ?>
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-500">File Laporan</h3>
                <p class="mt-1 text-md text-gray-700 bg-yellow-100 p-3 rounded-md">File laporan tidak ditemukan atau belum diunggah dengan benar.</p>
            </div>
            <?php endif; ?>

            <form action="grade_report.php?id=<?php echo $id_laporan; ?>" method="post">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <label for="nilai" class="block text-sm font-medium text-gray-700">Nilai (0-100)</label>
                        <input type="number" name="nilai" id="nilai" min="0" max="100" step="1" value="<?php echo htmlspecialchars($laporan['nilai'] ?? ''); ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md px-3 py-2">
                        <?php if ($laporan['tanggal_dinilai']): ?>
                             <p class="text-xs text-gray-500 mt-1">Terakhir dinilai: <?php echo date('d M Y, H:i', strtotime($laporan['tanggal_dinilai'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="sm:col-span-6">
                        <label for="feedback" class="block text-sm font-medium text-gray-700">Feedback</label>
                        <textarea id="feedback" name="feedback" rows="4" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border border-gray-300 rounded-md px-3 py-2"><?php echo htmlspecialchars($laporan['feedback'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="submit" name="simpan_nilai" class="ml-3 inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Simpan Penilaian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'templates/footer.php';
?>
