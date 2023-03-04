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

    public function __construct(
        private readonly UserService $userService
    )
    {
    }

    /**
     * Get the authenticated user.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getAuthUser(GenericRequest $request): JsonResponse|JsonResource
    {
        $relations = ['roles', 'permissions'];
        $serviceResponse = $this->userService->getById($request->getAuthUser()->id, $relations);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * Update auth user's username.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function updateAuthUserName(GenericRequest $request): JsonResponse|JsonResource
    {
        $username = $request->getInputAsString('username');

        if (empty($username)) {
            return ResponseUtil::error('Username is required.');
        }

        $serviceResponse = $this->userService->updateUsername($request->getAuthUser()->id, $username);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * Update auth user's email.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function updateAuthUserEmail(GenericRequest $request): JsonResponse|JsonResource
    {
        $email = $request->getInputAsString('email');

        if (empty($email)) {
            return ResponseUtil::error('Email is required.');
        }

        $serviceResponse = $this->userService->updateEmail($request->getAuthUser()->id, $email);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param UserRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function create(UserRequest $request): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::CAN_CREATE_USER);

        $userDto = $request->toDto();
        $userDto->setEmail($request->getInputAsString('email'));
        $userDto->setPassword($request->getInputAsString('password'));
        $userDto->setPasswordConfirmation($request->getInputAsString('passwordConfirmation'));
        $serviceResponse = $this->userService->create($userDto);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function get(GenericRequest $request): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::CAN_READ_USER);

        $userFilterDto = UserRequest::createFrom($request)->toFilterDto();
        $serviceResponse = $this->userService->get($userFilterDto);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     *
     * @return JsonResponse|JsonResource
     */
    public function getById(GenericRequest $request, int $id): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::CAN_READ_USER);

        $serviceResponse = $this->userService->getById($id);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->getData());
    }

    /**
     * @param UserRequest $request
     * @param int $id
     *
     * @return JsonResponse|JsonResource
     */
    public function update(UserRequest $request, int $id): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::CAN_UPDATE_USER);

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
     *
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        Gate::authorize(GateAbilityConstant::CAN_DELETE_USER);

        $serviceResponse = $this->userService->delete($id);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::success($serviceResponse->getMessage());
    }

}
