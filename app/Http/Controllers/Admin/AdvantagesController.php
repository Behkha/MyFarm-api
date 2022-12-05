<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advantage;
use Illuminate\Http\Request;

class AdvantagesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function create(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);
        $adv = Advantage::create($request->only(['title']));
        return response()->json(['data' => $adv], 201);
    }

    public function update(Advantage $advantage, Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);
        $advantage->update($request->only(['title']));
        return response()->json(['data' => $advantage]);
    }

    public function index()
    {
        $advs = Advantage::all();
        return response()->json(['data' => $advs]);
    }
}
