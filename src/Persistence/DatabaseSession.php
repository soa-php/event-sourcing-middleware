<?php

declare(strict_types=1);

namespace Soa\EventSourcingMiddleware\Persistence;

interface DatabaseSession
{
    public function beginTransaction(): void;

    public function commitTransaction(): void;

    public function rollbackTransaction(): void;
}
