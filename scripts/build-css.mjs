// Compiles resources/css/app.css with Tailwind CSS v4 into public/assets/app.css.
// Usage: node scripts/build-css.mjs
// Needs only the `tailwindcss` package (npm install). No other tooling.
import { compile } from 'tailwindcss';
import { readFileSync, writeFileSync, readdirSync, statSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve, extname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const input = join(root, 'resources/css/app.css');
const output = join(root, 'public/assets/app.css');
const tailwindDir = join(root, 'node_modules', 'tailwindcss');

// Files whose text may contain Tailwind class names.
const sources = [
  { dir: 'views', exts: ['.php'] },
  { dir: 'public/assets', exts: ['.js'] },
];

function walk(dir, exts, found = []) {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path, exts, found);
    else if (exts.includes(extname(name))) found.push(path);
  }
  return found;
}

// Every quote- or space-separated token is a candidate; Tailwind ignores the ones that are not classes.
function collectCandidates() {
  const candidates = new Set();
  for (const { dir, exts } of sources) {
    for (const file of walk(join(root, dir), exts)) {
      for (const token of readFileSync(file, 'utf8').match(/[^\s"'`]+/g) ?? []) candidates.add(token);
    }
  }
  return [...candidates];
}

async function loadStylesheet(id, base) {
  let path;
  if (id === 'tailwindcss') path = join(tailwindDir, 'index.css');
  else if (id.startsWith('tailwindcss/')) {
    const file = id.slice('tailwindcss/'.length);
    path = join(tailwindDir, file.endsWith('.css') ? file : `${file}.css`);
  } else path = resolve(base, id);
  return { path, base: dirname(path), content: readFileSync(path, 'utf8') };
}

const css = readFileSync(input, 'utf8');
const compiler = await compile(css, { base: dirname(input), from: input, loadStylesheet });
const built = compiler
  .build(collectCandidates())
  .replace(/\/\*[\s\S]*?\*\//g, '')
  .split('\n')
  .map((line) => line.trim())
  .filter(Boolean)
  .join('\n');

mkdirSync(dirname(output), { recursive: true });
writeFileSync(output, built + '\n');
console.log(`Wrote ${output} (${(built.length / 1024).toFixed(1)} KB)`);
