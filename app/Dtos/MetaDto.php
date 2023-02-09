<?php

namespace App\Dtos;

class MetaDto
{

    private ?string $searchQuery = null; // The search query/filter/key that will be used for searching in the database
    private ?array $relations = null; // The database relations that will be used for database query
    private ?string $sortField = null; // The field name that will be used for sorting
    private ?string $sortDirection = null; // The sort direction that will be used for sorting
    private ?int $page = null; // The current requested page that will be used for pagination
    private ?int $limit = null; // The current requested limit that will be used for pagination
    private ?int $offset = null; // The current requested offset that will be used for pagination
    private ?string $requestIp = null; // The ip address which the request is coming from

    /**
     * @return string|null
     */
    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }

    /**
     * @param string|null $searchQuery
     */
    public function setSearchQuery(?string $searchQuery): void
    {
        $this->searchQuery = $searchQuery;
    }

    /**
     * @return array|null
     */
    public function getRelations(): ?array
    {
        return $this->relations;
    }

    /**
     * @param array|null $relations
     */
    public function setRelations(?array $relations): void
    {
        $this->relations = $relations;
    }

    /**
     * @return string|null
     */
    public function getSortField(): ?string
    {
        return $this->sortField;
    }

    /**
     * @param string|null $sortField
     */
    public function setSortField(?string $sortField): void
    {
        $this->sortField = $sortField;
    }

    /**
     * @return string|null
     */
    public function getSortDirection(): ?string
    {
        return $this->sortDirection;
    }

    /**
     * @param string|null $sortDirection
     */
    public function setSortDirection(?string $sortDirection): void
    {
        $this->sortDirection = $sortDirection;
    }

    /**
     * @return int|null
     */
    public function getPage(): ?int
    {
        return $this->page;
    }

    /**
     * @param int|null $page
     */
    public function setPage(?int $page): void
    {
        $this->page = $page;
    }

    /**
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * @param int|null $limit
     */
    public function setLimit(?int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return int|null
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @param int|null $offset
     */
    public function setOffset(?int $offset): void
    {
        $this->offset = $offset;
    }

    /**
     * @return string|null
     */
    public function getRequestIp(): ?string
    {
        return $this->requestIp;
    }

    /**
     * @param string|null $requestIp
     */
    public function setRequestIp(?string $requestIp): void
    {
        $this->requestIp = $requestIp;
    }
}
