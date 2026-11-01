<?php

namespace App\Modules\Identity\Controllers\Customer;

use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Requests\AddressRequest;
use App\Modules\Identity\Resources\AddressResource;
use App\Modules\Identity\Services\AddressService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AddressController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AddressService $addressService
    ) {}

    /**
     * GET /api/account/addresses
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $this->addressService->getUserAddresses($request->user());

        return AddressResource::collection($addresses);
    }

    /**
     * POST /api/account/addresses
     */
    public function store(AddressRequest $request): JsonResponse
    {
        $address = $this->addressService->createAddress($request->user(), $request->validated());

        return (new AddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/account/addresses/{address}
     */
    public function show(Address $address): AddressResource
    {
        $this->authorize('view', $address);

        return new AddressResource($address);
    }

    /**
     * PUT /api/account/addresses/{address}
     */
    public function update(AddressRequest $request, Address $address): AddressResource
    {
        $this->authorize('update', $address);

        $updated = $this->addressService->updateAddress($address, $request->validated());

        return new AddressResource($updated);
    }

    /**
     * DELETE /api/account/addresses/{address}
     */
    public function destroy(Address $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $this->addressService->deleteAddress($address);

        return response()->json([
            'message' => 'Address deleted successfully.',
        ]);
    }

    /**
     * POST /api/account/addresses/{address}/default
     */
    public function setDefault(Address $address): AddressResource
    {
        $this->authorize('update', $address);

        $updated = $this->addressService->setDefaultAddress($address);

        return new AddressResource($updated);
    }
}
