<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Exceptions\BadRequestException;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\SignUpUserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Utils\ResponseUtil;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
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
     * @throws BadRequestException
     */
    public function signUp(SignUpUserRequest $request): JsonResponse|JsonResource
    {
        $this->userService->signUp($request->toData());

        return ResponseUtil::success('Sign up successful. Please check your email for verification link.');
    }

    /**
     * Verify user email.
     *
     * @param EmailVerificationRequest $request
     * @param int $userId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function verifyEmail(EmailVerificationRequest $request, int $userId): JsonResponse|JsonResource
    {
        if (!$request->hasValidSignature()) {
            return ResponseUtil::error('Invalid verification link.');
        }

        $this->userService->verifyEmail($userId);

        return ResponseUtil::success('Email verified successfully.');
    }

    /**
     * Get the authenticated user.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function getAuthUser(GenericRequest $request): JsonResponse|JsonResource
    {
        $relations = ['roles', 'permissions'];
        $user = $this->userService->getById($request->getAuthUserData()->id, $relations);

        return ResponseUtil::resource(UserResource::class, $user);
    }

    /**
     * Update auth user's username.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function updateAuthUsername(GenericRequest $request): JsonResponse|JsonResource
    {
        $username = $request->getInputAsString('username');

        if (empty($username)) {
            return ResponseUtil::error('Username is required.');
        }

        $user = $this->userService->updateUsername($request->getAuthUserData()->id, $username);

        return ResponseUtil::resource(UserResource::class, $user);
    }

    /**
     * Update auth user's email.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function updateAuthUserEmail(GenericRequest $request): JsonResponse|JsonResource
    {
        $email = $request->getInputAsString('email');

        if (empty($email)) {
            return ResponseUtil::error('Email is required.');
        }

        $user = $this->userService->updateEmail($request->getAuthUserData()->id, $email);

        return ResponseUtil::resource(UserResource::class, $user);
    }

    /**
     * Update auth user's password.
     *
     * @param UpdatePasswordRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function updateAuthUserPassword(UpdatePasswordRequest $request): JsonResponse|JsonResource
    {
        $this->userService->updatePassword($request->toData());

        return ResponseUtil::success('Password updated successfully.');
    }

    /**
     * Update user password.
     *
     * @param UpdatePasswordRequest $request
     * @param int $userId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function updatePassword(UpdatePasswordRequest $request, int $userId): JsonResponse|JsonResource
    {
        $updatePasswordData = $request->toData();
        $updatePasswordData->userId = $userId;
        $this->userService->updatePassword($updatePasswordData);

        return ResponseUtil::success('Password updated successfully.');
    }

    /**
     * Update auth user's profile image.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function updateAuthUserProfileImage(GenericRequest $request): JsonResponse|JsonResource
    {
        $profileImageFile = $request->file('profileImage');

        if (empty($profileImageFile)) {
            return ResponseUtil::error('Profile photo is required.');
        }

        $user = $this->userService->updateProfileImage($request->getAuthUserData()->id, $profileImageFile);

        return ResponseUtil::resource(UserResource::class, $user);
    }

    /**
     * @param CreateUserRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function create(CreateUserRequest $request): JsonResponse|JsonResource
    {
        Gate::authorize(UserPermission::CREATE_USER);

        $user = $this->userService->create($request->toData());

        return ResponseUtil::resource(UserResource::class, $user);
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getPaginated(GenericRequest $request): JsonResponse|JsonResource
    {
        Gate::authorize(UserPermission::READ_USER);

        $userFilterData = UserRequest::createFrom($request)->toFilterData();
        $users = $this->userService->getPaginated($userFilterData);

        return ResponseUtil::resource(UserResource::class, $users);
    }

    /**
     * @param GenericRequest $request
     * @param int $userId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function getById(GenericRequest $request, int $userId): JsonResponse|JsonResource
    {
        Gate::authorize(UserPermission::READ_USER);

        $user = $this->userService->getById($userId, $request->getRelations());

        return ResponseUtil::resource(UserResource::class, $user);
    }

    /**
     * @param UserRequest $request
     * @param int $userId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function update(UserRequest $request, int $userId): JsonResponse|JsonResource
    {
        Gate::authorize(UserPermission::UPDATE_USER);

        $user = $this->userService->update($request->toData());

        return ResponseUtil::resource(UserResource::class, $user);
    }

    /**
     * @param GenericRequest $request
     * @param int $userId
     *
     * @return JsonResponse
     * @throws BadRequestException
     */
    public function delete(GenericRequest $request, int $userId): JsonResponse
    {
        Gate::authorize(UserPermission::DELETE_USER);

        $this->userService->delete($userId);

        return ResponseUtil::success('User deleted successfully.');
    }

}
