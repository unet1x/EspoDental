# SimpleStom Slot Booking Wizard

Last updated: 2026-05-26

Stage 5 adds the first pass of the SimpleStom slot-first booking flow.

## Runtime Pieces

- Candidate search:
  `GET /EspoDental/Appointment/bookingCandidates?q=...`
- Slot booking:
  `POST /EspoDental/Appointment/bookFromSlot`
- Service methods:
  `AppointmentService::searchBookingCandidates` and
  `AppointmentService::bookFromSlot`
- Modal:
  `src/files/client/custom/modules/espo-dental/src/views/appointment/modals/slot-booking.js`
- Calendar integration:
  `resource-calendar-feedback.js` opens the modal from a free grid cell.

## Booking Contract

The wizard supports two paths:

- select an existing `Patient` or `PreliminaryPatient`;
- create a new `PreliminaryPatient` inline with name, phone and optional email.

The booking payload includes clinic, doctor, cabinet, optional planned service,
local slot start, timezone, duration, reason and notes. Duration is constrained
to 15-minute steps and capped by the available grid window, with a hard
180-minute quick-booking maximum. Backend validation rejects durations outside
15-180 minutes and relies on the existing appointment conflict hooks for
doctor, cabinet and patient collisions.

When a service is selected, the modal sends `serviceId`. The server validates
the selected cabinet against `Service.cabinetRequirements` through the shared
`CabinetRequirementMatcher`, and stores the planned service on
`Appointment.service`. If the selected service duration does not fit into the
free window before the next blocking appointment or the end of the day, the
modal disables booking and tells reception to pick another time or service.

Known backend booking conflicts are translated into operational Russian UI
messages for reception, including occupied doctor/cabinet/patient slots,
closed cabinets, unavailable doctor shifts and service/cabinet mismatch.

Stage 4 still owns the calendar grid and waitlist side panel. Stage 5 only
replaces the previous raw create-navigation slot click with a compact booking
modal.
