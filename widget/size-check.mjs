import { statSync } from 'node:fs';

const MAX_BYTES = 30 * 1024;
const path = 'dist/foyer-widget.js.gz';

try {
    const size = statSync(path).size;
    if (size > MAX_BYTES) {
        console.error(
            `[widget] FAIL: ${path} is ${size} bytes gzipped (cap ${MAX_BYTES}).`,
        );
        process.exit(1);
    }
    console.log(`[widget] OK: ${size} / ${MAX_BYTES} bytes gzipped.`);
} catch (e) {
    console.error(`[widget] FAIL: ${path} not found — run 'npm run build' first.`);
    process.exit(1);
}
