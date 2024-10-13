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
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    CONST SUPPLY_REQUIRED = 10;

    /**
    * @OA\Post(
    *     path="/api/v1/product",
    *     operationId="store",
    *     tags={"Product CRUD"},
    *     summary="Create new product",
    *
    *     @OA\RequestBody(
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 @OA\Property(
    *                     property="ean",
    *                     type="string",
    *                     default="0123456789123",
    *                 ),
    *                 @OA\Property(
    *                     property="name",
    *                     type="string",
    *                     default="Goleador",
    *                 ),
    *                 @OA\Property(
    *                     property="qty",
    *                     type="integer",
    *                     default="5000",
    *                 ),
    *                 @OA\Property(
    *                     property="price",
    *                     type="decimal",
    *                     default="0.10",
    *                 ),
    *             ),
    *         ),
    *     ),
    *
    *     @OA\Response(
    *         response=200,
    *         description="Successfull operation",
    *         @OA\JsonContent(
    *             type="object",
    *             @OA\Property(
    *                 type="boolean",
    *                 default="true",
    *                 description="Status",
    *                 property="status",
    *             ),
    *             @OA\Property(
    *                 type="object",
    *                 property="data",
    *             ),
    *         ),
    *     ),
    *     @OA\Response(
    *         response=400,
    *         description="Bad request",
    *     ),
    *     @OA\Response(
    *         response=401,
    *         description="Unauthorized",
    *     ),
    *     @OA\Response(
    *         response=500,
    *         description="Internal server error"
    *     ),
    * )
    */
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

    /**
     * @OA\Get(
     *     path="/api/v1/product/{id}",
     *     operationId="show",
     *     tags={"Product CRUD"},
     *     summary="Get product by ID",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product to search ID.",
     *         @OA\Schema(type="integer"),
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfull operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 type="boolean",
     *                 default="true",
     *                 description="Status",
     *                 property="status",
     *             ),
     *             @OA\Property(
     *                 type="object",
     *                 description="Requested product in json format",
     *                 property="data",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     ),
     * )
     */
    public function show(Request $request, int $id) : JsonResponse
    {
        if (Cache::has("product_$id")) {

            $product = Cache::get("product_$id");

        } else {
            
            $product = Cache::remember("product_$id", 60, function () use ($id) {
                return Product::find($id);
            });
        }

        if (!$product) {

            return response()->json([
                'status' => false,
                'data' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $product
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/product/{id}",
     *     operationId="update",
     *     tags={"Product CRUD"},
     *     summary="Update product",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     default="Goleador",
     *                 ),
     *                 @OA\Property(
     *                     property="qty",
     *                     type="integer",
     *                     default="5000",
     *                 ),
     *                 @OA\Property(
     *                     property="price",
     *                     type="double",
     *                     default="0.12",
     *                 ),
     *             ),
     *         ),
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfull operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 type="boolean",
     *                 default="true",
     *                 description="Status",
     *                 property="status",
     *             ),
     *             @OA\Property(
     *                 type="object",
     *                 description="Updated product in json format",
     *                 property="data",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function update(Request $request, int $id) : JsonResponse
    {
        $product = $prevProductVersion = Product::find($id);

        if (!$product) {

            return response()->json([
                'status' => false,
                'data' => 'Product not found'
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

        // aggiorno la cache
        Cache::forget("product_$id");

        $product = Cache::remember("product_$id", 60, function () use ($id) {
            return Product::find($id);
        });

        self::dispatchEvents($product, $prevProductVersion);

        return response()->json([
            'status' => true,
            'data' => $product
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/product/{id}",
     *     operationId="destroy",
     *     tags={"Product CRUD"},
     *     summary="Delete product",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfull operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 type="boolean",
     *                 default="true",
     *                 description="Status",
     *                 property="status",
     *             ),
     *             @OA\Property(
     *                 type="string",
     *                 default="Deleted",
     *                 property="data",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function destroy(Request $request, int $id) : JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {

            return response()->json([
                'status' => false,
                'data' => 'Product not found'
            ], 404);
        }

        $product->delete();
        Cache::forget("product_$id");

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
