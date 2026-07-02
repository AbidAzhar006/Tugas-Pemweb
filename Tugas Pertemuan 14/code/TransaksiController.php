<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\Buku;
use App\Models\Anggota;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransaksiController extends Controller
{
    // Filter Advanced
    public function index(Request $request)
    {
        // Menggunakan eager loading agar tidak terkena N+1 query problem
        $query = Transaksi::with(['anggota', 'buku']);

        // Implementasi Filter Advanced Berdasarkan Pilihan Dropdown
        $query->when($request->status_filter, function ($q, $filter) {
            $hariIni = now()->startOfDay();

            switch ($filter) {
                case 'dipinjam_aman':
                    return $q->where('status', 'Dipinjam')
                             ->whereDate('tanggal_kembali', '>=', $hariIni);
                    
                case 'dipinjam_terlambat':
                    // Mengecek data lewat kolom 'terlambat' (jika ada) ATAU lewat perbandingan tanggal kembali
                    return $q->where('status', 'Dipinjam')
                             ->whereDate('tanggal_kembali', '<', $hariIni);
                    
                case 'dikembalikan_tepat':
                    return $q->where('status', 'Dikembalikan')
                             ->whereColumn('tanggal_dikembalikan', '<=', 'tanggal_kembali');
                    
                case 'dikembalikan_terlambat':
                    return $q->where('status', 'Dikembalikan')
                             ->whereColumn('tanggal_dikembalikan', '>', 'tanggal_kembali');
            }
        });

        // Ambil data terbaru yang telah difilter
        $transaksis = $query->latest()->get();
        
        return view('transaksi.index', compact('transaksis'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get only anggota aktif
        $anggotas = Anggota::where('status', 'Aktif')->orderBy('nama')->get();
        
        // Get only buku yang tersedia (stok > 0)
        $bukus = Buku::where('stok', '>', 0)->orderBy('judul')->get();
        
        return view('transaksi.create', compact('anggotas', 'bukus'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'anggota_id' => 'required|exists:anggota,id',
            'buku_id' => 'required|exists:buku,id',
            'tanggal_pinjam' => 'required|date',
            'keterangan' => 'nullable|string',
        ], [
            'anggota_id.required' => 'Anggota wajib dipilih.',
            'buku_id.required' => 'Buku wajib dipilih.',
            'tanggal_pinjam.required' => 'Tanggal pinjam wajib diisi.',
        ]);
        
        try {
            DB::transaction(function () use ($request) {
                // 1. Check stok buku
                $buku = Buku::findOrFail($request->buku_id);
                if ($buku->stok <= 0) {
                    throw new \Exception('Stok buku habis!');
                }
                
                // 2. Generate kode transaksi
                $kodeTransaksi = $this->generateKodeTransaksi();
                
                // 3. Calculate tanggal kembali (7 hari dari tanggal pinjam)
                $tanggalKembali = Carbon::parse($request->tanggal_pinjam)->addDays(7);
                
                // 4. Create transaksi
                Transaksi::create([
                    'kode_transaksi' => $kodeTransaksi,
                    'anggota_id' => $request->anggota_id,
                    'buku_id' => $request->buku_id,
                    'tanggal_pinjam' => $request->tanggal_pinjam,
                    'tanggal_kembali' => $tanggalKembali,
                    'status' => 'Dipinjam',
                    'keterangan' => $request->keterangan,
                ]);
                
                // 5. Update stok buku (kurang 1)
                $buku->decrement('stok');
            });
            
            return redirect()->route('transaksi.index')
                             ->with('success', 'Transaksi peminjaman berhasil dibuat!');
                             
        } catch (\Exception $e) {
            return redirect()->back()
                             ->withInput()
                             ->with('error', 'Gagal membuat transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaksi = Transaksi::with(['anggota', 'buku'])->findOrFail($id);
        return view('transaksi.show', compact('transaksi'));
    }

    /**
     * Kembalikan buku (update status transaksi).
     */
    public function kembalikan(string $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $transaksi = Transaksi::findOrFail($id);

                // ===============================================================================================
                // ============================== Modul P15 - Praktikum 1 ========================================
                // ===============================================================================================
                if ($transaksi->status === 'Dikembalikan') {
                    throw new \Exception('Buku sudah dikembalikan sebelumnya.');
                }
                
                // Update transaksi
                $tanggalDikembalikan = now();
                $denda = $this->hitungDenda($transaksi, $tanggalDikembalikan);
                
                $transaksi->update([
                    'status' => 'Dikembalikan',
                    'tanggal_dikembalikan' => $tanggalDikembalikan,
                    'denda' => $denda,
                ]);
                
                // Update stok buku (tambah 1)
                $transaksi->buku->increment('stok');
            });
            
            return redirect()->route('transaksi.show', $id)
                             ->with('success', 'Buku berhasil dikembalikan!');
                             
        } catch (\Exception $e) {
            return redirect()->back()
                             ->with('error', 'Gagal mengembalikan buku: ' . $e->getMessage());
        }
    }
 
    /**
     * Generate kode transaksi otomatis.
     */
    private function generateKodeTransaksi()
    {
        $lastTransaksi = Transaksi::latest()->first();
        
        if ($lastTransaksi) {
            $lastNumber = intval(substr($lastTransaksi->kode_transaksi, -3));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return 'TRX-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
 
    /**
     * Hitung denda keterlambatan.
     */
    private function hitungDenda($transaksi, $tanggalDikembalikan)
    {
        $hariTerlambat = $transaksi->tanggal_kembali->diffInDays($tanggalDikembalikan, false);
        
        if ($hariTerlambat > 0) {
            // Denda Rp 5.000 per hari
            return $hariTerlambat * 5000;
        }
        
        return 0;
    }


    // =================================================================================================================================================
    // Pertemuan 14 - Tugas 2 - Laporan Transaksi dengan Filter & Export PDF
    // =================================================================================================================================================
    /**
     * Tampilkan Halaman Laporan Transaksi dengan Filter - Tugas 2 
     */
    public function laporan(Request $request)
    {
        $anggotas = Anggota::orderBy('nama')->get();
        
        // Membangun query dasar laporan dengan relasi anggota dan buku
        $query = Transaksi::with(['anggota', 'buku']);

        // 1. Filter Berdasarkan Range Tanggal Pinjam (dari - sampai)
        if ($request->filled('tanggal_dari') && $request->filled('tanggal_sampai')) {
            $query->whereBetween('tanggal_pinjam', [$request->tanggal_dari, $request->tanggal_sampai]);
        }
        
        // ================================================================================================
        // 2. Filter Berdasarkan Status (Tambahan)
        // ================================================================================================
        if ($request->filled('status') && $request->status !== 'Semua') {
            $hariIni = now()->startOfDay();
            
            switch ($request->status) {
                case 'dipinjam_aman':
                    $query->where('status', 'Dipinjam')
                        ->whereDate('tanggal_kembali', '>=', $hariIni);
                    break;
                case 'dipinjam_terlambat':
                    $query->where('status', 'Dipinjam')
                        ->whereDate('tanggal_kembali', '<', $hariIni);
                    break;
                case 'dikembalikan_tepat':
                    $query->where('status', 'Dikembalikan')
                        ->whereColumn('tanggal_dikembalikan', '<=', 'tanggal_kembali');
                    break;
                case 'dikembalikan_terlambat':
                    $query->where('status', 'Dikembalikan')
                        ->whereColumn('tanggal_dikembalikan', '>', 'tanggal_kembali');
                    break;
            }
        }

        // 3. Filter Berdasarkan Anggota
        if ($request->filled('anggota_id')) {
            $query->where('anggota_id', $request->anggota_id);
        }

        // Eksekusi query mendapatkan data transaksi ter-filter
        $transaksis = $query->latest()->get();

        // Hitung Ringkasan / Total Data
        $totalTransaksi = $transaksis->count();
        $totalDenda = $transaksis->sum('denda');

        return view('transaksi.laporan', compact('transaksis', 'anggotas', 'totalTransaksi', 'totalDenda'));
    }

    /**
     * Cetak Laporan Ke File PDF - Tugas 2
     */
    public function exportPdf(Request $request)
    {
        // Re-query data berdasarkan filter yang sama agar PDF singkron dengan view halaman web
        $query = Transaksi::with(['anggota', 'buku']);

        if ($request->filled('tanggal_dari') && $request->filled('tanggal_sampai')) {
            $query->whereBetween('tanggal_pinjam', [$request->tanggal_dari, $request->tanggal_sampai]);
        }
        
        // ================================================================================================
        // Tambahan
        // ================================================================================================
        if ($request->filled('status') && $request->status !== 'Semua') {
            $hariIni = now()->startOfDay();
            
            switch ($request->status) {
                case 'dipinjam_aman':
                    $query->where('status', 'Dipinjam')
                        ->whereDate('tanggal_kembali', '>=', $hariIni);
                    break;
                case 'dipinjam_terlambat':
                    $query->where('status', 'Dipinjam')
                        ->whereDate('tanggal_kembali', '<', $hariIni);
                    break;
                case 'dikembalikan_tepat':
                    $query->where('status', 'Dikembalikan')
                        ->whereColumn('tanggal_dikembalikan', '<=', 'tanggal_kembali');
                    break;
                case 'dikembalikan_terlambat':
                    $query->where('status', 'Dikembalikan')
                        ->whereColumn('tanggal_dikembalikan', '>', 'tanggal_kembali');
                    break;
            }
        }

        if ($request->filled('anggota_id')) {
            $query->where('anggota_id', $request->anggota_id);
        }

        $transaksis = $query->latest()->get();
        $totalTransaksi = $transaksis->count();
        $totalDenda = $transaksis->sum('denda');

        // Pastikan kelas PDF (barryvdh/laravel-dompdf) terinstall di project
        if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            return redirect()->back()->with('error', 'Package DomPDF belum terinstall di server/project ini.');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('transaksi.laporan-pdf', compact('transaksis', 'totalTransaksi', 'totalDenda', 'request'));
        
        return $pdf->download('Laporan_Transaksi_Perpustakaan_' . now()->format('Ymd_His') . '.pdf');
    }

    // =============================================================================================================================================================================

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
