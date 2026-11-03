<?php

namespace App\Domain\Customer\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = ['name', 'color'];
}