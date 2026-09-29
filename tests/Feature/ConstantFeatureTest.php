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
        $this->actingAsSystemAdmin();
        $response = $this->get("{$this->resource}/activity-log-type");

        $response->assertOk();
    }

    #[Test]
    public function testGetAppPlatforms(): void
    {
        $this->actingAsSystemAdmin();
        $response = $this->get("{$this->resource}/app-platform");

        $response->assertOk();
    }

    #[Test]
    public function testGetDeviceOs(): void
    {
        $this->actingAsSystemAdmin();
        $response = $this->get("{$this->resource}/device-os");

        $response->assertOk();
    }

    #[Test]
    public function testGetDeviceTypes(): void
    {
        $this->actingAsSystemAdmin();
        $response = $this->get("{$this->resource}/device-type");

        $response->assertOk();
    }

    #[Test]
    public function testGetSpreadsheetReaderTypes(): void
    {
        $this->actingAsSystemAdmin();
        $response = $this->get("{$this->resource}/spreadsheet-reader-type");

        $response->assertOk();
    }

    #[Test]
    public function testGetUserRoles(): void
    {
        $this->actingAsSystemAdmin();
        $response = $this->get("{$this->resource}/user-role");

        $response->assertOk();
    }

}
