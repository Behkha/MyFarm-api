<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrder;
use App\Http\Resources\OrderResource;
use App\Jobs\EmptyCart;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;
use Shetabit\Payment\Exceptions\InvalidPaymentException;
use Shetabit\Payment\Facade\Payment;
use Shetabit\Payment\Invoice;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.user')->except('verify');
        $this->middleware('auth:user')->only(
            'index', 'show', 'create', 'discount'
        );
    }

    public function index(Request $request)
    {
        if ($request->query('code')) {
            $order = Order::where('code', $request->query('code'))
                ->where('user_id', auth('user')->user()->id)
                ->where('status', '!=', Order::STATUS['canceled'])
                ->first();
            if ($order) {
                return new OrderResource($order);
            }
            return response()->json(['data' => '']);
        }
        if ($request->query('all')) {
            $orders = Order::where('user_id', auth('user')->user()->id)
                ->where('status', '!=', Order::STATUS['canceled'])
                ->orderBy('created_at', 'desc')
                ->get();
            return OrderResource::collection($orders);
        }
        $orders = Order::where('user_id', auth('user')->user()->id)
            ->where('status', '!=', Order::STATUS['canceled'])
            ->orderBy('created_at', 'desc')
            ->paginate();
        return OrderResource::collection($orders);
    }

    public function show($id)
    {
        $order = Order::where('user_id', auth('user')->user()->id)->findOrFail($id);
        $order->load('products', 'documents');
        return new OrderResource($order);
    }

    public function create(StoreOrder $request)
    {
        $error = $this->createOrderValidate($request);
        if ($error) {
            return $error;
        }
        $orderPrice = 0;
        foreach (auth('user')->user()->cart->products as $product) {
            $orderPrice += $product->discount_price ? $product->pivot->quantity * $product->discount_price :
            $product->price * $product->pivot->quantity;
        }
        if ($request->input('payment_method') === 'prepaid') {
            $order = Order::create([
                'address_id' => request()->input('address_id'),
                'delivery_method_id' => request()->input('delivery_method_id'),
                'status' => Order::STATUS['not_paid'],
                'code' => Str::random(5) . substr(str_replace('.', '', microtime(true)), -5, 5) . Str::random(5),
                'user_id' => auth('user')->user()->id,
                'price' => $orderPrice,
                'payment_method' => request()->input('payment_method'),
            ]);
        } else {
            $order = Order::create([
                'address_id' => request()->input('address_id'),
                'delivery_method_id' => request()->input('delivery_method_id'),
                'status' => Order::STATUS['unknown'],
                'code' => Str::random(5) . substr(str_replace('.', '', microtime(true)), -5, 5) . Str::random(5),
                'user_id' => auth('user')->user()->id,
                'price' => $orderPrice,
                'payment_method' => request()->input('payment_method'),
            ]);
        }
        $orderProducts = [];
        foreach (auth('user')->user()->cart->products as $product) {
            array_push($orderProducts, [
                'product_id' => $product->id,
                'order_id' => $order->id,
                'price' => $product->discount_price ?? $product->price,
                'quantity' => $product->pivot->quantity,
                'price_before_discount' => $product->price,
            ]);
        }
        $order->products()->attach($orderProducts);
        EmptyCart::dispatchNow(auth('user')->user()->id);
        return new OrderResource($order);
    }

    public function sendDocuments(Order $order, Request $request)
    {
        $request->validate([
            'cheque_id' => 'required|string|max:255',
            'paid_date' => 'required|date',
            'payer_name' => 'required|string|max:255',
            'description' => 'string|max:10000',
            'file' => 'file|max:5000',
        ]);
        $userId = auth('user')->user()->id;
        $orderStatus = Order::STATUS['not_paid'];
        if ($userId !== $order->user_id || $order->getOriginal('status') !== $orderStatus || $order->payment_method !== 'prepaid') {
            return response()->json(['errors' => 'bad request'], 400);
        }
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('orders');
            $request->merge(['file_path' => $filePath]);
        }
        $year = explode('-', $request->input('paid_date'))[0];
        $month = explode('-', $request->input('paid_date'))[1];
        $day = explode('-', $request->input('paid_date'))[2];
        $jdate = new Jalalian($year, $month, $day);
        $request->merge(['paid_date' => $jdate->toCarbon()]);
        $order->documents()->save(new OrderDocument($request->only([
            'cheque_id',
            'paid_date',
            'payer_name',
            'description',
            'file_path',
        ])));
        $order->status = Order::STATUS['waiting_for_verification'];
        $order->save();
        return response()->json(['message' => 'ok']);
    }

    public function pay(Order $order)
    {
        if ($order->getOriginal('status') != Order::STATUS['unknown']) {
            return response()->json(['errors' => 'bad request'], 400);
        }
        if ($order->user_id != auth('user')->user()->id) {
            return response()->json(['errors' => 'bad request'], 400);
        }
        $priceAfterDiscount = $order->price_after_discount ? $order->price_after_discount + $order->deliveryMethod->price : $order->price + $order->deliveryMethod->price;
        switch ($order->payment_method) {
            case 'internet':
                return $this->internet($order, $priceAfterDiscount);
            case 'wallet':
                return $this->wallet($order, $priceAfterDiscount);
            case 'cash':
                return $this->cash($order, $priceAfterDiscount);
        }
    }

    public function verify(Request $request)
    {
        $code = $request->query('Authority');
        $transaction = Transaction::where('code', $code)->firstOrFail();
        $order = $transaction->transactionable;
        try {
            $receipt = Payment::amount($transaction->amount)->transactionId($code)->verify();
            DB::transaction(function () use ($transaction, $order) {
                $transaction->is_verified = true;
                $order->status = Order::STATUS['verified'];
                $transaction->save();
                $order->save();
                $bonus = 0;
                foreach ($order->products as $product) {
                    $bonus += $product->bonus * $product->pivot->quantity;
                    $bonus += $product->discount_bonus;
                    $product->quantity -= $product->pivot->quantity;
                    $product->save();
                }
                $wallet = Wallet::where('user_id', $order->user_id)
                    ->first();
                $wallet->balance += $bonus;
                $wallet->save();
            });
            foreach ($order->products as $product) {
                $product->counter_sales += $product->pivot->quantity;
                $product->save();
            }
            EmptyCart::dispatchNow($transaction->user_id);

            $redirectUrl = $transaction->device === 'mobile' ? env('MOBILE_REDIRECT_URL') : env('WEB_REDIRECT_URL');

            return redirect(
                $redirectUrl . '?code=' . $code . '&status=ok' . '&order=' . $order->code . '&id=' . $order->id . '&payment=' . $order->payment_method);
        } catch (InvalidPaymentException $exception) {
            $redirectUrl = $transaction->device === 'mobile' ? env('MOBILE_REDIRECT_URL') : env('WEB_REDIRECT_URL');
            $order->status = Order::STATUS['unsuccessful'];
            $order->save();
            return redirect(
                $redirectUrl . '?code=' . $code . '&status=nok' . '&order=' . $order->code . '&id=' . $order->id . '&payment=' . $order->payment_method);
        }
    }

    private function createOrderValidate($request)
    {
        // Env Variables Must Be Set
        if (!(env('MERCHANT_ID') && env('MOBILE_REDIRECT_URL') && env('WEB_REDIRECT_URL') && env('CALLBACK_URL'))) {
            return response()->json(['errors' => 'env variables are not set'], 400);
        }

        // check if cart total price is above 50 million
        $totalPrice = 0;
        foreach (auth('user')->user()->cart->products as $product) {
            $totalPrice += $product->discount_price * $product->pivot->quantity;
        }
        if ($totalPrice >= 50000000 && $request->input('payment_method') === 'internet') {
            return response()->json(['errors' => 'cart total price is above 50 million'], 400);
        }

        // Checks If Cart Is Empty
        if (auth('user')->user()->cart->products->count() === 0) {
            return response()->json(['errors' => 'cart is empty'], 400);
        }

        // Checks Product Qty
        foreach (auth('user')->user()->cart->products as $product) {
            if ($product->quantity < $product->getCartQuantity() + $product->getUnknownOrderCount()) {
                return response()->json(['errors' => 'invalid quantity'], 400);
            }
        }

        // Checks User Balance
        if ($request->input('payment_method') === 'wallet') {
            $totalPrice = 0;
            foreach (auth('user')->user()->cart->products as $product) {
                $totalPrice += $product->discount_price ?? $product->price;
            }
            if (auth('user')->user()->wallet->balance < $totalPrice) {
                return response()->json(['Error' => 'Insufficient Balance'], 400);
            }
        }
    }

    private function wallet($order, $price)
    {
        if ($price != 0) {
            if (auth('user')->user()->wallet->balance < $price + $order->deliveryMethod->price) {
                return response()->json(['message' => 'insufficient balance'], 403);
            }
        } else {
            if (auth('user')->user()->wallet->balance < $order->total_price) {
                return response()->json(['message' => 'insufficient balance'], 403);
            }
        }
        DB::transaction(function () use (&$order, $price) {
            $order->transaction()->save(new Transaction([
                'amount' => $price ? $price + $order->deliveryMethod->price : $order->total_price,
                'code' => $order->code,
                'type' => Transaction::TRANSACTION_TYPES['wallet'],
                'user_id' => auth('user')->user()->id,
                'is_verified' => true,
                'device' => request()->header('device'),
            ]));
            $wallet = auth('user')->user()->wallet;
            $wallet->balance -= $price ? $price + $order->deliveryMethod->price : $order->total_price;
            $bonus = 0;
            foreach ($order->products as $product) {
                $bonus += $product->bonus * $product->pivot->quantity;
                $bonus += $product->discount_bonus;
                $product->quantity -= $product->pivot->quantity;
                $product->save();
            }
            $wallet->balance += $bonus;
            $wallet->save();
            $order->status = Order::STATUS['registered'];
            $order->save();
        });
        foreach ($order->products as $product) {
            $product->counter_sales += $product->pivot->quantity;
            $product->save();
        }
        EmptyCart::dispatchNow(auth('user')->user()->id);
        return response()->json(['message' => 'ok']);
    }

    private function internet($order, $price)
    {
        $result = '';
        DB::transaction(function () use (&$order, $price, &$result) {
            $invoice = new Invoice;
            $totalPrice = $price != 0 ?
            $price + $order->deliveryMethod->price : $order->total_price;
            $invoice->amount((int) $totalPrice);
            $result = Payment::callbackUrl(env('CALLBACK_URL'))->purchase(
                $invoice,
                function ($driver, $transactionId) use ($order, $price, $totalPrice) {
                    $order->transaction()->save(new Transaction([
                        'amount' => $totalPrice,
                        'code' => $transactionId,
                        'type' => Transaction::TRANSACTION_TYPES['zarinpal'],
                        'user_id' => auth('user')->user()->id,
                        'is_verified' => false,
                        'device' => request()->header('device'),
                    ]));
                }
            )->pay();
            $order->price_after_discount = $price;
            $order->save();
        });
        return response()->json(['data' => $result->getTargetUrl()], 201);
    }

    private function cash($order, $price)
    {
        DB::transaction(function () use (&$order, $price) {
            $order->transaction()->save(new Transaction([
                'amount' => $price ? $price + $order->deliveryMethod->price : $order->total_price,
                'code' => $order->code,
                'type' => Transaction::TRANSACTION_TYPES['cash'],
                'user_id' => auth('user')->user()->id,
                'is_verified' => false,
                'device' => request()->header('device'),
            ]));
            $order->status = Order::STATUS['registered'];
            $order->save();
        });
        EmptyCart::dispatchNow(auth('user')->user()->id);
        return response()->json(['message' => 'ok']);
    }

    public function sendFeedback(Order $order, Request $request)
    {
        if ($order->getOriginal('status') != Order::STATUS['delivered']) {
            return response()->json(['errors' => 'bad request'], 400);
        }
        if (auth('user')->user()->id != $order->user_id) {
            return response()->json(['errors' => 'bad request'], 400);
        }
        if (DB::table('order_feedback')->where('order_id', $order->id)->exists()) {
            return response()->json(['errors' => 'already voted'], 400);
        }
        $request->validate([
            'feedbacks' => 'required|array',
            'feedbacks.*.id' => 'required|exists:feedbacks_for_orders,id',
            'feedbacks.*.value' => 'required|exists:feedbacks_values_for_orders,id',
        ]);
        $data = [];
        foreach ($request->input('feedbacks') as $feedback) {
            array_push($data, ['order_id' => $order->id, 'feedback_id' => $feedback['id'], 'value_id' => $feedback['value']]);
        }
        DB::table('order_feedback')
            ->insert($data);
        return response()->json(['message' => 'ok']);
    }
}
