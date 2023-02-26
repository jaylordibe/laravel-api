<?php

namespace App\Models\Custom;

class ServiceResponse
{

    private bool $success = false;
    private bool $error = false;
    private int $code = 0;
    private string $status = '';
    private string $message = '';
    private string $rawData = '';
    private $data = null;

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
        $this->error = !$success;
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
        $this->success = !$error;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @param int $code
     */
    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
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
     * @return string
     */
    public function getRawData(): string
    {
        return $this->rawData;
    }

    /**
     * @param string $rawData
     */
    public function setRawData(string $rawData): void
    {
        $this->rawData = $rawData;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
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
