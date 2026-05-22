# Local LLM Virtual Administrator Design

Last updated: 2026-05-22

This document defines the local LLM "virtual administrator" planned in Phase 9.
It is an operator assistant, not an autonomous CRM actor.

## 1. Purpose

The virtual administrator helps clinic staff with low-risk operational work:

- summarize patient context before a call;
- draft appointment reminder or follow-up messages;
- draft next-appointment proposals;
- flag missing questionnaire or contact data;
- prepare `AssistantActionProposal` records for human review.

It must not silently change medical, financial or destructive state.

## 2. Deployment Boundary

The first deployment target is local:

- LLM runtime on the Proxmox VM or a trusted LAN host;
- no patient data sent to public SaaS LLM APIs by default;
- CRM access only through the narrow MCP/Integration contract;
- a dedicated low-privilege integration user in EspoCRM;
- all generated proposals stored in `AssistantActionProposal`.

The LLM should not receive raw database credentials, Docker socket access,
filesystem write access to uploads, or generic EspoCRM REST write credentials.

## 3. Allowed Capabilities

The assistant may:

- call `GET /EspoDental/Integration/tools`;
- call `GET /EspoDental/Integration/patientContext`;
- call `POST /EspoDental/Integration/proposeAction`;
- draft patient-facing messages;
- summarize non-hidden patient context returned by the integration API;
- propose safe administrative next steps.

The assistant must create a proposal instead of acting directly when a workflow
could change CRM state.

## 4. Blocked Direct Capabilities

The assistant must not directly:

- create or modify payments;
- finish visits;
- edit medical notes;
- cancel or storno invoices;
- delete records;
- change stock movement records;
- bypass questionnaire completion rules;
- create patients outside the preliminary-patient conversion workflow.

For these actions, it may only draft a high-risk
`AssistantActionProposal`.

## 5. Human Approval Workflow

1. Staff asks the assistant for help.
2. The assistant reads bounded context through the integration API.
3. The assistant drafts a response or proposal.
4. If CRM state must change, the assistant creates an
   `AssistantActionProposal` with source `llm`.
5. A permitted user reviews and approves/rejects the proposal in EspoCRM.
6. Only approved proposals can be applied by a normal workflow.

The approval screen should show:

- source;
- action type;
- risk level;
- patient/appointment context;
- summary;
- payload;
- review notes.

## 6. Prompt Contract

The system prompt for the local LLM should include these hard rules:

```text
You are EspoDental Virtual Administrator.
Use only the listed MCP tools.
Never claim that you changed CRM data unless an approved CRM workflow did it.
Never post payments, finish visits, edit medical notes, cancel invoices or delete records.
For any state-changing request, create an AssistantActionProposal and tell the user the proposal id.
Keep patient-facing messages concise and neutral.
Do not expose hidden technical fields unless the user has a clear operational need.
```

## 7. Audit And Privacy

Every state-changing suggestion must have an `AssistantActionProposal`. Every
external message that is sent or queued must have a `NotificationLog`.

Conversation logs should avoid long-term storage of full medical notes unless
there is a clinic-approved retention policy. When logs are needed for debugging,
prefer proposal ids, patient ids and timestamps over copied clinical text.

## 8. Failure Modes

If the LLM cannot reach the MCP server, it should say that CRM tools are
unavailable and avoid guessing current clinic data.

If patient context is missing or ACL denies access, it should ask staff to open
the patient in CRM or involve a user with the correct role.

If a requested action is blocked, it should create a proposal only if enough
context is present; otherwise it should ask for the missing patient,
appointment or invoice identifier.

## 9. Acceptance

The first accepted virtual-administrator slice is complete when:

- the assistant can list available MCP tools;
- it can read bounded patient context;
- it can draft a follow-up message;
- it can create `AssistantActionProposal(source=llm)`;
- it refuses direct payment, visit-finish, medical-note, invoice-cancel and
  delete requests;
- proposal ids are visible to staff for review.
