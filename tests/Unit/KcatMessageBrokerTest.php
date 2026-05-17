<?php

namespace Tests\Unit;

use Infrastructure\Notifications\Events\KcatMessageBroker;
use Tests\TestCase;

class KcatMessageBrokerTest extends TestCase
{
    public function test_broker_can_be_resolved(): void
    {
        $this->assertInstanceOf(KcatMessageBroker::class, new KcatMessageBroker);
    }
}
