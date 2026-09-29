<?php

namespace Tests\Unit;

use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AppVersionResource;
use App\Models\AppVersion;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * A loaded relation must never be serialized raw: it would bypass the related model's own Resource,
 * and with it that Resource's choice of which fields to expose.
 */
class BaseResourceTest extends TestCase
{

    /**
     * A user carrying every field a Resource must never expose.
     *
     * @return User
     */
    private function userWithSecrets(): User
    {
        return (new User())->forceFill([
            'id' => 7,
            'first_name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'not-a-real-hash',
            'remember_token' => 'not-a-real-token'
        ]);
    }

    #[Test]
    public function aLoadedRelationIsNotSerializedUnlessTheResourceMapsIt(): void
    {
        $appVersion = (new AppVersion())->forceFill(['id' => 1, 'version' => '1.0.0']);
        $appVersion->setRelation('createdByUser', $this->userWithSecrets());

        $data = (new AppVersionResource($appVersion))->resolve(Request::create('/'));

        self::assertSame(1, $data['id']);
        self::assertSame('1.0.0', $data['version']);
        self::assertArrayNotHasKey('createdByUser', $data);
        self::assertArrayNotHasKey('created_by_user', $data);
        self::assertStringNotContainsString('not-a-real-hash', json_encode($data));
    }

    #[Test]
    public function aMappedRelationIsRenderedThroughItsOwnResource(): void
    {
        $activity = (new Activity())->forceFill(['id' => 3, 'log_name' => 'default', 'description' => 'Signed in']);
        $activity->setRelation('causer', $this->userWithSecrets());

        $data = json_decode(json_encode((new ActivityLogResource($activity))->resolve(Request::create('/'))), true);

        self::assertArrayNotHasKey('causer', $data);
        self::assertSame(7, $data['user']['id']);
        self::assertSame('ada@example.com', $data['user']['email']);
        self::assertArrayNotHasKey('password', $data['user']);
        self::assertArrayNotHasKey('rememberToken', $data['user']);
        self::assertStringNotContainsString('not-a-real-hash', json_encode($data));
    }

    #[Test]
    public function aCauserThatNoLongerResolvesRendersAsNull(): void
    {
        $activity = (new Activity())->forceFill(['id' => 4, 'log_name' => 'default', 'description' => 'Signed in']);
        $activity->setRelation('causer', null);

        $data = json_decode(json_encode((new ActivityLogResource($activity))->resolve(Request::create('/'))), true);

        self::assertArrayHasKey('user', $data);
        self::assertNull($data['user']);
    }

    #[Test]
    public function anUnloadedCauserIsLeftOut(): void
    {
        $activity = (new Activity())->forceFill(['id' => 5, 'log_name' => 'default', 'description' => 'Signed in']);

        $data = json_decode(json_encode((new ActivityLogResource($activity))->resolve(Request::create('/'))), true);

        self::assertArrayNotHasKey('user', $data);
    }

}
