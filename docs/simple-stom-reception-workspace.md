# SimpleStom Doctor Reception Workspace

Last updated: 2026-05-26

Stage 8 aligns the EspoDental `Visit` page with the SimpleStom doctor
reception workspace while keeping the existing Visit entities and finish flow.

## Runtime Pieces

- Workspace endpoint:
  `GET /EspoDental/Visit/receptionWorkspace?id=...`
- Autosave endpoint:
  `POST /EspoDental/Visit/autosaveReception`
- Template endpoint:
  `POST /EspoDental/Visit/noteTemplate`
- Service methods:
  `VisitService::getReceptionWorkspace`,
  `VisitService::autosaveReceptionNotes` and
  `VisitService::createNoteTemplate`
- Client view:
  `src/files/client/custom/modules/espo-dental/src/views/visit/record/detail.js`
- Personal note templates:
  `VisitNoteTemplate`

## Workspace Contract

The workspace is rendered above the normal EspoCRM detail panels. It groups the
doctor workflow around:

- complaints;
- treatment notes;
- recommendations;
- treatment plan;
- service lines;
- material consumption;
- photos;
- tooth chart;
- invoice state;
- completion checklist.

Text fields autosave through the reception endpoint. Personal and shared note
templates can be applied to the active section and saved from the workspace.

Finished visits are treated as locked. In locked mode, complaints, performed
treatment and recommendations are read-only. Treatment plan additions and file
uploads remain allowed through the workspace/relationship panels, matching the
SimpleStom decision that completed medical records should not be silently
rewritten while post-visit documentation can still be attached.

The existing `finishVisit` transaction remains the source of truth for invoice
generation and stock write-off.
