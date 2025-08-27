<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Jobs\ProcessOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\ClientRepository;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request)
{
    $payload = $request->validated();

    // fetch products in one query and index by id for easy lookup
    $productIds = collect($payload['items'])->pluck('product_id')->unique()->values()->all();
    $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

    try {
        $order = DB::transaction(function () use ($payload, $products) {
            $now = now();
            $insert = [];
            $total = 0;

            //  check stock before creating order
            foreach ($payload['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (! $product) {
                    throw new \Exception("Product not found: {$item['product_id']}");
                }
                if ($product->price <= 0) {
                    throw new \Exception("Invalid product price for product: {$product->id}");
                }

                
                $quantity = (int) $item['quantity'];

                if ($product->stock < $quantity) {
                    throw new \Exception("Not enough stock for product: {$product->id}");
                }

                $unitPrice = (float) $product->price;
                $lineTotal = round($unitPrice * $quantity, 2);

                $insert[] = [
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $total += $lineTotal;
            }

            //  only create order if all items are valid
            $order = Order::create([
                'user_id' => $payload['user_id'],
                'total'   => round($total, 2),
                'status'  => 'pending',
            ]);

            // attach order_id after creating order
            foreach ($insert as &$row) {
                $row['order_id'] = $order->id;
            }

            // bulk insert items
            OrderItem::insert($insert);

            return $order;
        }, 5);

        // dispatch processing job after commit 
        ProcessOrder::dispatch($order)->afterCommit();

        return response()->json(['status' => true, 'order_id' => $order->id], 201);
    } catch (\Throwable $e) {
        Log::error('Order creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Order creation failed',
            'error' => $e->getMessage(),
        ], 400); 
    }
}

    /**
     * Display all orders with user & items.
     */
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(10); // Paginate results, 10 per page

        return response()->json([
            'status' => true,
            'data'    => $orders,
        ]);
    }

    /**
     * Display a single order by ID with items.
     */
    public function show($id)
    {
        $order = Order::with(['user', 'items.product'])->find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'    => $order,
        ]);
    }
    public function runMigrationsAndSeeders()
    {
        try {
            // Check if "passport:install" command exists
            $commands = Artisan::all();
            if (array_key_exists('passport:install', $commands)) {
                Artisan::call('passport:install', ['--force' => true]);
            } else {
                Log::warning('Passport command not found. Ensure laravel/passport is installed.');
            }
            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Run all seeders
            Artisan::call('db:seed', ['--force' => true]);
            //  Ensure Passport personal access client exists
            $clientRepository = new ClientRepository();
            if (DB::table('oauth_clients')->where('personal_access_client', 1)->count() == 0) {
                $clientRepository->createPersonalAccessClient(
                    null,
                    'Default Personal Access Client',
                    config('app.url')
                );
            }

            // Clear all caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('optimize:clear');

            return response()->json([
                'status'  => 'success',
                'message' => 'Migrations, seeders, and Passport executed successfully.',
                'output'  => Artisan::output()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Migration/Seeder failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to run migrations or seeders.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function resetDatabase()
    {
        try {
            // Refresh migrations
            Artisan::call('migrate:refresh', ['--force' => true]);

            // Run all seeders
            Artisan::call('db:seed', ['--force' => true]);

            //  Ensure Passport personal access client exists
            $clientRepository = new ClientRepository();
            if (DB::table('oauth_clients')->where('personal_access_client', 1)->count() == 0) {
                $clientRepository->createPersonalAccessClient(
                    null,
                    'Default Personal Access Client',
                    config('app.url')
                );
            }

            // Clear all caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('optimize:clear');

            return response()->json([
                'status'  => 'success',
                'message' => 'Database reset, seeded, Passport personal client created, and cache cleared successfully.',
                'output'  => Artisan::output()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Database reset failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reset database.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
