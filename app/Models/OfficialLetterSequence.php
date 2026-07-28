<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialLetterSequence extends Model
{
    protected $fillable = [
        'department_code',
        'year_month',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'last_sequence' => 'integer',
        ];
    }
}
