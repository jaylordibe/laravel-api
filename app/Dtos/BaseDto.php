<?php

namespace App\Dtos;

use App\Models\User;
use Illuminate\Support\Carbon;

class BaseDto
{

    public function __construct()
    {
    }

    private ?int $id = null;
    private ?Carbon $createdAt = null;
    private ?Carbon $updatedAt = null;
    private ?Carbon $deletedAt = null;
    private ?int $createdBy = null;
    private ?int $updatedBy = null;
    private ?int $deletedBy = null;
    private ?MetaDto $meta = null;
    private ?User $authUser = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return Carbon|null
     */
    public function getCreatedAt(): ?Carbon
    {
        return $this->createdAt;
    }

    /**
     * @param Carbon|null $createdAt
     */
    public function setCreatedAt(?Carbon $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return Carbon|null
     */
    public function getUpdatedAt(): ?Carbon
    {
        return $this->updatedAt;
    }

    /**
     * @param Carbon|null $updatedAt
     */
    public function setUpdatedAt(?Carbon $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return Carbon|null
     */
    public function getDeletedAt(): ?Carbon
    {
        return $this->deletedAt;
    }

    /**
     * @param Carbon|null $deletedAt
     */
    public function setDeletedAt(?Carbon $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return int|null
     */
    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    /**
     * @param int|null $createdBy
     */
    public function setCreatedBy(?int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return int|null
     */
    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    /**
     * @param int|null $updatedBy
     */
    public function setUpdatedBy(?int $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    /**
     * @return int|null
     */
    public function getDeletedBy(): ?int
    {
        return $this->deletedBy;
    }

    /**
     * @param int|null $deletedBy
     */
    public function setDeletedBy(?int $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }

    /**
     * @return MetaDto|null
     */
    public function getMeta(): ?MetaDto
    {
        return $this->meta;
    }

    /**
     * @param MetaDto|null $meta
     */
    public function setMeta(?MetaDto $meta): void
    {
        $this->meta = $meta;
    }

    /**
     * @return User|null
     */
    public function getAuthUser(): ?User
    {
        return $this->authUser;
    }

    /**
     * @param User|null $authUser
     */
    public function setAuthUser(?User $authUser): void
    {
        $this->authUser = $authUser;
    }

    /**
     * Check if the DTO is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->getId());
    }

    /**
     * Check if the DTO is present.
     *
     * @return bool
     */
    public function isPresent(): bool
    {
        return !$this->isEmpty();
    }

}
