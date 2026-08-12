<?php

namespace App\Utils;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class FileUtil
{

    /**
     * Per-request memo of generated temporary URLs, keyed by disk + path + ttl.
     *
     * @var array<string, string>
     */
    private static array $temporaryUrlCache = [];

    /**
     * Resolve the storage disk used for application files.
     *
     * This was a `private const DISK = 'public'`, which hard-wired every upload
     * to the LOCAL disk exposed through the public storage symlink. Two problems,
     * both of which this method fixes:
     *
     *   - Every stored object was world-readable to anyone who could guess or
     *     obtain its URL, with no authorization step anywhere in the path.
     *   - A constant cannot be pointed at object storage, so the local
     *     filesystem — which is ephemeral and per-replica in a container — was
     *     the only possible destination.
     *
     * The disk now comes from configuration (APP_STORAGE_DISK, falling back to
     * FILESYSTEM_DISK), so switching between local, S3-compatible and GCS storage
     * is an environment variable. All of those disks store objects PRIVATELY; the
     * URL helpers below issue short-lived signed URLs instead of public links.
     *
     * @return Filesystem
     */
    private static function disk(): Filesystem
    {
        return Storage::disk(config('custom.storage.disk'));
    }

    /**
     * Default lifetime, in minutes, for the signed URLs issued below.
     *
     * @return int
     */
    private static function defaultTtlInMinutes(): int
    {
        return config('custom.storage.temporary_url_ttl');
    }

    /**
     * Uploads a single file.
     *
     * @param UploadedFile $file - the file to be uploaded
     * @param string $path - the path where the file should be stored
     *
     * @return string - the uploaded file path
     */
    public static function upload(UploadedFile $file, string $path = ''): string
    {
        return $file->store($path, config('custom.storage.disk'));
    }

    /**
     * Uploads multiple files.
     *
     * @param UploadedFile[]|array $files - the files to be uploaded
     * @param string $path - the path where the files should be stored
     *
     * @return array - the uploaded file paths
     */
    public static function uploadMultiple(array $files, string $path = ''): array
    {
        if (empty($files)) {
            return [];
        }

        $uploadedFilePaths = [];

        foreach ($files as $file) {
            if (empty($file)) {
                continue;
            }

            $uploadedFilePaths[] = self::upload($file, $path);
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

        return self::disk()->delete($path);
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
            if (empty($path)) {
                continue;
            }

            self::delete($path);
        }

        return true;
    }

    /**
     * Generates a single pre-signed/public url that has an expiration.
     *
     * @param string $path - the file path to generate the url from
     * @param int|null $expirationInMinutes - minutes the url stays valid; null uses the configured default
     *
     * @return string - the pre-signed/public url
     */
    public static function generatePublicUrl(string $path, ?int $expirationInMinutes = null): string
    {
        if (empty($path)) {
            return '';
        }

        $ttl = $expirationInMinutes ?? self::defaultTtlInMinutes();

        // Memoised for the lifetime of the request.
        //
        // Signing is not always local. On S3 it is a pure HMAC and free. On GCS
        // with Application Default Credentials — the posture DEPLOYMENT.md
        // recommends, because it avoids shipping a key file — the client has no
        // private key, so each signature is an HTTPS round trip to the IAM
        // credentials API. A collection response that signs one URL per row then
        // makes one outbound call per row.
        //
        // This removes the repeat-path case entirely. It does NOT make a page of
        // 100 DISTINCT objects free on GCS+ADC; see the note in DEPLOYMENT.md for
        // the options there.
        $cacheKey = config('custom.storage.disk') . '|' . $path . '|' . $ttl;

        if (!array_key_exists($cacheKey, self::$temporaryUrlCache)) {
            self::$temporaryUrlCache[$cacheKey] = self::disk()->temporaryUrl($path, now()->addMinutes($ttl));
        }

        return self::$temporaryUrlCache[$cacheKey];
    }

    /**
     * Generates multiple pre-signed/public urls that has an expiration.
     *
     * @param array $paths - the file paths to generate the url from
     * @param int|null $expirationInMinutes - minutes the url stays valid; null uses the configured default
     *
     * @return array - the pre-signed/public urls
     */
    public static function generatePublicUrls(array $paths, ?int $expirationInMinutes = null): array
    {
        if (empty($paths)) {
            return [];
        }

        $preSignedUrls = [];

        foreach ($paths as $path) {
            if (empty($path)) {
                continue;
            }

            $preSignedUrls[] = self::generatePublicUrl($path, $expirationInMinutes);
        }

        return $preSignedUrls;
    }

    /**
     * Generates a single download url that has an expiration.
     *
     * @param string $path - the file path to generate the url from
     * @param int|null $expirationInMinutes - minutes the url stays valid; null uses the configured default
     *
     * @return string - the download url
     */
    public static function generateDownloadUrl(string $path, ?int $expirationInMinutes = null): string
    {
        if (empty($path)) {
            return '';
        }

        $expirationDate = now()->addMinutes($expirationInMinutes ?? self::defaultTtlInMinutes());
        $options = [
            'ResponseContentType' => 'application/octet-stream',
            'ResponseContentDisposition' => 'attachment; filename=' . basename($path)
        ];

        return self::disk()->temporaryUrl($path, $expirationDate, $options);
    }

