<?php

namespace App\Utils;

use App\Dtos\ServiceResponseDto;

class ServiceResponseUtil
{

    /**
     * Create a success service response.
     *
     * @param string $message
     * @param object|array|null $data
     *
     * @return ServiceResponseDto
     */
    public static function success(string $message, object|array|null $data = null): ServiceResponseDto
    {
        $response = new ServiceResponseDto();
        $response->setSuccess(true);
        $response->setMessage($message);
        $response->setData($data);

        return $response;
    }

    /**
     * Create an error service response.
     *
     * @param string $message
     * @param object|array|null $data
     *
     * @return ServiceResponseDto
     */
    public static function error(string $message, object|array|null $data = null): ServiceResponseDto
    {
        $response = new ServiceResponseDto();
        $response->setError(true);
        $response->setMessage($message);
        $response->setData($data);

        return $response;
    }

    /**
     * Create mapped service response.
     *
     * @param object|array|null $data
     *
     * @return ServiceResponseDto
     */
    public static function map(object|array|null $data = null): ServiceResponseDto
    {
        $response = new ServiceResponseDto();
        $response->setSuccess(true);
        $response->setMessage('Success');
        $response->setData($data);

        return $response;
    }

}
