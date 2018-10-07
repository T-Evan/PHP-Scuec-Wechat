<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/18
 * Time: 11:34
 */

class StudentTransformer extends TransformerAbstract
{
    public function transform(\App\Models\StudentInfo $student)
    {
        return [
            $student->toArray(),
        ];
    }
}
