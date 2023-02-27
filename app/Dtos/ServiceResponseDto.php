<?php

namespace App\Dtos;

class ServiceResponseDto
{

    private bool $success = false;
    private bool $error = false;
    private int $statusCode = 0;
    private string $message = '';
    private array|object|null $data = null;

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
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     */
    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
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
     * @return array|object|null
     */
    public function getData(): object|array|null
    {
        return $this->data;
    }

    /**
     * @param array|object|null $data
     */
    public function setData(object|array|null $data): void
    {
        $this->data = $data;
    }

}
