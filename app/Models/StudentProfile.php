<?php

namespace App\Models;

use App\Enums\PreferredContactChannel;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProfile extends Model
{
    /** @use HasFactory<\Database\Factories\StudentProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'enrollment_number',
        'campus_id',
        'academic_program_id',
        'current_semester',
        'group_name',
        'photo_path',
        'academic_status',
        'personal_email',
        'phone',
        'preferred_contact_channel',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'academic_status' => StudentStatus::class,
            'preferred_contact_channel' => PreferredContactChannel::class,
            'current_semester' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function academicProgram(): BelongsTo
    {
        return $this->belongsTo(AcademicProgram::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AcademicStatusHistory::class)->latest('changed_at');
    }
}

