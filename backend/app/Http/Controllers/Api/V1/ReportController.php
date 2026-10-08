<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FinancialCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanPengeluaranRequest;
use App\Http\Resources\FinancialTransactionResource;
use App\Models\FinancialTransaction;
use App\Services\FinanceService;
use App\Services\ReportService;
use App\Services\SettingService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReportService $laporan,
        private readonly FinanceService $keuangan,
        private readonly SettingService $setting,
    ) {}

    /* --------------------------------------------------------------- keuangan */

    public function finance(Request $request): JsonResponse
    {
        $data = $this->laporan->keuangan(
            (string) $request->query('period', 'bulanan'),
            $request->query('date'),
            $request->query('sampai')
        );

        $data['transaksi'] = FinancialTransactionResource::collection(
            $this->laporan->transaksi($data['dari'], $data['sampai'], 300)
        );

        return $this->sukses($data);
    }

    public function financeExport(Request $request): Response
    {
        $data = $this->laporan->keuangan(
            (string) $request->query('period', 'bulanan'),
            $request->query('date'),
            $request->query('sampai')
        );

        $data['bengkel'] = $this->identitasBengkel();

        if ($request->query('format', 'pdf') === 'excel') {
            return Excel::download(
                new \App\Exports\LaporanKeuanganExport($data),
                'laporan-keuangan-'.$data['dari'].'-'.$data['sampai'].'.xlsx'
            );
        }

        $pdf = Pdf::loadView('pdf.laporan-keuangan', $data + ['dicetak' => now()])->setPaper('a4');
        $pdf->setOption('isFontSubsettingEnabled', true);

        return $pdf->stream('laporan-keuangan-'.$data['dari'].'-'.$data['sampai'].'.pdf', ['Attachment' => false]);
    }

    /* ------------------------------------------------------------------ stok */

    public function stock(Request $request): JsonResponse
    {
        return $this->sukses($this->laporan->stok($request->boolean('menipis')));
    }

    public function stockPdf(Request $request): Response
    {
        $data = $this->laporan->stok($request->boolean('menipis'));

        $pdf = Pdf::loadView('pdf.laporan-stok', $data + [
            'bengkel' => $this->identitasBengkel(),
            'dicetak' => now(),
        ])->setPaper('a4');
        $pdf->setOption('isFontSubsettingEnabled', true);

        return $pdf->stream('laporan-stok-'.now()->format('Ymd').'.pdf', ['Attachment' => false]);
    }

    /* ------------------------------------------------------------ unit entry */

    public function unitEntries(Request $request): JsonResponse
    {
        return $this->sukses($this->laporan->unitEntry(
            $request->query('from'),
            $request->query('to'),
            $request->query('type')
        ));
    }

    public function unitEntriesExport(Request $request): Response
    {
        $data = $this->laporan->unitEntry(
            $request->query('from'),
            $request->query('to'),
            $request->query('type')
        );

        return Excel::download(
            new \App\Exports\LaporanUnitEntryExport($data),
            'laporan-unit-entry-'.$data['dari'].'-'.$data['sampai'].'.xlsx'
        );
    }

    /* ------------------------------------------------------- pengeluaran lain */

    public function expenses(Request $request): JsonResponse
    {
        $query = FinancialTransaction::query()->with('pembuat')
            ->where('type', 'expense')
            ->whereNotIn('category', [FinancialCategory::PembelianSparepart->value]);

        if ($request->filled('dari')) {
            $query->whereDate('transaction_date', '>=', $request->query('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('transaction_date', '<=', $request->query('sampai'));
        }

        $halaman = $query->latest('transaction_date')->latest('id')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, FinancialTransactionResource::class);
    }

    /** Pengeluaran operasional manual (listrik, gaji, dll.). */
    public function storeExpense(SimpanPengeluaranRequest $request): JsonResponse
    {
        $trx = $this->keuangan->expense(
            $request->input('transaction_date'),
            $request->input('category'),
            $request->integer('amount'),
            ['description' => $request->input('description')]
        );

        return $this->dibuat(new FinancialTransactionResource($trx), 'Pengeluaran berhasil dicatat.');
    }

    /** @return array<string, string> */
    private function identitasBengkel(): array
    {
        return [
            'nama' => $this->setting->string('shop_name', 'Bengkel'),
            'alamat' => $this->setting->string('shop_address'),
            'telepon' => $this->setting->string('shop_phone'),
        ];
    }
}