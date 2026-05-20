<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Application\Notifications\Ports\OutboxMessageRepository;
use Illuminate\Http\Response;

final class MetricsController extends Controller
{
    public function __invoke(OutboxMessageRepository $outbox): Response
    {
        $deadCount = $outbox->deadCount();
        $body = <<<TEXT
# HELP notifications_outbox_dead_messages Number of outbox messages moved to dead-letter state.
# TYPE notifications_outbox_dead_messages gauge
notifications_outbox_dead_messages {$deadCount}

TEXT;

        return response($body, Response::HTTP_OK)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
