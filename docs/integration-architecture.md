# EspoDental Integration Architecture

Last updated: 2026-05-22

This document defines the first Phase 9 integration slice. The goal is to keep
external communication, MCP tools and future local LLM automation behind a
small audited boundary instead of letting bots mutate clinical or financial
records directly.

## 1. Message Delivery Boundary

`NotificationLog` is the message outbox and audit record for external
communication. Every outbound message should record:

- patient and appointment context where available;
- channel, direction, provider and external message id;
- recipient, subject, message text and payload;
- status, attempts, scheduled time, sent time and error text.

`MessageDeliveryGateway` is the only delivery boundary used by appointment
reminders. It routes supported channels to replaceable adapters:

- `email` through the EspoCRM email sender;
- `telegram` through the existing Telegram sender;
- `whatsapp` through `WhatsAppSender`.

Unsupported channels stay auditable: the gateway returns a failed result with
`unsupported_channel` instead of silently pretending the message was sent.

## 2. WhatsApp Adapter

`WhatsAppSender` is intentionally provider-light. It reads system settings from
the EspoDental settings page:

- `espoDentalWhatsAppEnabled`;
- `espoDentalWhatsAppProvider`;
- `espoDentalWhatsAppApiBase`;
- `espoDentalWhatsAppAccessToken`.

The adapter supports two outbound text-message payload contracts:

- `generic` posts the original proxy payload with `to`, `type`, `text` and
  EspoDental audit `context`;
- `whatsapp-cloud`, `meta-cloud` or `facebook-cloud` posts a Meta WhatsApp
  Cloud API text payload with `messaging_product=whatsapp`, `recipient_type`,
  `to`, `type=text` and `text.body`.

Both modes send the request to the configured HTTPS endpoint with a bearer
token. For `whatsapp-cloud`, the endpoint must be the full Graph messages URL,
ending in `/{phone-number-id}/messages`. This lets a clinic either point the
module at a thin provider proxy or at a Meta-compatible Cloud API endpoint
without changing reminder workflow code.

## 3. Reminder Flow

Appointment reminders still choose recipients from the patient or linked parent
patient. The preferred channel can now be `whatsapp`; when no preferred channel
is usable, the reminder service falls back through enabled Telegram and
WhatsApp adapters, then email when an address exists.

Before delivery, the reminder creates a queued `NotificationLog` row. Delivery
updates the same row with provider, external id, status, attempts, sent time or
error text.

Failed notification rows can be reviewed by staff and returned to `queued` with
the `NotificationLog` requeue action. Requeue is a passive outbox operation: it
requires edit ACL, only accepts failed rows below the retry limit, stores a
`payload.requeueHistory` entry with reviewer context and the previous error,
and does not call `MessageDeliveryGateway` or any external provider.

Queued notification retries are processed explicitly through
`POST /EspoDental/NotificationLog/processQueue`, implemented by
`NotificationDeliveryService`. This is the only retry processing path added in
Stage J. It requires normal `NotificationLog` edit ACL, processes either one
queued row or a small bounded queue batch, calls `MessageDeliveryGateway`, then
writes attempts, sent/failed status, provider ids/errors and
`payload.deliveryHistory` back to the same audit row.

## 4. MCP And LLM Guardrails

Future MCP and local LLM work should use the same pattern:

- expose narrow, permission-checked commands rather than raw entity mutation;
- draft risky medical or financial actions instead of applying them silently;
- write every external message or proposed bot action to an auditable record;
- require explicit user approval for destructive, financial or clinical
  state changes.

The first MCP tools should therefore read patient context, draft messages or
prepare appointment proposals. They should not directly post payments, finish
visits, edit medical notes, delete records or cancel invoices.

## 5. Assistant Action Proposals

`AssistantActionProposal` is the audit and review object for MCP/LLM drafts.
It records source, action type, target, patient/appointment context, risk,
payload and review status.

Risky actions are never applied directly by bots. High/critical proposals and
known medical/financial mutation types force `requiresApproval = true`:

- `post_payment`;
- `finish_visit`;
- `edit_medical_note`;
- `cancel_invoice`.

The workflow is:

