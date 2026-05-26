<?php

declare(strict_types=1);

use Database\Seeders\ZoneHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kalaanba\Modules\Zone\Application\ApproveAreaSuggestion;
use Kalaanba\Modules\Zone\Application\RejectAreaSuggestion;
use Kalaanba\Modules\Zone\Application\SubmitAreaSuggestion;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionStatus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ZoneHierarchySeeder::class);

    $this->cityHubId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1003';
    $this->zoneId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1004';
    $this->userId = '00000000-0000-0000-0000-000000000aaa';
    $this->adminId = '00000000-0000-0000-0000-000000000bbb';

    $this->submit = $this->app->make(SubmitAreaSuggestion::class);
    $this->approve = $this->app->make(ApproveAreaSuggestion::class);
    $this->reject = $this->app->make(RejectAreaSuggestion::class);
});

it('submit emits zone.area_suggested with suggestion id as event id', function (): void {
    $suggestion = $this->submit->execute(
        cityHubId: $this->cityHubId,
        proposedZoneId: $this->zoneId,
        proposedName: 'Sakasaka',
        note: 'Football-active community',
        submittedByUserId: $this->userId,
    );

    expect($suggestion->status)->toBe(AreaSuggestionStatus::Pending);

    $row = DB::table('outbox_events')
        ->where('event_name', 'zone.area_suggested')
        ->where('event_id', $suggestion->id)
        ->first();

    expect($row)->not->toBeNull();
});

it('approve promotes the suggestion to an area and emits zone.area_approved', function (): void {
    $suggestion = $this->submit->execute(
        cityHubId: $this->cityHubId,
        proposedZoneId: $this->zoneId,
        proposedName: 'Choggu',
        note: null,
        submittedByUserId: $this->userId,
    );

    $updated = $this->approve->execute(
        suggestionId: $suggestion->id,
        reviewerUserId: $this->adminId,
        targetZoneId: $this->zoneId,
        finalName: 'Choggu',
        reviewNote: null,
    );

    expect($updated->status)->toBe(AreaSuggestionStatus::Approved)
        ->and($updated->resultingAreaId)->not->toBeNull();

    $areaCount = DB::table('areas')->where('zone_id', $this->zoneId)->where('name', 'Choggu')->count();
    expect($areaCount)->toBe(1);

    $approvedEvents = DB::table('outbox_events')->where('event_name', 'zone.area_approved')->count();
    expect($approvedEvents)->toBe(1);
});

it('approve is idempotent — second call does not double-emit or double-create', function (): void {
    $suggestion = $this->submit->execute(
        cityHubId: $this->cityHubId,
        proposedZoneId: $this->zoneId,
        proposedName: 'Lamashegu',
        note: null,
        submittedByUserId: $this->userId,
    );

    $this->approve->execute($suggestion->id, $this->adminId, $this->zoneId, 'Lamashegu', null);
    $this->approve->execute($suggestion->id, $this->adminId, $this->zoneId, 'Lamashegu', null);

    $areaCount = DB::table('areas')->where('name', 'Lamashegu')->count();
    expect($areaCount)->toBe(1);

    $approvedEvents = DB::table('outbox_events')->where('event_name', 'zone.area_approved')->count();
    expect($approvedEvents)->toBe(1);
});

it('reject emits zone.area_rejected and preserves the suggestion row', function (): void {
    $suggestion = $this->submit->execute(
        cityHubId: $this->cityHubId,
        proposedZoneId: $this->zoneId,
        proposedName: 'Bogus Area XYZ',
        note: null,
        submittedByUserId: $this->userId,
    );

    $updated = $this->reject->execute($suggestion->id, $this->adminId, 'Duplicate of existing area');

    expect($updated->status)->toBe(AreaSuggestionStatus::Rejected)
        ->and($updated->reviewedByUserId)->toBe($this->adminId);

    $rejectedEvents = DB::table('outbox_events')->where('event_name', 'zone.area_rejected')->count();
    expect($rejectedEvents)->toBe(1);

    $stillThere = DB::table('area_suggestions')->where('id', $suggestion->id)->count();
    expect($stillThere)->toBe(1);
});

it('reject is idempotent', function (): void {
    $suggestion = $this->submit->execute($this->cityHubId, null, 'Whatever', null, $this->userId);
    $this->reject->execute($suggestion->id, $this->adminId, null);
    $this->reject->execute($suggestion->id, $this->adminId, null);

    $events = DB::table('outbox_events')->where('event_name', 'zone.area_rejected')->count();
    expect($events)->toBe(1);
});
