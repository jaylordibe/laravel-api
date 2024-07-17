<?php

namespace App\Utils;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileUtil
{

    private const DISK = 'public';

    /**
     * Uploads a single file.
     *
     * @param string $path - the path where the file should be stored
     * @param File|UploadedFile|null $file - the file to be uploaded
     *
     * @return string - the uploaded file path
     */
    public static function upload(string $path, File|UploadedFile|null $file): string
    {
        if (empty($path) || empty($file)) {
            return '';
        }

        $fileName = time() . '_' . Str::uuid() . '_' . $file->getClientOriginalName();

        return Storage::disk(self::DISK)->putFileAs($path, $file, $fileName);
    }

    /**
     * Uploads multiple files.
     *
     * @param string $path - the path where the files should be stored
     * @param File[]|UploadedFile[]|array|null $files - the files to be uploaded
     *
     * @return array - the uploaded file paths
     */
    public static function uploadMultiple(string $path, ?array $files): array
    {
        if (empty($path) || empty($files)) {
            return [];
        }

        $uploadedFilePaths = [];

        foreach ($files as $file) {
            $uploadedFilePaths[] = self::upload($path, $file);
        }

        return $uploadedFilePaths;
    }

    /**
     * Deletes a single file.
     *
     * @param string $path - the path of the file that will be deleted
     *
     * @return bool - returns true if the deletion is successful, else false
     */
    public static function delete(string $path): bool
    {
        if (empty($path) || self::missing($path)) {
            return false;
        }

        return Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Deletes multiple files.
     *
     * @param array $paths - the paths of the file that will be deleted
     *
     * @return bool - returns true if the deletion is successful, else false
     */
    public static function deleteMultiple(array $paths): bool
    {
        if (empty($paths)) {
            return false;
        }

        foreach ($paths as $path) {

            if (self::missing($path)) {
                return false;
            }
        }

        foreach ($paths as $path) {
            self::delete($path);
        }

        return true;
    }

    /**
     * Generates a single pre-signed/public url that has an expiration.
     *
     * @param string $path - the file path to generate the url from
     * @param int $expirationInMinutes - the number of minutes that the url will be valid
     *
     * @return string - the pre-signed/public url
     */
    public static function generatePublicUrl(string $path, int $expirationInMinutes = 15): string
    {
        if (empty($path)) {
            return '';
        }

        $expirationDate = now()->addMinutes($expirationInMinutes);

        return Storage::disk(self::DISK)->temporaryUrl($path, $expirationDate);
    }

    /**
     * Generates multiple pre-signed/public urls that has an expiration.
     *
     * @param array $paths - the file paths to generate the url from
     * @param int $expirationInMinutes - the number of minutes that the url will be valid
     *
     * @return array - the pre-signed/public urls
     */
    public static function generatePublicUrls(array $paths, int $expirationInMinutes = 15): array
    {
        if (empty($paths)) {
            return [];
        }

        $preSignedUrls = [];

        foreach ($paths as $path) {
            $preSignedUrls[] = self::generatePublicUrl($path, $expirationInMinutes);
        }

        return $preSignedUrls;
    }

    /**
     * Generates a single download url that has an expiration.
     *
     * @param string $path - the file path to generate the url from
     * @param int $expirationInMinutes - the number of minutes that the url will be valid
     *
     * @return string - the download url
     */
    public static function generateDownloadUrl(string $path, int $expirationInMinutes = 15): string
    {
        if (empty($path)) {
            return '';
        }

        $expirationDate = now()->addMinutes($expirationInMinutes);
        $options = [
            'ResponseContentType' => 'application/octet-stream',
            'ResponseContentDisposition' => 'attachment; filename=' . basename($path)
        ];

        return Storage::disk(self::DISK)->temporaryUrl($path, $expirationDate, $options);
    }

    /**
     * Generates multiple download urls that has an expiration.
     *
     * @param array $paths - the file paths to generate the url from
     * @param int $expirationInMinutes - the number of minutes that the url will be valid
     *
     * @return array - the download urls
     */
    public static function generateDownloadUrls(array $paths, int $expirationInMinutes = 15): array
    {
        if (empty($paths)) {
            return [];
        }

        $preSignedUrls = [];

        foreach ($paths as $path) {
            $preSignedUrls[] = self::generateDownloadUrl($path, $expirationInMinutes);
        }

        return $preSignedUrls;
    }

    /**
     * Checks if the given file path exists.
     *
     * @param string $path - the file path to be checked if it exists
     *
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Checks if the given file path is missing.
     *
     * @param string $path - the file path to be checked if it is missing
     *
     * @return bool
     */
    public static function missing(string $path): bool
    {
        return Storage::disk(self::DISK)->missing($path);
    }

    /**
     * Checks if the given directory path exists.
     *
     * @param string $path - the directory path to be checked if it exists
     *
     * @return bool
     */
    public static function directoryExists(string $path): bool
    {
        return Storage::disk(self::DISK)->directoryExists($path);
    }

    /**
     * Checks if the given directory path is missing.
     *
     * @param string $path - the directory path to be checked if it is missing
     *
     * @return bool
     */
    public static function directoryMissing(string $path): bool
    {
        return Storage::disk(self::DISK)->directoryMissing($path);
    }

    /**
     * Create a directory.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function makeDirectory(string $path): bool
    {
        return Storage::disk(self::DISK)->makeDirectory($path);
    }

    /**
     * Delete a directory.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function deleteDirectory(string $path): bool
    {
        return Storage::disk(self::DISK)->deleteDirectory($path);
    }

    /**
     * Downloads a file using the path provided.
     *
     * @param string $path - file path
     *
     * @return StreamedResponse|null
     */
    public static function download(string $path): ?StreamedResponse
    {
        if (self::missing($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->download($path);
    }

    /**
     * Get the url for the file at the given path.
     *
     * @param string $path
     *
     * @return string
     */
    public static function getUrl(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Get the storage path for the file at the given path.
     *
     * @param string $path
     *
     * @return string
     */
    public static function getStoragePath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

}
