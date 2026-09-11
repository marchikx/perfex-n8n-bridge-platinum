// Verification test stand for the Perfex -> n8n bridge.
//
//   node tests/verify-node.test.js
//
// It reads the "Verify HMAC Signature" node straight out of n8n_blueprint.json and
// executes that code — not a copy of it, not a mock. If you edit the blueprint, this
// runs whatever you edited. Exit code is 0 when every case behaves as specified.
//
// Why it exists: version 2.0 shipped a verification step that failed on every request
// and carried a fallback secret in the file. Both were found by writing this, not by
// reading the code. Case 4 below is that exact bug, kept as a permanent regression.
//
// No dependencies beyond Node's own crypto and fs.
const fs = require('fs'), path = require('path'), crypto = require('crypto');

const blueprint = JSON.parse(
  fs.readFileSync(path.join(__dirname, '..', 'n8n_blueprint.json'), 'utf8'));
const node = blueprint.nodes.find(n => n.name === 'Verify HMAC Signature');
if (!node || !node.parameters || !node.parameters.functionCode) {
  console.error('Could not find the "Verify HMAC Signature" node in n8n_blueprint.json');
  process.exit(2);
}
const code = node.parameters.functionCode;
// The blueprint ships a legacy Function node, and that node exposes
// getWorkflowStaticData WITHOUT the $ prefix - only the Code node has the
// prefixed name. Until 2026-09-09 this harness injected the prefixed one, so
// all eighteen cases were green while every genuinely signed webhook threw
// inside a real n8n (measured, run 34324647318). Injecting the name the target
// runtime actually provides is what makes this suite able to fail for the
// reason that matters: put the $ back in the node and these cases go red.
const fn = new Function('items', '$env', 'getBinaryDataAsync', 'getWorkflowStaticData', 'require', 'Buffer',
  'return (async function(){' + code + '})()');

const SECRET = 's3cr3t-from-env';
const now = Math.floor(Date.now() / 1000);

function body(ts) {
  return JSON.stringify({ event: 'invoice_paid', timestamp: ts, source: 'perfex_crm', version: '2.0.0', data: { invoice_id: 77, total: 1234.50 } });
}
function sign(j, sec) { return crypto.createHmac('sha256', sec).update(j).digest('hex'); }
function item(json, sig, hdrTs) {
  return {
    json: { body: {}, headers: { 'x-perfex-signature': sig, 'x-perfex-event': 'invoice_paid', 'x-perfex-timestamp': String(hdrTs) }, params: {}, query: {} },
    binary: { data: { data: Buffer.from(json, 'utf8').toString('base64'), mimeType: 'application/json' } }, index: 0
  };
}
const getBin = async (it) => it.binary;

// One store for the whole run, exactly like a single n8n workflow would have.
let STATIC = {};
const staticData = () => STATIC;

let fails = 0;
async function run(name, items, env, expect) {
  let j;
  try {
    const out = await fn(items, env, getBin, staticData, require, Buffer);
    j = out[0].json;
  } catch (e) {
    j = { verified: false, error: 'THREW: ' + e.message };
  }
  const got = j.verified ? (j.duplicate ? 'DUP' : 'PASS') : 'BLOCK';
  const ok = got === expect;
  if (!ok) fails++;
  const detail = j.verified ? ('event=' + j.event + ' age=' + j.ageSeconds + 's') : j.error;
  console.log((ok ? ' ok  ' : 'FAIL ') + got.padEnd(6) + (' expected ' + expect).padEnd(16) + name.padEnd(48) + detail);
  return j;
}

(async () => {
  const good = body(now), gsig = sign(good, SECRET);

  console.log('--- security: everything a5 already covered ---');
  await run('1 genuine request', [item(good, gsig, now)], { PERFEX_HMAC_SECRET: SECRET }, 'PASS');
  await run('2 secret not set', [item(good, gsig, now)], {}, 'BLOCK');
  const tampered = good.replace('1234.5', '1.5');
  await run('3 body tampered, old signature', [item(tampered, gsig, now)], { PERFEX_HMAC_SECRET: SECRET }, 'BLOCK');
  await run('4 signed with the published fallback', [item(good, sign(good, 'your-hmac-secret-here'), now)], { PERFEX_HMAC_SECRET: SECRET }, 'BLOCK');
  const fut = body(now + 99999999);
  await run('5 timestamp from the future', [item(fut, sign(fut, SECRET), now)], { PERFEX_HMAC_SECRET: SECRET }, 'BLOCK');
  const it6 = item(good, gsig, now); delete it6.binary; it6.json.body = { event: 'invoice_paid' };
  await run('6 Raw Body off (parsed object)', [it6], { PERFEX_HMAC_SECRET: SECRET }, 'BLOCK');
  const it7 = item(good, gsig, now); delete it7.json.headers['x-perfex-signature'];
  await run('7 signature header missing', [it7], { PERFEX_HMAC_SECRET: SECRET }, 'BLOCK');
  const it8 = item(good, 'zz' + gsig.slice(2), now);
  await run('8 malformed hex in signature', [it8], { PERFEX_HMAC_SECRET: SECRET }, 'BLOCK');

  console.log('\n--- the retry ladder from N8n_sender.php, which v2 rejected wholesale ---');
  STATIC = {};
  for (const [label, delay] of [['5 min', 300], ['15 min', 900], ['1 hour', 3600], ['4 hours', 14400], ['12 hours', 43200]]) {
    STATIC = {}; // each is a first delivery that earlier attempts never reached
    const j = body(now - delay), s = sign(j, SECRET);
    await run('retry after ' + label + ' (first delivery)', [item(j, s, now)], { PERFEX_HMAC_SECRET: SECRET }, 'PASS');
  }

  console.log('\n--- replay, told apart from a retry by memory rather than by age ---');
  STATIC = {};
  await run('same body, first time', [item(good, gsig, now)], { PERFEX_HMAC_SECRET: SECRET }, 'PASS');
  await run('same body, replayed a second time', [item(good, gsig, now)], { PERFEX_HMAC_SECRET: SECRET }, 'DUP');
  const old = body(now - 3600), osig = sign(old, SECRET);
  await run('hour-old body, first time = genuine retry', [item(old, osig, now)], { PERFEX_HMAC_SECRET: SECRET }, 'PASS');
  await run('hour-old body, replayed = attacker', [item(old, osig, now)], { PERFEX_HMAC_SECRET: SECRET }, 'DUP');
  // The case the ladder loop above cannot see: it runs every step as a fresh
  // first delivery carrying the age of one interval. A real final retry carries
  // the cumulative age, 5m+15m+1h+4h+12h = 17h20m, and a window pinned to the
  // last interval rejects it.
  const last = body(now - 62400);
  await run('final retry, full ladder (17h20m)', [item(last, sign(last, SECRET), now)], { PERFEX_HMAC_SECRET: SECRET }, 'PASS');
  const ancient = body(now - 19 * 3600);
  await run('older than the window (19h)', [item(ancient, sign(ancient, SECRET), now)], { PERFEX_HMAC_SECRET: SECRET }, 'BLOCK');

  console.log('\n' + (fails === 0 ? 'all cases behaved as specified' : fails + ' case(s) did NOT behave as specified'));
  process.exit(fails === 0 ? 0 : 1);
})();
