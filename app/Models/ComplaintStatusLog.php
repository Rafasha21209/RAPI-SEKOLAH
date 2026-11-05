<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Histori perubahan modul pengaduan (audit trail).
 * Tidak pernah dihapus saat status pengaduan berubah.
 */
class ComplaintStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'actor_id',
        'action',
        'status_from',
        'status_to',
        'note',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class, 'complaint_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
