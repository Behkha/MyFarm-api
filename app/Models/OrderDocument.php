<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class OrderDocument extends Model
{
    public $timestamps = false;

    protected $fillable = ['cheque_id', 'paid_date', 'payer_name', 'description', 'file_path'];

    public function getFilePathAttribute($value)
    {
        return env('STORAGE_PATH') . $value;
    }

    public function getPaidDateAttribute($value)
    {
        return Jalalian::forge($value)->format('%Y-%m-%d');
    }
}
