/* apiUtil.js
 * Small helper to parse fetch responses safely.
 * Returns parsed JSON object, or null if body is empty or invalid JSON.
 */
async function parseJsonSafe(response) {
    try {
        const text = await response.text();
        if (!text) return null;
        return JSON.parse(text);
    } catch (err) {
        console.error('parseJsonSafe: invalid JSON response', err);
        return null;
    }
}

// expose for other modules
window.parseJsonSafe = parseJsonSafe;
