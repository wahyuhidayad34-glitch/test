<?php

namespace App\Models\Concerns;

trait GeneratesDocumentNumber
{
    /**
     * Nomor dokumen berikutnya: PREFIX-TAHUN-0001 (reset tiap tahun).
     * Berdasarkan nomor TERBESAR yang ada (bukan count()), sehingga aman
     * setelah ada record yang dihapus. Kolom bersifat UNIQUE sebagai pengaman terakhir.
     */
    protected static function nextDocumentNumber(string $prefix, string $column): string
    {
        $like = $prefix . '-' . date('Y') . '-';

        $last = static::query()
            ->where($column, 'like', $like . '%')
            ->orderByRaw("LENGTH({$column}) DESC")
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        $next = $last ? ((int) substr($last, strlen($like))) + 1 : 1;

        return $like . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
