<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Entry\Routing\EntryTypeMap;
use Crustum\Speculum\Enum\EntryType;

/**
 * Registry resolving entry types to presentation strategies.
 *
 * New types are covered by registering a presentation class; consumers
 * (`speculum list`/`show`, future MCP tools) never match on type strings.
 */
final class PresentationRegistry
{
    /**
     * Type value to presentation class map.
     *
     * @var array<string, class-string<\Crustum\Speculum\Entry\Presentation\EntryPresentationInterface>>
     */
    protected static array $presentations = [];

    /**
     * Whether first-party presentations are registered.
     *
     * @var bool
     */
    protected static bool $defaultsRegistered = false;

    /**
     * Register a presentation class for an entry type value.
     *
     * @param string $type Entry type value.
     * @param class-string<\Crustum\Speculum\Entry\Presentation\EntryPresentationInterface> $presentation Presentation class.
     * @return void
     */
    public static function register(string $type, string $presentation): void
    {
        self::$presentations[$type] = $presentation;
    }

    /**
     * Register first-party presentation classes (once).
     *
     * @return void
     */
    public static function registerDefaults(): void
    {
        if (self::$defaultsRegistered) {
            return;
        }

        self::$defaultsRegistered = true;
        self::register(EntryType::Request->value, RequestEntryPresentation::class);
        self::register(EntryType::Query->value, QueryEntryPresentation::class);
        self::register(EntryType::Exception->value, ExceptionEntryPresentation::class);
        self::register(EntryType::Job->value, JobEntryPresentation::class);
        self::register(EntryType::Cache->value, CacheEntryPresentation::class);
        self::register(EntryType::Log->value, LogEntryPresentation::class);
        self::register(EntryType::Mail->value, MailEntryPresentation::class);
        self::register(EntryType::Event->value, EventEntryPresentation::class);
        self::register(EntryType::Command->value, CommandEntryPresentation::class);
        self::register(EntryType::HttpClient->value, HttpClientEntryPresentation::class);
        self::register(EntryType::Ai->value, AiEntryPresentation::class);
        self::register(EntryType::Authorization->value, AuthorizationEntryPresentation::class);
        self::register(EntryType::CakeDCAuth->value, AuthorizationEntryPresentation::class);
        self::register(EntryType::Batch->value, BatchEntryPresentation::class);
        self::register(EntryType::BlazeCastDelivery->value, BlazeCastEntryPresentation::class);
        self::register(EntryType::BlazeCastMessage->value, BlazeCastEntryPresentation::class);
        self::register(EntryType::Broadcast->value, BroadcastEntryPresentation::class);
        self::register(EntryType::Explorator->value, ExploratorEntryPresentation::class);
        self::register(EntryType::Model->value, ModelEntryPresentation::class);
        self::register(EntryType::Mongo->value, MongoEntryPresentation::class);
        self::register(EntryType::MongoQuery->value, MongoQueryEntryPresentation::class);
        self::register(EntryType::MongoQueryLog->value, MongoQueryEntryPresentation::class);
        self::register(EntryType::Notification->value, NotificationEntryPresentation::class);
        self::register(EntryType::ScheduledTask->value, ScheduleEntryPresentation::class);
        self::register(EntryType::VarDump->value, VarDumpEntryPresentation::class);
        self::register(EntryType::View->value, ViewEntryPresentation::class);
    }

    /**
     * Return registered presentation type values (first-party defaults included).
     *
     * @return list<string>
     */
    public static function registeredTypes(): array
    {
        self::registerDefaults();

        return array_keys(self::$presentations);
    }

    /**
     * Reset registrations (tests).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$presentations = [];
        self::$defaultsRegistered = false;
    }

    /**
     * Resolve the presentation for an entry type (generic fallback when unlisted).
     *
     * @param \Crustum\Speculum\Entry\EntryResult|string $type Entry type value or result.
     * @return \Crustum\Speculum\Entry\Presentation\EntryPresentationInterface
     */
    public static function for(string|EntryResult $type): EntryPresentationInterface
    {
        self::registerDefaults();

        $key = $type instanceof EntryResult ? $type->type : $type;
        $class = self::$presentations[$key] ?? GenericEntryPresentation::class;

        return new $class();
    }

    /**
     * Return the generic fallback presentation.
     *
     * @return \Crustum\Speculum\Entry\Presentation\EntryPresentationInterface
     */
    public static function generic(): EntryPresentationInterface
    {
        return new GenericEntryPresentation();
    }

    /**
     * Return all valid entry type values.
     *
     * @return list<string>
     */
    public static function validTypes(): array
    {
        return EntryType::all();
    }

    /**
     * Resolve CLI type input to entry type value(s).
     *
     * Accepts a type value or a registered API resource path
     * (`http-clients`, `mongo-queries`, …).
     *
     * @param string|null $input CLI type argument.
     * @return list<string>|string|null
     */
    public static function resolveType(?string $input): string|array|null
    {
        if ($input === null || $input === '') {
            return null;
        }

        $type = EntryTypeMap::type($input);
        if ($type !== null) {
            return $type;
        }

        if (in_array($input, self::validTypes(), true)) {
            return $input;
        }

        return null;
    }
}
