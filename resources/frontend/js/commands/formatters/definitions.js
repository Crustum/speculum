import { registerCommandFormatter } from './registry';
import executeToolCommand from './executeToolCommand';

/**
 * Built-in command preview formatters.
 *
 * Add a new formatter file and register it here — commands/preview.vue
 * picks up matching tabs automatically.
 */
registerCommandFormatter(executeToolCommand);
