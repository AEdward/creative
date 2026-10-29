<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'course_id', 'title_snapshot', 'unit_price_minor'];

    protected function casts(): array
    {
        return ['unit_price_minor' => 'integer'];
    }
}
