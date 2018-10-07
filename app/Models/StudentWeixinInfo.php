<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentWeixinInfo extends Model
{
    protected $fillable = [
        'openid','nickname','avatar','introduction'
    ];
    protected $hidden = [
        'id','created_at', 'updated_at',
    ];
    public function getFillable()
    {
        return $this->fillable;
    }
}
