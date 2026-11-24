<?php

namespace BradTipper\AppBackend\Admin;

use BradTipper\AppBackend\AppConfig;
use BradTipper\AppBackend\Auth\ApiMemberExtension;
use BradTipper\AppBackend\Model\RevenueCatEvent;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Security\Member;

/**
 * What the RevenueCat module adds to a Member: a "Subscription events" tab in the CMS, the events in the account data
 * export, and removal of the RevenueCat customer when the account is deleted (a failure leaves the account queued
 * for the next run, so a paying customer is never orphaned).
 *
 * @extends Extension<Member>
 */
class MemberBillingExtension extends Extension
{
    public function updateCMSFields(FieldList $fields): void
    {
        $member = $this->owner;
        if (!$member->isInDB() || !$member->UUID) {
            return;
        }
        $fields->addFieldToTab('Root.SubscriptionEvents', GridField::create(
            'SubscriptionEvents',
            'SubscriptionEvents',
            RevenueCatEvent::get()->filter('AppUserUuid', $member->UUID)->sort('EventAt', 'DESC'),
            GridFieldConfig_RecordViewer::create(25)
        ));
        $fields->findOrMakeTab('Root.SubscriptionEvents')->setTitle('Subscription events');
    }

    /** @param array<string, mixed> $data */
    public function updateAccountExport(array &$data): void
    {
        $data['revenuecatWebhookEvents'] = array_map(static fn (RevenueCatEvent $e) => [
            'id' => $e->EventId,
            'type' => $e->Type,
            'eventAt' => ApiMemberExtension::iso($e->EventAt),
            'environment' => $e->Environment,
            'productId' => $e->ProductId ?: null,
            'outcome' => $e->Outcome,
        ], RevenueCatEvent::get()->filter('AppUserUuid', $this->owner->UUID)->toArray());
    }

    public function onAccountDeletion(): void
    {
        if (!AppConfig::get('revenuecat_enabled')) {
            return;
        }
        $uuid = (string) $this->owner->UUID;
        $key = trim((string) AppConfig::get('revenuecat_v2_secret_key'));
        $project = trim((string) AppConfig::get('revenuecat_project_id'));
        if ($key === '' || $project === '') {
            // A paying customer must never be orphaned in live, so retry instead.
            if (Environment::getEnv('SS_ENVIRONMENT_TYPE') === 'live') {
                throw new \RuntimeException('RevenueCat account deletion is not configured.');
            }
            return;
        }
        $context = stream_context_create(['http' => [
            'method' => 'DELETE',
            'header' => "Authorization: Bearer {$key}\r\nAccept: application/json",
            'timeout' => 15,
            'ignore_errors' => true,
        ]]);
        @file_get_contents('https://api.revenuecat.com/v2/projects/' . rawurlencode($project) . '/customers/' . rawurlencode($uuid), false, $context);
        $status = (int) (preg_match('/ (\d{3}) /', $http_response_header[0] ?? '', $m) ? $m[1] : 0);
        if ($status !== 404 && ($status < 200 || $status >= 300)) {
            throw new \RuntimeException("RevenueCat customer deletion failed ({$status}).");
        }
    }
}
