<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Siapkan skema tabel ruang & reservasi_ruang untuk lingkungan testing (kompatibel SQLite & MySQL)
    DB::statement('CREATE TABLE IF NOT EXISTS ruang (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama_ruang VARCHAR(100) NOT NULL UNIQUE
    )');

    DB::statement('CREATE TABLE IF NOT EXISTS reservasi_ruang (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kode_reservasi CHAR(10) NOT NULL UNIQUE,
        nama_peminjam VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        durasi_jam TINYINT NOT NULL,
        ruang_id INTEGER NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');

    // Bersihkan data lama
    DB::statement('DELETE FROM reservasi_ruang');
    DB::statement('DELETE FROM ruang');

    // Data awal wajib
    DB::statement("INSERT INTO ruang (id, nama_ruang) VALUES (1, 'Ruang Rapat A'), (2, 'Ruang Seminar')");
    DB::statement("INSERT INTO reservasi_ruang (id, kode_reservasi, nama_peminjam, email, durasi_jam, ruang_id) VALUES
        (1, 'RS00000001', 'Andi Saputra', 'andi.r@example.com', 2, 1),
        (2, 'RS00000002', 'Siti Rahma', 'siti.r@example.com', 3, 2),
        (3, 'RS00000003', 'Budi Pratama', 'budi.r@example.com', 1, 1)
    ");
});

test('fitur 1 (Read): menampilkan 3 data awal dan master ruang pada indeks reservasi', function () {
    $response = $this->get('/reservasi');

    $response->assertOk()
        ->assertSee('RS00000001')
        ->assertSee('Andi Saputra')
        ->assertSee('andi.r@example.com')
        ->assertSee('2')
        ->assertSee('Ruang Rapat A')
        ->assertSee('RS00000002')
        ->assertSee('Siti Rahma')
        ->assertSee('siti.r@example.com')
        ->assertSee('3')
        ->assertSee('Ruang Seminar')
        ->assertSee('RS00000003')
        ->assertSee('Budi Pratama')
        ->assertSee('budi.r@example.com')
        ->assertSee('1');
});

test('fitur 2 (Create valid): menyimpan data reservasi baru dan menampilkan umpan balik sukses', function () {
    $payload = [
        'kode_reservasi' => 'RS00000004',
        'nama_peminjam' => 'Citra Dewi',
        'email' => 'citra.d@example.com',
        'durasi_jam' => '4',
        'ruang_id' => '1',
    ];

    $response = $this->post('/reservasi', $payload);

    $response->assertRedirect('/reservasi')
        ->assertSessionHas('berhasil', 'Data reservasi berhasil ditambahkan.');

    $this->assertDatabaseHas('reservasi_ruang', [
        'kode_reservasi' => 'RS00000004',
        'nama_peminjam' => 'Citra Dewi',
        'email' => 'citra.d@example.com',
        'durasi_jam' => 4,
        'ruang_id' => 1,
    ]);
});

test('fitur 3 (Create tidak valid): menolak input kosong atau tidak sesuai ketentuan dan mempertahankan old input', function () {
    $payload = [
        'kode_reservasi' => 'RS1',         // Kurang dari 10 karakter
        'nama_peminjam' => '',             // Kosong
        'email' => 'bukan-format-email',   // Format email salah
        'durasi_jam' => '12',              // Di luar batas 1..8
        'ruang_id' => '',                  // Kosong
    ];

    $response = $this->from('/reservasi')->post('/reservasi', $payload);

    $response->assertRedirect('/reservasi')
        ->assertSessionHasErrors(['kode_reservasi', 'nama_peminjam', 'email', 'durasi_jam', 'ruang_id']);
});

test('fitur 4 (Duplikat): menolak kode reservasi atau email yang sama tanpa merusak data', function () {
    // Percobaan duplikat kode_reservasi
    $payload = [
        'kode_reservasi' => 'RS00000001',
        'nama_peminjam' => 'Peminjam Lain',
        'email' => 'lain@example.com',
        'durasi_jam' => '2',
        'ruang_id' => '1',
    ];

    $response = $this->from('/reservasi')->post('/reservasi', $payload);

    $response->assertRedirect('/reservasi')
        ->assertSessionHas('gagal', 'Kode reservasi atau email sudah terdaftar.');

    // Pastikan jumlah data tetap 3
    $count = DB::table('reservasi_ruang')->count();
    expect($count)->toBe(3);
});

