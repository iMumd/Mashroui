<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\GenderEnum;
use App\Enums\RegionEnum;
use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'whatsapp',
    'employee_number',
    'university_number',
    'gender',
    'region',
    'specialization_id',
    'scope_department_id',
    'term_id',
    'status',
    'restricted_reason',
    'deleted_reason',
    'must_change_password',
    'avatar_path',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $appends = ['avatar_url', 'committee_scope_ids'];

    /** رابط صورة الملف التعريفي (أو null) — asset() يتبع عنوان الطلب الحالي */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn () => $this->avatar_path ? asset('storage/'.$this->avatar_path) : null);
    }

    /** نطاق عضو اللجنة في الدخول و/me لتقييد قوائم التخصصات في الواجهة (null للأدوار الأخرى بلا استعلام) */
    protected function committeeScopeIds(): Attribute
    {
        return Attribute::get(fn () => $this->committeeSpecializationIds());
    }

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
            'gender' => GenderEnum::class,
            'region' => RegionEnum::class,
            'status' => UserStatusEnum::class,
            'must_change_password' => 'boolean',
        ];
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    /** تخصصات عضو لجنة الإشراف (حتى 5) — بديلها scope_department_id للقسم الكامل */
    public function committeeSpecializations(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'committee_specializations');
    }

    private array|false|null $scopeIdsCache = false;

    /**
     * التخصصات التي يقتصر عليها عضو اللجنة (null = بلا تقييد): تخصصات القسم الكامل، وإلا تخصصاته، وإلا specialization_id القديم.
     * تُحفظ طوال الطلب لأن SpecializationScope يستدعيها مع كل استعلام مجموعات/مشاريع.
     */
    public function committeeSpecializationIds(): ?array
    {
        if ($this->role !== RoleEnum::Committee) {
            return null;
        }

        if ($this->scopeIdsCache === false) {
            $this->scopeIdsCache = $this->scope_department_id
                ? Specialization::where('department_id', $this->scope_department_id)->pluck('id')->all()
                : ($this->committeeSpecializations()->pluck('specializations.id')->all()
                    ?: ($this->specialization_id ? [$this->specialization_id] : null));
        }

        return $this->scopeIdsCache;
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function inviteLinks(): HasMany
    {
        return $this->hasMany(InviteLink::class);
    }

    /** مهام كل الفرق التي يشرف عليها */
    public function supervisedTasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Team::class, 'supervisor_id', 'team_id');
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

    public function restrictions(): HasMany
    {
        return $this->hasMany(UserRestriction::class);
    }
}
