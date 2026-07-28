import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
    LOCAL_ROOT_STORAGE_KEY,
    buildEditorUrl,
    editorHref,
    formatFileLocation,
    localRoot,
    normalizePath,
    projectRoot,
    relativeToRoot,
    resolveLocalFile,
    setLocalRoot,
} from '../utils/projectPath';

describe('projectPath', () => {
    beforeEach(() => {
        window.Speculum = {
            root: 'C:/projects/_demo_db/testqa',
            editor: 'phpstorm',
        };
        window.localStorage.removeItem(LOCAL_ROOT_STORAGE_KEY);
    });

    afterEach(() => {
        window.localStorage.removeItem(LOCAL_ROOT_STORAGE_KEY);
        delete window.Speculum;
    });

    it('normalizes separators', () => {
        expect(normalizePath('C:\\projects\\app\\')).toBe('C:/projects/app');
    });

    it('relativizes under server ROOT', () => {
        expect(
            relativeToRoot('C:\\projects\\_demo_db\\testqa\\src\\Controller\\PostsController.php'),
        ).toBe('src/Controller/PostsController.php');
    });

    it('prefers local ROOT for projectRoot and IDE remap', () => {
        setLocalRoot('D:/local/testqa');
        expect(localRoot()).toBe('D:/local/testqa');
        expect(projectRoot()).toBe('D:/local/testqa');
        expect(
            resolveLocalFile('C:/projects/_demo_db/testqa/src/Controller/PostsController.php'),
        ).toBe('D:/local/testqa/src/Controller/PostsController.php');
        expect(
            editorHref(
                'C:/projects/_demo_db/testqa/src/Foo.php',
                10,
                'phpstorm://open?file=C:/projects/_demo_db/testqa/src/Foo.php&line=10',
            ),
        ).toBe('phpstorm://open?file=D:/local/testqa/src/Foo.php&line=10');
    });

    it('falls back to server editor_url without local ROOT', () => {
        expect(
            editorHref('C:/projects/_demo_db/testqa/src/Foo.php', 3, 'phpstorm://open?file=server&line=3'),
        ).toBe('phpstorm://open?file=server&line=3');
    });

    it('builds vscode editor URLs', () => {
        window.Speculum.editor = 'vscode';
        expect(buildEditorUrl('C:/app/x.php', 2)).toBe('vscode://file/C:/app/x.php:2');
    });

    it('formats file:line for display', () => {
        expect(formatFileLocation('C:/projects/_demo_db/testqa/src/Foo.php', 12)).toBe(
            'src/Foo.php:12',
        );
    });
});
