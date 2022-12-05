<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class Product extends JsonResource
{
    public function toArray($request)
    {
        if (Route::currentRouteName() === 'carts.show') {
            $this->load('attributes');
        }
        $features = \DB::table('product_features')
            ->where('product_id', $this->id)
            ->get();
        // feedback elements
        $feedbacks = collect();
        $feedbackIds = DB::table('category_feedback')
            ->where('category_id', $this->category_id)
            ->get()
            ->pluck('feedback_group_id');
        foreach ($feedbackIds as $id) {
            $feedback = DB::table('feedback_elements')
                ->where('feedback_group_id', $id)
                ->get();
            foreach ($feedback as $temp) {
                $temp->group = DB::table('feedback_groups')
                    ->where('id', $temp->feedback_group_id)
                    ->first();
                $temp->values = DB::table('feedback_element_values')
                    ->where('feedback_element_id', $temp->id)
                    ->get();
                $temp->count = DB::table('product_feedbacks')
                    ->where('product_id', $this->id)
                    ->where('feedback_element_id', $temp->id)
                    ->count();
                if (DB::table('product_feedbacks')
                    ->selectRaw('feedback_element_value_id, COUNT(*) AS count')
                    ->where('product_id', $this->id)
                    ->groupBy('feedback_element_value_id')
                    ->orderByRaw('count DESC')
                    ->first()) {
                    $temp->highest = DB::table('feedback_element_values')
                        ->where('id', DB::table('product_feedbacks')
                                ->selectRaw('feedback_element_value_id, COUNT(*) AS count')
                                ->where('product_id', $this->id)
                                ->groupBy('feedback_element_value_id')
                                ->orderByRaw('count DESC')
                                ->first()->feedback_element_value_id)
                        ->first();
                }
                $feedbacks->push($temp);
            }
        }
        // top 3 advs
        $temp = DB::table('product_advantage_feedback')
            ->selectRaw('advantage_id, COUNT(*) as count')
            ->where('product_id', $this->id)
            ->groupBy('advantage_id')
            ->orderByRaw('count DESC')
            ->take(3)
            ->get();
        $top3adv = collect();
        foreach ($temp as $adv) {
            $top3adv->push(DB::table('advantages')
                    ->where('id', $adv->advantage_id)
                    ->first());
        }
        // top 3 disadvs
        $temp = DB::table('product_disadvantage_feedback')
            ->selectRaw('disadvantage_id, COUNT(*) as count')
            ->where('product_id', $this->id)
            ->groupBy('disadvantage_id')
            ->orderByRaw('count DESC')
            ->take(3)
            ->get();
        $top3disadv = collect();
        foreach ($temp as $adv) {
            $top3disadv->push(DB::table('disadvantages')
                    ->where('id', $adv->disadvantage_id)
                    ->first());
        }
        return [
            'id' => $this->id,
            'title' => $this->title,
            'gallery' => $this->images,
            'price' => $this->price,
            'order_price' => $this->whenPivotLoaded('order_product', function () {
                return $this->pivot->price;
            }),
            'order_qty' => $this->whenPivotLoaded('order_product', function () {
                return $this->pivot->quantity;
            }),
            'order_price_before_discount' =>
            $this->whenPivotLoaded('order_product', function () {
                return $this->pivot->price_before_discount;
            }),
            'quantity' => $this->quantity,
            'discount' => $this->discounts,
            'price_after_discount' => $this->discount_price,
            'discount_price' => $this->price - $this->discount_price,
            'counter_sales' => $this->counter_sales,
            'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
            'description' => $this->description,
            'is_bookmarked' => $this->when(auth('user')->user(), $this->isBookmarked()),
            'category' => new Category($this->category),
            'features' => $features->pluck('title'),
            'remaining_time' => $this->remaining_time,
            'purchased_price' => $this->purchased_price,
            'brand' => $this->brand,
            'counter' => $this->counter,
            'next_level_discount' => $this->next_level_discount,
            'feedback_elements' => $this->when(Route::currentRouteName() === 'product.show', $feedbacks),
            'advantages' => $this->whenLoaded('advantages'),
            'disadvantages' => $this->whenLoaded('disadvantages'),
            'top_advantages' => $this->when(Route::currentRouteName() === 'product.show', $top3adv),
            'top_disadvantages' => $this->when(Route::currentRouteName() === 'product.show', $top3disadv),
        ];
    }
}
