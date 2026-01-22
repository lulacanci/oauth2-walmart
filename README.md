# Walmart Provider for OAuth 2.0 Client

[![License](https://img.shields.io/packagist/l/acip/oauth2-whatnot)](https://github.com/lulacanci/oauth2-walmart/blob/main/LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/acip/oauth2-whatnot)](https://packagist.org/packages/lulacanci/oauth2-walmart)

This package provides [Walmart][Walmart] OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

[whatnot]: https://walmart.com/

This package is compliant with [PSR-1][], [PSR-2][] and [PSR-4][]. If you notice compliance oversights, please send a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## Requirements

To use this package, it will be necessary to have a Walmart client ID and client secret. These are referred to as `{walmart-client-id}` and `{walmart-client-secret}` in the documentation.

Please follow the [Walmart instructions][oauth-setup] to create the required credentials.

[oauth-setup]: https://developers.whatnot.com/docs/getting-started/authentication

## Installation

To install, use composer:

```sh
composer require lulacanci/oauth2-walmart
```

## Usage

### Authorization Code Flow

```php
require __DIR__ . '/vendor/autoload.php';

use Lulacanci\OAuth2\Client\Provider\Walmart;
use Lulacanci\OAuth2\Client\Provider\WalmartMode;

session_start();
header('Content-Type: text/plain');

$clientId = '{walmart-client-id}';
$clientSecret = '{walmart-client-secret}';
$redirectUri = 'https://example.com/callback-url';

$provider = new Whatnot(
    [
        'clientId'     => $clientId,
        'clientSecret' => $clientSecret,
        'redirectUri'  => $redirectUri,
    ],
    mode: WalmartMode::STAGE // use WalmartMode::LIVE for production
);

if (empty($_GET['code'])) {
    // Step 1. redirect to the authorization URL
    $options = [
        // use the 'scope' key to specify the desired scopes
        // see https://developer.walmart.com/us-marketplace/docs/api-scope-walmart-marketplace
        'scope' => ['read:inventory', 'write:inventory'],
    ];

    $authorizationUrl = $provider->getAuthorizationUrl($options);
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authorizationUrl);
    exit;
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    // State is invalid, possible CSRF attack in progress
    unset($_SESSION['oauth2state']);
    exit('Invalid state');
} elseif (isset($_GET['code'])) {
    // Step 2. retrieve access and refresh tokens based on the authorization code

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code'],
        'client_id' => $clientId,
    ]);

    // store the token and the refresh token securely
    // $refreshToken = $token->getRefreshToken();
    // $accessToken = $token->getToken();

    // Optional: Now you have a token you can look up a users profile data
    try {
        // We got an access token, let's now get the user's details
        $resourceOwner = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $resourceOwner->getId());
    } catch (Exception $e) {
        // Failed to get user details
        exit('Oh dear... ' . $e->getMessage());
    }
}
```

### Refreshing a Token

It is important to note that the refresh token is invalidated when it is succefully used. You should securely store the refresh token when it is returned:

```php
require __DIR__ . '/vendor/autoload.php';

use Lulacanci\OAuth2\Client\Provider\Walmart;
use Lulacanci\OAuth2\Client\Provider\WalmartMode;

$provider = new Walmart([
        'clientId'     => '{walmart-client-id}',
        'clientSecret' => '{walmart-client-secret}',
        'redirectUri'  => 'https://example.com/callback-url',
    ],
    mode: WalmartMode::STAGE
);

$refreshToken = $token->getRefreshToken();
$grant = new \League\OAuth2\Client\Grant\RefreshToken();
$newToken = $provider->getAccessToken($grant, ['refresh_token' => $refreshToken]);

$refreshToken = $token->getRefreshToken();
$accessToken = $token->getToken();
// store the new access token and the refresh token securely
```

## Scopes

[Scopes][scopes] can be set by using the `scope` parameter when generating the authorization URL:

```php
$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => ['read:inventory', 'write:inventory'],
]);
```

[scopes]: https://developer.walmart.com/us-marketplace/docs/api-scope-walmart-marketplace

## Testing

Tests can be run with:

```sh
composer test
```

## Credits

* [Iulian Danaila](https://github.com/lulacanci)
* [All Contributors](https://github.com/lulacanci/oauth2-walmart/contributors)

## Sponsors
[Aureus POS - The Gold Standard Of Bullion & Collectibles Software](https://www.aureuspos.com/)


## License

The MIT License (MIT). Please see [License File](https://github.com/lulacanci/oauth2-walmart/blob/main/LICENSE) for more information.
