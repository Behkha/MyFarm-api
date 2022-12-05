<?php

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call(LocationSeeder::class);
        \DB::table('payment_methods')->insert(['name' => 'internet']);
        \DB::table('payment_methods')->insert(['name' => 'cash']);
        \DB::table('payment_methods')->insert(['name' => 'wallet']);
        \DB::table('settings')->insert([
            'id' => 1,
            'discount_mode' => 'static',
        ]);
        if (env('APP_ENV') === 'local') {
            Slide::create(['image_url' => 'http://lorempixel.com/400/200/']);
            Slide::create(['image_url' => 'http://lorempixel.com/400/200/']);
            Slide::create(['image_url' => 'http://lorempixel.com/400/200/']);

            Admin::create([
                'username' => 'admin',
                'password' => 'admin',
            ]);

            factory(App\Models\Ad::class, 10)->create();

            factory(App\Models\Category::class, 20)->create();

            factory(User::class, 10)->create();

            Attribute::create(['name' => 'Color']);
            Attribute::create(['name' => 'Size']);
            Attribute::create(['name' => 'Type']);
            Attribute::create(['name' => 'Attr-4']);
            Attribute::create(['name' => 'Attr-5']);

            Brand::create(['title' => 'برند-1']);
            Brand::create(['title' => 'برند-2']);
            Brand::create(['title' => 'برند-2']);

            factory(App\Models\Product::class, 30)->create()->each(function ($product) {
                $product->attributes()->attach([
                    0 => ['attribute_id' => 1, 'value' => 'Yellow'],
                    1 => ['attribute_id' => 2, 'value' => 'Small'],
                    2 => ['attribute_id' => 3, 'value' => 'A'],
                    3 => ['attribute_id' => 4, 'value' => 'Val-4'],
                ]);
                $discount = floor(($product->price - $product->purchased_price) / 6);
                $first = $product->price - $discount;
                $second = $first - $discount;
                $third = $second - $discount;
                $bonus = floor(floor(($third - $product->purchased_price) / 10) / 2);
                $firstBonus = $bonus;
                $secondBonus = floor($first / 2);
                $thirdBonus = floor($second / 2);
                $from = 1;
                $to = floor($product->quantity / 4);
                \DB::table('discounts')
                    ->insert([
                        'product_id' => $product->id,
                        'from' => $from,
                        'price' => $first,
                        'unit' => 'تن',
                    ]);
                $from = $to + 1;
                $to = floor($product->quantity / 3);
                \DB::table('discounts')
                    ->insert([
                        'product_id' => $product->id,
                        'from' => $from,
                        'price' => $second,
                        'unit' => 'تن',
                    ]);
                $from = $to + 1;
                $to = $product->quantity;
                \DB::table('discounts')
                    ->insert([
                        'product_id' => $product->id,
                        'from' => $from,
                        'price' => $third,
                        'unit' => 'تن',
                    ]);
            });

            $this->call(DeliveryMethodSeeder::class);
        }
    }
}
