<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConstantFeatureTest extends TestCase
{

    private string $resource = '/api/constants';

    #[Test]
    public function testGetActivityLogTypes(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}/activity-log-type");

        $response->assertOk();
    }

    #[Test]
    public function testGetAppPlatforms(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}/app-platform");

        $response->assertOk();
    }

    #[Test]
    public function testGetDeviceOs(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}/device-os");

        $response->assertOk();
    }

    #[Test]
    public function testGetDeviceTypes(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}/device-type");

        $response->assertOk();
    }

    #[Test]
    public function testGetSpreadsheetReaderTypes(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}/spreadsheet-reader-type");

        $response->assertOk();
    }

    #[Test]
    public function testGetUserRoles(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}/user-role");

        $response->assertOk();
    }

}
