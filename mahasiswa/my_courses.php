<?php
$pageTitle = 'Praktikum Saya';
$activePage = 'my_courses';
require_once '../config.php'; // Koneksi ke database
require_once 'templates/header_mahasiswa.php';

// Pastikan mahasiswa sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    // Jika belum login atau bukan mahasiswa, redirect ke halaman login
    header("Location: ../login.php");
    exit();
}

$id_mahasiswa = $_SESSION['user_id'];

// Ambil daftar mata praktikum yang diikuti oleh mahasiswa
$sql = "SELECT mp.id, mp.nama_praktikum, mp.deskripsi
        FROM mata_praktikum mp
        JOIN pendaftaran_praktikum pp ON mp.id = pp.id_praktikum
        WHERE pp.id_mahasiswa = ?
        ORDER BY mp.nama_praktikum ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_mahasiswa);
$stmt->execute();
$result = $stmt->get_result();
$praktikum_diikuti = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $praktikum_diikuti[] = $row;
    }
}
$stmt->close();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $pageTitle; ?></h1>

    <?php if (!empty($praktikum_diikuti)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($praktikum_diikuti as $praktikum): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col">
                    <div class="p-6 flex-grow">
                        <h2 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></h2>
                        <p class="text-gray-600 text-sm mb-4">
                            <?php echo nl2br(htmlspecialchars($praktikum['deskripsi'] ?? 'Tidak ada deskripsi.')); ?>
                        </p>
                    </div>
                    <div class="p-6 bg-gray-50">
                        <a href="course_detail.php?id=<?php echo $praktikum['id']; ?>" class="block w-full text-center bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md transition duration-150">
                            Lihat Detail & Tugas
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4" role="alert">
            <p class="font-bold">Informasi</p>
            <p>Anda belum terdaftar pada mata praktikum apapun. Silakan <a href="courses.php" class="font-semibold hover:underline">cari praktikum</a> untuk mendaftar.</p>
        </div>
    <?php endif; ?>

</div>

<?php
$conn->close();
require_once 'templates/footer_mahasiswa.php';
?>
