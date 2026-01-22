<?php

namespace Lulacanci\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class Walmart extends AbstractProvider
{
    /**
     * @var string
     */
    protected string $clientType = 'seller';

    /**
     * Authorization base URL
     */
    protected string $authorizationUrl = 'https://login.account.wal-mart.com/authorize';

    /**
     * Production Token API base URL
     */
    protected string $productionTokenUrl = 'https://marketplace.walmartapis.com/v3/token';

    /**
     * Sandbox Token API base URL
     */
    protected string $sandboxTokenUrl = 'https://sandbox.walmartapis.com/v3/token';

    /**
     * @param array $options
     * @param array $collaborators
     * @param WalmartMarketplace $marketplace The marketplace (US, CANADA, MEXICO)
     * @param WalmartMode $mode The environment mode (PRODUCTION or SANDBOX)
     */
    public function __construct(
        array $options = [],
        array $collaborators = [],
        public WalmartMarketplace $marketplace = WalmartMarketplace::US,
        public WalmartMode $mode = WalmartMode::PRODUCTION
    ) {
        parent::__construct($options, $collaborators);

        // Set client type based on marketplace
        $this->clientType = match ($this->marketplace) {
            WalmartMarketplace::US => 'seller',
            WalmartMarketplace::CANADA => 'seller-ca',
            WalmartMarketplace::MEXICO => 'seller-mx',
        };

        if (isset($options['clientType'])) {
            $this->clientType = $options['clientType'];
        }
    }

    /**
     * @inheritdoc
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->authorizationUrl;
    }

    /**
     * @inheritdoc
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return match ($this->mode) {
            WalmartMode::SANDBOX => $this->sandboxTokenUrl,
            WalmartMode::PRODUCTION => $this->productionTokenUrl,
        };
    }

    /**
     * @inheritdoc
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        // Walmart does not have a standard user info endpoint
        // This would need to be implemented based on specific API needs
        return '';
    }


    /**
     * @inheritdoc
     */
    protected function getDefaultScopes()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    protected function getScopeSeparator()
    {
        return ' ';
    }

    /**
     * Generate a random nonce for OAuth2 authorization
     *
     * @param int $length
     * @return string
     */
    protected function generateNonce(int $length = 10): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * @inheritdoc
     */
    protected function getAuthorizationParameters(array $options)
    {
        $options = parent::getAuthorizationParameters($options);

        // Walmart uses 'responseType' instead of 'response_type'
        // and 'clientId' instead of 'client_id'
        $params = [
            'responseType' => 'code',
            'clientId' => $this->clientId,
            'redirectUri' => $options['redirect_uri'],
            'clientType' => $this->clientType,
            'nonce' => $options['nonce'] ?? $this->generateNonce(),
            'state' => $options['state'],
        ];

        // Add scope if provided
        if (!empty($options['scope'])) {
            $params['scope'] = $options['scope'];
        }

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultHeaders()
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getAuthorizationHeaders($token = null)
    {
        // Walmart uses Basic auth with base64-encoded client_id:client_secret
        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

        return [
            'Authorization' => 'Basic ' . $credentials,
        ];
    }

    /**
     * Get access token using client credentials grant
     * Used for sellers accessing their own account
     *
     * @return AccessToken
     */
    public function getAccessTokenWithClientCredentials(): AccessToken
    {
        return $this->getAccessToken('client_credentials');
    }

    /**
     * @inheritdoc
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            $message = $data['error_description'] ?? $data['error'] ?? $response->getReasonPhrase();

            throw new IdentityProviderException(
                $message,
                $response->getStatusCode(),
                $response
            );
        }

        if (isset($data['error'])) {
            throw new IdentityProviderException(
                $data['error_description'] ?? $data['error'],
                $response->getStatusCode(),
                $response
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new WalmartResourceOwner($response);
    }

    /**
     * Get the client type for the current marketplace
     *
     * @return string
     */
    public function getClientType(): string
    {
        return $this->clientType;
    }

    /**
     * Get the marketplace
     *
     * @return WalmartMarketplace
     */
    public function getMarketplace(): WalmartMarketplace
    {
        return $this->marketplace;
    }

    /**
     * Get the current mode (SANDBOX or PRODUCTION)
     *
     * @return WalmartMode
     */
    public function getMode(): WalmartMode
    {
        return $this->mode;
    }

    /**
     * Check if the provider is in sandbox mode
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->mode === WalmartMode::SANDBOX;
    }
}
