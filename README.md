# N8N Bridge for Perfex CRM - Platinum Edition

**Version 2.1.0**

Professional bi-directional automation suite connecting Perfex CRM to N8N workflow automation platform.

---

## 📦 Package Contents

This package includes:

- ✅ **n8n_bridge/** - Complete Perfex CRM module
- ✅ **Documentation/** - Full interactive documentation
- ✅ **n8n_blueprint.json** - N8N workflow template

---

## ⚡ Quick Installation

### Step 1: Upload Module

Upload the `n8n_bridge/` folder to your Perfex CRM `modules/` directory:

```
your-perfex-crm/
└── modules/
    └── n8n_bridge/  ← Upload this folder here
```

### Step 2: Activate

1. Log in to Perfex CRM admin panel
2. Navigate to **Setup → Modules**
3. Find "N8N Bridge" in the module list
4. Click **Activate**

### Step 3: Configure

1. Go to **Setup → N8N Bridge**
2. Configure your webhook settings
3. Start automating!

---

## 🧪 Verify it yourself

Do not take the security claims on trust — run them:

```bash
node tests/verify-node.test.js
```

No dependencies and no setup. The stand reads the `Verify HMAC Signature` node out of
`n8n_blueprint.json` and executes that code, not a copy of it, so it tests whatever is
in the file you are about to import. Eighteen cases: eight on signature handling, five
on the retry ladder the PHP sender actually uses, five separating a genuine retry from
a replay. Exit code is 0 only when every one of them behaves as specified.

Two of those cases are permanent regressions rather than theory. Version 2.0 shipped a
verification step that read the request body after n8n had parsed it, so it failed on
every request; and it carried the literal string `your-hmac-secret-here` as a fallback
secret. Case 4 signs a request with that published string and requires it to be
rejected. If a future edit brings either fault back, this run turns red.

---

## 📚 Documentation

Open `Documentation/index.html` in your browser for complete documentation including:

- Installation guide
- Configuration walkthrough
- Outbound webhooks (Perfex → N8N)
- Inbound webhooks (N8N → Perfex)
- Event-based routing
- API reference
- Troubleshooting

---

## 🎯 Key Features

### Core Features
- **Real-time Webhooks** - Instant notifications when CRM events occur
- **Bi-directional Sync** - Send data TO n8n AND receive updates BACK
- **HMAC-SHA256 Security** - Enterprise-grade signature authentication
- **Resilient Queue System** - Guaranteed delivery with exponential backoff
- **Magic Button** - One-click manual triggers on Lead/Invoice pages

### Platinum Features
- **Visual Analytics Dashboard** - Chart.js powered statistics
- **Event-Based Advanced Routing** - Different webhook URLs per event type
- **System Health Monitoring** - Real-time health checks
- **Auto-Fix Tools** - One-click resolution for common issues

---

## 🔧 System Requirements

- Perfex CRM 2.3.0 or higher
- PHP 7.4+ (PHP 8.x recommended)
- cURL extension enabled
- MySQL 5.7+ or MariaDB 10.2+

---

## 🚀 Quick Start

### 1. Set Up N8N Webhook

1. Open N8N and create a new workflow
2. Add a **Webhook** node as the trigger
3. Set HTTP Method to **POST**
4. Copy the generated webhook URL

### 2. Configure in Perfex

1. Go to **Setup → N8N Bridge → Settings**
2. Paste your N8N webhook URL
3. Click "Test Connection" to verify
4. Enable the module
5. Select which events to trigger

### 3. Import N8N Blueprint (Optional)

1. Open `n8n_blueprint.json` in a text editor
2. Copy the contents
3. In N8N, go to Workflows → Import from File
4. Paste the JSON content
5. Customize as needed

---

## 📖 Supported Events

### Outbound (Perfex → N8N)
- Invoice Created / Updated
- Lead Created / Updated
- Task Created / Updated
- Ticket Created / Updated

### Inbound (N8N → Perfex)
- Update Lead Status & Fields
- Update Task Status
- Add Task Comments
- Update Invoice Status
- Add Notes to Entities

---

## 🎨 Features Overview

### Visual Analytics Dashboard
- Webhook success rates
- Traffic timeline (last 7 days)
- Top event triggers
- Performance metrics

### Event-Based Routing
- Configure different webhook URLs per event type
- Send invoice events to billing workflow
- Send lead events to sales automation workflow
- Fallback to global URL if not specified

### Smart Queue System
- Automatic retry on failure
- Exponential backoff (5 min → 12 hours)
- Up to 5 retry attempts
- Visual queue monitoring
- Manual retry option

### Security
- HMAC-SHA256 signatures on all outbound webhooks
- Token-based authentication for inbound webhooks
- Encrypted database storage
- Audit logging

---

## 🆘 Support

### Documentation
Complete documentation is available in `Documentation/index.html`

### Common Issues

**Webhooks not sending?**
1. Verify module is enabled
2. Check webhook URL is correct
3. Test connection from Settings tab
4. Check Health tab for system issues

**Queue items stuck?**
1. Go to Health & Support tab
2. Click "Reset Stuck Queue Items"
3. Verify Perfex cron job is running

**Signature verification failing?**
1. Ensure correct HMAC secret is used
2. Signature is calculated on raw JSON body
3. Check for middleware modifying request

**Receiving side: two settings the workflow cannot work without**

The `Verify HMAC Signature` node hashes the exact bytes that were signed, so it
needs the untouched request body and Node's `crypto`:

- On the **Webhook** node: `Options -> Raw Body = ON`. Without it n8n hands the
  node a re-serialised object, the bytes differ from what was signed, and every
  genuine request is rejected.
- On self-hosted n8n: `NODE_FUNCTION_ALLOW_BUILTIN=crypto` in the environment.
  Without it the node cannot load `crypto` at all.

**How replays are told apart from retries**

The queue re-sends the stored body, so the timestamp inside a retry never moves
and a 12-hour-old retry is indistinguishable from a replay by age alone. The
node therefore accepts anything inside the retry window (13 hours, one hour past
the final backoff step) and blocks repeats by remembering signatures it has
already accepted, in the workflow's static data.

A repeat comes back as `verified: true` with `duplicate: true`. The workflow
answers `200` so the queue stops retrying, and the `Already Processed?` branch
skips straight to the response instead of booking the same invoice twice.

Known limit, stated plainly: workflow static data is written when an execution
finishes and is not transactional, so two deliveries racing inside the same
window can both get through. This stops the ordinary replay, not a determined
attacker with concurrent access. A shared store such as Redis `SETNX` is the
real answer and needs a node this blueprint does not ship.

---

## 📝 What's Included

### Module Files (`n8n_bridge/`)
```
n8n_bridge/
├── n8n_bridge.php       # Main module file
├── install.php          # Installation script
├── controllers/         # Module logic
├── models/             # Database operations
├── views/              # Admin interface
├── libraries/          # Core functionality
└── language/           # Translations
```

### Documentation
- Interactive HTML documentation
- Installation guide
- Configuration walkthrough
- API reference
- Troubleshooting tips

### N8N Blueprint
- Ready-to-import workflow template
- Example automation flows
- Webhook configuration examples

---

## 🎯 Next Steps

1. **Install** the module (see Quick Installation above)
2. **Read** the documentation (`Documentation/index.html`)
3. **Configure** your first webhook
4. **Test** with the Test Connection button
5. **Start** automating your workflows!

---

## 💡 Use Cases

### Sales Automation
- New lead → Slack notification → Update Google Sheet
- Lead status change → Send email → Add to mailing list

### Billing & Invoicing
- Invoice created → Send to accounting → Update QuickBooks
- Payment received → Thank you email → Update inventory

### Task Management
- Task assigned → Create Trello card → Notify on Slack
- Task completed → Update tracker → Send report

### Customer Support
- New ticket → Create Jira issue → Assign to support
- Ticket resolved → Send survey → Update help desk

---

## 📞 Need Help?

Refer to the complete documentation in `Documentation/index.html` for:
- Detailed installation steps
- Configuration options
- API documentation
- Troubleshooting guide
- Code examples

---

## ✅ Checklist

- [ ] Module uploaded to `modules/` folder
- [ ] Module activated in Perfex
- [ ] Webhook URL configured
- [ ] Connection tested successfully
- [ ] Events enabled
- [ ] First automation running
- [ ] Documentation reviewed

---

**Thank you for choosing N8N Bridge!** 🚀

*Start automating your Perfex CRM workflows today.*

---

© 2024 N8N Bridge - Platinum Edition - All Rights Reserved

---

## Developer Notes (AI-Augmented Architecture)

This module was built using an AI-augmented workflow (Cursor IDE + Claude 3.5) and then manually reviewed and hardened.

- **Security design** – the choice of HMAC-SHA256, token-based inbound auth and strict header schema was made up-front and enforced across all requests, not added by AI “after the fact”.
- **Resilience** – the DB-backed queue with exponential backoff is based on standard distributed-systems patterns (no data loss, no hammering unstable endpoints).
- **Observability** – dashboard charts, queue views and health checks are implemented to make the integration easy to operate in production (not just “fire and forget” webhooks).
- **Code quality** – strict types, separation of controller/model/library concerns and SQL with bound parameters were used deliberately to keep the codebase maintainable.

In day-to-day work this project serves as a reference for how I use AI tools: they generate boilerplate and variations, while architecture, security and final review remain under my control.
