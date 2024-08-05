<?php

namespace App\Utils;

use App\Data\ServiceResponseData;

class ServiceResponseUtil
{

    /**
     * Create a success service response.
     *
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     *
     * @return ServiceResponseData
     */
    public static function success(string $message, mixed $data = null, int $statusCode = 200): ServiceResponseData
    {
        return new ServiceResponseData(
            success: true,
            message: $message,
            data: $data,
            statusCode: $statusCode
        );
    }

    /**
     * Create an error service response.
     *
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     *
     * @return ServiceResponseData
     */
    public static function error(string $message, mixed $data = null, int $statusCode = 400): ServiceResponseData
    {
        return new ServiceResponseData(
            success: false,
            message: $message,
            data: $data,
            statusCode: $statusCode
        );
    }

    /**
     * Create mapped service response.
     *
     * @param mixed $data
     * @param int $statusCode
     *
     * @return ServiceResponseData
     */
    public static function map(mixed $data = null, int $statusCode = 200): ServiceResponseData
    {
        return new ServiceResponseData(
            success: true,
            message: 'Success',
            data: $data,
            statusCode: $statusCode
        );
    }

}
