<?php

namespace App\Http\Controllers;

use App\Http\Resources\Category as CategoryResource;
use App\Http\Resources\Product as ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchEngine extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'search' => 'string',
            'category_id' => 'exists:categories,id',
            'brand_id' => 'exists:brands,id',
            'product' => 'string|max:255',
            'category' => 'string|max:255',
            'brand' => 'string|max:255',
        ]);
        $products = collect();
        if ($request->query('product')) {
            Product::where('title', 'like', '%' . $request->query('product') . '%')
                ->paginate()
                ->each(function ($item, $key) use ($products) {
                    $products->push($item);
                });
            return ProductResource::collection($products);
        }
        // search through category
        $categories = collect();
        if ($request->query('category')) {
            Category::where('name', 'like', '%' . $request->query('category') . '%')
                ->paginate()
                ->each(function ($item, $key) use ($categories) {
                    $categories->push($item);
                });
            return CategoryResource::collection($categories);
        }
        // search through brand
        $brands = collect();
        if ($request->query('brand')) {
            Brand::where('title', 'like', '%' . $request->query('brand') . '%')
                ->paginate()
                ->each(function ($item, $key) use ($brands) {
                    $brands->push($item);
                });
            return response()->json(['data' => $brands]);
        }
        $term = $request->query('search');
        $query = Product::query();
        if ($request->query('category_id')) {
            $query->whereHas('category', function ($category) {
                $category->where('id', request()->query('category_id'));
            });
        }
        if ($request->query('brand_id')) {
            $query->whereHas('brand', function ($brand) {
                $brand->where('id', request()->query('brand_id'));
            });
        }
        if ($request->query('category_id') || $request->query('brand_id')) {
            $query->where('title', 'like', '%' . $term . '%');
            return ProductResource::collection($query->paginate());
        }
        Product::where('title', 'like', '%' . $term . '%')
            ->paginate()
            ->each(function ($item, $key) use ($products) {
                $products->push($item);
            });
        Product::whereHas('category', function ($category) use ($term) {
            $category->where('name', 'like', '%' . $term . '%');
        })
            ->paginate()
            ->each(function ($item, $key) use ($products) {
                $products->push($item);
            });
        Product::whereHas('brand', function ($brand) use ($term) {
            $brand->where('title', 'like', '%' . $term . '%');
        })
            ->paginate()
            ->each(function ($item, $key) use ($products) {
                $products->push($item);
            });
        return ProductResource::collection($products);
    }
}
