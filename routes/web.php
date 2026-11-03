<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::get('/latihan-php', function () {
    $nama = 'Shalsabyla Finta Azalea';
    $nilai = request()->query('uji') === 'perbaikan'
        ? [60, 65, 70, 68, 72]
        : [80, 75, 90, 85, 95];

    $hitungRataRata = function (array $data): float {
        $total = 0;
        foreach ($data as $angka) {
            $total += $angka;
        }

        return $total / count($data);
    };

    $rataRata = $hitungRataRata($nilai);
    if ($rataRata >= 75) {
        $status = 'Lulus';
    } else {
        $status = 'Perlu Perbaikan';
    }

    return view('latihan-php', compact(
        'nama', 'nilai', 'rataRata', 'status'
    ));
});

Route::get('/form-mahasiswa', function () {
    return view('form-mahasiswa');
});

Route::post('/form-mahasiswa', function (Request $request) {
    $dataBersih = [
        'nama' => strip_tags(trim((string) $request->input('nama'))),
        'email' => filter_var(
            (string) $request->input('email'),
            FILTER_SANITIZE_EMAIL
        ),
        'usia' => trim((string) $request->input('usia')),
        'nim' => trim((string) $request->input('nim')),
    ];

    $validator = Validator::make($dataBersih, [
        'nama' => ['required', 'min:3', 'max:50'],
        'email' => ['required', 'email'],
        'usia' => ['required', 'integer', 'min:17', 'max:60'],
        'nim' => ['required', 'regex:/^[0-9]{8,12}$/'],
    ], [
        'nama.required' => 'Nama wajib diisi.',
        'nama.min' => 'Nama minimal 3 karakter.',
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'usia.required' => 'Usia wajib diisi.',
        'usia.integer' => 'Usia harus berupa angka.',
        'usia.min' => 'Usia minimal 17 tahun.',
        'usia.max' => 'Usia maksimal 60 tahun.',
        'nim.required' => 'NIM wajib diisi.',
        'nim.regex' => 'NIM harus berisi 8 sampai 12 digit angka.',
    ]);

    if ($validator->fails()) {
        return redirect('/form-mahasiswa')
            ->withErrors($validator)
            ->withInput();
    }

    $data = $validator->validated();
    $data['usia'] = (int) $data['usia'];

    return view('hasil-form', ['data' => $data]);

});

Route::get('/mahasiswa', function (Request $request) {
    $kataKunci = trim((string) $request->query('q', ''));
    $programStudiId = $request->query('program_studi_id', '');

    try {
        $pdo = DB::connection()->getPdo();

        $sql = 'SELECT m.id, m.nim, m.nama, m.email, m.usia,
                       m.program_studi_id, p.nama_prodi
                FROM mahasiswa AS m
                JOIN program_studi AS p
                  ON p.id = m.program_studi_id
                WHERE 1 = 1';
        $parameter = [];

        if ($kataKunci !== '') {
            $sql .= ' AND (m.nim LIKE :kata_kunci_nim
                      OR m.nama LIKE :kata_kunci_nama
                      OR m.email LIKE :kata_kunci_email)';
            $nilaiPencarian = '%'.$kataKunci.'%';
            $parameter['kata_kunci_nim'] = $nilaiPencarian;
            $parameter['kata_kunci_nama'] = $nilaiPencarian;
            $parameter['kata_kunci_email'] = $nilaiPencarian;
        }

        if ($programStudiId !== '') {
            $sql .= ' AND m.program_studi_id = :program_studi_id';
            $parameter['program_studi_id'] = (int) $programStudiId;
        }

        $sql .= ' ORDER BY m.nim';
        $ambilMahasiswa = $pdo->prepare($sql);
        $ambilMahasiswa->execute($parameter);
        $daftarMahasiswa = $ambilMahasiswa->fetchAll(PDO::FETCH_ASSOC);

        $ambilProdi = $pdo->prepare(
            'SELECT id, nama_prodi
             FROM program_studi
             ORDER BY nama_prodi'
        );
        $ambilProdi->execute();
        $daftarProgramStudi = $ambilProdi->fetchAll(PDO::FETCH_ASSOC);

        return view('mahasiswa', compact(
            'daftarMahasiswa',
            'daftarProgramStudi',
            'kataKunci',
            'programStudiId'
        ));
    } catch (Throwable $error) {
        report($error);

        return response(
            'Data tidak dapat dibaca. Periksa koneksi dan query.',
            500
        );
    }
})->name('mahasiswa.index');

