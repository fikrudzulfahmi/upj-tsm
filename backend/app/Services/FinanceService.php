<?php

namespace App\Services;

use App\Enums\FinancialCategory;
use App\Enums\FinancialType;
use App\Models\FinancialTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan pemasukan/pengeluaran. Pembatalan dilakukan lewat baris REVERSAL
 * (`reversal_of`), bukan menghapus baris asli — agar jejak audit tetap utuh.
 */
class FinanceService
{
    public function income(CarbonInterface|string $tanggal, FinancialCategory|string $kategori, int $jumlah, array $meta = []): ?FinancialTransaction
    {
        return $this->catat(FinancialType::Income, $tanggal, $kategori, $jumlah, $meta);
    }

    public function expense(CarbonInterface|string $tanggal, FinancialCategory|string $kategori, int $jumlah, array $meta = []): ?FinancialTransaction
    {
        return $this->catat(FinancialType::Expense, $tanggal, $kategori, $jumlah, $meta);
    }

    /** Buat baris kebalikan (income jadi expense, expense jadi income). */
    public function balik(FinancialTransaction $asal, ?string $alasan = null): FinancialTransaction
    {
        return DB::transaction(function () use ($asal, $alasan) {
            $tipe = $asal->type === FinancialType::Income ? FinancialType::Expense : FinancialType::Income;

            return FinancialTransaction::create([
                'transaction_date' => today()->toDateString(),
                'type' => $tipe->value,
                'category' => $asal->category,
                'amount' => $asal->amount,
                'reference_type' => $asal->reference_type,
                'reference_id' => $asal->reference_id,
                'description' => trim(($alasan ?: 'Pembatalan').' — kebalikan dari transaksi #'.$asal->id),
                'reversal_of' => $asal->id,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /** Baris keuangan yang terkait sebuah dokumen (untuk pembatalan SA). */
    public function untukReferensi(string $referenceType, int $referenceId): Collection
    {
        return FinancialTransaction::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereNull('reversal_of')
            ->get();
    }

    private function catat(
        FinancialType $tipe,
        CarbonInterface|string $tanggal,
        FinancialCategory|string $kategori,
        int $jumlah,
        array $meta = [],
    ): ?FinancialTransaction {
        if ($jumlah <= 0) {
            return null; // baris bernilai nol tidak dicatat
        }

        $kategoriNilai = $kategori instanceof FinancialCategory ? $kategori->value : $kategori;

        return FinancialTransaction::create([
            'transaction_date' => $tanggal instanceof CarbonInterface ? $tanggal->toDateString() : $tanggal,
            'type' => $tipe->value,
            'category' => $kategoriNilai,
            'amount' => $jumlah,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'description' => $meta['description'] ?? (FinancialCategory::tryFrom($kategoriNilai)?->label() ?? $kategoriNilai),
            'created_by' => $meta['created_by'] ?? auth()->id(),
        ]);
    }
}
