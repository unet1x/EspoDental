# SimpleStom Calendar Feedback UX

Last updated: 2026-05-26

Stage 4 introduces the SimpleStom feedback-calendar direction without touching
the pre-existing modified `resource-calendar.js` file. The `ResourceCalendar`
dashlet metadata now points to a new scoped view:
`espo-dental:views/dashlets/resource-calendar-feedback`.

## Runtime Pieces

- Waitlist entity: `AppointmentWaitlistEntry`
- Feedback service:
  `src/files/custom/Espo/Modules/EspoDental/Services/CalendarFeedbackService.php`
- Existing calendar controller extension:
  `GET /EspoDental/Calendar/feedbackPanel`
- New calendar dashlet view:
  `src/files/client/custom/modules/espo-dental/src/views/dashlets/resource-calendar-feedback.js`
- Dashlet metadata:
  `src/files/custom/Espo/Modules/EspoDental/Resources/metadata/dashlets/ResourceCalendar.json`

## Waitlist Contract

`AppointmentWaitlistEntry` stores patients waiting for an appointment slot:

- patient or preliminary patient through `parent`;
- optional clinic, requested doctor and preferred cabinet;
- requested, earliest and latest dates;
- status: `waiting`, `offered`, `booked`, `cancelled`, `expired`;
- priority: `normal`, `high`, `urgent`;
- optional booked appointment link.

The open bool filter includes `waiting` and `offered`.

## Calendar UI Contract

The feedback calendar view keeps the existing `ResourceGrid` behavior for:

- day/week resource grid;
- drag-and-drop appointment move;
- drag-resize appointment duration;
- backend conflict checks through `POST /EspoDental/Calendar/move`.

It adds the SimpleStom feedback layout:

- compact toolbar;
- date picker as the mini-calendar control;
- slot-first click path to appointment creation with date/cabinet prefilled in
  the URL query;
- right side panel with waitlist entries and cancelled/no-show appointments;
- scoped SimpleStom visual styles through `.espo-dental-stom`.

Stage 5 replaces the slot click path with the full booking wizard.