Route::post('/mahasiswa', function (Request $request) {
    $data = $request->validate([
        'nim' => ['required', 'string', 'max:10', 'regex:/^[0-9]{10}$/'],
        'nama' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email', 'max:100'],
        'usia' => ['required', 'integer', 'min:15', 'max:100'],
        'program_studi_id' => [
            'required',
            'integer',
            'exists:program_studi,id',
        ],
    ], [
        'nim.required' => 'NIM wajib diisi.',
        'nim.regex' => 'NIM harus terdiri dari 10 digit angka.',
        'nama.required' => 'Nama wajib diisi.',
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'usia.required' => 'Usia wajib diisi.',
        'usia.integer' => 'Usia harus berupa angka.',
        'program_studi_id.required' => 'Program studi wajib dipilih.',
        'program_studi_id.exists' => 'Program studi yang dipilih tidak tersedia.',
    ]);

    try {
        $pdo = DB::connection()->getPdo();

        $cekDuplikat = $pdo->prepare(
            'SELECT COUNT(*)
             FROM mahasiswa
             WHERE nim = :nim OR email = :email'
        );

        $cekDuplikat->execute([
            'nim' => $data['nim'],
            'email' => $data['email'],
        ]);

        if ((int) $cekDuplikat->fetchColumn() > 0) {
            return back()
                ->withInput()
                ->with('gagal', 'NIM atau email sudah digunakan.');
        }

        $simpan = $pdo->prepare(
            'INSERT INTO mahasiswa
                (nim, nama, email, usia, program_studi_id)
             VALUES
                (:nim, :nama, :email, :usia, :program_studi_id)'
        );

        $simpan->execute([
            'nim' => $data['nim'],
            'nama' => $data['nama'],
            'email' => $data['email'],
            'usia' => $data['usia'],
            'program_studi_id' => $data['program_studi_id'],
        ]);

        return redirect()
            ->route('mahasiswa.index')
            ->with('berhasil', 'Data mahasiswa berhasil ditambahkan.');
    } catch (Throwable $error) {
        report($error);

        return back()
            ->withInput()
            ->with('gagal', 'Data gagal disimpan. Periksa koneksi dan data.');
    }
})->name('mahasiswa.store');

Route::get('/mahasiswa/{id}/edit', function (int $id) {
    try {
        $pdo = DB::connection()->getPdo();
        $ambilMahasiswa = $pdo->prepare(
            'SELECT id, nim, nama, email, usia, program_studi_id
             FROM mahasiswa WHERE id = :id'
        );
        $ambilMahasiswa->execute(['id' => $id]);
        $mahasiswa = $ambilMahasiswa->fetch(PDO::FETCH_ASSOC);

        if (! $mahasiswa) {
            return redirect()->route('mahasiswa.index')->with(
                'gagal',
                'Data mahasiswa tidak ditemukan.'
            );
        }

        $ambilProdi = $pdo->prepare(
            'SELECT id, nama_prodi FROM program_studi ORDER BY nama_prodi'
        );
        $ambilProdi->execute();
        $daftarProgramStudi = $ambilProdi->fetchAll(PDO::FETCH_ASSOC);

        return view('mahasiswa-edit', compact(
            'mahasiswa',
            'daftarProgramStudi'
        ));
    } catch (Throwable $error) {
        report($error);

        return redirect()->route('mahasiswa.index')->with(
            'gagal',
            'Data gagal dibuka untuk diubah.'
        );
    }
})->name('mahasiswa.edit');

Route::put('/mahasiswa/{id}', function (
    Request $request,
    int $id
) {
    $data = $request->validate([
        'nim' => ['required', 'string', 'max:20'],
        'nama' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email', 'max:100'],
        'usia' => ['required', 'integer', 'min:15', 'max:100'],
        'program_studi_id' => ['required', 'integer'],
    ]);

    try {
        $pdo = DB::connection()->getPdo();
        $cek = $pdo->prepare(
            'SELECT COUNT(*) FROM mahasiswa
             WHERE (nim = :nim OR email = :email)
               AND id <> :id'
        );
        $cek->execute([
            'nim' => $data['nim'],
            'email' => $data['email'],
            'id' => $id,
        ]);

        if ((int) $cek->fetchColumn() > 0) {
            return back()->withInput()->with(
                'gagal',
                'NIM atau email sudah digunakan mahasiswa lain.'
            );
        }

        $ubah = $pdo->prepare(
            'UPDATE mahasiswa
             SET nim = :nim, nama = :nama, email = :email,
                 usia = :usia, program_studi_id = :program_studi_id
             WHERE id = :id'
        );
        $ubah->execute([
            'nim' => $data['nim'],
            'nama' => $data['nama'],
            'email' => $data['email'],
            'usia' => $data['usia'],
            'program_studi_id' => $data['program_studi_id'],
            'id' => $id,
        ]);

        return redirect()->route('mahasiswa.index')->with(
            'berhasil',
            'Data mahasiswa berhasil diperbarui.'
        );
    } catch (Throwable $error) {
        report($error);

        return back()->withInput()->with(
            'gagal',
            'Data gagal diperbarui. Periksa koneksi dan data.'
        );
    }
})->name('mahasiswa.update');