test('fitur 5 (Update): menampilkan data lama pada form edit dan menyimpan perubahan', function () {
    // 1. Periksa halaman edit
    $editPage = $this->get('/reservasi/1/edit');
    $editPage->assertOk()
        ->assertSee('RS00000001')
        ->assertSee('Andi Saputra');

    // 2. Kirim update
    $payload = [
        'kode_reservasi' => 'RS00000001',
        'nama_peminjam' => 'Andi Saputra M.Kom.',
        'email' => 'andi.r@example.com',
        'durasi_jam' => '4',
        'ruang_id' => '1',
    ];

    $response = $this->put('/reservasi/1', $payload);

    $response->assertRedirect('/reservasi')
        ->assertSessionHas('berhasil', 'Data reservasi berhasil diperbarui.');

    $this->assertDatabaseHas('reservasi_ruang', [
        'id' => 1,
        'nama_peminjam' => 'Andi Saputra M.Kom.',
        'durasi_jam' => 4,
    ]);
});

test('fitur 6 (Konflik Update): menolak pembaruan jika kode reservasi atau email bentrok dengan data lain', function () {
    // Coba ubah data ID 1 agar emailnya sama dengan ID 2
    $payload = [
        'kode_reservasi' => 'RS00000001',
        'nama_peminjam' => 'Andi Saputra',
        'email' => 'siti.r@example.com', // Milik ID 2
        'durasi_jam' => '2',
        'ruang_id' => '1',
    ];

    $response = $this->from('/reservasi/1/edit')->put('/reservasi/1', $payload);

    $response->assertRedirect('/reservasi/1/edit')
        ->assertSessionHas('gagal', 'Kode reservasi atau email sudah digunakan reservasi lain.');
});

test('fitur 7 (Delete): menghapus data reservasi dengan form DELETE dan CSRF', function () {
    $response = $this->delete('/reservasi/3');

    $response->assertRedirect('/reservasi')
        ->assertSessionHas('berhasil', 'Data reservasi berhasil dihapus.');

    $this->assertDatabaseMissing('reservasi_ruang', [
        'id' => 3,
        'kode_reservasi' => 'RS00000003',
    ]);
});

test('fitur 8 (Pencarian): mencari kata kunci pada 3 kolom (kode_reservasi, nama_peminjam, email)', function () {
    // 1. Cari berdasarkan nama
    $this->get('/reservasi?q=Siti')
        ->assertOk()
        ->assertSee('Siti Rahma')
        ->assertDontSee('Andi Saputra');

    // 2. Cari berdasarkan kode
    $this->get('/reservasi?q=RS00000003')
        ->assertOk()
        ->assertSee('Budi Pratama')
        ->assertDontSee('Andi Saputra');

    // 3. Cari berdasarkan email
    $this->get('/reservasi?q=andi.r@example.com')
        ->assertOk()
        ->assertSee('Andi Saputra')
        ->assertDontSee('Siti Rahma');
});

test('fitur 9 (Filter): menyaring daftar reservasi berdasarkan ruang kampus', function () {
    // Filter ke Ruang Seminar (ruang_id = 2)
    $response = $this->get('/reservasi?ruang_id=2');

    $response->assertOk()
        ->assertSee('Siti Rahma')
        ->assertDontSee('Andi Saputra')
        ->assertDontSee('Budi Pratama');
});

test('fitur 10 & Modul 2: fungsi statusDurasi dan penampilan status Panjang vs Singkat', function () {
    // Unit test helper statusDurasi
    expect(statusDurasi(4))->toBe('Panjang');
    expect(statusDurasi(6))->toBe('Panjang');
    expect(statusDurasi(3))->toBe('Singkat');
    expect(statusDurasi(1))->toBe('Singkat');

    // Cek penampilan pada halaman indeks (data awal berdurasi 2, 3, 1 jam -> 'Singkat')
    $this->get('/reservasi')
        ->assertOk()
        ->assertSee('Singkat');

    // Tambah data berdurasi 5 jam (status 'Panjang')
    DB::statement("INSERT INTO reservasi_ruang (kode_reservasi, nama_peminjam, email, durasi_jam, ruang_id)
        VALUES ('RS00000099', 'Dosen Penguji', 'dosen@example.com', 5, 1)
    ");

    $this->get('/reservasi')
        ->assertOk()
        ->assertSee('Panjang');
});

