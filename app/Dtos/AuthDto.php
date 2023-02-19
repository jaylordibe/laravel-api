<?php

namespace App\Dtos;

class AuthDto
{

    private ?string $identifier = null;
    private ?string $password = null;
    private ?bool $remember = null;

    /**
     * @return string|null
     */
    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * @param string|null $identifier
     */
    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string|null $password
     */
    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    /**
     * @return bool|null
     */
    public function isRemember(): ?bool
    {
        return $this->remember;
    }

    /**
     * @param bool|null $remember
     */
    public function setRemember(?bool $remember): void
    {
        $this->remember = $remember;
    }
}
