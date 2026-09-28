<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RootRouteFeatureTest extends TestCase
{

    #[Test]
    public function itRedirectsToTheConfiguredFrontend(): void
    {
        config()->set('custom.app_frontend_url', 'https://app.example.test');

        $this->get('/')->assertRedirect('https://app.example.test');
    }

    #[Test]
    public function itAnswersNotFoundWhenNoFrontendIsConfigured(): void
    {
        config()->set('custom.app_frontend_url', null);

        $this->get('/')->assertNotFound();
    }

    #[Test]
    public function itAnswersNotFoundWhenTheFrontendUrlIsEmpty(): void
    {
        // `APP_FRONTEND_URL=` in an env file resolves to an empty string, not null.
        config()->set('custom.app_frontend_url', '');

        $this->get('/')->assertNotFound();
    }

}
