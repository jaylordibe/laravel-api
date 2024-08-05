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
     *
     * @return ServiceResponseData
     */
    public static function success(string $message, mixed $data = null): ServiceResponseData
    {
        return new ServiceResponseData(
            success: true,
            message: $message,
            data: $data
        );
    }

    /**
     * Create an error service response.
     *
     * @param string $message
     * @param mixed $data
     *
     * @return ServiceResponseData
     */
    public static function error(string $message, mixed $data = null): ServiceResponseData
    {
        return new ServiceResponseData(
            error: true,
            message: $message,
            data: $data
        );
    }

    /**
     * Create mapped service response.
     *
     * @param mixed $data
     *
     * @return ServiceResponseData
     */
    public static function map(mixed $data = null): ServiceResponseData
    {
        return new ServiceResponseData(
            success: true,
            message: 'Success',
            data: $data
        );
    }

}
