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

    protected ?string $sellerId = null;

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
     * Build the authorization URL with Walmart-specific parameter handling.
     * Walmart requires redirectUri to NOT be URL-encoded.
     *
     * @param array $options
     * @return string
     */
    public function getAuthorizationUrl(array $options = [])
    {
        $this->state = $options['state'] ?? $this->getRandomState();
        $options['state'] = $this->state;

        $params = $this->getAuthorizationParameters($options);

        // Build URL without encoding redirectUri (Walmart requirement)
        $redirectUri = $params['redirectUri'];
        unset($params['redirectUri']);

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $url = $this->getBaseAuthorizationUrl() . '?' . $query . '&redirectUri=' . $redirectUri;

        return $url;
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

        // Only include scope if explicitly provided (scopes are configured in Walmart Developer Portal)
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
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => bin2hex(random_bytes(16)),
            'WM_SVC.VERSION' => '1.0.0',
            'WM_MARKET' => strtolower($this->marketplace->value),
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
     * Override to remove client_id and client_secret from the POST body.
     * Walmart expects credentials only in the Authorization header (Basic auth),
     * and rejects requests that include them in both places.
     *
     * @inheritdoc
     */
    protected function getAccessTokenRequest(array $params)
    {
        unset($params['client_id'], $params['client_secret']);
        
        // Walmart rejects redirect_uri on refresh token requests
        if (isset($params['grant_type']) && $params['grant_type'] === 'refresh_token') {
            unset($params['redirect_uri']);
        }
        
        $request = parent::getAccessTokenRequest($params);

        // Add Basic auth header — getAuthorizationHeaders() is not called during token exchange
        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);
        $request = $request->withHeader('Authorization', 'Basic ' . $credentials);
        if ($this->sellerId) {
            $request = $request->withHeader('WM_PARTNER.ID', $this->sellerId);
        }

        // Fix duplicate Content-Type
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        \Log::info('[Walmart OAuth] Full token request', [
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
            'body' => (string) $request->getBody(),
        ]);

        return $request;
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
            $message = $data['error_description']
                ?? $data['error']
                ?? $response->getReasonPhrase();

            if (is_array($message)) {
                $message = $message[0]['description'] ?? json_encode($message);
            }

            \Log::error('[Walmart OAuth] Token request failed', [
                'status' => $response->getStatusCode(),
                'message' => $message,
                'response_body' => $data,
                'response_headers' => $response->getHeaders(),
            ]);

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

    public function setSellerId(string $sellerId): void
    {
        $this->sellerId = $sellerId;
    }
}
