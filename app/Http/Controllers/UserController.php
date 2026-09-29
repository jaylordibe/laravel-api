<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidVerificationLinkException;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\ResendEmailVerificationRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SignUpUserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileImageRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
     * Opened from the emailed link with no signed-in user, so the signed URL is the credential.
     *
     * @param GenericRequest $request
     * @param int $userId
     *
     * @return RedirectResponse
     * @throws InvalidVerificationLinkException
     */
    public function verifyEmail(GenericRequest $request, int $userId): RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            throw new InvalidVerificationLinkException();
        }

        $this->userService->verifyEmail($userId, $request->string('hash')->toString());

        // Opened in a browser, so land on the client app, like Breeze's API kit.
        return redirect()->away(config('custom.app_frontend_url') . '/?verified=1');
    }

    /**
     * Send a password reset link. The answer is the same whether or not the email has an account.
     *
     * @param ForgotPasswordRequest $request
     *
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->userService->sendPasswordResetLink($request->string('email')->toString());

        return ResponseUtil::success('If that email has an account, a reset link has been sent.');
    }

    /**
     * Reset the password with the token from the emailed link.
     *
     * @param ResetPasswordRequest $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->userService->resetPassword($request->toData());

        return ResponseUtil::success('Password reset successfully.');
    }

    /**
     * Send a new email verification link, for a user whose link expired or never arrived.
     *
     * The answer is the same whether or not the address has an unverified account.
     *
     * @param ResendEmailVerificationRequest $request
     *
     * @return JsonResponse
     */
    public function resendEmailVerification(ResendEmailVerificationRequest $request): JsonResponse
    {
        $this->userService->resendEmailVerification($request->string('email')->toString());

        return ResponseUtil::success('If that email needs verification, a new link has been sent.');
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
     * @param UserRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function updateAuthUserInfo(UserRequest $request): JsonResponse|JsonResource
    {
        $userData = $request->toData();
        $userData->id = auth()->user()->id;
        $user = $this->userService->update($userData);

        return ResponseUtil::resource(UserResource::class, $user);
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     */
    public function deleteAuthUser(GenericRequest $request): JsonResponse
    {
        $this->userService->delete(auth()->user()->id);

        return ResponseUtil::success('User deleted successfully.');
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
        $username = $request->string('username');

        if ($username->isEmpty()) {
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
        $email = $request->string('email');

        if ($email->isEmpty()) {
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
     * The file is validated in UpdateProfileImageRequest — type, real image
     * content, dimensions and size. It used to arrive through a GenericRequest
     * with no rules at all, and the only check was a hand-rolled "is it empty"
     * branch here, which is both an unvalidated upload and a controller doing
     * validation. Both are now gone: a missing or invalid file fails in the Form
     * Request and returns the same 400 shape as every other validation failure.
     *
     * @param UpdateProfileImageRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function updateAuthUserProfileImage(UpdateProfileImageRequest $request): JsonResponse|JsonResource
    {
        $user = $this->userService->updateProfileImage(
            $request->getAuthUserData()->id,
            $request->getProfileImage()
        );

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
