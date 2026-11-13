import { readFileSync } from 'node:fs';
import path from 'node:path';
import { wpEval } from './wp';

/** Runs tests/bin/setup.php inside WordPress before the browser tests. */
export default function globalSetup() {
  const code = readFileSync(path.resolve(__dirname, '../bin/setup.php'), 'utf8').replace(/^<\?php/, '');
  process.stdout.write(wpEval(code));
}
