<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Reservasi Ruang</title>
</head>
<body>
    <h1>Ubah Reservasi Ruang</h1>

    {{-- Feedback Notifikasi --}}
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

    {{-- Form Ubah Reservasi (Update) --}}
    <form action="{{ route('reservasi.update', $reservasi['id']) }}" method="POST">
        @csrf
        @method('PUT')
        <p>
            Kode Reservasi (10 Karakter Alfanumerik)<br>
            <input type="text" name="kode_reservasi" value="{{ old('kode_reservasi', $reservasi['kode_reservasi']) }}" maxlength="10">
        </p>
        <p>
            Nama Peminjam (3 - 100 Karakter)<br>
            <input type="text" name="nama_peminjam" value="{{ old('nama_peminjam', $reservasi['nama_peminjam']) }}">
        </p>
        <p>
            Email Peminjam<br>
            <input type="email" name="email" value="{{ old('email', $reservasi['email']) }}">
        </p>
        <p>
            Durasi (1 - 8 Jam)<br>
            <input type="number" name="durasi_jam" value="{{ old('durasi_jam', $reservasi['durasi_jam']) }}" min="1" max="8">
        </p>
        <p>
            Ruang Kampus<br>
            <select name="ruang_id">
                <option value="">-- Pilih Ruang --</option>
                @foreach ($daftarRuang as $ruang)
                    <option value="{{ $ruang['id'] }}" @selected((string) old('ruang_id', $reservasi['ruang_id']) === (string) $ruang['id'])>
                        {{ $ruang['nama_ruang'] }}
                    </option>
                @endforeach
            </select>
        </p>
        <button type="submit">Simpan Perubahan</button>
        <a href="{{ route('reservasi.index') }}">Batal</a>
    </form>
</body>
</html>

