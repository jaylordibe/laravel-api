<?php

namespace App\Http\Resources;

use App\Utils\FileUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class UserResource extends BaseResource
{

    /**
     * Shown when a user has no profile image, or when one cannot be signed.
     *
     * @var string
     */
    private const string DEFAULT_PROFILE_IMAGE_URL = 'https://i.imgur.com/UJ0N2SN.jpg';

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Load the attributes
        $data = parent::transformAttributes();

        if ($request->boolean('includeAccessControl')) {
            $data['roles'] = $this->getRoleNames()->toArray();
            $data['permissions'] = $this->getAllPermissions()->pluck('name')->toArray();
        }

        $data['profileImage'] = $this->resolveProfileImageUrl();

        if (!$this->isPendingEmailVisibleTo($request)) {
            $data['pendingEmail'] = null;
            $data['pendingEmailExpiresAt'] = null;
        }

        // Load the relations

        return $data;
    }

    /**
     * Whether the pending email change is shown: only to its own user, and only while it can still be
     * confirmed. The address is unconfirmed and user-typed, possibly someone else's, so an admin reading the
     * record does not see it; and an expired change is not pending, though its columns stay until replaced.
     *
     * @param Request $request
     *
     * @return bool
     */
    private function isPendingEmailVisibleTo(Request $request): bool
    {
        return $request->user()?->id === $this->id
            && !empty($this->pending_email_expires_at)
            && $this->pending_email_expires_at->isFuture();
    }

    /**
     * Turn the stored profile image object key into a short-lived signed URL.
     *
     * `profile_image` holds a storage KEY now, not a URL, so the objects can stay
     * private — see UserService::updateProfileImage. The signed URL is minted per
     * response and expires, which means it is only useful to the caller who was
     * already authorized to read this record.
     *
     * A value that is already an absolute URL is passed through untouched. That
     * covers rows written before the change and any fork that points the column
     * at an externally hosted avatar; signing an http(s) string as though it were
     * an object key would produce a broken link.
     *
     * Signing is best-effort: a storage backend that is unreachable, or an object
     * that has been deleted underneath the row, must not turn a user lookup into
     * a 500.
     *
     * A signing FAILURE returns null, not the placeholder. Returning the
     * placeholder would make "this user has no avatar" and "storage is
     * misconfigured for every user" identical on the wire — so a total outage of
     * the storage backend would render as a perfectly healthy API serving default
     * avatars, and nobody would look. null says "there should be something here
     * and I could not produce it", which is the truth.
     *
     * @return string|null
     */
    private function resolveProfileImageUrl(): ?string
    {
        $profileImage = $this->profile_image;

        if (empty($profileImage)) {
            return self::DEFAULT_PROFILE_IMAGE_URL;
        }

        if (Str::startsWith($profileImage, ['http://', 'https://'])) {
            return $profileImage;
        }

        try {
            return FileUtil::generatePublicUrl($profileImage);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

}
