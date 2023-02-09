<?php

namespace App\Http\Controllers;

use App\Constants\GateAbilityConstant;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{

    private UserService $userService;

    /**
     * @param UserService $userService
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Get the authenticated user.
     * @param GenericRequest $request
     * @return JsonResponse|JsonResource
     */
    public function getAuthUser(GenericRequest $request): JsonResponse|JsonResource
    {
        $serviceResponse = $this->userService->getById($request->getAuthUser()->getId());

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param UserRequest $request
     * @return JsonResponse|JsonResource
     */
    public function create(UserRequest $request): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::SYSTEM_ADMIN_OR_ADMIN);

        $serviceResponse = $this->userService->create($request->toDto());

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param GenericRequest $request
     * @return JsonResponse|JsonResource
     */
    public function get(GenericRequest $request): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::SYSTEM_ADMIN_OR_ADMIN);

        $userDto = UserRequest::createFrom($request)->toDto();
        $serviceResponse = $this->userService->get($userDto);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse|JsonResource
     */
    public function getById(GenericRequest $request, int $id): JsonResponse|JsonResource
    {
        $serviceResponse = $this->userService->getById($id);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param UserRequest $request
     * @param int $id
     * @return JsonResponse|JsonResource
     */
    public function update(UserRequest $request, int $id): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::SYSTEM_ADMIN_OR_ADMIN);

        $userDto = $request->toDto();
        $userDto->setId($id);
        $serviceResponse = $this->userService->update($userDto);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        Gate::authorize(GateAbilityConstant::SYSTEM_ADMIN_OR_ADMIN);

        $serviceResponse = $this->userService->delete($id);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::success($serviceResponse->getMessage());
    }
}
