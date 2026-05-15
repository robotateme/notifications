<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use Application\Notifications\Commands\CreateNotificationCommand;
use Application\Notifications\Commands\CreateNotificationHandler;
use Application\Notifications\NotificationPresenter;
use Application\Notifications\Queries\GetNotificationHandler;
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

    public function show(string $notification, GetNotificationHandler $handler): JsonResponse
    {
        $result = $handler->handle($notification);

        if ($result === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $this->presenter->toArray($result)]);
    }
}
