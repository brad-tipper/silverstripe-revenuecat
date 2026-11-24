# silverstripe-revenuecat

RevenueCat subscriptions for apps built on `silverstripe-restful-framework`.

- `POST /api/v1/webhooks/revenuecat` receives RevenueCat events (`Authorization: Bearer <secret>`), keeps a log of them (`RevenueCatEvent`) and updates the member's subscription tier and status.
- Subscription emails: trial started, welcome to Pro, cancelled, refunded, billing issue, expired, resubscribed.
- A "Subscription events" tab on each user in Security, and the events in the account data export.
- When an account is deleted, its RevenueCat customer is deleted first. A failure leaves the account queued for the next run.

## Install

```sh
composer require bradtipper/silverstripe-revenuecat
```

## Configure

Secrets and settings are read from the framework's `AppConfig`:

```yaml
BradTipper\AppBackend\AppConfig:
  revenuecat_enabled: true
  revenuecat_webhook_secret: '...'     # the Authorization bearer RevenueCat sends
  revenuecat_v2_secret_key: '...'      # for deleting customers
  revenuecat_project_id: '...'
  subscription_path: '/settings/subscription'   # link used in emails

BradTipper\AppBackend\Account\RevenueCatWebhookController:
  environment: PRODUCTION
  entitlement_ids: [pro]
  product_ids: []
```

The subscription fields themselves (tier, status) live on the member in the framework; this module is the only writer.

If `silverstripe-service-desk` is installed, the events also appear under Support in the CMS.

## Licence

Proprietary.
