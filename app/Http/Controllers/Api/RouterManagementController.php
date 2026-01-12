<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MikroTikDevice;
use App\Services\MikroTikApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RouterManagementController extends Controller
{
    private MikroTikApiService $apiService;
    
    public function __construct(MikroTikApiService $apiService)
    {
        $this->apiService = $apiService;
    }
    /**
     * Get all routers for management
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MikroTikDevice::query();

            // Apply search filter
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      ->orWhereJsonContains('location->region', $search)
                      ->orWhereJsonContains('location->district', $search);
                });
            }

            // Apply status filter
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $perPage = min($request->get('per_page', 15), 100);
            $routers = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $routers->items(),
                'meta' => [
                    'current_page' => $routers->currentPage(),
                    'last_page' => $routers->lastPage(),
                    'per_page' => $routers->perPage(),
                    'total' => $routers->total(),
                ],
                'summary' => [
                    'total_routers' => MikroTikDevice::count(),
                    'online_routers' => MikroTikDevice::online()->count(),
                    'offline_routers' => MikroTikDevice::offline()->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch routers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch routers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a new router
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:mikrotik_devices,name',
                'ip_address' => 'required|ip|unique:mikrotik_devices,ip_address',
                'api_port' => 'required|integer|min:1|max:65535',
                'username' => 'required|string|max:100',
                'password' => 'required|string|min:6',
                'location' => 'required|array',
                'location.region' => 'required|string|max:100',
                'location.district' => 'required|string|max:100',
                'location.coordinates' => 'nullable|array',
                'location.coordinates.lat' => 'nullable|numeric|between:-90,90',
                'location.coordinates.lng' => 'nullable|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Test connection before saving
            $tempDevice = new MikroTikDevice([
                'ip_address' => $data['ip_address'],
                'api_port' => $data['api_port'],
                'username' => $data['username'],
                'password' => $data['password']
            ]);
            
            $connectionTest = $this->apiService->testConnection($tempDevice);

            if (!$connectionTest['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Router connection test failed',
                    'error' => $connectionTest['error']
                ], 400);
            }

            // Create the router
            $router = MikroTikDevice::create([
                'name' => $data['name'],
                'ip_address' => $data['ip_address'],
                'api_port' => $data['api_port'],
                'username' => $data['username'],
                'password' => $data['password'], // Will be encrypted by model mutator
                'location' => $data['location'],
                'status' => 'online', // Set as online since connection test passed
                'last_seen' => now(),
            ]);

            Log::info('Router created successfully', [
                'router_id' => $router->id,
                'name' => $router->name,
                'ip_address' => $router->ip_address,
                'created_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Router created successfully',
                'data' => $router
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create router', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create router',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Show a specific router
     */
    public function show(string $id): JsonResponse
    {
        try {
            $router = MikroTikDevice::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $router
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Router not found'
            ], 404);
        }
    }

    /**
     * Update a router
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $router = MikroTikDevice::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mikrotik_devices', 'name')->ignore($router->id)
                ],
                'ip_address' => [
                    'sometimes',
                    'required',
                    'ip',
                    Rule::unique('mikrotik_devices', 'ip_address')->ignore($router->id)
                ],
                'api_port' => 'sometimes|required|integer|min:1|max:65535',
                'username' => 'sometimes|required|string|max:100',
                'password' => 'sometimes|required|string|min:6',
                'location' => 'sometimes|required|array',
                'location.region' => 'sometimes|required|string|max:100',
                'location.district' => 'sometimes|required|string|max:100',
                'location.coordinates' => 'nullable|array',
                'location.coordinates.lat' => 'nullable|numeric|between:-90,90',
                'location.coordinates.lng' => 'nullable|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Test connection if connection details changed
            $connectionChanged = isset($data['ip_address']) || 
                               isset($data['api_port']) || 
                               isset($data['username']) || 
                               isset($data['password']);

            if ($connectionChanged) {
                $tempDevice = new MikroTikDevice([
                    'ip_address' => $data['ip_address'] ?? $router->ip_address,
                    'api_port' => $data['api_port'] ?? $router->api_port,
                    'username' => $data['username'] ?? $router->username,
                    'password' => $data['password'] ?? $router->password
                ]);
                
                $connectionTest = $this->apiService->testConnection($tempDevice);

                if (!$connectionTest['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Router connection test failed',
                        'error' => $connectionTest['error']
                    ], 400);
                }

                // Update status and last_seen if connection test passed
                $data['status'] = 'online';
                $data['last_seen'] = now();
            }

            $router->update($data);

            Log::info('Router updated successfully', [
                'router_id' => $router->id,
                'name' => $router->name,
                'updated_by' => auth()->id(),
                'changes' => array_keys($data)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Router updated successfully',
                'data' => $router->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update router', [
                'router_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update router',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a router
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $router = MikroTikDevice::findOrFail($id);

            // Check if router has associated vouchers
            $voucherCount = $router->vouchers()->count();
            if ($voucherCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete router. It has {$voucherCount} associated vouchers.",
                    'error' => 'Router has dependencies'
                ], 400);
            }

            $routerName = $router->name;
            $router->delete();

            Log::info('Router deleted successfully', [
                'router_id' => $id,
                'name' => $routerName,
                'deleted_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Router deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete router', [
                'router_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete router',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Test router connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required|ip',
                'api_port' => 'required|integer|min:1|max:65535',
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $tempDevice = new MikroTikDevice([
                'ip_address' => $data['ip_address'],
                'api_port' => $data['api_port'],
                'username' => $data['username'],
                'password' => $data['password']
            ]);
            
            $result = $this->apiService->testConnection($tempDevice);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Router connection test failed', [
                'error' => $e->getMessage(),
                'ip_address' => $request->get('ip_address')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}