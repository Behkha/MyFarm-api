<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProduct;
use App\Http\Resources\CommentResource;
use App\Http\Resources\Product as ProductResource;
use App\Jobs\AddToRedis;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class ProductsController extends Controller
{
    public function __construct(Request $request)
    {
        if ($request->header('device') === 'mobile') {
            $this->middleware('auth:user');
        }
        $this->middleware('auth:user')->only([
            'addComment',
        ]);
        $this->middleware('auth:admin')->only([
            'create',
            'update',
            'updateGallery',
            'deleteImage',
        ]);
    }

    public function index(Request $request)
    {
        if ($request->query('title')) {
            $products = Product::where(
                'title', 'like', '%' . $request->query('title') . '%'
            )->paginate();
            return ProductResource::collection($products);
        }
        $request->validate([
            'categories' => 'array|min:1',
            'categories.*' => 'required|distinct|exists:categories,id',
            'min_price' => 'integer|min:1',
            'max_price' => 'integer|min:1',
            'is_available' => 'boolean',
            'has_discount' => 'boolean',
            'filters' => 'array|min:1',
            'filters.*.id' => 'required|distinct|exists:attributes,id',
            'filters.*.values' => 'required|array|min:1',
            'filters.*.values.*' => 'required|string|max:255',
        ]);
        $query = Product::query();
        // applying dynamic filters
        if ($request->query('filters')) {
            foreach ($request->query('filters') as $filter) {
                $query->whereHas('attributes', function ($builder) use ($filter) {
                    $builder->where('id', $filter['id']);
                });
                foreach ($filter['values'] as $value) {
                    $query->whereHas('attributes', function ($builder) use ($value) {
                        $builder->where('attribute_product.value', $value);
                    });
                }
            }
        }

        if ($request->query('categories')) {
            foreach ($request->query('categories') as $category) {
                $query->whereHas('category', function ($builder) use ($category) {
                    $builder->where('id', $category);
                });
            }
        }

        if ($request->query('min_price')) {
            $query->where('price', '>=', $request->query('min_price'));
        }

        if ($request->query('max_price')) {
            $query->where('price', '<=', $request->query('max_price'));
        }

        if ($request->query('is_available')) {
            $query->where('quantity', '>', 0);
        }

        if ($request->query('has_discount')) {
            $query->whereHas('discounts');
        }

        if ($request->query('sort_by_views')) {
            $query->orderBy('view_count', 'desc');
        }

        if ($request->query('sort_by_bookmarks')) {
            $query->withCount('bookmarks')->orderBy('bookmarks_count', 'desc');
        }

        if ($request->query('sort_by_sales')) {
            $query->withCount('orders')->orderBy('orders_count', 'desc');
        }

        if ($request->query('sort_by_lowest_price')) {
            $products = $query->get();
            foreach ($products as $product) {
                if ($product->discount_price) {
                    $product->price_after_discount = $product->discount_price;
                } else {
                    $product->price_after_discount = $product->price;
                }
            }
            $sorted = $products->sortBy('price_after_discount');
            $data = $sorted->paginate(15);
            return ProductResource::collection($data);
        } elseif ($request->query('sort_by_highest_price')) {
            $products = $query->get();
            foreach ($products as $product) {
                if ($product->discount_price) {
                    $product->price_after_discount = $product->discount_price;
                } else {
                    $product->price_after_discount = $product->price;
                }
            }
            $sorted = $products->sortByDesc('price_after_discount');
            $data = $sorted->paginate(15);
            return ProductResource::collection($data);
        }

        $products = $query->paginate();

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->view_count++;
        $product->save();
        $product->load('attributes', 'advantages', 'disadvantages');
        return new ProductResource($product);
    }

    public function similarProducts(Product $product)
    {
        $products = Product::whereHas('category', function ($query) use ($product) {
            $query->where('id', $product->category->id);
        })->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->paginate();
        return ProductResource::collection($products);
    }

    public function newProducts()
    {
        $products = Product::orderBy('created_at', 'desc')
            ->take(20)
            ->get();
        return ProductResource::collection($products);
    }

    public function create(CreateProduct $request)
    {
        $request->merge([
            'counter_sales' => 0,
            'bonus' => 100,
            'counter_created_at' => now()->addHours($request->input('counter')),
        ]);
        $product = Product::create($request->only([
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
        ]));
        $gallery = [];
        $attrs = [];
        if ($request->file('gallery')) {
            foreach ($request->file('gallery') as $image) {
                array_push($gallery, $image->store('products'));
            }
        }
        if ($request->input('attributes')) {
            foreach ($request->input('attributes') as $attribute) {
                array_push($attrs, ['attribute_id' => $attribute['id'], 'value' => $attribute['value']]);
            }
        }
        $product->gallery = $gallery;
        $product->attributes()->attach($attrs);
        $product->save();
        // save features
        if (sizeof($request->input('features')) > 0) {
            foreach ($request->input('features') as $feature) {
                \DB::table('product_features')
                    ->insert(['title' => $feature, 'product_id' => $product->id]);
            }
        }
        // save advantages
        $product->advantages()->attach($request->input('advantages'));
        // save disadvantages
        $product->disadvantages()->attach($request->input('disadvantages'));
        $this->createDiscounts($product->id, $request->input('discounts'));
        return new ProductResource($product);
    }

    public function update(Product $product, CreateProduct $request)
    {
        $product->update($request->only([
            'title',
            'price',
            'quantity',
            'description',
            'category_id',
            'counter',
            'brand_id',
        ]));
        $attrs = [];
        // delete gallery
        if ($request->input('delete_gallery')) {
            if ($product->gallery) {
                foreach ($product->gallery as $image) {
                    Storage::delete($image);
                }
                $product->gallery = null;
                $product->save();
            }
        }

        if ($request->input('attributes')) {
            foreach ($request->input('attributes') as $attribute) {
                array_push($attrs, ['attribute_id' => $attribute['id'], 'value' => $attribute['value']]);
            }
        }
        $product->attributes()->detach();
        $product->attributes()->attach($attrs);
        // save features
        if (sizeof($request->input('features')) > 0) {
            \DB::table('product_features')
                ->where('product_id', $product->id)
                ->delete();
            foreach ($request->input('features') as $feature) {
                \DB::table('product_features')
                    ->insert(['title' => $feature, 'product_id' => $product->id]);
            }
        }
        DB::table('discounts')
            ->where('product_id', $product->id)
            ->delete();
        // save advantages
        if ($request->input('advantages')) {
            $product->advantages()->detach();
        }
        $product->advantages()->attach($request->input('advantages'));
        // save disadvantages
        if ($request->input('disadvantages')) {
            $product->disadvantages()->detach();
        }
        $product->disadvantages()->attach($request->input('disadvantages'));
        $this->createDiscounts($product->id, $request->input('discounts'));
        return new ProductResource($product);
    }

    public function updateGallery(Product $product, Request $request)
    {
        $request->validate([
            'gallery' => 'required|array|min:1|max:10',
            'gallery.*' => 'required|file|image|max:5000',
        ]);
        $temp = $product->gallery;
        $gallery = [];
        foreach ($request->gallery as $image) {
            array_push($temp, $image->store('products'));
        }
        $product->gallery = $temp;
        $product->save();
        return new ProductResource($product);
    }

    public function addComment(Product $product, Request $request)
    {
        $request->validate(['body' => 'required|string|max:255']);
        $product->comments()->save(new Comment([
            'body' => $request->input('body'),
            'commented_by' => auth('user')->user()->id,
        ]));
        return response()->json(['message' => 'comment added'], 201);
    }

    public function getComments(Product $product)
    {
        $comments = $product->comments()->paginate();
        return CommentResource::collection($comments);
    }

    private function storeGallery($request, &$product)
    {
        if ($request->hasFile('gallery')) {

            $images = [];

            foreach ($request->file('gallery') as $image) {

                array_push($images, $image->store('products'));
            }

            $product->gallery = $images;
        }
    }

    private function storeAttributes($request, &$product)
    {
        if ($request->input('attributes')) {

            $attrs = [];

            foreach ($request->input('attributes') as $attribute) {

                array_push($attrs, [
                    'attribute_name' => $attribute['name'],
                    'value' => $attribute['value'],
                ]);
            }

            $product->attributes()->attach($attrs);
        }
    }

    private function storeFeatures($request, &$product)
    {
        if ($request->input('features')) {

            $product->features()->attach($request->input('features'));
        }
    }

    private function validateIndexProducts($request)
    {
        $request->validate([
            'paginate' => 'integer|min:1|max:20',

            'page' => 'integer|min:1',

            'sort_by' => 'in:view,bookmark,price',

            'sort_order' => 'in:asc,desc',
        ]);
    }

    private function getProducts($key, $start, $end)
    {
        if (($key === 'products_by_price' && request()->input('sort_order') === 'desc') || $key === 'products_by_bookmark') {

            $ids = Redis::ZREVRANGE($key, $start, $end);
        } else {

            $ids = Redis::ZRANGE($key, $start, $end);
        }

        $products = collect();

        foreach ($ids as $id) {

            $product = unserialize(Redis::GET('product:' . $id));

            if (!$product) {

                $product = Product::find($id);

                AddToRedis::dispatch('Product', $product->id, $product);
            }

            $products->push($product);
        }

        return ProductResource::collection($products);
    }

    public function getFilters(Request $request)
    {
        $request->validate(['category_id' => 'exists:categories,id']);
        if ($request->query('category_id')) {
            $attributes = collect();
            $category = Category::find($request->query('category_id'));
            $attributes->push($category->attributes);
            foreach (Category::where('parent_id', $category->id)->get() as $child) {
                foreach (Category::where('parent_id', $child->id)->get() as $second) {
                    foreach (Category::where('parent_id', $second->id)->get() as $third) {
                        $attributes->push($third->attributes);
                    }
                    $attributes->push($second->attributes);
                }
                $attributes->push($child->attributes);
            }
            $result = [];
            foreach ($attributes as $attribute) {
                foreach ($attribute as $item) {
                    $values = \DB::table('attribute_product')
                        ->where('attribute_id', $item->id)
                        ->select('value')
                        ->groupBy('value')
                        ->get();
                    array_push($result, [
                        'key_id' => $item->id,
                        'key_name' => $item->name,
                        'values' => $values,
                    ]);
                }
            }
            return $result;
        }
        $result = [];
        $attrs = Attribute::all();
        foreach ($attrs as $attr) {
            $values = \DB::table('attribute_product')
                ->where('attribute_id', $attr->id)
                ->select('value')
                ->groupBy('value')
                ->get();
            array_push($result, ['key_id' => $attr->id, 'key_name' => $attr->name, 'values' => $values]);

        }
        return response()->json(['data' => $result]);
    }

    public function deleteImage(Product $product, $imageUrl)
    {
        if (sizeof($product->gallery) === 0) {
            return response()->json(['error' => 'does not have gallery'], 400);
        }
        foreach ($product->gallery as $key => $image) {
            if (explode('/', $image)[1] === $imageUrl) {
                $gallery = $product->gallery;
                unset($gallery[$key]);
                Storage::delete($image);
                $product->gallery = $gallery;
                $product->save();
                return response()->json(['message' => 'image deleted']);
            }
        }
        return response()->json(['error' => 'image not found'], 404);
    }

    private function createDiscounts($productId, $discounts)
    {
        foreach ($discounts as $discount) {
            Discount::create([
                'product_id' => $productId,
                'from' => $discount['from'],
                'price' => $discount['price'],
                'unit' => $discount['unit'],
            ]);
        }
    }
}
