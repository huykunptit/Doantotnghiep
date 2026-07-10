<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'google_id',
        'bio',
        'institution_id',
        'unit_id',
        'program_id',
        'major_id',
        'specialization_id',
        'cohort_id',
        'administrative_class_id',
        'advisor_id',
        'user_type',
        'student_code',
        'staff_code',
        'phone',
        'id_card_number',
        'gender',
        'date_of_birth',
        'nationality',
        'hometown',
        'permanent_address',
        'study_status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function lessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function userVouchers(): HasMany
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function cvs(): HasMany
    {
        return $this->hasMany(UserCV::class);
    }

    public function latestCv()
    {
        return $this->hasOne(UserCV::class)->latestOfMany();
    }

    public function careerRecommendations(): HasMany
    {
        return $this->hasMany(CareerRecommendation::class);
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'user_assignments')
            ->withPivot(['position_id', 'is_primary', 'start_date', 'end_date', 'status'])
            ->withTimestamps();
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function major()
    {
        return $this->belongsTo(Major::class);
    }

    public function specialization()
    {
        return $this->belongsTo(Specialization::class);
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class);
    }

    public function administrativeClass()
    {
        return $this->belongsTo(AdministrativeClass::class);
    }

    public function advisedAdministrativeClasses(): HasMany
    {
        return $this->hasMany(AdministrativeClass::class, 'advisor_id');
    }

    public function advisor()
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function advisees(): HasMany
    {
        return $this->hasMany(User::class, 'advisor_id');
    }

    public function teachingClassSections(): HasMany
    {
        return $this->hasMany(ClassSection::class, 'lecturer_id');
    }
}
