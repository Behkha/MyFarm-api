<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackElement extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'feedback_group_id'];

    public function values()
    {
        return $this->hasMany(FeedbackElementValue::class);
    }
}
