<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSyncLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;


class ProductSyncController extends Controller
{

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        return $this->respondWithToken(Auth::user());
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return $this->respondWithToken($user, 201);
    }

    private function respondWithToken(User $user, int $status = 200)
    {
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => $status === 201 ? 'Registration successful' : 'Login successful',
            'token' => $token,
            'user' => $user,
        ], $status);
    }
    public function syncProducts(Request $request)
    {
        $items = $request->all();

        if (!is_array($items)) {
            return response()->json(['message' => 'Invalid payload, expected array of products'], 422);
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($items as $item) {
            $validator = Validator::make($item, [
                'product_code' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer',
                'updated_at' => 'required|date_format:Y-m-d H:i:s',
                'product_name' => 'sometimes|string|max:255',
                'category' => 'sometimes|string|max:255',
                'status' => 'sometimes|string|in:active,inactive,Active,Inactive',
            ]);

            if ($validator->fails()) {
                $summary['failed']++;
                $this->logSync($item['product_code'] ?? '', 'Fail', 'validation_failed');
                continue;
            }

            if ($item['stock'] < 0) {
                $summary['failed']++;
                $this->logSync($item['product_code'], 'Fail', 'invalid_stock');
                continue;
            }

            try {
                $result = $this->processItem($item);
                $actionKey = match (strtolower($result['action'])) {
                    'create' => 'created',
                    'update' => 'updated',
                    'skip' => 'skipped',
                    default => 'failed',
                };
                $summary[$actionKey]++;
            } catch (QueryException $exception) {
                $summary['failed']++;
                $this->logSync($item['product_code'], 'Fail', 'database_error');
            }
        }

        return response()->json($summary);
    }

    public function search(Request $request)
    {
        $query = Product::query();

        if ($code = $request->query('product_code')) {
            $query->where('product_code', 'like', "%{$code}%");
        }

        if ($name = $request->query('product_name')) {
            $query->where('product_name', 'like', "%{$name}%");
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

        return response()->json($query->paginate($perPage));
    }

    public function dashboard()
    {
        $today = now()->toDateString();

        return response()->json([
            'total_products' => Product::count(),
            'created_today' => Product::whereDate('created_at', $today)->count(),
            'updated_today' => Product::whereDate('updated_at', $today)->count(),
            'skipped_records' => ProductSyncLog::whereDate('created_at', $today)->where('action', 'Skip')->count(),
            'failed_records' => ProductSyncLog::whereDate('created_at', $today)->where('action', 'Fail')->count(),
        ]);
    }

    private function processItem(array $item): array
    {
        $incomingUpdated = Carbon::createFromFormat('Y-m-d H:i:s', $item['updated_at']);

        return DB::transaction(function () use ($item, $incomingUpdated) {
            $product = Product::where('product_code', $item['product_code'])->lockForUpdate()->first();

            if (!$product) {
                $product = Product::create($this->buildPayload($item, $incomingUpdated));
                $this->logSync($product->product_code, 'Create', 'created');

                return ['action' => 'Create', 'reason' => 'created'];
            }

            if ($incomingUpdated->lessThanOrEqualTo($product->last_updated_at)) {
                $this->logSync($product->product_code, 'Skip', 'incoming_not_newer');

                return ['action' => 'Skip', 'reason' => 'incoming_not_newer'];
            }

            $product->fill($this->buildPayload($item, $incomingUpdated, $product));
            $product->save();
            $this->logSync($product->product_code, 'Update', 'updated');

            return ['action' => 'Update', 'reason' => 'updated'];
        }, 3);
    }

    private function buildPayload(array $item, Carbon $incomingUpdated, Product $product = null): array
    {
        $status = strtolower($item['status'] ?? ($product?->status ?? 'active'));
        $status = in_array($status, ['active', 'inactive']) ? ucfirst($status) : 'Active';

        return [
            'product_code' => $item['product_code'],
            'product_name' => $item['product_name'] ?? $product?->product_name ?? $item['product_code'],
            'category' => $item['category'] ?? $product?->category ?? 'Uncategorized',
            'price' => $item['price'],
            'stock' => $item['stock'],
            'status' => $status,
            'last_updated_at' => $incomingUpdated,
        ];
    }

    private function logSync(string $productCode, string $action, string $reason): void
    {
        ProductSyncLog::create([
            'product_code' => $productCode,
            'action' => $action,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
