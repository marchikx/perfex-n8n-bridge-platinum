/**
 * Builds the smallest workflow that can answer one question:
 * does the published verification node work inside a real n8n?
 *
 * The webhook node and the verification node are lifted out of
 * n8n_blueprint.json unchanged - same options, same code, same typeVersion.
 * Only three things are ours, and each is here because the shipped workflow
 * would otherwise answer a different question:
 *
 *   - the webhook path, so a test run cannot collide with a real one
 *   - responseMode: responseNode, so the verifier's own output is what comes
 *     back over HTTP and the assertion reads the node rather than a guess
 *   - the wiring, because the shipped flow routes through Slack, which needs
 *     credentials and has nothing to do with signature verification
 *
 * Writes the workflow to stdout as a one-element array: `n8n import:workflow`
 * calls .map on what it parses, so a bare object fails with
 * "workflows.map is not a function" - measured, not guessed (run 34324043342).
 */

const fs = require("fs");

const bp = JSON.parse(fs.readFileSync("n8n_blueprint.json", "utf8"));
const byName = (n) => bp.nodes.find((x) => x.name === n);

const webhook = JSON.parse(JSON.stringify(byName("Perfex Webhook Trigger")));
const verify = JSON.parse(JSON.stringify(byName("Verify HMAC Signature")));
if (!webhook || !verify) {
  console.error("blueprint is missing the webhook or the verify node");
  process.exit(1);
}

// Keep every option the published node carries - Raw Body above all, since the
// binary path is the one the harness can only stub.
webhook.parameters = {
  ...webhook.parameters,
  path: "perfex-e2e",
  responseMode: "responseNode",
};
webhook.webhookId = "e2e-perfex-webhook";
webhook.position = [0, 0];
verify.position = [220, 0];

const respond = {
  parameters: { respondWith: "allIncomingItems", options: {} },
  name: "Respond",
  type: "n8n-nodes-base.respondToWebhook",
  typeVersion: 1,
  position: [440, 0],
};

process.stdout.write(
  JSON.stringify(
    [{
      name: "e2e verify (published nodes, ours wiring)",
      nodes: [webhook, verify, respond],
      connections: {
        [webhook.name]: { main: [[{ node: verify.name, type: "main", index: 0 }]] },
        [verify.name]: { main: [[{ node: respond.name, type: "main", index: 0 }]] },
      },
      settings: { executionOrder: "v1" },
      active: false,
    }],
    null,
    2,
  ) + "\n",
);
