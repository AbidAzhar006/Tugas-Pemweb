<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Transaksi Peminjaman') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            {{-- Alert Notification --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- ============================================================================================
            =================== Tugas 3 - Pertemuan 14 - Warning Reminder Box Buku Terlambat ================
            ============================================================================================= --}}
            @if($transaksi->status == 'Dipinjam' && $transaksi->terlambat > 0)
                <div class="alert alert-warning border-danger shadow-sm mb-4" role="alert">
                    <h5 class="alert-heading text-danger fw-bold mb-1">
                        <i class="bi bi-exclamation-triangle-fill"></i> Peringatan Keterlambatan!
                    </h5>
                    <p class="mb-0 text-dark">
                        Peminjaman buku ini telah melewati batas tanggal pengembalian selama 
                        <strong class="text-danger">{{ $transaksi->terlambat }} hari</strong>. 
                        Harap segera lakukan proses pengembalian dan penagihan denda sebesar 
                        <strong class="text-danger">Rp {{ number_format($transaksi->terlambat * 5000, 0, ',', '.') }}</strong>.
                    </p>
                </div>
            @endif

            {{-- Card Detail --}}
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">
                        <i class="bi bi-info-circle"></i> Detail Transaksi: <code>{{ $transaksi->kode_transaksi }}</code>
                    </h5>
                    <span class="badge {{ $transaksi->status == 'Dipinjam' ? 'bg-warning text-dark' : 'bg-success' }}">
                        {{ $transaksi->status }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted border-bottom pb-2 fw-bold">Data Anggota</h6>
                            <p class="mb-1"><strong>Nama:</strong> {{ $transaksi->anggota->nama }}</p>
                            <p class="mb-1"><strong>Email:</strong> {{ $transaksi->anggota->email }}</p>
                            <p class="mb-1"><strong>Telepon:</strong> {{ $transaksi->anggota->telepon }}</p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted border-bottom pb-2 fw-bold">Data Buku</h6>
                            <p class="mb-1"><strong>Judul:</strong> {{ $transaksi->buku->Judul }}</p>
                            <p class="mb-1"><strong>Pengarang:</strong> {{ $transaksi->buku->pengarang }}</p>
                            <p class="mb-1"><strong>Kategori:</strong> {{ $transaksi->buku->Kategori }}</p>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h6 class="text-muted border-bottom pb-2 fw-bold">Detail Peminjaman</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td style="width: 200px;"><strong>Tanggal Pinjam</strong></td>
                                    <td>: {{ \Carbon\Carbon::parse($transaksi->tanggal_pinjam)->format('d M Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Tanggal Kembali (Batas)</strong></td>
                                    <td>: {{ \Carbon\Carbon::parse($transaksi->tanggal_kembali)->format('d M Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Tanggal Dikembalikan</strong></td>
                                    <td>: 
                                        @if($transaksi->tanggal_dikembalikan)
                                            {{ \Carbon\Carbon::parse($transaksi->tanggal_dikembalikan)->format('d M Y H:i') }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Total Denda</strong></td>
                                    <td>: 
                                        @if($transaksi->status == 'Dikembalikan')
                                            <span class="fw-bold text-{{ $transaksi->denda > 0 ? 'danger' : 'success' }}">
                                                Rp {{ number_format($transaksi->denda, 0, ',', '.') }}
                                            </span>
                                        @else
                                            {{-- Perhitungan denda real-time sebelum dikembalikan --}}
                                            @php
                                                $hariIni = now()->startOfDay();
                                                $tglKembali = \Carbon\Carbon::parse($transaksi->tanggal_kembali)->startOfDay();
                                                $selisih = $tglKembali->diffInDays($hariIni, false);
                                                $estimasiDenda = $selisih > 0 ? $selisih * 5000 : 0;
                                            @endphp
                                            
                                            @if($estimasiDenda > 0)
                                                <span class="fw-bold text-danger">
                                                    Rp {{ number_format($estimasiDenda, 0, ',', '.') }} (Terlambat {{ $selisih }} Hari)
                                                </span>
                                            @else
                                                <span class="text-success">Rp 0 (Belum Terlambat)</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Keterangan</strong></td>
                                    <td>: {{ $transaksi->keterangan ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <hr>
                    
                    {{-- Status Alert Tepat Waktu / Terlambat --}}
                    @if($transaksi->status == 'Dikembalikan')
                        @if($transaksi->denda <= 0)
                            <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <div>
                                    Buku dikembalikan <strong>tepat waktu</strong> pada {{ \Carbon\Carbon::parse($transaksi->tanggal_dikembalikan)->format('d M Y') }}.
                                </div>
                            </div>
                        @else
                            <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div>
                                    Buku <strong>terlambat dikembalikan</strong>! Total denda yang harus dibayar: 
                                    <strong>Rp {{ number_format($transaksi->denda, 0, ',', '.') }}</strong>.
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- Action Buttons --}}
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('transaksi.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                        </a>

                        {{-- Tombol Pengembalian Buku --}}
                        @if($transaksi->status == 'Dipinjam')
                            <form action="{{ route('transaksi.kembalikan', $transaksi->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memproses pengembalian buku ini?')">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-warning fw-bold text-dark">
                                    <i class="bi bi-arrow-counterclockwise"></i> Kembalikan Buku
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>