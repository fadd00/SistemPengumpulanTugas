<?php
$pageTitle = 'Cari Praktikum';
$activePage = 'courses';
require_once '../config.php'; // Koneksi ke database
require_once 'templates/header_mahasiswa.php';

// Ambil semua mata praktikum dari database
$sql = "SELECT id, nama_praktikum, deskripsi FROM mata_praktikum ORDER BY nama_praktikum ASC";
$result = $conn->query($sql);
$mata_praktikum_list = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $mata_praktikum_list[] = $row;
    }
}

// Inisialisasi pesan
$message = '';
$message_type = ''; // 'success' atau 'error'

// Logika untuk pendaftaran praktikum
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['daftar_praktikum'])) {
    if (!isset($_SESSION['user_id'])) {
        // Seharusnya tidak terjadi karena tombol daftar hanya muncul jika login,
        // tapi sebagai pengaman tambahan
        header("Location: ../login.php?redirect=courses.php");
        exit();
    }

    $id_praktikum = $_POST['id_praktikum'];
    $id_mahasiswa = $_SESSION['user_id'];

    // Cek apakah mahasiswa sudah terdaftar
    $cek_sql = "SELECT id FROM pendaftaran_praktikum WHERE id_mahasiswa = ? AND id_praktikum = ?";
    $cek_stmt = $conn->prepare($cek_sql);
    $cek_stmt->bind_param("ii", $id_mahasiswa, $id_praktikum);
    $cek_stmt->execute();
    $cek_result = $cek_stmt->get_result();

    if ($cek_result->num_rows > 0) {
        $message = "Anda sudah terdaftar pada mata praktikum ini.";
        $message_type = 'error';
    } else {
        // Daftarkan mahasiswa
        $daftar_sql = "INSERT INTO pendaftaran_praktikum (id_mahasiswa, id_praktikum) VALUES (?, ?)";
        $daftar_stmt = $conn->prepare($daftar_sql);
        $daftar_stmt->bind_param("ii", $id_mahasiswa, $id_praktikum);
        if ($daftar_stmt->execute()) {
            $message = "Berhasil mendaftar mata praktikum!";
            $message_type = 'success';
        } else {
            $message = "Gagal mendaftar mata praktikum. Silakan coba lagi. Error: " . $daftar_stmt->error;
            $message_type = 'error';
        }
        $daftar_stmt->close();
    }
    $cek_stmt->close();
}

?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $pageTitle; ?></h1>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mata_praktikum_list)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($mata_praktikum_list as $praktikum): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></h2>
                        <p class="text-gray-600 text-sm mb-4">
                            <?php echo nl2br(htmlspecialchars($praktikum['deskripsi'] ?? 'Tidak ada deskripsi.')); ?>
                        </p>

                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'mahasiswa'): ?>
                            <?php
                            // Cek lagi apakah sudah terdaftar untuk menampilkan status tombol yang sesuai
                            $id_praktikum_current = $praktikum['id'];
                            $id_mahasiswa_current = $_SESSION['user_id'];
                            $stmt_check_enroll = $conn->prepare("SELECT id FROM pendaftaran_praktikum WHERE id_mahasiswa = ? AND id_praktikum = ?");
                            $stmt_check_enroll->bind_param("ii", $id_mahasiswa_current, $id_praktikum_current);
                            $stmt_check_enroll->execute();
                            $is_enrolled = $stmt_check_enroll->get_result()->num_rows > 0;
                            $stmt_check_enroll->close();
                            ?>
                            <?php if ($is_enrolled): ?>
                                <button class="w-full bg-green-500 text-white font-semibold py-2 px-4 rounded-md" disabled>Sudah Terdaftar</button>
                            <?php else: ?>
                                <form action="courses.php" method="post">
                                    <input type="hidden" name="id_praktikum" value="<?php echo $praktikum['id']; ?>">
                                    <button type="submit" name="daftar_praktikum" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md transition duration-150">
                                        Daftar Praktikum
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php elseif (!isset($_SESSION['user_id'])): ?>
                             <a href="../login.php?redirect=mahasiswa/courses.php" class="block w-full text-center bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-md transition duration-150">
                                Login untuk Mendaftar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
            <p class="font-bold">Informasi</p>
            <p>Saat ini belum ada mata praktikum yang tersedia.</p>
        </div>
    <?php endif; ?>

</div>

<?php
$conn->close();
require_once 'templates/footer_mahasiswa.php';
?>
