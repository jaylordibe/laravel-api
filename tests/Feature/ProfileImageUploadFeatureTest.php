<?php

namespace Tests\Feature;

use App\Models\User;
use App\Utils\FileUtil;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The profile image upload used to be the least defended path in the template:
 * a GenericRequest with no validation rules at all, writing the file to a
 * world-readable disk and persisting a permanent public URL in the database.
 *
 * These tests pin each part of the fix. They are worth keeping verbatim in a fork
 * — an upload endpoint is where "it works" and "it is safe" diverge most quietly.
 */
class ProfileImageUploadFeatureTest extends TestCase
{

    private string $token;

    private string $disk;

    private ?string $originalProfileImage = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = config('custom.storage.disk');
        Storage::fake($this->disk);

        $this->token = $this->loginSystemAdminUser();

        // The suite runs against a live shared database with no RefreshDatabase
        // trait, so these tests permanently mutated the seeded system-admin row.
        // Snapshot and restore it rather than leaving state for the next run.
        $this->originalProfileImage = User::query()
            ->where('email', config('custom.sysad_email'))
            ->value('profile_image');
    }

    protected function tearDown(): void
    {
        User::query()
            ->where('email', config('custom.sysad_email'))
            ->update(['profile_image' => $this->originalProfileImage]);

        parent::tearDown();
    }

    #[Test]
    public function itRejectsARequestWithNoFile(): void
    {
        // Previously a hand-rolled `if (empty($file))` branch in the controller.
        // Now it is a validation rule, so it returns the same shape as every
        // other invalid request.
        $this->withToken($this->token)
            ->post('/api/users/auth/profile-image', [], ['Accept' => 'application/json'])
            ->assertStatus(400);
    }

    #[Test]
    public function itRejectsANonImageFile(): void
    {
        // The headline case: a PHP script with an image extension and an image
        // MIME type. The `image` rule inspects the CONTENT, so the
        // client-supplied metadata does not get it through.
        $file = UploadedFile::fake()->createWithContent(
            'avatar.jpg',
            '<?php echo shell_exec($_GET["c"]); ?>'
        );

        $this->withToken($this->token)
            ->post('/api/users/auth/profile-image', ['profileImage' => $file], ['Accept' => 'application/json'])
            ->assertStatus(400);

        self::assertEmpty(Storage::disk($this->disk)->allFiles());
    }

    #[Test]
    public function itRejectsAnSvgUpload(): void
    {
        // SVG is XML and can carry script. A browser rendering one served from
        // your origin executes it, so it is excluded from the allowlist even
        // though it is legitimately "an image".
        $file = UploadedFile::fake()->createWithContent(
            'avatar.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->withToken($this->token)
            ->post('/api/users/auth/profile-image', ['profileImage' => $file], ['Accept' => 'application/json'])
            ->assertStatus(400);
    }

    #[Test]
    public function itRejectsAFileOverTheConfiguredSizeLimit(): void
    {
        $maxKilobytes = (int) config('custom.storage.max_image_upload_kilobytes');
        $file = UploadedFile::fake()->image('huge.jpg')->size($maxKilobytes + 1);

        $this->withToken($this->token)
            ->post('/api/users/auth/profile-image', ['profileImage' => $file], ['Accept' => 'application/json'])
            ->assertStatus(400);
    }

    #[Test]
    public function itRequiresAuthentication(): void
    {
        $this->post(
            '/api/users/auth/profile-image',
            ['profileImage' => UploadedFile::fake()->image('avatar.jpg')],
            ['Accept' => 'application/json']
        )->assertStatus(401);
    }

    #[Test]
    public function itStoresAValidImageUnderAServerGeneratedKey(): void
    {
        $userId = $this->getAuthUser($this->token)->id;

        $this->withToken($this->token)
            ->post(
                '/api/users/auth/profile-image',
                ['profileImage' => UploadedFile::fake()->image('avatar.jpg', 200, 200)],
                ['Accept' => 'application/json']
            )
            ->assertOk();

        $storedPath = User::query()->findOrFail($userId)->profile_image;

        // A KEY, not a URL. Storing a URL pinned the object to one provider, one
        // bucket and permanent public readability.
        self::assertFalse(
            Str::startsWith($storedPath, ['http://', 'https://']),
            'profile_image must hold a storage key, not a URL.'
        );

        // Scoped by user id, so one user's objects cannot land in another's
        // prefix, and named by the framework rather than by the client.
        self::assertTrue(Str::startsWith($storedPath, "profile-images/{$userId}/"));
        self::assertStringNotContainsString('avatar', $storedPath);

        Storage::disk($this->disk)->assertExists($storedPath);
    }

    #[Test]
    public function itNeverUsesTheClientFilenameInTheStoredKey(): void
    {
        // Traversal in the client filename is the classic way to write outside
        // the intended prefix, or to overwrite somebody else's object.
        $userId = $this->getAuthUser($this->token)->id;

        $this->withToken($this->token)
            ->post(
                '/api/users/auth/profile-image',
                ['profileImage' => UploadedFile::fake()->image('../../../etc/passwd.jpg', 50, 50)],
                ['Accept' => 'application/json']
            )
            ->assertOk();

        $storedPath = User::query()->findOrFail($userId)->profile_image;

        self::assertStringNotContainsString('..', $storedPath);
        self::assertStringNotContainsString('passwd', $storedPath);
        self::assertTrue(Str::startsWith($storedPath, "profile-images/{$userId}/"));
    }

    #[Test]
    public function theResponseExposesAUrlRatherThanTheStorageKey(): void
    {
        // The stored value is private; what a client receives is a signed,
        // expiring URL generated per response.
        $response = $this->withToken($this->token)
            ->post(
                '/api/users/auth/profile-image',
                ['profileImage' => UploadedFile::fake()->image('avatar.jpg', 120, 120)],
                ['Accept' => 'application/json']
            )
            ->assertOk();

        $profileImage = $response->json('profileImage');

        self::assertIsString($profileImage);
        self::assertTrue(
            Str::startsWith($profileImage, ['http://', 'https://']),
            'The API must return a resolvable URL, not the raw object key.'
        );
    }

    #[Test]
    public function theUrlIsSignedAndExpiringRatherThanAPlainObjectPath(): void
    {
        // Storage::fake() installs its own temporaryUrl stub, so asserting on a
        // faked disk tests the stub, not the signing contract. This exercises the
        // REAL local disk, whose `serve` flag makes temporaryUrl issue a Laravel
        // signed route — the same code path an object store takes.
        // setUp() faked this disk, and Storage::fake installs its own
        // temporaryUrl stub — asserting against it would test the stub. Drop the
        // fake so the REAL local disk (serve => true) issues a signed route.
        Storage::forgetDisk('local');

        $path = 'profile-images/999/signing-check.txt';
        Storage::disk('local')->put($path, 'x');

        try {
            $url = FileUtil::generatePublicUrl($path);

            self::assertStringContainsString('signature=', $url, 'A temporary URL must carry a signature.');
            self::assertStringContainsString('expires=', $url, 'A temporary URL must carry an expiry.');
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    #[Test]
    public function everyApplicationDiskStoresObjectsPrivately(): void
    {
        // The regression guard for the original defect: the disks were configured
        // with 'visibility' => 'public', which no amount of application-level
        // authorization can compensate for.
        foreach (config('filesystems.disks') as $name => $disk) {
            if ($name === 'public') {
                // Deliberately public: local-only, for genuinely public assets.
                continue;
            }

            self::assertNotSame(
                'public',
                $disk['visibility'] ?? 'private',
                "Disk [{$name}] must not store objects with public visibility."
            );
        }
    }

}
