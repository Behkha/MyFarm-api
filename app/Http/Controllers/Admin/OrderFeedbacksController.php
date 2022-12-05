<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderFeedbacksController extends Controller
{
    public function __construct()
    {
        $this
            ->middleware('auth:admin')
            ->except('index');
    }

    public function index()
    {
        $feedbacks = DB::table('feedbacks_for_orders')
            ->get();
        foreach ($feedbacks as $feedback) {
            $feedback->values = DB::table('feedbacks_values_for_orders')
                ->where('feedback_id', $feedback->id)
                ->get();
        }
        return response()->json(['data' => $feedbacks]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'values' => 'required|array',
            'values.*' => 'required|string|max:255',
        ]);
        $id = DB::table('feedbacks_for_orders')
            ->insertGetId($request->only(['title']));
        $data = [];
        foreach ($request->input('values') as $value) {
            array_push($data, ['feedback_id' => $id, 'value' => $value]);
        }
        DB::table('feedbacks_values_for_orders')
            ->insert($data);
        return response()->json(['message' => 'created'], 201);
    }

    public function update($id, Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'values' => 'array',
            'values.*' => 'required|string|max:255',
        ]);
        $feedback = DB::table('feedbacks_for_orders')
            ->where('id', $id)
            ->first();
        if (!$feedback) {
            return response()->json(['errors' => 'model not found'], 404);
        }
        DB::table('feedbacks_for_orders')
            ->where('id', $id)
            ->update($request->only(['title']));
        if ($request->input('values')) {
            DB::table('feedbacks_values_for_orders')
                ->where('feedback_id', $feedback->id)
                ->delete();
            $data = [];
            foreach ($request->input('values') as $value) {
                array_push($data, ['feedback_id' => $id, 'value' => $value]);
            }
            DB::table('feedbacks_values_for_orders')
                ->insert($data);
        }
        return response()->json(['message' => 'updated']);
    }
}
