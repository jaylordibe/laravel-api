<?php

namespace App\Utils;

use App\Data\ServiceResponseData;

class ServiceResponseUtil
{

    /**
     * Create a success service response.
     *
     * @param string $message
     * @param object|array|null $data
     *
     * @return ServiceResponseData
     */
    public static function success(string $message, object|array|null $data = null): ServiceResponseData
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
     * @param object|array|null $data
     *
     * @return ServiceResponseData
     */
    public static function error(string $message, object|array|null $data = null): ServiceResponseData
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
     * @param object|array|null $data
     *
     * @return ServiceResponseData
     */
    public static function map(object|array|null $data = null): ServiceResponseData
    {
        return new ServiceResponseData(
            success: true,
            message: 'Success',
            data: $data
        );
    }

}
