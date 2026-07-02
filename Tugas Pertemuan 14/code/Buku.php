<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Transaksi;

class Buku extends Model
{
    use HasFactory;

    /**
     * Nama tabel
     *
     * @var string
     */
    protected $table = 'buku';

    /**
     * Kolom yang dapat diisi secara mass assignment
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'Kode_Buku',
        'Judul',
        'Kategori',
        'pengarang',
        'penerbit',
        'tahun_terbit',
        'isbn',
        'harga',
        'stok',
        'deskripsi',
        'bahasa',
    ];

    /**
     * Tipe casting untuk atribut
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tahun_terbit' => 'integer',
        'harga'        => 'decimal:2',
        'stok'         => 'integer',
    ];

    // ===========================================================================================================================================
    // Pertemuan 14 - Transaksi
    // ===========================================================================================================================================
    public function transaksis()
    {
        return $this->hasMany(Transaksi::class);
    }

    // ===========================================================================================================================================
    // ACCESSOR
    // ===========================================================================================================================================
    public function getHargaFormatAttribute(): string
    {
        return 'Rp ' . number_format($this->harga, 0, ',', '.');
    }

    /**
     * Accessor untuk status ketersediaan
     */
    public function getTersediaAttribute(): bool
    {
        return $this->stok > 0;
    }

    /**
     * Accessor untuk badge status stok
     * Stok = 0         : badge danger  "Habis"
     * Stok 1–5         : badge warning "Menipis"
     * Stok 6–15        : badge info    "Sedang"
     * Stok > 15        : badge success "Aman"
     */
    public function getStatusStokBadgeAttribute(): string
    {
        if ($this->stok == 0) {
            return '<span class="badge bg-danger">Habis</span>';
        } elseif ($this->stok <= 5) {
            return '<span class="badge bg-warning">Menipis</span>';
        } elseif ($this->stok <= 15) {
            return '<span class="badge bg-info">Sedang</span>';
        } else {
            return '<span class="badge bg-success">Aman</span>';
        }
    }

    /**
     * Accessor untuk label tahun terbit
     * - tahun_terbit >= 2024 : "Buku Baru"
     * - tahun_terbit <  2024 : "Buku Lama"
     */
    public function getTahunLabelAttribute(): string
    {
        if ($this->tahun_terbit >= 2024) {
            return 'Buku Baru';
        }

        return 'Buku Lama';
    }

    // Kode Buku - Bk-001 dst
    public function getKodeBukuRapiAttribute(): string
    {
        // Ambil nilai asli kolom dari database, jika null pakai fallback 'BK-' . ID
        $kodeAsli = $this->attributes['Kode_Buku'] ?? 'BK-' . $this->id;

        if (str_contains($kodeAsli, '-')) {
            $parts = explode('-', $kodeAsli);
            // Mengubah angka setelah strip menjadi format 3 digit (contoh: 1 -> 001)
            $angkaFormat = str_pad($parts[1], 3, '0', STR_PAD_LEFT);
            return $parts[0] . '-' . $angkaFormat;
        }

        return $kodeAsli;
    }

    // ===========================================================================================================================================
    // SCOPE
    // ===========================================================================================================================================

    /**
     * Filter buku yang stoknya > 0
     */
    public function scopeTersedia($query)
    {
        return $query->where('stok', '>', 0);
    }

    /**
     * Filter berdasarkan kategori
     */
    public function scopeKategori($query, $kategori)
    {
        return $query->where('Kategori', $kategori);
    }

    /**
     * Scope (TUGAS 2 - P10)
     * Filter buku dengan stok < 5 (stok menipis)
     */
    public function scopeStokMenipis($query)
    {
        return $query->where('stok', '<', 5);
    }

    /**
     * Scope (TUGAS 2 - P10)
     * Filter buku dengan harga antara $min dan $max
     */
    public function scopeHargaRange($query, $min, $max)
    {
        return $query->whereBetween('harga', [$min, $max]);
    }

    /**
     * Scope (TUGAS 2 - P10)
     * Urutkan buku berdasarkan created_at descending (paling terbaru yang ditambahkan)
     * Filter tambahan: hanya buku dengan tahun_terbit >= 2024
     */
    public function scopeTerbaru($query)
    {
        return $query->where('tahun_terbit', '>=', 2024)
                     ->orderBy('created_at', 'desc');
    }

    // ===========================================================================================================================================
    // TUGAS 1 - P11
    // ========================================================================================================
    /**
     * Filter buku yang stoknya habis (stok <= 0)
     */
    public function scopeHabis($query)
    {
        return $query->where('stok', '<=', 0);
    }
}