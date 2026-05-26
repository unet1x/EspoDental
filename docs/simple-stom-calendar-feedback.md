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
- Appointment list workspace:
  `src/files/client/custom/modules/espo-dental/src/views/appointment/record/list.js`
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
- visible doctor and cabinet filters backed by
  `GET /EspoDental/Calendar/appointments`;
- visible service filter that narrows compatible cabinet columns through
  `Service.cabinetRequirements`;
- the `Appointment` list route opens with the resource calendar first, while the
  standard table remains below as a secondary working list;
- slot-first click path to appointment creation with date/cabinet prefilled in
  the URL query;
- right side panel can be shown/hidden from the toolbar and switches between
  waitlist, cancelled/no-show appointments and active reschedule requests
  instead of rendering all sections permanently;
- scoped SimpleStom visual styles through `.espo-dental-stom`.

Stage 5 replaces the slot click path with the full booking wizard.

## Post-Parity Pass 2 Contract

The 2026-05-26 calendar-fidelity pass keeps day/week grid semantics and adds
the missing operational controls:

- `GET /EspoDental/Calendar/appointments` accepts `doctorId` and `cabinetId`;
- the appointment payload returns `filters.doctors` and `filters.cabinets` so
  both the dashboard dashlet and `Appointment` list workspace can render stable
  dropdowns;
- `GET /EspoDental/Calendar/feedbackPanel` accepts the same doctor/cabinet
  filters for waitlist and cancellation context;
- the same feedback-panel payload includes `rescheduleRequests` for active
  `AppointmentRescheduleRequest` rows on the selected date;
- selected doctor is passed into the slot-booking modal when booking starts
  from a free grid cell;
- month view remains deferred until backend shift semantics and grid density are
  explicitly designed.

The follow-up Stage C service-filtering slice adds `serviceId` to the same
calendar request. It returns `filters.services`, filters cabinet columns through
`CabinetRequirementMatcher`, passes the selected service into the booking modal
and keeps existing appointments visible as blocking occupancy inside compatible
cabinet columns.

The slot-click integration also sends the calculated free window from the grid
to the booking modal. The window stops at the next blocking appointment for the
same cabinet or selected doctor, or at the configured end of the calendar day,
so service duration warnings are based on the visible schedule instead of a
fixed three-hour assumption.

Verification on 2026-05-26 covered focused SimpleStom calendar/slot/service
tests, related Phase 11/13/20 checks, the full PHPUnit suite, JS/PHP syntax
checks and browser smoke for the service filter on the dashboard dashlet and
`Appointment` list workspace. After the slot-window polish, browser smoke also
confirmed that a free dashboard calendar cell opens the booking modal with
service options, duration choices and an available-window hint.

The final Stage C panel pass adds the third `Переносы` mode to both calendar
surfaces. It shows active portal/admin reschedule requests for the selected
calendar day and respects the same clinic, doctor and cabinet filters.
