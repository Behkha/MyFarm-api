<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackElementValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['value'];
}