Route::delete('/mahasiswa/{id}', function (int $id) {
    try {
        $pdo = DB::connection()->getPdo();
        $hapus = $pdo->prepare(
            'DELETE FROM mahasiswa WHERE id = :id'
        );
        $hapus->execute(['id' => $id]);

        if ($hapus->rowCount() === 0) {
            return redirect()->route('mahasiswa.index')->with(
                'gagal',
                'Data mahasiswa tidak ditemukan.'
            );
        }

        return redirect()->route('mahasiswa.index')->with(
            'berhasil',
            'Data mahasiswa berhasil dihapus.'
        );
    } catch (Throwable $error) {
        report($error);

        return redirect()->route('mahasiswa.index')->with(
            'gagal',
            'Data gagal dihapus. Periksa relasi atau koneksi.'
        );
    }
})->name('mahasiswa.destroy');

/**
 * ============================================================================
 * STUDI KASUS PRAKTIKUM PEMROGRAMAN WEB (MODUL 1 - MODUL 7)
 * TIPE B: SISTEM INVENTARIS LABORATORIUM
 * Mahasiswa: Shalsabyla Finta Azalea (NIM: 2401020167)
 * ============================================================================
 */
if (! function_exists('statusUsiaAset')) {
    /**
     * Menentukan status usia aset berdasarkan tahun perolehan.
     * Mengembalikan 'Baru' jika tahun >= 2023 dan 'Lama' jika di bawahnya.
     */
    function statusUsiaAset(int $tahun): string
    {
        return $tahun >= 2023 ? 'Baru' : 'Lama';
    }
}

Route::get('/inventaris', function (Request $request) {
    $kataKunci = trim((string) $request->query('q', ''));
    $ruangId = $request->query('ruang_id', '');

    try {
        $pdo = DB::connection()->getPdo();

        $sql = 'SELECT i.id, i.kode_barang, i.nama_barang, i.nomor_aset,
                       i.tahun_perolehan, i.ruang_id, r.nama_ruang
                FROM inventaris AS i
                JOIN ruang AS r ON r.id = i.ruang_id
                WHERE 1 = 1';
        $parameter = [];

        // Pencarian memeriksa 3 kolom: kode_barang, nama_barang, dan nomor_aset
        if ($kataKunci !== '') {
            $sql .= ' AND (i.kode_barang LIKE :kata_kunci_kode
                      OR i.nama_barang LIKE :kata_kunci_nama
                      OR i.nomor_aset LIKE :kata_kunci_aset)';
            $nilaiPencarian = '%'.$kataKunci.'%';
            $parameter['kata_kunci_kode'] = $nilaiPencarian;
            $parameter['kata_kunci_nama'] = $nilaiPencarian;
            $parameter['kata_kunci_aset'] = $nilaiPencarian;
        }

        // Filter berdasarkan tabel master ruang
        if ($ruangId !== '') {
            $sql .= ' AND i.ruang_id = :ruang_id';
            $parameter['ruang_id'] = (int) $ruangId;
        }

        $sql .= ' ORDER BY i.kode_barang ASC';
        $ambilInventaris = $pdo->prepare($sql);
        $ambilInventaris->execute($parameter);
        $daftarInventaris = $ambilInventaris->fetchAll(PDO::FETCH_ASSOC);

        $ambilRuang = $pdo->prepare('SELECT id, nama_ruang FROM ruang ORDER BY nama_ruang ASC');
        $ambilRuang->execute();
        $daftarRuang = $ambilRuang->fetchAll(PDO::FETCH_ASSOC);

        return view('inventaris', compact('daftarInventaris', 'daftarRuang', 'kataKunci', 'ruangId'));
    } catch (Throwable $error) {
        report($error);

        return response('Data inventaris tidak dapat dibaca. Periksa koneksi dan query.', 500);
    }
})->name('inventaris.index');

