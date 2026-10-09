<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create Speculum storage tables.
 */
class CreateSpeculumEntries extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('speculum_entries', ['id' => false, 'primary_key' => ['sequence']])
            ->addColumn('sequence', 'biginteger', [
                'identity' => true,
                'signed' => false,
                'null' => false,
            ])
            ->addColumn('uuid', 'uuid', ['null' => false])
            ->addColumn('batch_id', 'uuid', ['null' => false])
            ->addColumn('family_hash', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('should_display_on_index', 'boolean', ['default' => true, 'null' => false])
            ->addColumn('type', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('content', 'json', ['null' => false])
            ->addColumn('duration', 'integer', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['uuid'], ['unique' => true])
            ->addIndex(['batch_id'])
            ->addIndex(['family_hash'])
            ->addIndex(['created'])
            ->addIndex(['type', 'should_display_on_index'], ['name' => 'speculum_entries_type_index'])
            ->addIndex(['type', 'duration'], ['name' => 'speculum_entries_type_duration'])
            ->create();

        $this->table('speculum_entries_tags', ['id' => false, 'primary_key' => ['entry_uuid', 'tag']])
            ->addColumn('entry_uuid', 'uuid', ['null' => false])
            ->addColumn('tag', 'string', ['limit' => 255, 'null' => false])
            ->addIndex(['tag', 'entry_uuid'], ['name' => 'speculum_entries_tags_tag_entry_uuid'])
            ->create();

        $this->table('speculum_monitoring', ['id' => false, 'primary_key' => ['tag']])
            ->addColumn('tag', 'string', ['limit' => 255, 'null' => false])
            ->create();
    }
}
