<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::firstOrCreate(
            [
                "user_id" => $request->user()->id,
                "branch_id" => $request->user()->branch_id,
                "status" => "active"
            ]
        );

        $cart->load("items.product");

        return response()->json([
            "success" => true,
            "data" => [
                "cart" => $cart,
                "total" => $cart->total,
                "items_count" => $cart->items->count()
            ]
        ]);
    }

    public function addItem(Request $request)
    {
        $request->validate([
            "product_id" => "required|exists:products,id",
            "quantity" => "required|numeric|min:1",
            "unit_price" => "required|numeric"
        ]);

        $cart = Cart::firstOrCreate(
            [
                "user_id" => $request->user()->id,
                "branch_id" => $request->user()->branch_id,
                "status" => "active"
            ]
        );

        $cartItem = CartItem::updateOrCreate(
            [
                "cart_id" => $cart->id,
                "product_id" => $request->product_id
            ],
            [
                "quantity" => $request->quantity,
                "unit_price" => $request->unit_price,
                "discount" => $request->discount ?? 0
            ]
        );

        $cart->load("items.product");

        return response()->json([
            "success" => true,
            "message" => "Item added to cart",
            "data" => [
                "cart" => $cart,
                "total" => $cart->total
            ]
        ]);
    }

    public function updateItem(Request $request, $itemId)
    {
        $request->validate([
            "quantity" => "required|numeric|min:0"
        ]);

        $cartItem = CartItem::findOrFail($itemId);

        if ($request->quantity <= 0) {
            $cartItem->delete();
        } else {
            $cartItem->update(["quantity" => $request->quantity]);
        }

        $cart = Cart::find($cartItem->cart_id);
        $cart->load("items.product");

        return response()->json([
            "success" => true,
            "data" => [
                "cart" => $cart,
                "total" => $cart->total
            ]
        ]);
    }

    public function removeItem($itemId)
    {
        $cartItem = CartItem::findOrFail($itemId);
        $cartId = $cartItem->cart_id;
        $cartItem->delete();

        $cart = Cart::find($cartId);
        $cart->load("items.product");

        return response()->json([
            "success" => true,
            "message" => "Item removed from cart",
            "data" => [
                "cart" => $cart,
                "total" => $cart->total
            ]
        ]);
    }

    public function clear(Request $request)
    {
        $cart = Cart::where([
            "user_id" => $request->user()->id,
            "status" => "active"
        ])->first();

        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        return response()->json([
            "success" => true,
            "message" => "Cart cleared"
        ]);
    }
}
