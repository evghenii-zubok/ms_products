<?php

namespace App\Jobs;

use App\Http\Controllers\ProductController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProcessOrder implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    private array $order;
    
    public function __construct(array $order)
    {
        $this->order = $order;
    }

    public function handle(): void
    {
        // Log::channel('warehouse_update')->info(print_r($this->order, true));

        if (!isset($this->order['product_list'])) {
            return;
        }

        $productList = json_decode($this->order['product_list'], true);

        // Log::channel('warehouse_update')->info(print_r($productList, true));

        foreach ($productList as $productToUpdate) {

            if (!empty($productToUpdate['product_id'])) {

                $product = Product::find($productToUpdate['product_id']);

                if (!$product) {

                    Log::channel('daily')->warning('Product with id: ' . $productToUpdate['product_id'] . ' was not found during update qty.');
                    continue;
                }

                $product->qty -= $productToUpdate['qty'];
                $product->save();

                ProductController::dispatchEventsByQty($product);
            }
        }
    }
}
