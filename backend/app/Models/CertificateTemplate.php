<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'name',
        'background_image_url',
        'fields_config',
    ];

    protected $casts = [
        'fields_config' => 'array',
    ];
}
