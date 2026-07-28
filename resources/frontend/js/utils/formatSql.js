import { format, supportedDialects } from 'sql-formatter';

/**
 * Format an SQL string for display, mapping CakePHP driver names to sql-formatter dialects.
 *
 * @param {string|null|undefined} sql
 * @param {string|null|undefined} driver
 * @returns {string}
 */
export function formatSql(sql, driver) {
    if (!sql) {
        return '';
    }

    let dialect = driver;
    let formatterConfig = {};

    if (dialect) {
        if (dialect === 'pgsql') {
            dialect = 'postgresql';
        }

        if (dialect === 'sqlsrv') {
            dialect = 'transactsql';
        }

        if (supportedDialects.includes(dialect)) {
            formatterConfig = { language: dialect };
        }
    }

    try {
        return format(sql, formatterConfig);
    } catch (_e) {
        return sql;
    }
}
