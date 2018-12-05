<?php

declare(strict_types=1);

namespace Soa\EventSourcingMiddleware\Middleware;

use Soa\EventSourcingMiddleware\Persistence\DatabaseSession;
use Soa\EventSourcing\Command\Command;
use Soa\EventSourcing\Command\CommandMiddleware;
use Soa\EventSourcing\Command\CommandResponse;

class TransactionMiddleware implements CommandMiddleware
{
    /**
     * @var DatabaseSession
     */
    private $session;

    public function __construct(DatabaseSession $session)
    {
        $this->session = $session;
    }

    public function __invoke(Command $command, callable $nextMiddleware): CommandResponse
    {
        try {
            $this->session->beginTransaction();

            $result = $nextMiddleware($command);

            $this->session->commitTransaction();

            return $result;
        } catch (\Throwable $exception) {
            $this->session->rollbackTransaction();

            throw $exception;
        }
    }
}
