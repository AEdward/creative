<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'announcements', 'support'];

    protected function casts(): array
    {
        return ['announcements' => 'boolean', 'support' => 'boolean'];
    }
}
