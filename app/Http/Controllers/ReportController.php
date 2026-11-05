<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * LAPORAN HARIAN & BULANAN (khusus Waka/Admin)
 *
 * Merangkum peminjaman ruangan (agenda) dan pengaduan kerusakan pada satu bulan.
 * Bisa dilihat di layar, dicetak / disimpan PDF lewat browser, diunduh sebagai
 * CSV (dibuka di Excel), atau dikirim ringkasannya lewat WhatsApp.
 *
 * Akses dibatasi middleware 'role:Waka/Admin' di routes/web.php.
 */
class ReportController extends Controller
{
    private const TZ = 'Asia/Jakarta';

    public function index(Request $request)
    {
        $data = $this->buildReport($request);

        $data['waUrl'] = 'https://wa.me/?text=' . rawurlencode($this->summaryText($data));

        return view('reports.index', $data);
    }

    public function export(Request $request)
    {
        $d = $this->buildReport($request);
        $filename = 'laporan-rapi-sekolah-' . ($d['mode'] === 'daily' ? $d['date'] : $d['month']) . '.csv';

        return response()->streamDownload(function () use ($d) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            $put = fn (array $row) => fputcsv($out, $row, ';');

            $put([($d['mode'] === 'daily' ? 'LAPORAN HARIAN' : 'LAPORAN BULANAN') . ' RAPI-SEKOLAH - ' . $d['periodLabel']]);
            $put([]);
            $put(['PEMINJAMAN RUANGAN']);
            $put(['Tanggal', 'Sesi', 'Ruangan', 'Agenda', 'Peminjam', 'Status', 'Alasan Ditolak', 'Alasan Pindah']);
            foreach ($d['events'] as $e) {
                $put([
                    $e->date->format('d/m/Y'),
                    $e->sessionLabel(),
                    $e->room->room_name ?? '-',
                    $e->event_name,
                    $e->user->fullname ?? $e->user->username ?? '-',
                    ucfirst($e->normalizedStatus() ?: 'pending'),
                    $e->reject_reason ?? '',
                    $e->move_reason ?? '',
                ]);
            }

            $put([]);
            $put(['PENGADUAN KERUSAKAN']);
            $put(['Tanggal Lapor', 'Ruangan', 'Kerusakan', 'Pelapor', 'PIC', 'Status', 'Tanggal Selesai']);
            foreach ($d['complaints'] as $c) {
                $put([
                    $c->reported_at ? $c->reported_at->copy()->timezone(self::TZ)->format('d/m/Y H:i') : '-',
                    $c->room->room_name ?? '-',
                    $c->description,
                    $c->reporter->fullname ?? '-',
                    $c->pic->fullname ?? $c->room->pic->fullname ?? '-',
                    $c->status_repair,
                    $c->repaired_at ? $c->repaired_at->copy()->timezone(self::TZ)->format('d/m/Y H:i') : '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Kumpulkan data satu periode.
     *   ?mode=daily&date=YYYY-MM-DD  -> laporan harian
     *   ?mode=monthly&month=YYYY-MM  -> laporan bulanan (default, bulan ini WIB)
     */
    private function buildReport(Request $request): array
    {
        $mode = $request->query('mode') === 'daily' ? 'daily' : 'monthly';

        $month = (string) $request->query('month', '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = Carbon::now(self::TZ)->format('Y-m');
        }

        $date = (string) $request->query('date', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            $date = Carbon::now(self::TZ)->toDateString();
        }

        if ($mode === 'daily') {
            $start = Carbon::createFromFormat('Y-m-d', $date, self::TZ)->startOfDay();
            $end   = $start->copy()->endOfDay();
            $periodLabel = $start->copy()->locale('id')->translatedFormat('l, d F Y');
        } else {
            $start = Carbon::createFromFormat('Y-m-d', $month . '-01', self::TZ)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $periodLabel = $start->copy()->locale('id')->translatedFormat('F Y');
        }

        $events = Event::with(['room', 'user'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->orderBy('start_session')
            ->orderBy('start_time')
            ->get();

        // reported_at tersimpan dalam timezone aplikasi (UTC), jadi batas periode WIB dikonversi dulu.
        $appTz = config('app.timezone');
        $complaints = Complaint::with(['room.pic', 'pic', 'reporter'])
            ->whereBetween('reported_at', [
                $start->copy()->timezone($appTz),
                $end->copy()->timezone($appTz),
            ])
            ->orderBy('reported_at')
            ->get();

        $eventStats = [
            'total'    => $events->count(),
            'approved' => $events->filter(fn ($e) => $e->isApproved())->count(),
            'rejected' => $events->filter(fn ($e) => $e->normalizedStatus() === 'rejected')->count(),
        ];
        $eventStats['pending'] = $eventStats['total'] - $eventStats['approved'] - $eventStats['rejected'];

        $complaintStats = ['total' => $complaints->count()];
        foreach (Complaint::STATUSES as $status) {
            $complaintStats[$status] = $complaints->where('status_repair', $status)->count();
        }

        return [
            'mode'           => $mode,
            'month'          => $month,
            'date'           => $date,
            'periodLabel'    => $periodLabel,
            'monthLabel'     => $periodLabel,
            'events'         => $events,
            'complaints'     => $complaints,
            'eventStats'     => $eventStats,
            'complaintStats' => $complaintStats,
        ];
    }

    private function summaryText(array $d): string
    {
        $e = $d['eventStats'];
        $c = $d['complaintStats'];

        $text = "*" . ($d['mode'] === 'daily' ? 'LAPORAN HARIAN' : 'LAPORAN BULANAN') . " RAPI-Sekolah*\n"
            . "Periode: " . $d['monthLabel'] . "\n\n"
            . "*Peminjaman Ruangan*\n"
            . "Total: {$e['total']}\n"
            . "Disetujui: {$e['approved']}\n"
            . "Menunggu: {$e['pending']}\n"
            . "Ditolak: {$e['rejected']}\n\n"
            . "*Pengaduan Kerusakan*\n"
            . "Total: {$c['total']}\n";

        foreach (Complaint::STATUSES as $status) {
            $text .= $status . ': ' . $c[$status] . "\n";
        }

        return $text . "\nRincian lengkap ada di sistem RAPI-Sekolah (menu Laporan).";
    }
}
