<?php

namespace App\Utils;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResponseUtil
{

    /**
     * Returns json data with status code.
     * If status code is not provided, default status code is 200(OK).
     * @param object|array|string|null $response
     * @param int $status
     * @return JsonResponse
     */
    public static function json(object|array|string|null $response, int $status = SymfonyResponse::HTTP_OK): JsonResponse
    {
        return response()->json($response, $status);
    }

    /**
     * Returns 200(OK) status code.
     * Can be used for webhook response.
     * @return JsonResponse
     */
    public static function ok(): JsonResponse
    {
        return response()->json([], SymfonyResponse::HTTP_OK);
    }

    /**
     * Returns an empty object with 200(OK) status code.
     * @return JsonResponse
     */
    public static function empty(): JsonResponse
    {
        return response()->json(null, SymfonyResponse::HTTP_OK);
    }

    /**
     * Returns an empty object or resource with 200(OK) status code.
     * @param string $resource
     * @param object|null $data
     * @return JsonResponse|JsonResource
     */
    public static function resource(string $resource, ?object $data): JsonResponse|JsonResource
    {
        $response = self::empty();

        if ($data instanceof Model && $data->isPresent()) {
            $response = new $resource($data);
        } elseif ($data instanceof LengthAwarePaginator || $data instanceof Collection) {
            $response = $resource::collection($data);
        }

        return $response;
    }

    /**
     * Returns a success message with 200(OK) status code.
     * @param string $message
     * @return JsonResponse
     */
    public static function success(string $message): JsonResponse
    {
        return response()->json(['success' => $message], SymfonyResponse::HTTP_OK);
    }

    /**
     * Returns an error message with 400(Bad Request) status code.
     * @param string $message
     * @return JsonResponse
     */
    public static function error(string $message): JsonResponse
    {
        return response()->json(['error' => $message], SymfonyResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Returns an error message with 401(Unauthorized) status code.
     * @param string $message
     * @return JsonResponse
     */
    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return response()->json(['error' => $message], SymfonyResponse::HTTP_UNAUTHORIZED);
    }

    /**
     * Returns an error message with 401(Unauthorized) status code.
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(string $message = 'The requested resource could not be found'): JsonResponse
    {
        return response()->json(['error' => $message], SymfonyResponse::HTTP_NOT_FOUND);
    }
}
