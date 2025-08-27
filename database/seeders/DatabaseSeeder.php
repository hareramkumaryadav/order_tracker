<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create 10 users
        User::factory(10)->create();

        // Create 20 products
        $products = Product::factory(20)->create();

        // Create 5 orders with items
        Order::factory(5)->create()->each(function ($order) use ($products) {
            $items = $products->random(rand(2, 5));
            $total = 0;

            foreach ($items as $product) {
                $quantity = rand(1, 3);
                $total += $product->price * $quantity;

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price'      => $product->price,
                    'line_total' => $product->price * $quantity,
                ]);
            }

            $order->update(['total' => $total]);
        });
    }
}
