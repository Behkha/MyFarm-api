<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index()
    {
        $brands = Brand::all();
        return response()->json(['data' => $brands]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'file|image|max:5000',
        ]);
        if ($request->file('image')) {
            $url = $request->file('image')->store('brands');
        }
        $brand = new Brand([
            'title' => $request->input('title'),
            'image_url' => isset($url) ? $url : null,
        ]);
        $brand->save();
        return response()->json(['data' => $brand], 201);
    }

    public function update(Brand $brand, Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'file|image|max:5000',
        ]);
        $brand->title = $request->input('title');
        if ($request->input('delete_image')) {
            Storage::delete($brand->getOriginal('image_url'));
            $brand->image_url = null;
            $brand->save();
        }
        if ($request->file('image')) {
            Storage::delete($brand->getOriginal('image_url'));
            $brand->image_url = $request->file('image')->store('brands');
            $brand->save();
        }
        $brand->save();
        return response()->json(['data' => $brand]);
    }
}