Route::post('/inventaris', function (Request $request) {
    $request->merge([
        'kode_barang' => strtoupper(strip_tags(trim((string) $request->input('kode_barang')))),
        'nama_barang' => strip_tags(trim((string) $request->input('nama_barang'))),
        'nomor_aset' => strip_tags(trim((string) $request->input('nomor_aset'))),
        'tahun_perolehan' => trim((string) $request->input('tahun_perolehan')),
        'ruang_id' => trim((string) $request->input('ruang_id')),
    ]);

    $data = $request->validate([
        'kode_barang' => ['required', 'string', 'size:10', 'regex:/^[A-Za-z0-9]{10}$/'],
        'nama_barang' => ['required', 'string', 'min:3', 'max:100'],
        'nomor_aset' => ['required', 'string', 'min:5', 'max:30'],
        'tahun_perolehan' => ['required', 'integer', 'between:2000,2026'],
        'ruang_id' => ['required', 'integer', 'exists:ruang,id'],
    ], [
        'kode_barang.required' => 'Kode barang wajib diisi.',
        'kode_barang.size' => 'Kode barang harus tepat 10 karakter.',
        'kode_barang.regex' => 'Kode barang harus berupa 10 karakter alfanumerik.',
        'nama_barang.required' => 'Nama barang wajib diisi.',
        'nama_barang.min' => 'Nama barang minimal 3 karakter.',
        'nama_barang.max' => 'Nama barang maksimal 100 karakter.',
        'nomor_aset.required' => 'Nomor aset wajib diisi.',
        'nomor_aset.min' => 'Nomor aset minimal 5 karakter.',
        'nomor_aset.max' => 'Nomor aset maksimal 30 karakter.',
        'tahun_perolehan.required' => 'Tahun perolehan wajib diisi.',
        'tahun_perolehan.integer' => 'Tahun perolehan harus berupa angka.',
        'tahun_perolehan.between' => 'Tahun perolehan harus berada di antara tahun 2000 dan 2026.',
        'ruang_id.required' => 'Ruang laboratorium wajib dipilih.',
        'ruang_id.exists' => 'Ruang laboratorium yang dipilih tidak tersedia.',
    ]);

    try {
        $pdo = DB::connection()->getPdo();

        $cekDuplikat = $pdo->prepare(
            'SELECT COUNT(*) FROM inventaris WHERE kode_barang = :kode_barang OR nomor_aset = :nomor_aset'
        );
        $cekDuplikat->execute([
            'kode_barang' => $data['kode_barang'],
            'nomor_aset' => $data['nomor_aset'],
        ]);

        if ((int) $cekDuplikat->fetchColumn() > 0) {
            return back()
                ->withInput()
                ->with('gagal', 'Kode barang atau nomor aset sudah terdaftar.');
        }

        $simpan = $pdo->prepare(
            'INSERT INTO inventaris (kode_barang, nama_barang, nomor_aset, tahun_perolehan, ruang_id)
             VALUES (:kode_barang, :nama_barang, :nomor_aset, :tahun_perolehan, :ruang_id)'
        );
        $simpan->execute([
            'kode_barang' => $data['kode_barang'],
            'nama_barang' => $data['nama_barang'],
            'nomor_aset' => $data['nomor_aset'],
            'tahun_perolehan' => $data['tahun_perolehan'],
            'ruang_id' => $data['ruang_id'],
        ]);

        return redirect()
            ->route('inventaris.index')
            ->with('berhasil', 'Data inventaris berhasil ditambahkan.');
    } catch (Throwable $error) {
        report($error);

        return back()
            ->withInput()
            ->with('gagal', 'Data gagal disimpan. Periksa koneksi dan data.');
    }
})->name('inventaris.store');

Route::get('/inventaris/{id}/edit', function (int $id) {
    try {
        $pdo = DB::connection()->getPdo();
        $ambilInventaris = $pdo->prepare(
            'SELECT id, kode_barang, nama_barang, nomor_aset, tahun_perolehan, ruang_id
             FROM inventaris WHERE id = :id'
        );
        $ambilInventaris->execute(['id' => $id]);
        $inventaris = $ambilInventaris->fetch(PDO::FETCH_ASSOC);

        if (! $inventaris) {
            return redirect()->route('inventaris.index')->with(
                'gagal',
                'Data inventaris tidak ditemukan.'
            );
        }

        $ambilRuang = $pdo->prepare('SELECT id, nama_ruang FROM ruang ORDER BY nama_ruang ASC');
        $ambilRuang->execute();
        $daftarRuang = $ambilRuang->fetchAll(PDO::FETCH_ASSOC);

        return view('inventaris-edit', compact('inventaris', 'daftarRuang'));
    } catch (Throwable $error) {
        report($error);

        return redirect()->route('inventaris.index')->with(
            'gagal',
            'Data gagal dibuka untuk diubah.'
        );
    }
})->name('inventaris.edit');

