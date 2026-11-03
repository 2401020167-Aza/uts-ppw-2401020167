<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Reservasi Ruang</title>
</head>
<body>
    <h1>Sistem Reservasi Ruang</h1>

    {{-- Feedback Notifikasi --}}
    @if (session('berhasil'))
        <p><strong>Berhasil:</strong> {{ session('berhasil') }}</p>
    @endif
    @if (session('gagal'))
        <p><strong>Gagal:</strong> {{ session('gagal') }}</p>
    @endif

    {{-- Pesan Kesalahan Validasi --}}
    @if ($errors->any())
        <div>
            <strong>Terjadi kesalahan input:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Tambah Reservasi (Create) --}}
    <h2>Tambah Reservasi Baru</h2>
    <form action="{{ route('reservasi.store') }}" method="POST">
        @csrf
        <p>
            Kode Reservasi (10 Karakter Alfanumerik)<br>
            <input type="text" name="kode_reservasi" value="{{ old('kode_reservasi') }}" maxlength="10">
        </p>
        <p>
            Nama Peminjam (3 - 100 Karakter)<br>
            <input type="text" name="nama_peminjam" value="{{ old('nama_peminjam') }}">
        </p>
        <p>
            Email Peminjam<br>
            <input type="email" name="email" value="{{ old('email') }}">
        </p>
        <p>
            Durasi (1 - 8 Jam)<br>
            <input type="number" name="durasi_jam" value="{{ old('durasi_jam') }}" min="1" max="8">
        </p>
        <p>
            Ruang Kampus<br>
            <select name="ruang_id">
                <option value="">-- Pilih Ruang --</option>
                @foreach ($daftarRuang as $ruang)
                    <option value="{{ $ruang['id'] }}" @selected(old('ruang_id') == $ruang['id'])>
                        {{ $ruang['nama_ruang'] }}
                    </option>
                @endforeach
            </select>
        </p>
        <button type="submit">Tambah Reservasi</button>
    </form>

    <hr>

    {{-- Form Pencarian 3 Kolom dan Filter Ruang --}}
    <h2>Pencarian dan Filter Reservasi</h2>
    <form action="{{ route('reservasi.index') }}" method="GET">
        <p>
            Kata Kunci Pencarian (Kode, Nama, atau Email)<br>
            <input type="text" name="q" value="{{ $kataKunci }}" placeholder="Cari reservasi...">
        </p>
        <p>
            Filter Berdasarkan Ruang<br>
            <select name="ruang_id">
                <option value="">-- Semua Ruang --</option>
                @foreach ($daftarRuang as $ruang)
                    <option value="{{ $ruang['id'] }}" @selected((string) $ruangId === (string) $ruang['id'])>
                        {{ $ruang['nama_ruang'] }}
                    </option>
                @endforeach
            </select>
        </p>
        <button type="submit">Cari Data</button>
        <a href="{{ route('reservasi.index') }}">Reset</a>
    </form>

    <hr>

    {{-- Tabel Data Reservasi (Read) --}}
    <h2>Daftar Reservasi Ruang</h2>
    <table border="1" cellpadding="6" cellspacing="0">
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Reservasi</th>
                <th>Nama Peminjam</th>
                <th>Email</th>
                <th>Durasi (Jam)</th>
                <th>Status Durasi</th>
                <th>Ruang</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($daftarReservasi as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item['kode_reservasi'] }}</td>
                    <td>{{ $item['nama_peminjam'] }}</td>
                    <td>{{ $item['email'] }}</td>
                    <td>{{ $item['durasi_jam'] }}</td>
                    <td>
                        {{-- Logika Percabangan statusDurasi dengan @if --}}
                        @if (statusDurasi((int) $item['durasi_jam']) === 'Panjang')
                            <strong>Panjang</strong>
                        @else
                            <span>Singkat</span>
                        @endif
                    </td>
                    <td>{{ $item['nama_ruang'] }}</td>
                    <td>
                        <a href="{{ route('reservasi.edit', $item['id']) }}">Ubah</a>
                        |
                        <form action="{{ route('reservasi.destroy', $item['id']) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Data reservasi tidak ditemukan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

