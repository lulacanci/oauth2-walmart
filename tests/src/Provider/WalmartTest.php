<?php

namespace Lulacanci\OAuth2\Client\Tests\Provider;

use Lulacanci\OAuth2\Client\Provider\Walmart;
use Lulacanci\OAuth2\Client\Provider\WalmartMarketplace;
use Lulacanci\OAuth2\Client\Provider\WalmartMode;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class WalmartTest extends TestCase
{
    use QueryBuilderTrait;

    protected Walmart $provider;

    protected function setUp(): void
    {
        $this->provider = new Walmart(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'https://example.com/callback',
            ]
        );
    }

    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('clientId', $query);
        $this->assertArrayHasKey('redirectUri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('responseType', $query);
        $this->assertArrayHasKey('clientType', $query);
        $this->assertArrayHasKey('nonce', $query);
        $this->assertEquals('code', $query['responseType']);
        $this->assertEquals('seller', $query['clientType']);
        $this->assertNotNull($this->provider->getState());
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertEquals('login.account.wal-mart.com', $uri['host']);
        $this->assertEquals('/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];
        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('marketplace.walmartapis.com', $uri['host']);
        $this->assertEquals('/v3/token', $uri['path']);
    }

    public function testDefaultMarketplaceIsUS(): void
    {
        $this->assertEquals(WalmartMarketplace::US, $this->provider->getMarketplace());
        $this->assertEquals('seller', $this->provider->getClientType());
    }

    public function testDefaultModeIsProduction(): void
    {
        $this->assertEquals(WalmartMode::PRODUCTION, $this->provider->getMode());
        $this->assertFalse($this->provider->isSandbox());
    }

    public function testSandboxMode(): void
    {
        $provider = new Walmart(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'https://example.com/callback',
            ],
            [],
            WalmartMarketplace::US,
            WalmartMode::SANDBOX
        );

        $this->assertEquals(WalmartMode::SANDBOX, $provider->getMode());
        $this->assertTrue($provider->isSandbox());

        $url = $provider->getBaseAccessTokenUrl([]);
        $uri = parse_url($url);

        $this->assertEquals('sandbox.walmartapis.com', $uri['host']);
        $this->assertEquals('/v3/token', $uri['path']);
    }

    public function testCanadaMarketplace(): void
    {
        $provider = new Walmart(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'https://example.com/callback',
            ],
            [],
            WalmartMarketplace::CANADA
        );

        $this->assertEquals(WalmartMarketplace::CANADA, $provider->getMarketplace());
        $this->assertEquals('seller-ca', $provider->getClientType());

        $url = $provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertEquals('seller-ca', $query['clientType']);
    }

    public function testMexicoMarketplace(): void
    {
        $provider = new Walmart(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'https://example.com/callback',
            ],
            [],
            WalmartMarketplace::MEXICO
        );

        $this->assertEquals(WalmartMarketplace::MEXICO, $provider->getMarketplace());
        $this->assertEquals('seller-mx', $provider->getClientType());

        $url = $provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertEquals('seller-mx', $query['clientType']);
    }

    public function testCustomClientType(): void
    {
        $provider = new Walmart(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'https://example.com/callback',
                'clientType' => 'custom-type',
            ]
        );

        $this->assertEquals('custom-type', $provider->getClientType());
    }

    public function testCanadaSandboxMode(): void
    {
        $provider = new Walmart(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'https://example.com/callback',
            ],
            [],
            WalmartMarketplace::CANADA,
            WalmartMode::SANDBOX
        );

        $this->assertEquals(WalmartMarketplace::CANADA, $provider->getMarketplace());
        $this->assertEquals(WalmartMode::SANDBOX, $provider->getMode());
        $this->assertEquals('seller-ca', $provider->getClientType());
        $this->assertTrue($provider->isSandbox());

        $url = $provider->getBaseAccessTokenUrl([]);
        $this->assertStringContainsString('sandbox.walmartapis.com', $url);
    }
}
