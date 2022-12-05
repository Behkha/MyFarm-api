<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Disadvantage;
use Illuminate\Http\Request;

class DisadvantagesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function create(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);
        $adv = Disadvantage::create($request->only(['title']));
        return response()->json(['data' => $adv], 201);
    }

    public function update(Disadvantage $disadvantage, Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);
        $disadvantage->update($request->only(['title']));
        return response()->json(['data' => $disadvantage]);
    }

    public function index()
    {
        $advs = Disadvantage::all();
        return response()->json(['data' => $advs]);
    }
}
