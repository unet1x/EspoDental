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

The first implementation posts a JSON text-message payload to the configured
HTTPS endpoint with a bearer token. A clinic can point it at a thin provider
proxy, WhatsApp Cloud API wrapper, or another audited service without changing
reminder workflow code.

## 3. Reminder Flow

Appointment reminders still choose recipients from the patient or linked parent
patient. The preferred channel can now be `whatsapp`; when no preferred channel
is usable, the reminder service falls back through enabled Telegram and
WhatsApp adapters, then email when an address exists.

Before delivery, the reminder creates a queued `NotificationLog` row. Delivery
updates the same row with provider, external id, status, attempts, sent time or
error text.

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
