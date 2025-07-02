<?php
require_once '../config.php';
header('Content-Type: application/json');

// Pastikan asisten sudah login (opsional, tergantung kebijakan keamanan, tapi sebaiknya ada)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    echo json_encode(['error' => 'Akses ditolak']);
    exit();
}

$praktikum_id = isset($_GET['praktikum_id']) ? (int)$_GET['praktikum_id'] : 0;
$modules = [];

if ($praktikum_id > 0) {
    $stmt = $conn->prepare("SELECT id, nama_modul FROM modul WHERE id_praktikum = ? ORDER BY nama_modul ASC");
    $stmt->bind_param("i", $praktikum_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $modules[] = [
            'id' => $row['id'],
            'nama_modul' => htmlspecialchars($row['nama_modul']) // Sanitasi output
        ];
    }
    $stmt->close();
}

echo json_encode($modules);
$conn->close();
?>
