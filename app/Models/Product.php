<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $casts = [
        'gallery' => 'json',
    ];

    protected $hidden = ['purchased_price'];

    protected $fillable = [
        'title',
        'price',
        'quantity',
        'description',
        'category_id',
        'counter_sales',
        'counter',
        'bonus',
        'purchased_price',
        'counter_created_at',
        'brand_id',
    ];

    public $timestamps = false;

    public function getImagesAttribute()
    {
        if ($this->gallery) {
            $images = [];
            foreach ($this->gallery as $image) {
                if (strstr($image, 'http://lorempixel.com')) {
                    array_push($images, $image);
                } else {
                    array_push($images, env('STORAGE_PATH') . $image);
                }
            }
            return $images;
        } else {
            return null;
        }

    }

    public function getDiscountPriceAttribute()
    {
        if ($this->counter_sales < $this->discounts[0]->from) {
            return $this->price;
        }
        $discounts = $this->discounts;
        for ($i = 0; $i < $discounts->count(); $i++) {
            if ($i === $discounts->count() - 1) {
                return $discounts[$discounts->count() - 1]->price;
            }
            if ($this->counter_sales >= $discounts[$i]->from && $this->counter_sales < $discounts[$i + 1]->from) {
                return $discounts[$i]->price;
            }
        }
    }

    public function getNextLevelDiscountAttribute()
    {
        if ($this->counter_sales < $this->discounts[0]->from) {
            return $this->discounts[0];
        }
        $discounts = $this->discounts;
        for ($i = 0; $i < $discounts->count(); $i++) {
            if ($i === $discounts->count() - 1) {
                return null;
            }
            if ($this->counter_sales >= $discounts[$i]->from && $this->counter_sales < $discounts[$i + 1]->from) {
                return $discounts[$i];
            }
        }
    }

    public function getFinalPriceAttribute()
    {
        return 1;
    }

    public function getRemainingTimeAttribute()
    {
        $timer = new Carbon($this->counter_created_at);
        return $timer->diffInSeconds(now());
    }

    public function attributes()
    {
        return $this
            ->belongsToMany(Attribute::class)
            ->withPivot('value');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function bookmarks()
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }

    public function discounts()
    {
        return $this
            ->hasMany(Discount::class)
            ->orderBy('from', 'asc');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class)->withPivot(
            'quantity', 'price', 'price_before_discount'
        );
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function advantages()
    {
        return $this->belongsToMany(Advantage::class);
    }

    public function disadvantages()
    {
        return $this->belongsToMany(Disadvantage::class);
    }

    public function carts()
    {
        return $this
            ->belongsToMany(Cart::class)
            ->withPivot('quantity');
    }

    public function isBookmarked()
    {
        $user = auth('user')->user();
        if (!$user) {
            return false;
        }
        return $this->bookmarks->contains('user_id', $user->id);
    }

    public function getCartQuantity()
    {
        if (!auth('user')->user()) {
            return false;
        }
        return \DB::table('cart_product')
            ->where('cart_id', auth('user')->user()->cart->id)
            ->where('product_id', $this->id)
            ->count('quantity');
    }

    // return count of this product in orders with status unknown
    public function getUnknownOrderCount()
    {
        $orders = Order::where('status', Order::STATUS['unknown'])
            ->where('created_at', '>=', now()->subMinutes(60)->toDateTimeString())
            ->whereHas('products', function ($query) {
                $query->where('id', $this->id);
            })
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            foreach ($order->products as $product) {
                if ($product->id == $this->id) {
                    $count += $product->pivot->quantity;

                    break;
                }
            }
        }

        return $count;
    }

    public function feedbacks()
    {
        return $this
            ->belongsToMany(FeedbackElementValue::class, 'product_feedbacks')
            ->withPivot('user_id', 'created_at');
    }
}
