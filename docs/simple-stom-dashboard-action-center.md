# SimpleStom Dashboard Action Center

Last updated: 2026-05-26

Stage 3 implements the SimpleStom feedback-dashboard direction inside
EspoDental without replacing the existing EspoCRM dashboard shell.

## Runtime Pieces

- Backend service:
  `src/files/custom/Espo/Modules/EspoDental/Services/DashboardActionCenterService.php`
- API controller:
  `src/files/custom/Espo/Modules/EspoDental/Controllers/Dashboard.php`
- Route:
  `GET /EspoDental/Dashboard/actionCenter`
- Dashlet metadata:
  `src/files/custom/Espo/Modules/EspoDental/Resources/metadata/dashlets/DashboardActionCenter.json`
- Client view:
  `src/files/client/custom/modules/espo-dental/src/views/dashlets/dashboard-action-center.js`

## Data Contract

`DashboardActionCenterService::getActionCenter` returns:

- `summary`: counts for waiting patients, pending actions, assigned tasks, open
  alerts and weekly appointments.
- `waitingPatients`: today appointments in `arrived` or `in_progress` status.
- `pendingActions`: pending `AssistantActionProposal` records for staff review.
- `assignedTasks`: native EspoCRM `Task` records assigned to the current user,
  excluding completed/cancelled tasks.
- `alerts`: open `LowStockAlert` rows.
- `weeklyWorkload`: seven-day appointment load for the dashboard chart/table.

The service deliberately uses native EspoCRM `Task` for the first migration pass
instead of adding a new task entity. A custom task entity is still possible later
only if native Task cannot satisfy assignment, visibility or status rules.

## UI Contract

The `DashboardActionCenter` dashlet is the first consumer of
`espo-dental:lib/simple-stom-ui`. It renders:

- compact KPI strip;
- waiting patient list;
- pending action/proposal list;
- current user's task list;
- stock alert list;
- weekly workload table.

All styling is scoped by `.espo-dental-stom`.

## Dashboard Templates

The action center is now seeded at the top of these templates:

- `EspoDental: рабочее место клиники`
- `EspoDental: администратор`
- `EspoDental: врач`
- `EspoDental: ассистент`
- `EspoDental: менеджер`
- `EspoDental: склад`

Existing specialized dashlets remain in place below the action center.
