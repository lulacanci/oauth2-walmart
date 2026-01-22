<?php

/**
 * Walmart OAuth2 Example
 *
 * This example demonstrates how to use the Walmart OAuth2 provider
 * for both sellers and solution providers.
 *
 * @see https://developer.walmart.com/us-marketplace/docs/oauth-20-authorization
 * @see https://developer.walmart.com/us-marketplace/docs/get-an-access-token
 * @see https://developer.walmart.com/us-marketplace/docs/walmart-api-sandbox
 */

require __DIR__ . '/../vendor/autoload.php';

use Lulacanci\OAuth2\Client\Provider\Walmart;
use Lulacanci\OAuth2\Client\Provider\WalmartMarketplace;
use Lulacanci\OAuth2\Client\Provider\WalmartMode;

session_start();
header('Content-Type: text/plain');

// Your Walmart API credentials from Seller Center / Developer Portal
$clientId = '{walmart-client-id}';
$clientSecret = '{walmart-client-secret}';
$redirectUri = 'https://example.com/callback';

// Choose marketplace: US, CANADA, or MEXICO
$marketplace = WalmartMarketplace::US;

// Choose mode: SANDBOX for testing, PRODUCTION for live
// Sandbox uses: sandbox.walmartapis.com
// Production uses: marketplace.walmartapis.com
$mode = WalmartMode::PRODUCTION; // or WalmartMode::SANDBOX for testing

$provider = new Walmart(
    [
        'clientId'     => $clientId,
        'clientSecret' => $clientSecret,
        'redirectUri'  => $redirectUri,
    ],
    [],
    $marketplace,
    $mode
);

// ============================================================================
// OPTION 1: Client Credentials Grant (for Sellers accessing their own account)
// ============================================================================
// Use this when your app is only accessing your own Walmart seller account
// and not acting on behalf of any other seller.
//
// $token = $provider->getAccessTokenWithClientCredentials();
// echo 'Access Token: ' . $token->getToken() . "\n";
// echo 'Expires in: ' . $token->getExpires() . " seconds\n";

// ============================================================================
// OPTION 2: Authorization Code Grant (for Solution Providers)
// ============================================================================
// Use this when acting on behalf of another seller. The seller must authorize
// your app first.

if (empty($_GET['code'])) {
    // Step 1: Redirect to the Walmart authorization URL
    // The seller will be prompted to log in and authorize your app

    $authorizationUrl = $provider->getAuthorizationUrl();

    // Store state for CSRF protection
    $_SESSION['oauth2state'] = $provider->getState();

    echo "Redirecting to Walmart for authorization...\n";
    echo "Authorization URL: " . $authorizationUrl . "\n";

    // In production, uncomment the following:
    // header('Location: ' . $authorizationUrl);
    // exit;

} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    // State is invalid, possible CSRF attack
    unset($_SESSION['oauth2state']);
    exit('Invalid state - possible CSRF attack');

} else {
    // Step 2: Exchange authorization code for access token + refresh token

    try {
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
            'redirect_uri' => $redirectUri,
        ]);

        echo "Access Token: " . $token->getToken() . "\n";
        echo "Refresh Token: " . $token->getRefreshToken() . "\n";
        echo "Expires: " . date('Y-m-d H:i:s', $token->getExpires()) . "\n";

        // Store these tokens securely for future API calls
        // The access token is valid for 15 minutes (900 seconds)
        // The refresh token is valid for 1 year (365 days)

    } catch (Exception $e) {
        exit('Error getting access token: ' . $e->getMessage());
    }
}

// ============================================================================
// OPTION 3: Refresh Token Grant
// ============================================================================
// Use this to get a new access token when the current one expires.
// You must have a valid refresh token from a previous authorization code grant.
//
// $storedRefreshToken = 'your-stored-refresh-token';
// $newToken = $provider->getAccessToken('refresh_token', [
//     'refresh_token' => $storedRefreshToken,
// ]);
// echo 'New Access Token: ' . $newToken->getToken() . "\n";

// ============================================================================
// Using the Access Token with Walmart APIs
// ============================================================================
// Include the access token in the WM_SEC.ACCESS_TOKEN header for API calls:
//
// $client = new GuzzleHttp\Client();
// $response = $client->get('https://marketplace.walmartapis.com/v3/items', [
//     'headers' => [
//         'WM_SEC.ACCESS_TOKEN' => $token->getToken(),
//         'WM_SVC.NAME' => 'Walmart Marketplace',
//         'WM_QOS.CORRELATION_ID' => uniqid(),
//         'Accept' => 'application/json',
//     ],
// ]);
