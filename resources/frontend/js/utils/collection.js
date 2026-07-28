export function reject(array, predicate) {
    return (array || []).filter((item) => !predicate(item));
}
