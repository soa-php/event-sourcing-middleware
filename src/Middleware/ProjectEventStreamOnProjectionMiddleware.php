<?php

declare(strict_types=1);

namespace Soa\EventSourcingMiddleware\Middleware;

use Soa\EventSourcing\Command\Command;
use Soa\EventSourcing\Command\CommandMiddleware;
use Soa\EventSourcing\Command\CommandResponse;
use Soa\EventSourcing\Projection\ProjectionTable;
use Soa\EventSourcing\Projection\Projector;

class ProjectEventStreamOnProjectionMiddleware implements CommandMiddleware
{
    /**
     * @var ProjectionTable
     */
    private $table;

    /**
     * @var Projector
     */
    private $projector;

    public function __construct(ProjectionTable $table, Projector $projector)
    {
        $this->table     = $table;
        $this->projector = $projector;
    }

    public function __invoke(Command $command, callable $nextMiddleware): CommandResponse
    {
        /** @var CommandResponse $response */
        $response = $nextMiddleware($command);

        $commandResponse = $response->withProjection($this->projector->projectEventStream($response->eventStream(), $this->table->findOfId($response->eventStream()->id())));

        return $commandResponse;
    }
}
