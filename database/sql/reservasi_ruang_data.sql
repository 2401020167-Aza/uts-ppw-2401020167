USE reservasi_ruang_2401020167;

INSERT INTO ruang (nama_ruang) VALUES
    ('Ruang Rapat A'),
    ('Ruang Seminar')
ON DUPLICATE KEY UPDATE nama_ruang = VALUES(nama_ruang);

INSERT INTO reservasi_ruang
    (kode_reservasi, nama_peminjam, email, durasi_jam, ruang_id)
VALUES
    ('RS00000001', 'Andi Saputra', 'andi.r@example.com', 2, 1),
    ('RS00000002', 'Siti Rahma', 'siti.r@example.com', 3, 2),
    ('RS00000003', 'Budi Pratama', 'budi.r@example.com', 1, 1)
ON DUPLICATE KEY UPDATE
    nama_peminjam = VALUES(nama_peminjam),
    email = VALUES(email),
    durasi_jam = VALUES(durasi_jam),
    ruang_id = VALUES(ruang_id);

