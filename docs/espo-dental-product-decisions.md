# EspoDental Product Decisions

Дата: 2026-05-26.

Этот документ фиксирует продуктовые решения для дальнейшей разработки
EspoDental после сверки с `/Users/unet1x/Codex/SimpleStom`.

Статусы:

- Accepted - решение принято и должно учитываться в разработке.
- Deferred - решение не входит в ближайший этап, но не запрещено.
- Review - нужно пересмотреть после демо или пользовательской проверки.

## Accepted Decisions

| ID | Статус | Решение | Почему | Влияет на |
| --- | --- | --- | --- | --- |
| ED-001 | Accepted | SimpleStom остается продуктовым и UX-референсом, но runtime EspoDental остается EspoCRM module. | Не нужно переносить FastAPI/React как отдельное приложение; нужно развивать модуль внутри EspoCRM. | Архитектура, UI, deployment. |
| ED-002 | Accepted | Ближайшая работа идет как product hardening, а не переписывание ядра. | В EspoDental уже есть основные сущности и доменные сервисы: запись, анкета, портал, прием, склад, касса, отчеты и зарплата. | Roadmap, backlog. |
| ED-003 | Accepted | Cabinet/procedure requirements в ближайшем этапе остаются JSON-backed: `Service.cabinetRequirements` плюс настройки/поля кабинета. Новая сущность capability добавляется только если UX фильтрации потребует управляемый справочник. | В коде уже есть `Service.cabinetRequirements`; отдельная сущность преждевременна до финального календарного UX. | Calendar, service catalog, cabinet settings. |
| ED-004 | Accepted | Patient flags на ближайший этап покрываются существующими `vip`, `restrictions`, questionnaire alerts и статусами. Отдельный `PatientFlagDefinition` откладывается до явной потребности в клинико-управляемых тегах. | Текущие риски пациента уже структурированы; отдельный справочник может раздуть UX раньше времени. | Patient workspace, filters, reports. |
| ED-005 | Accepted | Family в MVP остается через linked parent/guardian, child patients и manual guardian fields. Общая семейная граф-модель между любыми пациентами откладывается. | Сейчас главный юридический сценарий - несовершеннолетние и представитель. Скидки/семейные отчеты можно добавить позже. | Patient workspace, care summary, discounts. |
| ED-006 | Accepted | Patient portal authentication canonical path - OTP session with hashed code/token and audit events. Signed links используются для узких token flows вроде анкеты, но не как основной вход в портал. | В текущем коде уже есть `PatientPortalSession` с `otpHash`, attempts, lock and events; это ближе к SimpleStom. | Portal, security, notifications. |
| ED-007 | Accepted | Reschedule request должен быть dedicated `AppointmentRescheduleRequest`, а AI/assistant работает через `AssistantActionProposal` только как review/proposal layer. | Перенос записи не должен напрямую мутировать календарь без подтверждения клиники. | Portal, dashboard, calendar. |
| ED-008 | Accepted | Cash shift остается отдельной сущностью `CashShift`; закрытие смены должно быть операционным действием, а не только отчетом. | Касса требует проверяемые totals и связь с платежами. | Cash desk, payments, audit. |
| ED-009 | Accepted | Reports стартуют от существующих report endpoints, dashlets and `ReportDefinition`. Полный report builder уровня SimpleStom добавляется только после manager demo review. | Сейчас важнее стабильные управленческие отчеты и демо-сценарии, чем универсальный конструктор. | Reports, payroll, manager dashboard. |
| ED-010 | Accepted | AI/MCP не получает прямые medical/financial mutations. Разрешены narrow tools, patient context и proposal records с human approval для рискованных действий. | Это уже соответствует текущему MCP/assistant design и снижает риск. | Integrations, virtual administrator. |
| ED-011 | Accepted | Главный acceptance source - end-to-end demo клиники, а не наличие отдельных entity screens. | SimpleStom показал, что продукт оценивается рабочим днем клиники: звонок, запись, анкета, прием, счет, оплата, склад, следующая запись. | Demo seed, QA, release readiness. |

## Deferred Decisions

| ID | Статус | Решение | Условие возврата |
| --- | --- | --- | --- |
| ED-D01 | Deferred | Отдельная сущность `CabinetCapability`. | Вернуть, если JSON-backed requirements мешают удобному календарному фильтру или отчетам по кабинетам. |
| ED-D02 | Deferred | Отдельный справочник клинических patient flags. | Вернуть, если врачам/администраторам нужны настраиваемые цветные теги кроме VIP/restrictions/questionnaire alerts. |
| ED-D03 | Deferred | Общая сущность семейных связей между любыми пациентами. | Вернуть при реализации семейных скидок, семейной аналитики или связей супруг/родственник без child/guardian. |
| ED-D04 | Deferred | Полный произвольный report builder. | Вернуть после manager demo, если сохраненных report definitions и готовых отчетов недостаточно. |

## Immediate Working Rule

Следующий этап начинается с UX-language pass и календаря. Новые сущности вносятся
только если они нужны для конкретного вертикального workflow и не могут быть
выражены существующими entity/service contracts без ухудшения UX.
