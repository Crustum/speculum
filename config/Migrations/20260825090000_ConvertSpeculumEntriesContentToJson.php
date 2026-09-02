<?php
declare(strict_types=1);

use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Migrations\BaseMigration;

/**
 * Promote the Speculum entries `content` column to a native JSON type.
 *
 * The initial migration declared `content` as the portable `json` column, which
 * Phinx renders as `json` on Postgres. This migration upgrades it to `jsonb` on
 * Postgres (with a GIN index for fast containment queries) and to `JSON` on
 * MySQL. Drivers without native JSON support keep the portable `json` column and
 * rely on the ORM `json` type for (de)serialization.
 */
class ConvertSpeculumEntriesContentToJson extends BaseMigration
{
    /**
     * Upgrade `content` to a native JSON column per database driver.
     *
     * @return void
     */
    public function up(): void
    {
        $driver = $this->getAdapter()->getConnection()->getDriver();

        if ($driver instanceof Postgres) {
            $this->execute('ALTER TABLE speculum_entries ALTER COLUMN content TYPE jsonb USING content::jsonb');
            $this->execute(
                'CREATE INDEX IF NOT EXISTS speculum_entries_content_gin '
                . 'ON speculum_entries USING gin (content)',
            );

            return;
        }

        if ($driver instanceof Mysql) {
            $this->execute('ALTER TABLE speculum_entries MODIFY content JSON');
        }
    }

    /**
     * Revert `content` to the portable text representation per database driver.
     *
     * @return void
     */
    public function down(): void
    {
        $driver = $this->getAdapter()->getConnection()->getDriver();

        if ($driver instanceof Postgres) {
            $this->execute('DROP INDEX IF EXISTS speculum_entries_content_gin');
            $this->execute('ALTER TABLE speculum_entries ALTER COLUMN content TYPE text USING content::text');

            return;
        }

        if ($driver instanceof Mysql) {
            $this->execute('ALTER TABLE speculum_entries MODIFY content TEXT');
        }
    }
}
