<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmDeliveryRequest;
use App\Http\Requests\StoreBulkNotificationsRequest;
use App\Http\Requests\StoreNotificationRequest;
use Application\Notifications\Commands\ConfirmNotificationDeliveryCommand;
use Application\Notifications\Commands\ConfirmNotificationDeliveryHandler;
use Application\Notifications\Commands\CreateBulkNotificationsCommand;
use Application\Notifications\Commands\CreateBulkNotificationsHandler;
use Application\Notifications\Commands\CreateNotificationCommand;
use Application\Notifications\Commands\CreateNotificationHandler;
use Application\Notifications\NotificationPresenter;
use Application\Notifications\Queries\GetNotificationHandler;
use Application\Notifications\Queries\ListSubscriberNotificationsHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationPresenter $presenter) {}

    public function store(StoreNotificationRequest $request, CreateNotificationHandler $handler): JsonResponse
    {
        $result = $handler->handle(CreateNotificationCommand::fromArray($request->validated()));

        return response()->json(
            ['data' => $this->presenter->toArray($result->notification)],
            $result->created ? Response::HTTP_ACCEPTED : Response::HTTP_OK,
        );
    }

    public function storeBulk(StoreBulkNotificationsRequest $request, CreateBulkNotificationsHandler $handler): JsonResponse
    {
        $result = $handler->handle(CreateBulkNotificationsCommand::fromArray($request->validated()));

        return response()->json([
            'data' => array_map(
                fn ($notification): array => $this->presenter->toArray($notification),
                $result->notifications,
            ),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(string $notification, GetNotificationHandler $handler): JsonResponse
    {
        $result = $handler->handle($notification);

        if ($result === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $this->presenter->toArray($result)]);
    }

    public function confirmDelivery(
        string $notification,
        ConfirmDeliveryRequest $request,
        ConfirmNotificationDeliveryHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(ConfirmNotificationDeliveryCommand::fromArray($notification, $request->validated()));

        if ($result === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $this->presenter->toArray($result)]);
    }

    public function subscriberHistory(string $subscriber, ListSubscriberNotificationsHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn ($notification): array => $this->presenter->toArray($notification),
                $handler->handle($subscriber),
            ),
        ]);
    }
}
