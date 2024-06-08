<?php

namespace App\Http\Controllers;

use App\Constants\GateAbilityConstant;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\SignUpUserRequest;
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
     * Sign up a new user.
     *
     * @param SignUpUserRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function signUp(SignUpUserRequest $request): JsonResponse|JsonResource
    {
        $serviceResponse = $this->userService->signUp($request->toData());

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->data);
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

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->data);
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

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->data);
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

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->data);
    }

    /**
     * @param CreateUserRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function create(CreateUserRequest $request): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::CAN_CREATE_USER);

        $serviceResponse = $this->userService->create($request->toData());

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->data);
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getPaginated(GenericRequest $request): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::CAN_READ_USER);

        $userFilterData = UserRequest::createFrom($request)->toFilterData();
        $serviceResponse = $this->userService->getPaginated($userFilterData);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->data);
    }

    /**
     * @param GenericRequest $request
     * @param int $userId
     *
     * @return JsonResponse|JsonResource
     */
    public function getById(GenericRequest $request, int $userId): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::CAN_READ_USER);

        $serviceResponse = $this->userService->getById($userId);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->data);
    }

    /**
     * @param UserRequest $request
     * @param int $userId
     *
     * @return JsonResponse|JsonResource
     */
    public function update(UserRequest $request, int $userId): JsonResponse|JsonResource
    {
        Gate::authorize(GateAbilityConstant::CAN_UPDATE_USER);

        $serviceResponse = $this->userService->update($request->toData());

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(UserResource::class, $serviceResponse->data);
    }

    /**
     * @param GenericRequest $request
     * @param int $userId
     *
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $userId): JsonResponse
    {
        Gate::authorize(GateAbilityConstant::CAN_DELETE_USER);

        $serviceResponse = $this->userService->delete($userId);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::success($serviceResponse->message);
    }

}
