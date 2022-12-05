<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FeedbackElement;
use App\Models\FeedbackElementValue;
use App\Models\FeedbackGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbacksController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function createGroup(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
        ]);
        $gp = FeedbackGroup::create($request->only(['title']));
        $category = Category::find($request->input('category_id'));
        $category->feedbackGroups()->save($gp);
        return response()->json(['data' => $gp], 201);
    }

    public function updateGroup(FeedbackGroup $group, Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
        ]);
        $group->update($request->only(['title']));
        $category = Category::find($request->input('category_id'));
        DB::table('category_feedback')
            ->where('feedback_group_id', $group->id)
            ->delete();
        $category->feedbackGroups()->save($group);
        return response()->json(['data' => $group]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'feedback_group_id' => 'required|exists:feedback_groups,id',
            'values' => 'required|array',
            'values.*' => 'required|string|max:255',
        ]);
        $element = FeedbackElement::create($request->only(['title', 'feedback_group_id']));
        foreach ($request->input('values') as $value) {
            $element->values()->save(new FeedbackElementValue(['value' => $value]));
        }
        return response()->json(['data' => $element], 201);
    }

    public function update(FeedbackElement $element, Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'feedback_group_id' => 'required|exists:feedback_groups,id',
            'values' => 'required|array',
            'values.*' => 'required|string|max:255',
        ]);
        $element->update($request->only(['title', 'feedback_group_id']));
        $element->values()->delete();
        foreach ($request->input('values') as $value) {
            $element->values()->save(new FeedbackElementValue(['value' => $value]));
        }
        return response()->json(['data' => $element]);
    }

    public function indexGroup()
    {
        $gps = FeedbackGroup::all();
        return response()->json(['data' => $gps]);
    }

    public function index()
    {
        $elements = FeedbackElement::paginate();
        return response()->json(['data' => $elements]);
    }

    public function show(FeedbackElement $element)
    {
        $element->load('values');
        return response()->json(['data' => $element]);
    }
}
