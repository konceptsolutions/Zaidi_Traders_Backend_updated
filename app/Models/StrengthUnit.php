<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrengthUnit extends Model
{
    use HasFactory;
    protected $table = 'strength_unit';
    protected $fillable = ['name', 'description'];
}
