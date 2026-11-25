<?php

namespace BradTipper\RestfulFramework\Support;

use BradTipper\RestfulFramework\AppConfig;
use SilverStripe\Security\Member;

/** Subscription emails, driven by RevenueCat webhook events. Never throws. */
final class RevenueCatNotifications
{
    /**
     * Subscription lifecycle, driven by RevenueCat events.
     *
     * @param array<string, mixed> $event the RevenueCat webhook event
     * @return bool whether an email was queued for this event
     */
    public static function subscription(Member $member, array $event): bool
    {
        $name = (string) AppConfig::get('name');
        $type = (string) ($event['type'] ?? '');
        $manage = ['label' => 'Manage subscription', 'url' => Notifications::webUrl((string) AppConfig::get('subscription_path'))];
        $until = isset($event['expiration_at_ms']) ? Notifications::date(gmdate('c', intdiv((int) $event['expiration_at_ms'], 1000))) : null;

        switch ($type) {
            case 'INITIAL_PURCHASE':
                $trial = ($event['period_type'] ?? '') === 'TRIAL';
                $subject = $trial ? "Your {$name} free trial has started" : "Welcome to {$name} Pro";
                $paragraphs = $trial
                    ? ['Your free trial is active and everything in Pro is unlocked.'
                        . ($until ? " Your trial ends on {$until}; after that your subscription starts unless you cancel first." : '')]
                    : ['Thanks for subscribing. Everything in Pro is now unlocked.'];
                $content = ['heading' => $subject, 'paragraphs' => $paragraphs, 'button' => $manage];
                break;
            case 'UNCANCELLATION':
                $content = [
                    'heading' => 'Your subscription is back on',
                    'paragraphs' => ['Your subscription will renew as normal' . ($until ? " on {$until}" : '') . '. Nothing else to do.'],
                    'button' => $manage,
                ];
                $subject = 'Your subscription is back on';
                break;
            case 'CANCELLATION':
                if (($event['cancel_reason'] ?? '') === 'CUSTOMER_SUPPORT') {
                    $subject = 'Your purchase was refunded';
                    $content = [
                        'heading' => $subject,
                        'paragraphs' => ['Your purchase has been refunded and Pro access has ended.'],
                        'footnote' => 'The refund can take a few days to reach your account.',
                    ];
                    break;
                }
                $subject = 'Your subscription was cancelled';
                $content = [
                    'heading' => $subject,
                    'paragraphs' => [
                        'Your subscription will not renew.'
                        . ($until ? " You keep Pro until {$until}." : ' You keep Pro until the end of the current period.'),
                        'You can resubscribe at any time from the subscription screen.',
                    ],
                    'button' => $manage,
                ];
                break;
            case 'BILLING_ISSUE':
                $subject = 'We could not take your payment';
                $content = [
                    'heading' => $subject,
                    'paragraphs' => [
                        'The store could not renew your subscription. Please update your payment method so you do not lose Pro.',
                        'The store will retry for a few days before the subscription ends.',
                    ],
                    'button' => $manage,
                ];
                break;
            case 'EXPIRATION':
                $subject = 'Your subscription has ended';
                $content = [
                    'heading' => $subject,
                    'paragraphs' => [
                        "Pro access has ended and your account is back on the free plan. Your data is safe and still yours.",
                        'Resubscribe whenever you like to get everything back.',
                    ],
                    'button' => ['label' => 'Resubscribe', 'url' => Notifications::webUrl((string) AppConfig::get('subscription_path'))],
                ];
                break;
            default:
                return false;
        }
        Notifications::to($member, $subject, $content);
        return true;
    }
}
