<?php
/**
 * Copy to stripe.config.php and add your Stripe test keys.
 * stripe.config.php is gitignored.
 *
 * Get keys: https://dashboard.stripe.com/test/apikeys
 * Webhook secret: Stripe CLI → stripe listen --forward-to localhost/stories/stripe-webhook.php
 *
 * Set demo_mode true to skip Stripe and unlock instantly (local dev only).
 */
return [
    'secret_key' => 'sk_test_PUT_YOUR_SECRET_KEY_HERE',
    'webhook_secret' => 'whsec_PUT_WEBHOOK_SECRET_HERE',
    'demo_mode' => true,
];
