<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WakeSignInfo extends Model
{
    protected $fillable = [
        'openid', 'sign_day', 'sign_score'
    ];

    protected $hidden = [
        'id', 'created_at', 'updated_at'
    ];

    public function getFillable()
    {
        return $this->fillable;
    }
}
