<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = ['fitness_level', 'age', 'sex', 'height', 'weight'];
}
