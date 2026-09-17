<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Temporal;

use Spiral\TemporalBridge\Attribute\AssignWorker;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Простой ping workflow для проверки Temporal.
 *
 * @link https://docs.temporal.io/develop/php/
 */
#[AssignWorker('default')]
#[WorkflowInterface]
class Ping
{
    #[WorkflowMethod(name: 'ping')]
    public function handle(): string
    {
        return 'pong';
    }
}
