<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\DealDetected;
use App\Jobs\OutOfStock;
use App\Jobs\SupplyRequired;

class ProductController extends Controller
{
    CONST SUPPLY_REQUIRED = 10;

    public function store(Request $request) : JsonResponse
    {
        // 1. verifico la bontà del dato
        $validator = Validator::make($request->all(), [
            'ean' => 'required|string|min:13|max:13|unique:products',
            'name' => 'required|string|max:255',
            'qty' => 'required|integer',
            'price' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            
            return response()->json([
                'status' => false,
                'data' => $validator->errors()
            ], 400);
        }

        // 2. inserisco il prodotto

        try {

            $product = Product::create([
                'ean' => $request->input('ean'),
                'name' => $request->input('name'),
                'qty' => $request->input('qty'),
                'price' => $request->input('price'),
            ]);
        
        } catch (Exception $e) {

            return response()->json([
                'status' => false,
                'data' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => true,
            'data' => $product
        ], 201);
    }

    public function show(Request $request, int $id) : JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {

            return response()->json([
                'status' => false,
                'data' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $product
        ], 200);
    }

    public function update(Request $request, int $id) : JsonResponse
    {
        $product = $prevProductVersion = Product::find($id);

        if (!$product) {

            return response()->json([
                'status' => false,
                'data' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'qty' => 'integer',
            'price' => 'numeric',
        ]);

        if ($validator->fails()) {
            
            return response()->json([
                'status' => false,
                'data' => $validator->errors()
            ], 400);
        }

        $product->update([
            'name' => $request->input('name'),
            'qty' => $request->input('qty'),
            'price' => $request->input('price'),
        ]);

        self::dispatchEvents($product, $prevProductVersion);

        return response()->json([
            'status' => true,
            'data' => $product
        ]);
    }

    public function destroy(Request $request, int $id) : JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {

            return response()->json([
                'status' => false,
                'data' => 'Order not found'
            ], 404);
        }

        $product->delete();

        return response()->json([
            'status' => true,
            'data' => 'Deleted'
        ]);
    }
    
    private static function dispatchEvents(Product $product, Product $prevProductVersion) : void
    {
        self::dispatchEventsByPrice($product, $prevProductVersion);
        self::dispatchEventsByQty($product);
    }

    public static function dispatchEventsByQty(Product $product) : void
    {
        if ($product->qty < self::SUPPLY_REQUIRED) {

            // Notifica che è ora ri riordinare il prodotto
            SupplyRequired::dispatch($product->toArray());
        }

        if ($product->qty === 0) {

            // Notifica la mancanza del prodotto nel magazzino
            OutOfStock::dispatch($product->toArray());
        }
    }

    public static function dispatchEventsByPrice(Product $product, Product $prevProductVersion) : void
    {
        if ($product->price < $prevProductVersion->price) {

            // Visualizza il prodotto in qualche sezione di affare del sito
            // Invia mail / notifica agli utenti che segueno l'andamento del prezzo del prodotto

            DealDetected::dispatch($product->toArray());
        }
    }
}