Route::put('/inventaris/{id}', function (Request $request, int $id) {
    $request->merge([
        'kode_barang' => strtoupper(strip_tags(trim((string) $request->input('kode_barang')))),
        'nama_barang' => strip_tags(trim((string) $request->input('nama_barang'))),
        'nomor_aset' => strip_tags(trim((string) $request->input('nomor_aset'))),
        'tahun_perolehan' => trim((string) $request->input('tahun_perolehan')),
        'ruang_id' => trim((string) $request->input('ruang_id')),
    ]);

    $data = $request->validate([
        'kode_barang' => ['required', 'string', 'size:10', 'regex:/^[A-Za-z0-9]{10}$/'],
        'nama_barang' => ['required', 'string', 'min:3', 'max:100'],
        'nomor_aset' => ['required', 'string', 'min:5', 'max:30'],
        'tahun_perolehan' => ['required', 'integer', 'between:2000,2026'],
        'ruang_id' => ['required', 'integer', 'exists:ruang,id'],
    ], [
        'kode_barang.required' => 'Kode barang wajib diisi.',
        'kode_barang.size' => 'Kode barang harus tepat 10 karakter.',
        'kode_barang.regex' => 'Kode barang harus berupa 10 karakter alfanumerik.',
        'nama_barang.required' => 'Nama barang wajib diisi.',
        'nama_barang.min' => 'Nama barang minimal 3 karakter.',
        'nama_barang.max' => 'Nama barang maksimal 100 karakter.',
        'nomor_aset.required' => 'Nomor aset wajib diisi.',
        'nomor_aset.min' => 'Nomor aset minimal 5 karakter.',
        'nomor_aset.max' => 'Nomor aset maksimal 30 karakter.',
        'tahun_perolehan.required' => 'Tahun perolehan wajib diisi.',
        'tahun_perolehan.integer' => 'Tahun perolehan harus berupa angka.',
        'tahun_perolehan.between' => 'Tahun perolehan harus berada di antara tahun 2000 dan 2026.',
        'ruang_id.required' => 'Ruang laboratorium wajib dipilih.',
        'ruang_id.exists' => 'Ruang laboratorium yang dipilih tidak tersedia.',
    ]);

    try {
        $pdo = DB::connection()->getPdo();

        $cekKonflik = $pdo->prepare(
            'SELECT COUNT(*) FROM inventaris
             WHERE (kode_barang = :kode_barang OR nomor_aset = :nomor_aset)
               AND id <> :id'
        );
        $cekKonflik->execute([
            'kode_barang' => $data['kode_barang'],
            'nomor_aset' => $data['nomor_aset'],
            'id' => $id,
        ]);

        if ((int) $cekKonflik->fetchColumn() > 0) {
            return back()->withInput()->with(
                'gagal',
                'Kode barang atau nomor aset sudah digunakan inventaris lain.'
            );
        }

        $ubah = $pdo->prepare(
            'UPDATE inventaris
             SET kode_barang = :kode_barang,
                 nama_barang = :nama_barang,
                 nomor_aset = :nomor_aset,
                 tahun_perolehan = :tahun_perolehan,
                 ruang_id = :ruang_id
             WHERE id = :id'
        );
        $ubah->execute([
            'kode_barang' => $data['kode_barang'],
            'nama_barang' => $data['nama_barang'],
            'nomor_aset' => $data['nomor_aset'],
            'tahun_perolehan' => $data['tahun_perolehan'],
            'ruang_id' => $data['ruang_id'],
            'id' => $id,
        ]);

        return redirect()->route('inventaris.index')->with(
            'berhasil',
            'Data inventaris berhasil diperbarui.'
        );
    } catch (Throwable $error) {
        report($error);

        return back()->withInput()->with(
            'gagal',
            'Data gagal diperbarui. Periksa koneksi dan data.'
        );
    }
})->name('inventaris.update');

