# EspoDental MCP Server Design

Last updated: 2026-05-22

This document defines the CRM-side contract for a future MCP server. The MCP
transport can be implemented outside EspoCRM, but it must use these narrow API
routes instead of generic entity mutation.

## 1. CRM Routes

The module exposes three controlled integration routes:

| Tool | Method | Route | Purpose |
| --- | --- | --- | --- |
| `tools.list` | `GET` | `/EspoDental/Integration/tools` | Returns the supported integration tool contract. |
| `patient_context.read` | `GET` | `/EspoDental/Integration/patientContext` | Reads bounded patient context for drafting. |
| `assistant_action.propose` | `POST` | `/EspoDental/Integration/proposeAction` | Creates `AssistantActionProposal` for human review. |

The routes require an authenticated regular/admin user and still use EspoCRM
ACL checks. Patient context requires `Patient` read access. Proposals require
`AssistantActionProposal` create access.

## 2. Allowed Tool Behavior

Allowed MCP behavior:

- read bounded patient context;
- draft a patient message;
- propose a next appointment;
- propose issuing a questionnaire;
- propose a contact update.

Disallowed direct MCP behavior:

- posting payments;
- finishing visits;
- editing medical notes;
- cancelling invoices;
- deleting records.

For disallowed mutations, MCP must create an `AssistantActionProposal` with the
corresponding action type and wait for human review.

## 3. Patient Context Shape

`patient_context.read` returns patient identifiers, contact channels,
questionnaire warning flags and allowed/blocked action lists. Financial balance
is only included when `includeFinancials=1` and the authenticated user has both
invoice and payment read access.

## 4. Proposal Shape

`assistant_action.propose` accepts:

- `name`;
- `source` (`mcp`, `llm`, `manual`, `system`);
- `actionType`;
- `riskLevel`;
- `patientId`;
- `appointmentId`;
- `notificationLogId`;
- `targetType`;
- `targetId`;
- `summary`;
- `payload`.

The server always stores proposals as `pending_review` and
`requiresApproval = true`. The proposal workflow hook then enforces approval
before the proposal can be marked `applied`.

## 5. First MCP Server Boundary

The first external MCP server should be a thin adapter:

1. Authenticate to EspoCRM as a dedicated low-privilege integration user.
2. Expose only the three routes above as MCP tools.
3. Never expose generic REST write access to entity records.
4. Show returned proposal ids to the operator for audit and follow-up.
