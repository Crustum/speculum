<?php
declare(strict_types=1);

/**
 * Test database schema for Speculum plugin tests.
 */
return [
    'speculum_entries' => [
        'columns' => [
            'sequence' => ['type' => 'integer', 'autoIncrement' => true, 'null' => false],
            'uuid' => ['type' => 'uuid', 'null' => false],
            'batch_id' => ['type' => 'uuid', 'null' => false],
            'family_hash' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
            'should_display_on_index' => ['type' => 'boolean', 'null' => false, 'default' => true],
            'type' => ['type' => 'string', 'length' => 20, 'null' => false],
            'content' => ['type' => 'text', 'null' => false],
            'duration' => ['type' => 'integer', 'null' => true, 'default' => null],
            'created' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['sequence']],
            'speculum_entries_uuid' => ['type' => 'unique', 'columns' => ['uuid']],
        ],
        'indexes' => [
            'speculum_entries_type_duration' => [
                'type' => 'index',
                'columns' => ['type', 'duration'],
            ],
        ],
    ],
    'speculum_entries_tags' => [
        'columns' => [
            'entry_uuid' => ['type' => 'uuid', 'null' => false],
            'tag' => ['type' => 'string', 'length' => 255, 'null' => false],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['entry_uuid', 'tag']],
        ],
        'indexes' => [
            'speculum_entries_tags_tag_entry_uuid' => [
                'type' => 'index',
                'columns' => ['tag', 'entry_uuid'],
            ],
        ],
    ],
    'speculum_monitoring' => [
        'columns' => [
            'tag' => ['type' => 'string', 'length' => 255, 'null' => false],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['tag']],
        ],
    ],
];
