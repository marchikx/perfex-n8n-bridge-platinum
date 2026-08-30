# N8N Bridge for Perfex CRM

**Version 2.1.0**

Professional bi-directional automation suite connecting Perfex CRM to N8N workflow automation platform.

---

## Features

### Core Features
- **Real-time Webhooks** - Instant notifications when CRM events occur
- **Bi-directional Sync** - Send data to N8N AND receive updates back
- **HMAC-SHA256 Security** - Enterprise-grade signature authentication
- **Resilient Queue System** - Guaranteed delivery with exponential backoff
- **Magic Button** - One-click manual triggers on Lead/Invoice detail pages

### Platinum Features (v2.0)
- **Visual Analytics Dashboard** - Chart.js powered statistics
- **Event-Based Advanced Routing** - Different webhook URLs per event type
- **System Health Monitoring** - Real-time health checks
- **Auto-Fix Tools** - One-click resolution for common issues

---

## Installation

1. Upload the `n8n_bridge` folder to `modules/` in your Perfex CRM installation
2. Go to **Setup → Modules** in Perfex admin
3. Find "N8N Bridge" and click **Activate**
4. Navigate to **Setup → N8N Bridge** to configure

---

## Configuration

### Outbound Webhooks (Perfex → N8N)

1. **Create a Webhook Trigger node in N8N**
   - Add a "Webhook" node to your N8N workflow
   - Set it to receive POST requests
   - Copy the webhook URL

2. **Configure in Perfex**
   - Go to **Setup → N8N Bridge → Settings**
   - Paste your N8N webhook URL
   - Click "Test Connection" to verify
   - Enable the module and select which events to trigger

### Inbound Webhooks (N8N → Perfex)

1. **Copy your Inbound URL** from the Settings tab
2. **In N8N**, use an HTTP Request node to call this URL
3. **Send JSON payload** with the following structure:

```json
{
  "action": "update_lead_status",
  "data": {
    "lead_id": 123,
    "status_id": 2
  }
}
```

### Supported Inbound Actions

| Action | Required Fields | Description |
|--------|----------------|-------------|
| `update_lead_status` | `lead_id`, `status_id` | Update lead status |
| `update_lead` | `lead_id`, + fields | Update lead fields |
| `add_task_comment` | `task_id`, `content` | Add comment to task |
| `update_task_status` | `task_id`, `status` | Update task status (1-5) |
| `update_invoice_status` | `invoice_id`, `status` | Update invoice status (1-6) |
| `add_note` | `rel_id`, `rel_type`, `description` | Add note to entity |

---

## Event-Based Routing

You can configure different webhook URLs for specific events:

1. Go to **Triggers & Routing** tab
2. Enable the events you want
3. Click "Override URL" to set a custom URL for that event
4. Leave empty to use the global webhook URL

**Use Case**: Send invoice events to one N8N workflow and lead events to another.

---

## Webhook Payload Format

All outbound webhooks include:

```json
{
  "event": "invoice_created",
  "timestamp": 1642531200,
  "source": "perfex_crm",
  "version": "2.0.0",
  "data": {
    // Entity-specific data
  }
}
```

### Headers

| Header | Description |
|--------|-------------|
| `X-Perfex-Event` | Event type identifier |
| `X-Perfex-Timestamp` | Unix timestamp |
| `X-Perfex-Signature` | HMAC-SHA256 signature |
| `X-Perfex-Version` | Module version |

### Verifying Signatures in N8N

```javascript
// In N8N Function node
const crypto = require('crypto');
const secret = 'YOUR_HMAC_SECRET';
const payload = JSON.stringify($input.all()[0].json);
const signature = crypto.createHmac('sha256', secret).update(payload).digest('hex');

if (signature === $input.all()[0].headers['x-perfex-signature']) {
  return [{ json: { verified: true, data: $input.all()[0].json } }];
}
return [{ json: { verified: false } }];
```

---

## Queue System

Failed webhook deliveries are automatically queued for retry:

| Attempt | Retry After |
|---------|-------------|
| 1 | 5 minutes |
| 2 | 15 minutes |
| 3 | 1 hour |
| 4 | 4 hours |
| 5 | 12 hours |

After 5 failed attempts, items are marked as "dead" and can be manually retried or cleared.

---

## System Requirements

- Perfex CRM 2.3.0 or higher
- PHP 7.4 or higher (PHP 8.x recommended)
- cURL extension enabled
- MySQL 5.7+ or MariaDB 10.2+

---

## Troubleshooting

### Webhooks not sending
1. Check that the module is enabled
2. Verify the webhook URL is correct
3. Test the connection from the Settings tab
4. Check the Health tab for issues

### Queue items stuck
1. Go to Health & Support tab
2. Click "Reset Stuck Queue Items"
3. Verify Perfex cron is running properly

### Signature verification failing
1. Ensure you're using the correct HMAC secret
2. The signature is calculated on the raw JSON body
3. Check for any middleware modifying the request

---

## Changelog

### v2.1.0

Signature verification did not work in 2.0. It hashed the request body after n8n had
already parsed it, so the bytes it checked were never the bytes that had been signed,
and every genuine request failed. The blueprint also shipped a fallback secret in the
file, the literal string `your-hmac-secret-here`. The first fix for those broke the
sender's retry ladder, because a delivery retried an hour later looks exactly like a
replay unless something remembers what it has already seen.

All three are fixed, and the fix is checkable rather than promised:

```
node tests/verify-node.test.js
```

No arguments, no dependencies. It reads the `Verify HMAC Signature` node out of
`n8n_blueprint.json` and runs that code, so it tests the file you import rather than a
copy of it. Eighteen cases, exit code 0 — eight on signature handling, five on the
retry ladder (5 min to 12 h), five telling a genuine retry apart from a replay by
memory rather than by age. A request signed with the published fallback secret has to
be rejected for the run to pass.

The stand covers the signature node. It does not cover the other thirteen nodes, the
PHP sender, or an import into a live n8n instance.

### v2.0.0 - Platinum Release
- Added Visual Analytics Dashboard with Chart.js
- Added Event-Based Advanced Routing
- Added System Health monitoring
- Added Auto-Fix tools
- Improved UI with tabbed interface
- Enhanced security features

### v1.1.0
- Added bi-directional webhooks (N8N → Perfex)
- Added inbound token authentication
- Added Magic Button feature

### v1.0.0
- Initial release
- Basic outbound webhooks
- Queue system with retry

---

## Support

For support, please contact your module vendor or visit the documentation.

---

## License

This module is licensed for use with Perfex CRM. Redistribution is prohibited without permission.

**Copyright © 2024 - All Rights Reserved**
