<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Http\Requests\Notifications\ListMyNotificationsRequest;
use App\Http\Resources\Notifications\InboxItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\NotificationDistribution\Application\CountMyUnreadNotificationsService;
use Kalaanba\Modules\NotificationDistribution\Application\ListMyNotificationsService;
use Kalaanba\Modules\NotificationDistribution\Application\MarkInboxItemActedOnService;
use Kalaanba\Modules\NotificationDistribution\Application\MarkInboxItemSeenService;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxCategory;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxCursor;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxListFilters;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxRepository;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxStatus;
use Kalaanba\Support\Config as KxConfig;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * HTTP entry point for the in-app inbox (engine: Notification & Distribution).
 *
 * Per docs/engines/notification-distribution/.../System_Document.md §13
 * (lifecycle) and §41 (engine outputs). The controller is thin — all
 * business rules live in the engine module's Application services.
 */
final class MyInboxController extends Controller
{
    public function __construct(
        private readonly ListMyNotificationsService $listService,
        private readonly CountMyUnreadNotificationsService $countService,
        private readonly MarkInboxItemSeenService $markSeenService,
        private readonly MarkInboxItemActedOnService $markActedOnService,
        private readonly InboxRepository $repository,
    ) {}

    public function index(ListMyNotificationsRequest $request): JsonResponse
    {
        $user = $request->user();
        \abort_if($user === null, SymfonyResponse::HTTP_UNAUTHORIZED);

        $limit = $this->resolveLimit($request->integer('limit', 0));
        $cursor = $this->resolveCursor($request->string('cursor')->toString());
        $filters = new InboxListFilters(
            status: $this->resolveEnum($request->string('status')->toString(), InboxStatus::class),
            category: $this->resolveEnum($request->string('category')->toString(), InboxCategory::class),
        );

        $page = $this->listService->handle((string) $user->getKey(), $filters, $cursor, $limit);

        return new JsonResponse([
            'data' => InboxItemResource::collection(collect($page->items))->resolve(),
            'meta' => [
                'next_cursor' => $page->nextCursor?->encode(),
                'limit' => $limit,
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        \abort_if($user === null, SymfonyResponse::HTTP_UNAUTHORIZED);

        $cap = $this->readConfigInt('notification.inbox.unread_badge_cap', 99);
        $result = $this->countService->handle((string) $user->getKey(), $cap);

        return new JsonResponse([
            'data' => [
                'count' => $result['count'],
                'truncated' => $result['truncated'],
            ],
            'meta' => [],
        ]);
    }

    public function markSeen(Request $request, string $id): Response
    {
        $user = $request->user();
        \abort_if($user === null, SymfonyResponse::HTTP_UNAUTHORIZED);

        $this->ensureOwned($id, (string) $user->getKey());
        $this->markSeenService->handle($id, (string) $user->getKey());

        return new Response('', SymfonyResponse::HTTP_NO_CONTENT);
    }

    public function markActedOn(Request $request, string $id): Response
    {
        $user = $request->user();
        \abort_if($user === null, SymfonyResponse::HTTP_UNAUTHORIZED);

        $this->ensureOwned($id, (string) $user->getKey());
        $this->markActedOnService->handle($id, (string) $user->getKey());

        return new Response('', SymfonyResponse::HTTP_NO_CONTENT);
    }

    private function ensureOwned(string $id, string $userId): void
    {
        $item = $this->repository->findById($id);
        // 404 hides existence on non-ownership (engineering-standards §11).
        \abort_if($item === null || $item->recipientUserId !== $userId, SymfonyResponse::HTTP_NOT_FOUND);
    }

    private function resolveLimit(int $raw): int
    {
        $default = $this->readConfigInt('notification.inbox.default_page_size', 25);
        $max = $this->readConfigInt('notification.inbox.max_page_size', 100);

        if ($raw < 1) {
            return min($default, $max);
        }

        return min($raw, $max);
    }

    private function resolveCursor(string $cursor): ?InboxCursor
    {
        return $cursor === '' ? null : InboxCursor::decode($cursor);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private function resolveEnum(string $value, string $enum): ?\BackedEnum
    {
        if ($value === '') {
            return null;
        }

        return $enum::tryFrom($value);
    }

    private function readConfigInt(string $key, int $fallback): int
    {
        try {
            $value = KxConfig::get($key);
            if ($value === null) {
                return $fallback;
            }

            return (int) $value->value;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
