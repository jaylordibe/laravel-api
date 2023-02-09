<?php

namespace App\Models\Custom;

class ServiceResponse
{

    private bool $success = false;
    private bool $error = false;
    private string $message = '';
    private mixed $data = null;

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return $this->error;
    }

    /**
     * @param bool $error
     */
    public function setError(bool $error): void
    {
        $this->error = $error;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return mixed|null
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @param mixed|null $data
     */
    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * Create success response.
     * @param string $message
     * @param $data
     * @return ServiceResponse
     */
    public static function success(string $message, $data = null): ServiceResponse
    {
        $response = new ServiceResponse();
        $response->setSuccess(true);
        $response->setMessage($message);
        $response->setData($data);

        return $response;
    }

    /**
     * Create error response.
     * @param string $message
     * @param $data
     * @return ServiceResponse
     */
    public static function error(string $message, $data = null): ServiceResponse
    {
        $response = new ServiceResponse();
        $response->setError(true);
        $response->setMessage($message);
        $response->setData($data);

        return $response;
    }

    /**
     * Create mapped response.
     * @param $data
     * @return ServiceResponse
     */
    public static function map($data = null): ServiceResponse
    {
        $response = new ServiceResponse();
        $response->setSuccess(true);
        $response->setMessage('Success');
        $response->setData($data);

        return $response;
    }
}
