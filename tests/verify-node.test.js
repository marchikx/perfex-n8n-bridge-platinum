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
const fn = new Function('items', '$env', 'getBinaryDataAsync', '$getWorkflowStaticData', 'require', 'Buffer',
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
  const ancient = body(now - 14 * 3600);
  await run('older than the whole ladder (14h)', [item(ancient, sign(ancient, SECRET), now)], { PERFEX_HMAC_SECRET: SECRET }, 'BLOCK');

  console.log('\n' + (fails === 0 ? 'all cases behaved as specified' : fails + ' case(s) did NOT behave as specified'));
  process.exit(fails === 0 ? 0 : 1);
})();
