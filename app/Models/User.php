<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'whatsapp',
    'employee_number',
    'university_number',
    'specialization_id',
    'term_id',
    'status',
    'must_change_password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleEnum::class,
            'status' => UserStatusEnum::class,
            'must_change_password' => 'boolean',
        ];
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function inviteLinks(): HasMany
    {
        return $this->hasMany(InviteLink::class);
    }

    public function supervisedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'supervisor_id');
    }

    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'leader_id');
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'student_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
