<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WakeSignDetailInfo extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'openid', 'day_timestamp', 'sign_timestamp', 'sign_rank'
    ];

    protected $hidden = [
        'id'
    ];

    public function getFillable()
    {
        return $this->fillable;
    }
}
