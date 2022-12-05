<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product as ProductResource;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.user');
    }

    public function bookmark(Request $request)
    {
        $this->validateAddBookmark($request);

        $user = auth('user')->user();

        $bookmarks = [];

        foreach ($request->input('products') as $product) {

            $dbProduct = Product::find($product);

            if (!$user->productBookmarks->contains('id', $dbProduct->id)) {

                array_push($bookmarks, [

                    'user_id' => $user->id,

                    'bookmarkable_type' => Product::class,

                    'bookmarkable_id' => $dbProduct->id,
                ]);
            }
        }

        foreach ($bookmarks as $bookmark) {

            \DB::table('bookmarks')
                ->insert($bookmark);
        }

        return response()->json(['message' => 'added to bookmarks'], 201);
    }

    public function deleteBookmark(Request $request)
    {
        $request->validate(['products' => 'required|array|min:1', 'products.*' => 'required|distinct|exists:products,id']);
        $user = auth('user')->user();
        foreach ($request->products as $product) {
            $bookmark = $user->bookmarks()->where('bookmarkable_type', 'App\Models\Product')->get()->firstWhere('bookmarkable_id', $product);
            if ($bookmark) {
                $bookmark->delete();
            }
        }
        return response()->json(['message' => 'deleted from bookmarks']);
    }

    public function index()
    {
        $bookmarks = Bookmark::where('bookmarkable_type', 'App\Models\Product')->where('user_id', auth('user')->user()->id)->paginate();
        $products = [];
        foreach ($bookmarks as $bookmark) {
            array_push($products, $bookmark->bookmarkable);
        }
        return ProductResource::collection($products);
    }

    public function addComment(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id', 'body' => 'required|string|max:255']);
        $comment = Product::find($request->product_id)->comments()->save(new Comment([
            'user_phone' => auth('user')->user()->phone,
            'body' => $request->body,
        ]));
        return new CommentResource($comment);
    }

    public function addFeedback(Product $product, Request $request)
    {
        $request->validate([
            'feedbacks' => 'required|array',
            'feedbacks.*.element' => 'required|exists:feedback_elements,id',
            'feedbacks.*.value' => 'required|exists:feedback_element_values,id',
            'advantages' => 'array',
            'advantages.*' => 'required|exists:advantages,id',
            'disadvantages' => 'array',
            'disadvantages.*' => 'required|exists:disadvantages,id',
        ]);
        $data = [];
        foreach ($request->input('feedbacks') as $feedback) {
            array_push($data, [
                'user_id' => auth('user')->user()->id,
                'feedback_element_value_id' => $feedback['value'],
                'feedback_element_id' => $feedback['element'],
            ]);
        }
        $product->feedbacks()->attach($data);
        if ($request->input('advantages')) {
            $advantages = [];
            foreach ($request->input('advantages') as $adv) {
                array_push($advantages, ['product_id' => $product->id, 'advantage_id' => $adv]);
            }
            DB::table('product_advantage_feedback')
                ->insert($advantages);
        }
        if ($request->input('disadvantages')) {
            $disadvantages = [];
            foreach ($request->input('disadvantages') as $adv) {
                array_push($disadvantages, ['product_id' => $product->id, 'disadvantage_id' => $adv]);
            }
            DB::table('product_disadvantage_feedback')
                ->insert($disadvantages);
        }
        return response()->json(['message' => 'ok'], 201);
    }

    /*
     * -------------------------------------------------------------------------------
     * Secondary Methods
     * -------------------------------------------------------------------------------
     */

    private function validateAddBookmark($request)
    {
        $request->validate([

            'products' => 'required|array|min:1',

            'products.*' => 'required|distinct|exists:products,id',

        ]);
    }
}
