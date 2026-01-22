<?php

namespace Lulacanci\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class WalmartResourceOwner implements ResourceOwnerInterface
{
    /**
     * @var array
     */
    protected array $response;

    /**
     * @param array $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->response['sellerId'] ?? $this->response['id'] ?? null;
    }

    /**
     * Get the seller ID
     *
     * @return string|null
     */
    public function getSellerId(): ?string
    {
        return $this->response['sellerId'] ?? null;
    }

    /**
     * Get the partner ID (for solution providers)
     *
     * @return string|null
     */
    public function getPartnerId(): ?string
    {
        return $this->response['partnerId'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        return $this->response;
    }
}
