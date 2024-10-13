<?php

namespace App\Jobs;

use App\Http\Controllers\ProductController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
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

        // TODO :: --- DA VERIFICARE ---
        //      Tenendo presente che si potrebbero collegare alla stessa istanza redis sia il microservizio degli ordini che quello dei prodotti,
        //      la lista dei prodotti si potrebbe recuperare direttamente da redis se in cache. Non ho testato la cosa quindi resta una supposizione.
        //      Quindi non scrivo il codice non essendo sicuro, ma si potrebbe testare. In questo modo pur non potendo fare query sul DB degli ordini,
        //      potrei avere il risultato della query direttamente in cache con prestazioni decisamente migliori.

        $productList = json_decode($this->order['product_list'], true);

        // Log::channel('warehouse_update')->info(print_r($productList, true));

        foreach ($productList as $productToUpdate) {

            if (!empty($productToUpdate['product_id'])) {

                if (Cache::has("product_".$productToUpdate['product_id'])) {

                    $product = Cache::get("product_".$productToUpdate['product_id']);
        
                } else {
                    
                    $product = Cache::remember("product_".$productToUpdate['product_id'], 60, function () use ($productToUpdate) {
                        return Product::find($productToUpdate['product_id']);
                    });
                }

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