Route::delete('/inventaris/{id}', function (int $id) {
    try {
        $pdo = DB::connection()->getPdo();
        $hapus = $pdo->prepare('DELETE FROM inventaris WHERE id = :id');
        $hapus->execute(['id' => $id]);

        if ($hapus->rowCount() === 0) {
            return redirect()->route('inventaris.index')->with(
                'gagal',
                'Data inventaris tidak ditemukan.'
            );
        }

        return redirect()->route('inventaris.index')->with(
            'berhasil',
            'Data inventaris berhasil dihapus.'
        );
    } catch (Throwable $error) {
        report($error);

        return redirect()->route('inventaris.index')->with(
            'gagal',
            'Data gagal dihapus. Periksa relasi atau koneksi.'
        );
    }
})->name('inventaris.destroy');

/**
 * ============================================================================
 * STUDI KASUS PRAKTIKUM PEMROGRAMAN WEB (MODUL 1 - MODUL 7)
 * PAKET 2 / TIPE A: SISTEM RESERVASI RUANG
 * Mahasiswa: Shalsabyla Finta Azalea (NIM: 2401020167)
 * ============================================================================
 */
if (! function_exists('statusDurasi')) {
    /**
     * Menentukan status durasi reservasi ruang.
     * Mengembalikan 'Panjang' jika durasi >= 4 jam dan 'Singkat' jika di bawahnya.
     */
    function statusDurasi(int $durasi): string
    {
        return $durasi >= 4 ? 'Panjang' : 'Singkat';
    }
}

Route::get('/reservasi', function (Request $request) {
    $kataKunci = trim((string) $request->query('q', ''));
    $ruangId = trim((string) $request->query('ruang_id', ''));

    try {
        $pdo = DB::connection()->getPdo();

        $sql = 'SELECT rr.id, rr.kode_reservasi, rr.nama_peminjam, rr.email,
                       rr.durasi_jam, rr.ruang_id, r.nama_ruang
                FROM reservasi_ruang AS rr
                JOIN ruang AS r ON r.id = rr.ruang_id
                WHERE 1 = 1';
        $parameter = [];

        // Pencarian memeriksa 3 kolom: kode_reservasi, nama_peminjam, dan email
        if ($kataKunci !== '') {
            $sql .= ' AND (rr.kode_reservasi LIKE :kata_kunci_kode
                      OR rr.nama_peminjam LIKE :kata_kunci_nama
                      OR rr.email LIKE :kata_kunci_email)';
            $nilaiPencarian = '%'.$kataKunci.'%';
            $parameter['kata_kunci_kode'] = $nilaiPencarian;
            $parameter['kata_kunci_nama'] = $nilaiPencarian;
            $parameter['kata_kunci_email'] = $nilaiPencarian;
        }

        // Filter berdasarkan tabel master ruang
        if ($ruangId !== '') {
            $sql .= ' AND rr.ruang_id = :ruang_id';
            $parameter['ruang_id'] = (int) $ruangId;
        }

        $sql .= ' ORDER BY rr.kode_reservasi ASC';
        $ambilReservasi = $pdo->prepare($sql);
        $ambilReservasi->execute($parameter);
        $daftarReservasi = $ambilReservasi->fetchAll(PDO::FETCH_ASSOC);

        $ambilRuang = $pdo->prepare('SELECT id, nama_ruang FROM ruang ORDER BY nama_ruang ASC');
        $ambilRuang->execute();
        $daftarRuang = $ambilRuang->fetchAll(PDO::FETCH_ASSOC);

        return view('reservasi', compact('daftarReservasi', 'daftarRuang', 'kataKunci', 'ruangId'));
    } catch (Throwable $error) {
        report($error);

        return response('Data reservasi tidak dapat dibaca. Periksa koneksi dan query.', 500);
    }
})->name('reservasi.index');

