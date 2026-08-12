<?php

namespace App\Http\Requests;

use Illuminate\Http\UploadedFile;

/**
 * Validates a profile image upload.
 *
 * This replaces `$request->file('profileImage')` read straight out of a
 * GenericRequest with no rules at all — which accepted any file, of any type, at
 * any size, and wrote it to a publicly readable disk under a name derived from
 * the upload.
 *
 * Every rule below closes a specific hole:
 *
 *   `file` + `image`   — `image` verifies the file is actually a decodable image
 *                        by inspecting its contents, not by trusting the
 *                        client-supplied MIME type or extension. A PHP script
 *                        named avatar.jpg fails here.
 *   `mimes`            — an explicit extension allowlist on top, so the accepted
 *                        set is a stated decision rather than whatever the image
 *                        library happens to decode. SVG is EXCLUDED on purpose:
 *                        it is XML, it can carry script, and a browser that
 *                        renders it from your origin executes that script.
 *   `dimensions`       — bounds a decompression bomb: a small file that expands
 *                        to an enormous bitmap and exhausts memory in whatever
 *                        later resizes it.
 *   `max`              — bounds the bytes accepted per request.
 *
 * The stored object key is generated server-side (see UserService), never taken
 * from the client filename.
 */
class UpdateProfileImageRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'profileImage' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'dimensions:max_width=8000,max_height=8000',
                'max:' . config('custom.storage.max_image_upload_kilobytes'),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        // One entry per rule. A rule without a message falls back to Laravel's
        // default wording, so the endpoint would explain four of its five
        // constraints in the project's voice and the fifth in the framework's.
        return [
            'profileImage.required' => 'Profile image is required.',
            'profileImage.file' => 'Profile image must be an uploaded file.',
            'profileImage.image' => 'Profile image must be a valid image file.',
            'profileImage.mimes' => 'Profile image must be a jpg, jpeg, png or webp file.',
            'profileImage.dimensions' => 'Profile image dimensions are too large.',
            'profileImage.max' => 'Profile image is too large.',
        ];
    }

    /**
     * Get the validated uploaded file.
     *
     * @return UploadedFile
     */
    public function getProfileImage(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('profileImage');

        return $file;
    }

}
