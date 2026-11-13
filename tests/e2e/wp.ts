/**
 * Runs PHP inside the WordPress under test, without logging in to wp-admin.
 * Default: wp-env (Docker). For another local WordPress set LZCRS_WP_PATH, LZCRS_PHP and LZCRS_PHP_ARGS
 * (a JSON array such as ["-d","mysqli.default_socket=/path with spaces/mysqld.sock"]).
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const TMP = path.join(ROOT, 'tests/.tmp');
const SLUG = path.basename(ROOT);

export const BASE = process.env.WP_BASE_URL || 'http://localhost:8888';

export function wpEval(php: string): string {
  mkdirSync(TMP, { recursive: true });
  const file = path.join(TMP, `eval-${process.pid}-${Date.now()}.php`);
  writeFileSync(file, `<?php\n${php}\n`);
  const wpPath = process.env.LZCRS_WP_PATH;
  if (wpPath) {
    const php = process.env.LZCRS_PHP || 'php';
    // LZCRS_PHP_ARGS: a JSON array (safe for paths with spaces), or space-separated words.
    const raw = (process.env.LZCRS_PHP_ARGS || '').trim();
    const args: string[] = raw.startsWith('[') ? JSON.parse(raw) : raw.split(' ').filter(Boolean);
    const boot = `$_SERVER['HTTP_HOST']=${JSON.stringify(new URL(BASE).host)};$_SERVER['REQUEST_URI']='/';define('WP_USE_THEMES',false);require ${JSON.stringify(wpPath + '/wp-load.php')};require ${JSON.stringify(file)};`;
    return execFileSync(php, [...args, '-r', boot]).toString();
  }
  const inContainer = `wp-content/plugins/${SLUG}/tests/.tmp/${path.basename(file)}`;
  return execFileSync('npx', ['wp-env', 'run', 'cli', 'wp', 'eval-file', inContainer], { cwd: ROOT }).toString();
}

/** Runs PHP that prints `echo 'LZJSON:' . wp_json_encode( $x );` and returns $x. */
export function wpJson<T>(php: string): T {
  const out = wpEval(php);
  const i = out.lastIndexOf('LZJSON:');
  if (i < 0) throw new Error(`No JSON in WordPress output:\n${out}`);
  return JSON.parse(out.slice(i + 7).split('\n')[0]);
}
