<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class Category extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function getImageAttribute()
    {
        if (strpos($this->logo, 'http://lorempixel.com/400/200/') === 0) {
            return $this->logo;
        }

        return $this->logo ? env('STORAGE_PATH') . $this->logo : null;
    }

    public function getLogoAttribute($value)
    {
        return $value ? env('STORAGE_PATH') . $value : null;
    }

    public function isBookmarkedByUser()
    {
        $user = auth('user')->user();

        return Redis::SISMEMBER('user:' . $user->id . ':bookmarks', 'category:' . $this->id);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class);
    }

    public function feedbackGroups()
    {
        return $this->belongsToMany(FeedbackGroup::class, 'category_feedback');
    }

}
