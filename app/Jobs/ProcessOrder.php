<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;
    public $tries = 3;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }
    public function handle()
    {
        DB::beginTransaction();

        try {
            $order = Order::with('items.product')->findOrFail($this->order->id);

            if ($order->status !== 'pending') {
                DB::commit();
                return;
            }
            $order->update(['status' => 'processing']);
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product) {
                    throw new \Exception('Product missing for item: ' . $item->id);
                }
                if ($product->stock < $item->quantity) {
                    throw new \Exception('Insufficient stock for product ' . $product->id);
                }
                $product->decrement('stock', $item->quantity);
            }
            $order->update(['status' => 'processed']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Order processing failed', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);

            try {
                $order = Order::find($this->order->id);
                if ($order) {
                    $meta = array_merge($order->meta ?? [], ['last_error' => $e->getMessage()]);
                    $order->update(['status' => 'failed', 'meta' => $meta]);
                }
            } catch (\Throwable $ex) {
                Log::error('Failed to update order status after processing exception', ['error' => $ex->getMessage()]);
            }

            throw $e;
        }
    }
}
