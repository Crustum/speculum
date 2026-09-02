// Maps the short AI event name (after `Ai.`) to its panel category.
// Kept in sync with Crustum\Speculum\Watcher\AiWatcher::CATEGORY so old entries
// that were recorded before `category` was stored on `content` can still be
// routed to the right panel via their event `name`.
const AI_CATEGORY = {
    promptingAgent: 'agent',
    streamingAgent: 'agent',
    agentPrompted: 'agent',
    agentStreamed: 'agent',
    agentFailedEvent: 'agent',
    startingStep: 'agent',
    stepCompleted: 'agent',
    stepFailed: 'agent',
    invokingTool: 'tool',
    toolInvoked: 'tool',
    toolFailed: 'tool',
    toolApprovalRequested: 'tool',
    toolApprovalResolved: 'tool',
    generatingImage: 'generation',
    imageGenerated: 'generation',
    generatingAudio: 'generation',
    audioGenerated: 'generation',
    generatingTranscription: 'generation',
    transcriptionGenerated: 'generation',
    generatingEmbeddings: 'generation',
    embeddingsGenerated: 'generation',
    reranking: 'generation',
    reranked: 'generation',
    creatingStore: 'store',
    storeCreated: 'store',
    storeDeleted: 'store',
    addingFileToStore: 'store',
    fileAddedToStore: 'store',
    removingFileFromStore: 'store',
    fileRemovedFromStore: 'store',
    storingFile: 'file',
    fileStored: 'file',
    fileDeleted: 'file',
    agentFailedOver: 'failover',
    providerFailedOver: 'failover',
};

export function aiCategory(entry) {
    const content = entry?.content ?? {};

    if (content.category) {
        return content.category;
    }

    const name = content.name ?? '';
    if (name.startsWith('Ai.')) {
        return AI_CATEGORY[name.slice(3)] ?? 'agent';
    }

    return 'agent';
}
