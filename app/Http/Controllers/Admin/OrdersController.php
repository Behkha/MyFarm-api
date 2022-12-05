<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        if ($request->query('code')) {
            $order = Order::where('code', $request->query('code'))
                ->first();
            if ($order) {
                return new OrderResource($order);
            } else {
                return response()->json(['data' => '']);
            }
        }
        $orders = Order::orderBy('created_at', 'desc')->paginate();
        return OrderResource::collection($orders);
    }

    public function show(Order $order)
    {
        $order->load('user', 'products', 'documents');

        return new OrderResource($order);
    }

    public function update(Order $order, Request $request)
    {
        $status = $order->getOriginal('status');
        $reject = $request->query('reject');
        // reject order
        if ($reject && ($status == Order::STATUS['waiting_for_verification'] || $status == Order::STATUS['verified'])) {
            $order->status = Order::STATUS['rejected'];
            $order->save();
            return response()->json(['message' => 'order rejected']);
        }
        // order is delivered
        if ($order->getOriginal('status') === Order::STATUS['delivered']) {
            return response()->json(['errors' => 'invalid order'], 400);
        }

        if ($order->getOriginal('status') === Order::STATUS['verified']) {
            $order->status = Order::STATUS['waiting_for_send'];
        }

        if ($order->getOriginal('status') === Order::STATUS['waiting_for_send']) {
            $order->status = Order::STATUS['sent'];
        }

        if ($order->getOriginal('status') === Order::STATUS['sent']) {
            $order->status = Order::STATUS['delivered'];
        }

        if ($order->getOriginal('status') === Order::STATUS['waiting_for_verification']) {
            $order->status = Order::STATUS['verified'];
            foreach ($order->products as $product) {
                $product->counter_sales += $product->pivot->quantity;
                $product->quantity -= $product->pivot->quantity;
                $product->save();
            }
        }
        $order->save();
    }
}
