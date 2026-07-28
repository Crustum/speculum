<script setup>
import { formatFileLocation, editorHref } from '../../utils/projectPath';

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

function varDumpLabel(entry) {
    if (entry?.content?.summary) {
        return entry.content.summary;
    }

    const htmls = Array.isArray(entry?.content?.vardumps)
        ? entry.content.vardumps
        : entry?.content?.vardump
          ? [entry.content.vardump]
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

function varDumpLocation(entry) {
    const file = entry?.content?.file;
    if (!file) {
        return entry?.content?.entry_point_description || undefined;
    }

    return formatFileLocation(file, entry?.content?.line);
}
</script>

<template>
    <index-screen
        title="Var Dumps"
        resource="vardumps"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Var Dump
                </th>
                <th scope="col">
                    Happened
                </th>
                <th scope="col" />
            </tr>
        </template>

        <template #row="slotProps">
            <td>
                <entry-title
                    :to="{ name: 'vardump-preview', params: { id: slotProps.entry.id } }"
                    :text="varDumpLabel(slotProps.entry)"
                    :limit="80"
                    code
                    :subtitle="varDumpLocation(slotProps.entry)"
                    :subtitle-href="editorHref(slotProps.entry.content?.file, slotProps.entry.content?.line, slotProps.entry.content?.editor_url)"
                />
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'vardump-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