Route::post('/reservasi', function (Request $request) {
    $request->merge([
        'kode_reservasi' => strtoupper(strip_tags(trim((string) $request->input('kode_reservasi')))),
        'nama_peminjam' => strip_tags(trim((string) $request->input('nama_peminjam'))),
        'email' => filter_var(trim((string) $request->input('email')), FILTER_SANITIZE_EMAIL),
        'durasi_jam' => trim((string) $request->input('durasi_jam')),
        'ruang_id' => trim((string) $request->input('ruang_id')),
    ]);

    $data = $request->validate([
        'kode_reservasi' => ['required', 'string', 'size:10', 'regex:/^[A-Za-z0-9]{10}$/'],
        'nama_peminjam' => ['required', 'string', 'min:3', 'max:100'],
        'email' => ['required', 'email', 'max:100'],
        'durasi_jam' => ['required', 'integer', 'between:1,8'],
        'ruang_id' => ['required', 'integer'],
    ], [
        'kode_reservasi.required' => 'Kode reservasi wajib diisi.',
        'kode_reservasi.size' => 'Kode reservasi harus tepat 10 karakter.',
        'kode_reservasi.regex' => 'Kode reservasi harus berupa 10 karakter alfanumerik.',
        'nama_peminjam.required' => 'Nama peminjam wajib diisi.',
        'nama_peminjam.min' => 'Nama peminjam minimal 3 karakter.',
        'nama_peminjam.max' => 'Nama peminjam maksimal 100 karakter.',
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'email.max' => 'Email maksimal 100 karakter.',
        'durasi_jam.required' => 'Durasi wajib diisi.',
        'durasi_jam.integer' => 'Durasi harus berupa angka bulat.',
        'durasi_jam.between' => 'Durasi harus di antara 1 sampai 8 jam.',
        'ruang_id.required' => 'Ruang kampus wajib dipilih.',
        'ruang_id.integer' => 'Ruang kampus yang dipilih tidak valid.',
    ]);

    try {
        $pdo = DB::connection()->getPdo();

        $cekRuang = $pdo->prepare('SELECT COUNT(*) FROM ruang WHERE id = :ruang_id');
        $cekRuang->execute(['ruang_id' => $data['ruang_id']]);
        if ((int) $cekRuang->fetchColumn() === 0) {
            return back()->withInput()->with('gagal', 'Ruang kampus yang dipilih tidak tersedia.');
        }

        $cekDuplikat = $pdo->prepare(
            'SELECT COUNT(*) FROM reservasi_ruang WHERE kode_reservasi = :kode_reservasi OR email = :email'
        );
        $cekDuplikat->execute([
            'kode_reservasi' => $data['kode_reservasi'],
            'email' => $data['email'],
        ]);

        if ((int) $cekDuplikat->fetchColumn() > 0) {
            return back()
                ->withInput()
                ->with('gagal', 'Kode reservasi atau email sudah terdaftar.');
        }

        $simpan = $pdo->prepare(
            'INSERT INTO reservasi_ruang (kode_reservasi, nama_peminjam, email, durasi_jam, ruang_id)
             VALUES (:kode_reservasi, :nama_peminjam, :email, :durasi_jam, :ruang_id)'
        );
        $simpan->execute([
            'kode_reservasi' => $data['kode_reservasi'],
            'nama_peminjam' => $data['nama_peminjam'],
            'email' => $data['email'],
            'durasi_jam' => (int) $data['durasi_jam'],
            'ruang_id' => (int) $data['ruang_id'],
        ]);

        return redirect()
            ->route('reservasi.index')
            ->with('berhasil', 'Data reservasi berhasil ditambahkan.');
    } catch (Throwable $error) {
        report($error);

        return back()
            ->withInput()
            ->with('gagal', 'Data gagal disimpan. Periksa koneksi dan data.');
    }
})->name('reservasi.store');

Route::get('/reservasi/{id}/edit', function (int $id) {
    try {
        $pdo = DB::connection()->getPdo();
        $ambilReservasi = $pdo->prepare(
            'SELECT id, kode_reservasi, nama_peminjam, email, durasi_jam, ruang_id
             FROM reservasi_ruang WHERE id = :id'
        );
        $ambilReservasi->execute(['id' => $id]);
        $reservasi = $ambilReservasi->fetch(PDO::FETCH_ASSOC);

        if (! $reservasi) {
            return redirect()->route('reservasi.index')->with(
                'gagal',
                'Data reservasi tidak ditemukan.'
            );
        }

        $ambilRuang = $pdo->prepare('SELECT id, nama_ruang FROM ruang ORDER BY nama_ruang ASC');
        $ambilRuang->execute();
        $daftarRuang = $ambilRuang->fetchAll(PDO::FETCH_ASSOC);

        return view('reservasi-edit', compact('reservasi', 'daftarRuang'));
    } catch (Throwable $error) {
        report($error);

        return redirect()->route('reservasi.index')->with(
            'gagal',
            'Data gagal dibuka untuk diubah.'
        );
    }
})->name('reservasi.edit');

