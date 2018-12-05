<?php

declare(strict_types=1);

namespace Soa\EventSourcingMiddleware\Middleware;

use Soa\EventSourcing\Command\Command;
use Soa\EventSourcing\Command\CommandHandler;
use Soa\EventSourcing\Command\CommandMiddleware;
use Soa\EventSourcing\Command\CommandResponse;
use Soa\EventSourcing\Event\EventStream;
use Soa\EventSourcing\Repository\AggregateRootNotFound;
use Soa\EventSourcing\Repository\Repository;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

class CommandHandlerSelectorMiddleware implements CommandMiddleware
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Repository
     */
    private $repository;

    public function __construct(ContainerInterface $container, Repository $repository)
    {
        $this->container  = $container;
        $this->repository = $repository;
    }

    public function __invoke(Command $command, callable $nextMiddleware): CommandResponse
    {
        $handler = $this->container->get(get_class($command) . 'Handler');

        $aggregateRoot = null;
        if ($this->requireAggregateRoot($handler)) {
            $aggregateRoot = $this->repository->findOfId($command->aggregateRootId());
            if (!$aggregateRoot) {
                return CommandResponse::fromEventStream(EventStream::fromDomainEvents(new AggregateRootNotFound($command->aggregateRootId(), get_class($this->repository))));
            }
        }

        return CommandResponse::fromEventStream($handler->handle($command, $aggregateRoot));
    }

    private function requireAggregateRoot(CommandHandler $commandHandler)
    {
        $params = (new ReflectionMethod(get_class($commandHandler), 'handle'))->getParameters();

        return !$params[1]->allowsNull();
    }
}
