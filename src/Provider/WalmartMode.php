<?php

namespace Lulacanci\OAuth2\Client\Provider;

/**
 * Walmart API Mode (Environment)
 *
 * SANDBOX: For testing with mock data (sandbox.walmartapis.com)
 * PRODUCTION: For live API calls (marketplace.walmartapis.com)
 *
 * @see https://developer.walmart.com/us-marketplace/docs/walmart-api-sandbox
 */
enum WalmartMode: string
{
    case SANDBOX = 'sandbox';
    case PRODUCTION = 'production';
}
