<?php

declare(strict_types=1);

namespace Soa\EventSourcingMiddleware\Middleware;

use Soa\Clock\Clock;
use Soa\MessageStore\Message;
use Soa\EventSourcing\Command\Command;
use Soa\EventSourcing\Command\CommandMiddleware;
use Soa\EventSourcing\Command\CommandResponse;
use Soa\EventSourcing\Event\DomainEvent;
use Soa\EventSourcing\Event\EventStream;
use Soa\IdentifierGenerator\IdentifierGenerator;
use function Martinezdelariva\Hydrator\extract;
use Soa\MessageStore\MessageStore;
use Soa\Traceability\Trace;

class PersistMessagesMiddleware implements CommandMiddleware
{
    /**
     * @var MessageStore
     */
    private $eventStore;

    /**
     * @var IdentifierGenerator
     */
    private $identifierGenerator;

    /**
     * @var string
     */
    private $replyTo;

    /**
     * @var string
     */
    private $boundedContextName;

    /**
     * @var Trace
     */
    private $trace;

    /**
     * @var MessageStore
     */
    private $commandStore;

    /**
     * @var Clock
     */
    private $clock;

    public function __construct(
        Clock $clock,
        MessageStore $eventStore,
        MessageStore $commandStore,
        IdentifierGenerator $identifierGenerator,
        string $replyTo,
        string $boundedContextName,
        Trace $trace
    )
    {
        $this->eventStore              = $eventStore;
        $this->identifierGenerator     = $identifierGenerator;
        $this->replyTo                 = $replyTo;
        $this->boundedContextName      = $boundedContextName;
        $this->trace                   = $trace;
        $this->commandStore            = $commandStore;
        $this->clock                   = $clock;
    }

    public function __invoke(Command $command, callable $nextMiddleware): CommandResponse
    {
        $commandId = $this->identifierGenerator->nextIdentity();
        $this->commandStore->appendMessages($this->convertCommandIntoMessage($command, $commandId));

        /** @var CommandResponse $response */
        $response = $nextMiddleware($command);

        $this->eventStore->appendMessages(...$this->convertEventsIntoMessages($response->eventStream(), $commandId));

        return $response;
    }

    private function convertCommandIntoMessage(Command $command, string $commandId): Message
    {
        return new Message(
            'com.' . $this->boundedContextName . '.commands.' . $this->messageType($command),
            $this->clock->now()->format(Clock::MICROSECONDS_FORMAT),
            extract($command),
            $command->aggregateRootId(),
            $this->trace->correlationId(),
            $this->trace->messageId(),
            $this->replyTo,
            $commandId,
            $this->boundedContextName,
            $this->trace->processId()
        );
    }

    private function convertEventsIntoMessages(EventStream $eventStream, string $causationId): array
    {
        return array_map(function (DomainEvent $domainEvent) use ($causationId) {
            return new Message(
                'com.' . $this->boundedContextName . '.events.' . $this->messageType($domainEvent),
                $this->clock->now()->format(Clock::MICROSECONDS_FORMAT),
                extract($domainEvent),
                $domainEvent->streamId(),
                $this->trace->correlationId(),
                $causationId,
                $this->replyTo,
                $this->identifierGenerator->nextIdentity(),
                $this->boundedContextName,
                $this->trace->processId()
            );
        }, $eventStream->domainEvents());
    }

    private function messageType($object): string
    {
        $parts = explode('\\', get_class($object));

        return $this->camelCaseToSnakeCase(end($parts));
    }

    private function camelCaseToSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
    }
}
