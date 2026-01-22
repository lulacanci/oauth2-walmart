# Walmart Marketplace Provider for OAuth 2.0 Client

[![License](https://img.shields.io/packagist/l/lulacanci/oauth2-walmart)](https://github.com/lulacanci/oauth2-walmart/blob/main/LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/lulacanci/oauth2-walmart)](https://packagist.org/packages/lulacanci/oauth2-walmart)

This package provides [Walmart Marketplace][walmart] OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

[walmart]: https://developer.walmart.com/

This package is compliant with [PSR-1][], [PSR-2][] and [PSR-4][]. If you notice compliance oversights, please send a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## Features

- **Client Credentials Grant** - For sellers accessing their own Walmart Marketplace account
- **Authorization Code Grant** - For solution providers acting on behalf of sellers
- **Refresh Token Grant** - Automatically refresh expired access tokens
- **Multi-Marketplace Support** - US, Canada, and Mexico marketplaces

## Requirements

To use this package, you will need a Walmart client ID and client secret. These are referred to as `{walmart-client-id}` and `{walmart-client-secret}` in the documentation.

### For Sellers
Follow the [Get started as a seller][seller-setup] guide to create your API credentials.

### For Solution Providers
Follow the [Get started as a Solution Provider][sp-setup] guide to register your application.

[seller-setup]: https://developer.walmart.com/us-marketplace/docs/get-started-as-a-seller
[sp-setup]: https://developer.walmart.com/us-marketplace/docs/get-started-as-a-solution-provider

## Installation

To install, use composer:

```sh
composer require lulacanci/oauth2-walmart
```

## Usage

### Option 1: Client Credentials Grant (Sellers)

Use this when your application is accessing your own Walmart seller account only.

```php
require __DIR__ . '/vendor/autoload.php';

use Lulacanci\OAuth2\Client\Provider\Walmart;
use Lulacanci\OAuth2\Client\Provider\WalmartMarketplace;

$provider = new Walmart(
    [
        'clientId'     => '{walmart-client-id}',
        'clientSecret' => '{walmart-client-secret}',
    ],
    [],
    WalmartMarketplace::US // or CANADA, MEXICO
);

// Get access token using client credentials
$token = $provider->getAccessTokenWithClientCredentials();

echo 'Access Token: ' . $token->getToken() . "\n";
echo 'Expires in: ' . $token->getExpires() . " seconds\n";

// Use the token with Walmart APIs
// Include it in the WM_SEC.ACCESS_TOKEN header
```

### Option 2: Authorization Code Grant (Solution Providers)

Use this when your application acts on behalf of other sellers. The seller must authorize your app first.

```php
require __DIR__ . '/vendor/autoload.php';

use Lulacanci\OAuth2\Client\Provider\Walmart;
use Lulacanci\OAuth2\Client\Provider\WalmartMarketplace;

session_start();

$clientId = '{walmart-client-id}';
$clientSecret = '{walmart-client-secret}';
$redirectUri = 'https://example.com/callback-url';

$provider = new Walmart(
    [
        'clientId'     => $clientId,
        'clientSecret' => $clientSecret,
        'redirectUri'  => $redirectUri,
    ],
    [],
    WalmartMarketplace::US
);

if (empty($_GET['code'])) {
    // Step 1: Redirect to Walmart authorization URL
    // The seller will be prompted to log in and authorize your app
    
    $authorizationUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    
    header('Location: ' . $authorizationUrl);
    exit;
    
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    // State is invalid, possible CSRF attack in progress
    unset($_SESSION['oauth2state']);
    exit('Invalid state');
    
} else {
    // Step 2: Exchange authorization code for access token + refresh token
    
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code'],
        'redirect_uri' => $redirectUri,
    ]);

    // Store these tokens securely!
    $accessToken = $token->getToken();           // Valid for 15 minutes
    $refreshToken = $token->getRefreshToken();   // Valid for 1 year
    
    echo 'Access Token: ' . $accessToken . "\n";
    echo 'Refresh Token: ' . $refreshToken . "\n";
}
```

### Option 3: Refresh Token Grant

Access tokens expire after 15 minutes. Use the refresh token to get a new access token without requiring user interaction. Refresh tokens are valid for 1 year.

```php
require __DIR__ . '/vendor/autoload.php';

use Lulacanci\OAuth2\Client\Provider\Walmart;
use Lulacanci\OAuth2\Client\Provider\WalmartMarketplace;

$provider = new Walmart([
        'clientId'     => '{walmart-client-id}',
        'clientSecret' => '{walmart-client-secret}',
        'redirectUri'  => 'https://example.com/callback-url',
    ],
    [],
    WalmartMarketplace::US
);

// Use your stored refresh token
$storedRefreshToken = 'your-stored-refresh-token';

$newToken = $provider->getAccessToken('refresh_token', [
    'refresh_token' => $storedRefreshToken,
]);

$accessToken = $newToken->getToken();
// Store the new access token securely
```

## Multi-Marketplace Support

The package supports all Walmart marketplaces:

```php
use Lulacanci\OAuth2\Client\Provider\WalmartMarketplace;

// US Marketplace (default)
$provider = new Walmart($options, [], WalmartMarketplace::US);
// Sets clientType=seller

// Canada Marketplace
$provider = new Walmart($options, [], WalmartMarketplace::CANADA);
// Sets clientType=seller-ca

// Mexico Marketplace
$provider = new Walmart($options, [], WalmartMarketplace::MEXICO);
// Sets clientType=seller-mx
```

## Using the Access Token with Walmart APIs

Include the access token in the `WM_SEC.ACCESS_TOKEN` header for all Walmart Marketplace API calls:

```php
$client = new GuzzleHttp\Client();

$response = $client->get('https://marketplace.walmartapis.com/v3/items', [
    'headers' => [
        'WM_SEC.ACCESS_TOKEN' => $token->getToken(),
        'WM_SVC.NAME' => 'Walmart Marketplace',
        'WM_QOS.CORRELATION_ID' => uniqid(),
        'Accept' => 'application/json',
    ],
]);
```

## Scopes

[Scopes][scopes] can be set by using the `scope` parameter when generating the authorization URL:

```php
$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => ['items', 'orders', 'inventory'],
]);
```

See the [API scopes documentation][scopes] for available scopes.

[scopes]: https://developer.walmart.com/us-marketplace/docs/api-scope-walmart-marketplace

## Testing

Tests can be run with:

```sh
./vendor/bin/phpunit
```

Or with the watcher:

```sh
composer test
```

## Documentation

- [Walmart OAuth 2.0 Authorization](https://developer.walmart.com/us-marketplace/docs/oauth-20-authorization)
- [Get an Access Token](https://developer.walmart.com/us-marketplace/docs/get-an-access-token)
- [Log in and Authorize App Scope](https://developer.walmart.com/us-marketplace/docs/log-in-and-authorize-app-scope)
- [API Scopes for Walmart Marketplace](https://developer.walmart.com/us-marketplace/docs/api-scope-walmart-marketplace)

## Credits

* [Iulian Danaila](https://github.com/lulacanci)
* [All Contributors](https://github.com/lulacanci/oauth2-walmart/contributors)

## Sponsors

[Aureus POS - The Gold Standard Of Bullion & Collectibles Software](https://www.aureuspos.com/)

## License

The MIT License (MIT). Please see [License File](https://github.com/lulacanci/oauth2-walmart/blob/main/LICENSE) for more information.
