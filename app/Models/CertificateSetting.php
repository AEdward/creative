<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateSetting extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
