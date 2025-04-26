# Secure Credentials Management in KwetuPizza

This document explains how API credentials are securely stored and accessed in the KwetuPizza plugin.

## Overview

The KwetuPizza plugin integrates with several third-party services, including WhatsApp, Flutterwave payment gateway, and NextSMS. Instead of storing sensitive API credentials in plaintext in the WordPress database, this plugin encrypts all credentials using secure AES-256-CBC encryption.

## Encrypted Credentials

The following credentials are automatically encrypted:

- **WhatsApp API**
  - WhatsApp Token
  - WhatsApp Business Account ID
  - WhatsApp Phone ID (Legacy)
  - WhatsApp App Secret
  - WhatsApp Verify Token

- **Flutterwave**
  - Public Key
  - Secret Key
  - Encryption Key
  - Webhook Secret

- **NextSMS**
  - Username
  - Password
  - Sender ID

## How Encryption Works

1. The plugin uses standard PHP OpenSSL encryption functions
2. A secure random encryption key is generated and stored in WordPress options
3. Every credential is encrypted with AES-256-CBC encryption using a unique IV
4. The IV is stored with the encrypted data for decryption

## Accessing Credentials in Code

### Using the Secure Option Functions

When you need to access credentials in your code, use these functions instead of the standard WordPress `get_option()`:

```php
// Retrieving credentials
$token = kwetupizza_get_secure_option('kwetupizza_whatsapp_token');
$phone_id = kwetupizza_get_secure_option('kwetupizza_whatsapp_phone_id');

// Storing credentials
kwetupizza_update_secure_option('kwetupizza_flw_public_key', $new_key);
```

### Accessing Credentials via API

For external services or callbacks that need to access credentials, there's a secure API endpoint:

```
GET /wp-json/kwetupizza/v1/service-credentials/{service_name}
```

Parameters:
- `service_name`: One of `whatsapp`, `flutterwave`, or `nextsms`
- `callback_type`: Optional parameter to specify the context (defaults to `webhook`)

Headers:
- `X-Webhook-Token`: The webhook security token from your plugin settings

Example Response:
```json
{
  "token": "encrypted_token_value",
  "phone_id": "encrypted_phone_id",
  "verify_token": "encrypted_verify_token",
  "api_version": "v15.0"
}
```

## Migrating Existing Credentials

If you've been using the plugin before the encryption feature was added, you can encrypt your existing credentials:

1. Go to KwetuPizza Settings page
2. Scroll to the bottom to the "Security Migration" section
3. Click "Encrypt Existing Credentials" button

## Troubleshooting

If you're having issues with the encryption system:

1. Run the "Encryption Test" from the Settings page
2. Check that the encryption key is properly set
3. Verify that the OpenSSL extension is enabled on your server

## Security Recommendations

1. Keep your encryption key secure - don't share it or expose it
2. Keep your webhook security token secure when interacting with the API
3. Regularly update your credentials as a security best practice
4. Minimize the number of places where credentials are accessed in code 