1. MCP or LLM creates a `pending_review` proposal.
2. A permitted user reviews the summary and payload.
3. The user marks it `approved` or `rejected` through the
   `AssistantActionProposal` approve/reject actions, which require edit ACL and
   stamp `reviewedBy`, `reviewedAt` and optional `reviewNotes`.
4. Only an already `approved` proposal can be marked `applied`.

This keeps the assistant useful for drafting and triage while preserving the
hard product invariant that bots do not silently mutate critical clinical or
financial state.

## 6. CRM-Side MCP Contract

The first MCP server should wrap the CRM-side routes documented in
`docs/mcp-server-design.md`:

- `GET /EspoDental/Integration/tools`;
- `GET /EspoDental/Integration/healthcheck`;
- `GET /EspoDental/Integration/patientContext`;
- `POST /EspoDental/Integration/proposeAction`.

These routes are intentionally narrow. They expose tool discovery, bounded
patient context, proposal creation and read-only operational health only. They
do not expose generic entity write access or direct clinical/financial
mutations.

## 7. Integration Ops Center

`IntegrationOpsCenter` is the first Stage J manager dashboard surface. It reads
`GET /EspoDental/Integration/healthcheck` and shows:

- MCP tool audit, including direct-mutation tool count;
- SMTP, Telegram and WhatsApp settings readiness;
- failed `NotificationLog` rows and retry candidates;
- pending `AssistantActionProposal` rows, including high-risk pending count.

The endpoint is deliberately passive. It does not call external providers and
does not resend messages; retry candidates are shown for staff review while the
existing reminder and messaging services remain the delivery boundary. Failed
notifications and pending proposals link to their CRM records so staff can open
the proposal review screen and use the human approve/reject workflow without
giving the assistant direct write authority. Failed notification retry
candidates can also be requeued from the dashlet; the requeue action only moves
the audit row back to `queued` and leaves actual provider delivery to the
existing delivery boundary. Queued retries can then be processed by staff from
the same dashboard through `NotificationLog/processQueue`; this is an explicit
operator action, not an MCP tool and not a hidden background mutation.

The same healthcheck now includes a provider readiness checklist for SMTP,
Telegram and WhatsApp. It checks the IntegrationSettings row, enabled flag,
secret reference and runtime configuration without returning secret values.
When those checks pass the row moves to `pending_acceptance`, which is still a
manual gate: staff must explicitly accept clinic credentials before any live
provider smoke is run.

The provider credential acceptance decision is stored on `IntegrationSettings` through the
staff-only `Integration/acceptProviderCredentials` action. The action is not an
MCP tool and does not send a message; it only records the accepted status,
timestamp, reviewer and note after the checklist is complete. The
`MessageDeliveryGateway` enforces the gate and returns
`provider_acceptance_required` for SMTP, Telegram and WhatsApp until acceptance
is recorded, so queued processing and reminders cannot call external providers
by accident. If accepted channel settings, enabled state or secret reference
change later, the acceptance is revoked and staff must accept the new
credential set before live sends are allowed again.

Notification queue preflight uses the same provider gate without sending messages.
`Integration/healthcheck` returns queued rows with `deliveryGate` status plus
`queuedReadyCount` and `queuedBlockedCount`. `IntegrationOpsCenter` displays
those blockers before staff invokes `NotificationLog/processQueue`. The process
action is also preflight-aware: a preflight-blocked row is returned as skipped,
remains queued, does not spend an attempt and does not append delivery history.
Only a row with a clear delivery gate can be sent or marked failed by the
explicit process action. The process batch limit counts skipped rows too, so a
single staff action remains bounded even when every queued row is blocked.

`Integration/healthcheck` also returns `stageJAcceptance`, a read-only summary
of the Stage J acceptance gates. It checks that MCP tools contain no direct
mutations, restricted staff routes are not exposed as MCP tools, provider
readiness is visible, live sends remain behind credential acceptance, queue
preflight is visible before processing, notification retry is staff-controlled
and assistant proposals remain in human review. `IntegrationOpsCenter` renders
that summary as the Stage J acceptance panel.

## 8. Virtual Administrator

The local LLM design is documented in
`docs/virtual-administrator-design.md`. The virtual administrator is an
operator assistant that reads bounded context, drafts messages and creates
`AssistantActionProposal(source=llm)` records. It is not allowed to hold broad
CRM write credentials or perform direct medical/financial mutations.
