<?php

namespace App\Utils;

use App\Models\DeviceToken;
use Google\Client;
use Google\Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PushNotificationUtil
{

    /**
     * Get the URL for sending push notification.
     *
     * @return string
     */
    private static function getUrl(): string
    {
        $firebaseProjectId = config('custom.firebase_project_id');

        return "https://fcm.googleapis.com/v1/projects/{$firebaseProjectId}/messages:send";
    }

    /**
     * Get the access token for sending push notification.
     *
     * @return string
     * @throws Exception
     */
    private static function getAccessToken(): string
    {
        $client = new Client();
        $client->setAuthConfig(config('custom.firebase_project_service_account_file'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->useApplicationDefaultCredentials();
        $token = $client->fetchAccessTokenWithAssertion();

        return $token['access_token'];
    }

    /**
     * Send push notification to user.
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     *
     * @throws Exception
     * @throws ConnectionException
     */
    public static function send(int $userId, string $title, string $body): void
    {
        $deviceTokens = DeviceToken::where('user_id', $userId)->get();
        $url = self::getUrl();
        $accessToken = self::getAccessToken();

        $deviceTokens->each(function (DeviceToken $deviceToken) use ($url, $accessToken, $title, $body) {
            Http::withToken($accessToken)
                ->asJson()
                ->post($url, [
                    'message' => [
                        'token' => $deviceToken->token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body
                        ]
                    ]
                ]);
        });
    }

}
