<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Kalaanba\Modules\AdminGovernance\AdminGovernanceServiceProvider;
use Kalaanba\Modules\Analytics\AnalyticsServiceProvider;
use Kalaanba\Modules\AwardsRecognition\AwardsRecognitionServiceProvider;
use Kalaanba\Modules\Challenge\ChallengeServiceProvider;
use Kalaanba\Modules\Club\ClubServiceProvider;
use Kalaanba\Modules\CompetitionRules\CompetitionRulesServiceProvider;
use Kalaanba\Modules\FanBuzz\FanBuzzServiceProvider;
use Kalaanba\Modules\MatchFixture\MatchFixtureServiceProvider;
use Kalaanba\Modules\ModerationSafety\ModerationSafetyServiceProvider;
use Kalaanba\Modules\NotificationDistribution\NotificationDistributionServiceProvider;
use Kalaanba\Modules\PlayerAffiliation\PlayerAffiliationServiceProvider;
use Kalaanba\Modules\RefereeOfficiator\RefereeOfficiatorServiceProvider;
use Kalaanba\Modules\RpEconomy\RpEconomyServiceProvider;
use Kalaanba\Modules\Season\SeasonServiceProvider;
use Kalaanba\Modules\TrustVerification\TrustVerificationServiceProvider;
use Kalaanba\Modules\VenueSurfaceBooking\VenueSurfaceBookingServiceProvider;
use Kalaanba\Modules\Zone\ZoneServiceProvider;

return [
    AppServiceProvider::class,

    // Engine modules (one provider per engine, in canonical order).
    AdminGovernanceServiceProvider::class,
    AnalyticsServiceProvider::class,
    AwardsRecognitionServiceProvider::class,
    ChallengeServiceProvider::class,
    ClubServiceProvider::class,
    CompetitionRulesServiceProvider::class,
    FanBuzzServiceProvider::class,
    MatchFixtureServiceProvider::class,
    ModerationSafetyServiceProvider::class,
    NotificationDistributionServiceProvider::class,
    PlayerAffiliationServiceProvider::class,
    RefereeOfficiatorServiceProvider::class,
    RpEconomyServiceProvider::class,
    SeasonServiceProvider::class,
    TrustVerificationServiceProvider::class,
    VenueSurfaceBookingServiceProvider::class,
    ZoneServiceProvider::class,
];
