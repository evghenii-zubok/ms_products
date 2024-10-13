<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DealDetected implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    private array $product;

    public function __construct(array $product)
    {
        $this->product = $product;
    }

    public function handle(): void {}
}
