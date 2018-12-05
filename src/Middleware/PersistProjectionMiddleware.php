<?php

declare(strict_types=1);

namespace Soa\EventSourcingMiddleware\Middleware;

use Soa\EventSourcing\Command\Command;
use Soa\EventSourcing\Command\CommandMiddleware;
use Soa\EventSourcing\Command\CommandResponse;
use Soa\EventSourcing\Projection\ProjectionTable;

class PersistProjectionMiddleware implements CommandMiddleware
{
    /**
     * @var ProjectionTable
     */
    private $table;

    public function __construct(ProjectionTable $table)
    {
        $this->table = $table;
    }

    public function __invoke(Command $command, callable $nextMiddleware): CommandResponse
    {
        /** @var CommandResponse $response */
        $response = $nextMiddleware($command);

        $this->table->save($response->eventStream()->id(), $response->projection());

        return $response;
    }
}
