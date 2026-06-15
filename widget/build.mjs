import { build } from 'esbuild';
import { gzipSync } from 'node:zlib';
import { writeFileSync, mkdirSync, readFileSync } from 'node:fs';

mkdirSync('dist', { recursive: true });

await build({
    entryPoints: ['src/index.tsx'],
    bundle: true,
    format: 'iife',
    target: 'es2019',
    minify: true,
    sourcemap: false,
    outfile: 'dist/foyer-widget.js',
    define: { 'process.env.NODE_ENV': '"production"' },
    jsxFactory: 'h',
    jsxFragment: 'Fragment',
    loader: { '.tsx': 'tsx', '.ts': 'ts' },
});

const raw = readFileSync('dist/foyer-widget.js');
const gz = gzipSync(raw);
writeFileSync('dist/foyer-widget.js.gz', gz);

console.log(
    `[widget] built: ${raw.length} bytes raw, ${gz.length} bytes gz`,
);