Route::put('/reservasi/{id}', function (Request $request, int $id) {
    $request->merge([
        'kode_reservasi' => strtoupper(strip_tags(trim((string) $request->input('kode_reservasi')))),
        'nama_peminjam' => strip_tags(trim((string) $request->input('nama_peminjam'))),
        'email' => filter_var(trim((string) $request->input('email')), FILTER_SANITIZE_EMAIL),
        'durasi_jam' => trim((string) $request->input('durasi_jam')),
        'ruang_id' => trim((string) $request->input('ruang_id')),
    ]);

    $data = $request->validate([
        'kode_reservasi' => ['required', 'string', 'size:10', 'regex:/^[A-Za-z0-9]{10}$/'],
        'nama_peminjam' => ['required', 'string', 'min:3', 'max:100'],
        'email' => ['required', 'email', 'max:100'],
        'durasi_jam' => ['required', 'integer', 'between:1,8'],
        'ruang_id' => ['required', 'integer'],
    ], [
        'kode_reservasi.required' => 'Kode reservasi wajib diisi.',
        'kode_reservasi.size' => 'Kode reservasi harus tepat 10 karakter.',
        'kode_reservasi.regex' => 'Kode reservasi harus berupa 10 karakter alfanumerik.',
        'nama_peminjam.required' => 'Nama peminjam wajib diisi.',
        'nama_peminjam.min' => 'Nama peminjam minimal 3 karakter.',
        'nama_peminjam.max' => 'Nama peminjam maksimal 100 karakter.',
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'email.max' => 'Email maksimal 100 karakter.',
        'durasi_jam.required' => 'Durasi wajib diisi.',
        'durasi_jam.integer' => 'Durasi harus berupa angka bulat.',
        'durasi_jam.between' => 'Durasi harus di antara 1 sampai 8 jam.',
        'ruang_id.required' => 'Ruang kampus wajib dipilih.',
        'ruang_id.integer' => 'Ruang kampus yang dipilih tidak valid.',
    ]);

    try {
        $pdo = DB::connection()->getPdo();

        $cekRuang = $pdo->prepare('SELECT COUNT(*) FROM ruang WHERE id = :ruang_id');
        $cekRuang->execute(['ruang_id' => $data['ruang_id']]);
        if ((int) $cekRuang->fetchColumn() === 0) {
            return back()->withInput()->with('gagal', 'Ruang kampus yang dipilih tidak tersedia.');
        }

        $cekKonflik = $pdo->prepare(
            'SELECT COUNT(*) FROM reservasi_ruang
             WHERE (kode_reservasi = :kode_reservasi OR email = :email)
               AND id <> :id'
        );
        $cekKonflik->execute([
            'kode_reservasi' => $data['kode_reservasi'],
            'email' => $data['email'],
            'id' => $id,
        ]);

        if ((int) $cekKonflik->fetchColumn() > 0) {
            return back()->withInput()->with(
                'gagal',
                'Kode reservasi atau email sudah digunakan reservasi lain.'
            );
        }

        $ubah = $pdo->prepare(
            'UPDATE reservasi_ruang
             SET kode_reservasi = :kode_reservasi,
                 nama_peminjam = :nama_peminjam,
                 email = :email,
                 durasi_jam = :durasi_jam,
                 ruang_id = :ruang_id
             WHERE id = :id'
        );
        $ubah->execute([
            'kode_reservasi' => $data['kode_reservasi'],
            'nama_peminjam' => $data['nama_peminjam'],
            'email' => $data['email'],
            'durasi_jam' => (int) $data['durasi_jam'],
            'ruang_id' => (int) $data['ruang_id'],
            'id' => $id,
        ]);

        return redirect()->route('reservasi.index')->with(
            'berhasil',
            'Data reservasi berhasil diperbarui.'
        );
    } catch (Throwable $error) {
        report($error);

        return back()->withInput()->with(
            'gagal',
            'Data gagal diperbarui. Periksa koneksi dan data.'
        );
    }
})->name('reservasi.update');

Route::delete('/reservasi/{id}', function (int $id) {
    try {
        $pdo = DB::connection()->getPdo();
        $hapus = $pdo->prepare('DELETE FROM reservasi_ruang WHERE id = :id');
        $hapus->execute(['id' => $id]);

        if ($hapus->rowCount() === 0) {
            return redirect()->route('reservasi.index')->with(
                'gagal',
                'Data reservasi tidak ditemukan.'
            );
        }

        return redirect()->route('reservasi.index')->with(
            'berhasil',
            'Data reservasi berhasil dihapus.'
        );
    } catch (Throwable $error) {
        report($error);

        return redirect()->route('reservasi.index')->with(
            'gagal',
            'Data gagal dihapus. Periksa relasi atau koneksi.'
        );
    }
})->name('reservasi.destroy');

require __DIR__.'/settings.php';
