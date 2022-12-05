<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackGroup extends Model
{
    public $timestamps = false;

    protected $fillable = ['title'];
}
