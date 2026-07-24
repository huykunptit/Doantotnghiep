<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CareerPath extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'target_role',
        'price',
        'status',
        'cover_url',
        'certificate_template_id',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'price' => 'integer',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CareerPath $path) {
            if (blank($path->slug)) {
                $path->slug = static::uniqueSlug((string) $path->title);
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'path';
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function pathCourses(): HasMany
    {
        return $this->hasMany(CareerPathCourse::class)->orderBy('sort_order');
    }

    public function userCareerPaths(): HasMany
    {
        return $this->hasMany(UserCareerPath::class);
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requiredPathCourses()
    {
        return $this->pathCourses()->where('is_required', true);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
