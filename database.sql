CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('mahasiswa','asisten') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `mata_praktikum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_praktikum` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `modul` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_praktikum` int(11) NOT NULL,
  `nama_modul` varchar(255) NOT NULL,
  `nama_file_materi` varchar(255) DEFAULT NULL,
  `path_file_materi` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_praktikum`) REFERENCES `mata_praktikum`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pendaftaran_praktikum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_mahasiswa` int(11) NOT NULL,
  `id_praktikum` int(11) NOT NULL,
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_mahasiswa`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_praktikum`) REFERENCES `mata_praktikum`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_pendaftaran` (`id_mahasiswa`, `id_praktikum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `laporan_praktikum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_modul` int(11) NOT NULL,
  `id_mahasiswa` int(11) NOT NULL,
  `nama_file_laporan` varchar(255) DEFAULT NULL,
  `path_file_laporan` varchar(255) DEFAULT NULL,
  `nilai` int(3) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `tanggal_kumpul` timestamp NOT NULL DEFAULT current_timestamp(),
  `tanggal_dinilai` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_modul`) REFERENCES `modul`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_mahasiswa`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Data (Optional, for testing)
-- Users
INSERT INTO `users` (`nama`, `email`, `password`, `role`) VALUES
('Mahasiswa Satu', 'mahasiswa1@test.com', '$2y$10$examplehashedpassword1', 'mahasiswa'),
('Mahasiswa Dua', 'mahasiswa2@test.com', '$2y$10$examplehashedpassword2', 'mahasiswa'),
('Asisten Satu', 'asisten1@test.com', '$2y$10$examplehashedpasswordA1', 'asisten');

-- Mata Praktikum
INSERT INTO `mata_praktikum` (`nama_praktikum`, `deskripsi`) VALUES
('Pemrograman Web', 'Mata praktikum dasar-dasar pengembangan web dengan HTML, CSS, JavaScript, dan PHP.'),
('Jaringan Komputer', 'Mata praktikum yang membahas konsep dasar jaringan komputer, topologi, dan protokol.');

-- Modul (Contoh untuk Pemrograman Web)
INSERT INTO `modul` (`id_praktikum`, `nama_modul`) VALUES
((SELECT id FROM mata_praktikum WHERE nama_praktikum = 'Pemrograman Web'), 'Modul 1: HTML Dasar'),
((SELECT id FROM mata_praktikum WHERE nama_praktikum = 'Pemrograman Web'), 'Modul 2: CSS Styling'),
((SELECT id FROM mata_praktikum WHERE nama_praktikum = 'Pemrograman Web'), 'Modul 3: JavaScript Interaktif');

-- Modul (Contoh untuk Jaringan Komputer)
INSERT INTO `modul` (`id_praktikum`, `nama_modul`) VALUES
((SELECT id FROM mata_praktikum WHERE nama_praktikum = 'Jaringan Komputer'), 'Modul 1: Pengenalan Jaringan'),
((SELECT id FROM mata_praktikum WHERE nama_praktikum = 'Jaringan Komputer'), 'Modul 2: Topologi Jaringan');

-- Pendaftaran Praktikum (Contoh)
INSERT INTO `pendaftaran_praktikum` (`id_mahasiswa`, `id_praktikum`) VALUES
((SELECT id FROM users WHERE email = 'mahasiswa1@test.com'), (SELECT id FROM mata_praktikum WHERE nama_praktikum = 'Pemrograman Web')),
((SELECT id FROM users WHERE email = 'mahasiswa1@test.com'), (SELECT id FROM mata_praktikum WHERE nama_praktikum = 'Jaringan Komputer')),
((SELECT id FROM users WHERE email = 'mahasiswa2@test.com'), (SELECT id FROM mata_praktikum WHERE nama_praktikum = 'Pemrograman Web'));

-- Laporan Praktikum (Contoh, Mahasiswa 1, Modul 1 Pemrograman Web)
INSERT INTO `laporan_praktikum` (`id_modul`, `id_mahasiswa`, `nama_file_laporan`, `path_file_laporan`, `nilai`, `feedback`, `tanggal_dinilai`) VALUES
((SELECT m.id FROM modul m JOIN mata_praktikum mp ON m.id_praktikum = mp.id WHERE mp.nama_praktikum = 'Pemrograman Web' AND m.nama_modul = 'Modul 1: HTML Dasar'),
 (SELECT id FROM users WHERE email = 'mahasiswa1@test.com'),
 'laporan_mhs1_modul1.pdf',
 'uploads/laporan/laporan_mhs1_modul1.pdf',
 85,
 'Pekerjaan bagus, namun perhatikan lagi penggunaan semantic HTML tags.',
 NOW());
