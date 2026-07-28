<script setup>
import { formatFileLocation, editorHref } from '../../utils/projectPath';

defineProps({
    items: { type: Array, default: () => [] },
});

function typeFromHtml(html) {
    const note = html.match(/class=["']sf-dump-note["'][^>]*>([^<]+)/i);
    if (note?.[1]) {
        return note[1].trim();
    }

    const text = html
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    const beforeBrace = text.split('{')[0]?.trim();
    if (beforeBrace && /^[A-Za-z_\\][\w\\]*$/.test(beforeBrace)) {
        return beforeBrace;
    }

    return null;
}

function varDumpLabel(item) {
    if (item.content?.summary) {
        return item.content.summary;
    }

    const htmls = Array.isArray(item.content?.vardumps)
        ? item.content.vardumps
        : item.content?.vardump
          ? [item.content.vardump]
          : [];

    const types = [];
    for (const html of htmls) {
        if (typeof html !== 'string' || html === '') {
            continue;
        }
        const type = typeFromHtml(html);
        if (type) {
            types.push(type);
        }
    }

    return types.length ? types.join(', ') : 'Value';
}

function varDumpLocation(item) {
    const file = item.content?.file;
    if (!file) {
        return undefined;
    }

    return formatFileLocation(file, item.content?.line);
}
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Var Dump</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td :title="varDumpLabel(item)">
                    <entry-title
                        :to="{ name: 'vardump-preview', params: { id: item.id } }"
                        :text="varDumpLabel(item)"
                        :limit="100"
                        code
                        :subtitle="varDumpLocation(item)"
                        :subtitle-href="editorHref(item.content?.file, item.content?.line, item.content?.editor_url)"
                    />
                </td>
                <view-link-cell :to="{ name: 'vardump-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
