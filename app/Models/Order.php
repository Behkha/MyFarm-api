<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use \Morilog\Jalali\Jalalian;

class Order extends Model
{
    protected $fillable = [
        'address_id',
        'delivery_method_id',
        'status',
        'code',
        'user_id',
        'price',
        'payment_method',
    ];

    public const STATUS = [
        'unknown' => 1,
        'waiting_for_verification' => 2,
        'verified' => 3,
        'rejected' => 4,
        'waiting_for_send' => 5,
        'sent' => 6,
        'delivered' => 7,
        'canceled' => 8,
        'unsuccessful' => 9,
        'not_paid' => 10,
    ];

    public function getStatusAttribute($value)
    {
        switch ($value) {
            case 1:
                return 'نامعلوم';
            case 2:
                return 'در انتظار تایید';
            case 3:
                return 'پرداخت شده';
            case 4:
                return 'رد شده';
            case 5:
                return 'در انتظار ارسال';
            case 6:
                return 'ارسال شده';
            case 7:
                return 'تحویل داده شده';
            case 8:
                return 'لغو شده';
            case 9:
                return 'ناموفق';
            case 10:
                return 'پرداخت نشده';
        }
    }

    public function getStatusCodeAttribute()
    {
        return $this->getOriginal('status');
    }

    public function getCreatedAtAttribute($value)
    {
        return Jalalian::forge($value)->format('%Y-%m-%d %H:i');
    }

    public function getTotalPriceAttribute()
    {
        $totalPrice = 0;
        foreach ($this->products as $product) {
            $totalPrice += $product->pivot->price * $product->pivot->quantity;
        }
        $totalPrice += $this->deliveryMethod->price;
        return $totalPrice;
    }

    public function getPriceBeforeDiscountAttribute()
    {
        $price = 0;
        foreach ($this->products as $product) {
            $price += $product->pivot->price_before_discount * $product->pivot->quantity;
        }
        return $price;
    }

    public function products()
    {
        return $this
            ->belongsToMany(Product::class)
            ->withPivot('quantity', 'price', 'price_before_discount');
    }

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }

    public function deliveryMethod()
    {
        return $this->belongsTo(DeliveryMethod::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasOne(OrderDocument::class);
    }

    public function getOrderFeedbackAttribute()
    {
        $feedbacks = DB::table('order_feedback')
            ->where('order_id', $this->id)
            ->get();
        $orderFeedbacks = collect();
        foreach ($feedbacks as $feedback) {
            $fb = DB::table('feedbacks_for_orders')
                ->where('id', $feedback->feedback_id)
                ->first();
            $value = DB::table('feedbacks_values_for_orders')
                ->where('id', $feedback->value_id)
                ->first();
            $orderFeedbacks->push(['feedback' => $fb, 'value' => $value]);
        }
        return $orderFeedbacks;
    }
}
