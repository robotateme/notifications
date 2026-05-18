<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmDeliveryRequest;
use App\Http\Requests\StoreBulkNotificationsRequest;
use App\Http\Requests\StoreNotificationRequest;
use Application\Notifications\Commands\ConfirmNotificationDeliveryHandler;
use Application\Notifications\Commands\CreateBulkNotificationsHandler;
use Application\Notifications\Commands\CreateNotificationHandler;
use Application\Notifications\Ports\NotificationRepository;
use App\Http\Presenters\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationPresenter $presenter) {}

    /**
     * Queue a single notification and return the existing notification when the idempotency key was already used.
     */
    public function store(StoreNotificationRequest $request, CreateNotificationHandler $handler): JsonResponse
    {
        $result = $handler->handle($request->toCommand());

        return response()->json(
            ['data' => $this->presenter->toArray($result->notification)],
            $result->created ? Response::HTTP_ACCEPTED : Response::HTTP_OK,
        );
    }

    /**
     * Queue a notification for multiple recipients and return the created or reused notification records.
     */
    public function storeBulk(StoreBulkNotificationsRequest $request, CreateBulkNotificationsHandler $handler): JsonResponse
    {
        $notifications = $handler->handle($request->toCommand());

        return response()->json([
            'data' => array_map(
                fn ($notification): array => $this->presenter->toArray($notification),
                $notifications,
            ),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Return the current state and delivery details for a notification by its public identifier.
     */
    public function show(string $notification, NotificationRepository $notifications): JsonResponse
    {
        $result = $notifications->findByPublicId($notification);

        if ($result === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $this->presenter->toArray($result)]);
    }

    /**
     * Apply a provider delivery callback and return the updated notification state.
     */
    public function confirmDelivery(
        string $notification,
        ConfirmDeliveryRequest $request,
        ConfirmNotificationDeliveryHandler $handler,
    ): JsonResponse {
        $result = $handler->handle($request->toCommand($notification));

        if ($result === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $this->presenter->toArray($result)]);
    }

    /**
     * Return all notifications currently known for the subscriber.
     */
    public function subscriberHistory(string $subscriber, NotificationRepository $notifications): JsonResponse
    {
        $data = [];

        foreach ($notifications->findBySubscriberId($subscriber) as $notification) {
            $data[] = $this->presenter->toArray($notification);
        }

        return response()->json([
            'data' => $data,
        ]);
    }
}