    /**
     * Generates multiple download urls that has an expiration.
     *
     * @param array $paths - the file paths to generate the url from
     * @param int|null $expirationInMinutes - minutes the url stays valid; null uses the configured default
     *
     * @return array - the download urls
     */
    public static function generateDownloadUrls(array $paths, ?int $expirationInMinutes = null): array
    {
        if (empty($paths)) {
            return [];
        }

        $preSignedUrls = [];

        foreach ($paths as $path) {
            if (empty($path)) {
                continue;
            }

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
        return self::disk()->exists($path);
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
        return self::disk()->missing($path);
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
        return self::disk()->directoryExists($path);
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
        return self::disk()->directoryMissing($path);
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
        return self::disk()->makeDirectory($path);
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
        return self::disk()->deleteDirectory($path);
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

        return self::disk()->download($path);
    }

    /**
     * Get the unsigned url for the file at the given path.
     *
     * PREFER generatePublicUrl(). A URL from this method carries no signature and
     * no expiry, so it only resolves if the object is publicly readable — and on
     * every disk configured by this template objects are private, which means
     * this returns a link that 403s.
     *
     * `Storage::url()` returning a string is NOT evidence that the object is
     * reachable: it composes a URL from the disk configuration without asking the
     * storage backend anything. That is exactly how a "working" upload feature
     * ships against a private bucket and fails only in the browser.
     *
     * Kept for genuinely public assets on the `public` disk.
     *
     * @param string $path
     *
     * @return string
     */
    public static function getUrl(string $path): string
    {
        return self::disk()->url($path);
    }

    /**
     * Get the urls for the files at the given paths.
     *
     * @param array $paths
     *
     * @return array
     */
    public static function getUrls(array $paths): array
    {
        $urls = [];

        foreach ($paths as $path) {
            if (empty($path)) {
                continue;
            }

            $urls[] = self::getUrl($path);
        }

        return $urls;
    }

    /**
     * Get the absolute filesystem path for the file at the given path.
     *
     * LOCAL DISKS ONLY. An object store has no filesystem path, so this throws
     * rather than returning something that looks like a path and fails later in
     * whatever tries to open it. Anything reading file CONTENT should use get()
     * or readStream(), which work on every disk.
     *
     * @param string $path
     *
     * @return string
     * @throws RuntimeException when the configured disk is not a local filesystem
     */
    public static function getStoragePath(string $path): string
    {
        // Tests the DRIVER, not `method_exists($disk, 'path')`.
        //
        // That earlier check was dead code: `path()` is declared on the
        // Filesystem CONTRACT itself, so every disk has it, and the guard could
        // never fire. Worse, on an object store `path()` succeeds and returns a
        // bare object key — so the method quietly handed back a relative string
        // that whatever opened it resolved against the process working
        // directory. The guard's own docblock describes preventing exactly that.
        if (!self::isLocalDisk()) {
            throw new RuntimeException(
                'Disk [' . config('custom.storage.disk') . '] has no local filesystem path. '
                . 'Use FileUtil::get() or a temporary URL instead.'
            );
        }

        return self::disk()->path($path);
    }

    /**
     * Whether the configured storage disk is a local filesystem.
     *
     * @return bool
     */
    private static function isLocalDisk(): bool
    {
        return config('filesystems.disks.' . config('custom.storage.disk') . '.driver') === 'local';
    }

    /**
     * Get the contents of the file from the given path.
     *
     * @param string $path
     *
     * @return string
     */
    public static function get(string $path): string
    {
        return self::disk()->get($path);
    }

    /**
     * Zip files.
     *
     * @param array $paths
     * @param string $fileName
     *
     * @return string
     */
    public static function zipFiles(array $paths, string $fileName = ''): string
    {
        if (empty($paths)) {
            return '';
        }

        if (empty($fileName)) {
            $fileName = time() . '_' . Str::uuid() . '.zip';
        } else {
            if (!str_contains($fileName, '.zip')) {
                $fileName .= '.zip';
            }

            if (str_contains($fileName, '/')) {
                $directoryPath = Str::beforeLast($fileName, '/');

                if (self::directoryMissing($directoryPath)) {
                    self::makeDirectory($directoryPath);
                }
            }
        }

        $zip = new ZipArchive();
        $zipPath = self::getStoragePath($fileName);

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            foreach ($paths as $path) {
                // Get the file contents and add it to zip
                $stream = self::get($path);
                $zip->addFromString(basename($path), $stream);

                // Delete the file after adding to zip
                self::delete($path);
            }

            $zip->close();
        }

        return $fileName;
    }

}
