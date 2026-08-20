<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * Club-level roles (engine doc §7). Stable internal keys. High-risk actions
 * (RP, challenges, ownership) require Owner/Admin authority. The club creator
 * becomes Owner.
 */
enum ClubRole: string
{
    case Owner = 'owner';
    case Cofounder = 'cofounder';
    case Admin = 'admin';
    case Manager = 'manager';
    case Captain = 'captain';
    case Scorer = 'scorer';
    case MediaManager = 'media_manager';
    case Member = 'member';
    case Viewer = 'viewer';
}
