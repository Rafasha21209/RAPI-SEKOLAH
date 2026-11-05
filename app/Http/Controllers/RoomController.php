<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * MANAJEMEN RUANGAN (khusus Waka/Admin)
 *
 * Akses dibatasi lewat middleware 'role:Waka/Admin' di routes/web.php.
 */
class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::with('pic')
            ->withCount(['events', 'complaints'])
            ->orderBy('room_name')
            ->get();

        $pics  = $this->picCandidates();
        $types = Room::TYPES;

        return view('rooms.index', compact('rooms', 'pics', 'types'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $room = Room::create([
            'room_name' => trim($validated['room_name']),
            'room_type' => $validated['room_type'],
            'capacity'  => (int) $validated['capacity'],
            'location'  => trim($validated['location']),
            'pic_id'    => $validated['pic_id'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('rooms.index')
            ->with('success', 'Ruangan "' . $room->room_name . '" berhasil ditambahkan.');
    }

    public function update(Request $request, Room $room)
    {
        $validated = $request->validate($this->rules($room), $this->messages());

        $room->update([
            'room_name' => trim($validated['room_name']),
            'room_type' => $validated['room_type'],
            'capacity'  => (int) $validated['capacity'],
            'location'  => trim($validated['location']),
            'pic_id'    => $validated['pic_id'] ?? null,
        ]);

        return back()->with('success', 'Ruangan "' . $room->room_name . '" berhasil diperbarui.');
    }

    /**
     * Aktif/nonaktifkan ruangan. Ruangan nonaktif tidak muncul di pilihan
     * peminjaman & pengaduan, tetapi riwayatnya tetap tersimpan.
     */
    public function toggle(Room $room)
    {
        $room->update(['is_active' => !$room->is_active]);

        return back()->with(
            'success',
            'Ruangan "' . $room->room_name . '" sekarang ' . ($room->is_active ? 'aktif.' : 'nonaktif.')
        );
    }

    /**
     * Hapus ruangan. Dicegah jika sudah punya agenda/pengaduan agar riwayat
     * tidak rusak; sarankan menonaktifkan sebagai gantinya.
     */
    public function destroy(Room $room)
    {
        $jumlahAgenda    = $room->events()->count();
        $jumlahPengaduan = $room->complaints()->withTrashed()->count();

        if ($jumlahAgenda > 0 || $jumlahPengaduan > 0) {
            return back()->with(
                'error',
                'Ruangan "' . $room->room_name . '" sudah punya ' . $jumlahAgenda . ' agenda dan '
                    . $jumlahPengaduan . ' pengaduan, tidak bisa dihapus. Nonaktifkan saja ruangan ini.'
            );
        }

        $nama = $room->room_name;
        $room->delete();

        return back()->with('success', 'Ruangan "' . $nama . '" berhasil dihapus.');
    }

    private function picCandidates()
    {
        return User::whereHas('role', fn ($q) => $q->where('role_name', 'PIC Ruang'))
            ->orderBy('fullname')
            ->get();
    }

    private function rules(?Room $room = null): array
    {
        return [
            'room_name' => [
                'required', 'string', 'max:100',
                Rule::unique('rooms', 'room_name')->ignore($room?->id),
            ],
            'room_type' => ['required', Rule::in(Room::TYPES)],
            'capacity'  => ['required', 'integer', 'min:1', 'max:1000'],
            'location'  => ['required', 'string', 'max:150'],
            'pic_id'    => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->whereIn('role_id', Role::where('role_name', 'PIC Ruang')->pluck('id'))
                ),
            ],
        ];
    }

    private function messages(): array
    {
        return [
            'room_name.required' => 'Nama ruangan wajib diisi.',
            'room_name.unique'   => 'Nama ruangan tersebut sudah ada.',
            'room_name.max'      => 'Nama ruangan maksimal 100 karakter.',
            'room_type.required' => 'Jenis ruangan wajib dipilih.',
            'room_type.in'       => 'Jenis ruangan tidak valid.',
            'capacity.required'  => 'Kapasitas wajib diisi.',
            'capacity.integer'   => 'Kapasitas harus berupa angka.',
            'capacity.min'       => 'Kapasitas minimal 1.',
            'capacity.max'       => 'Kapasitas maksimal 1000.',
            'location.required'  => 'Lokasi wajib diisi.',
            'location.max'       => 'Lokasi maksimal 150 karakter.',
            'pic_id.exists'      => 'PIC yang dipilih harus user dengan role PIC Ruang.',
        ];
    }
}
