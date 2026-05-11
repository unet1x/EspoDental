# EspoDental — User Guide / Руководство пользователя

> Quick reference for daily workflows. Sections are paired EN / RU.

---

## 1. Reception desk / Регистратура

### 1.1 Pre-registering a patient (anonymous link)

1. Open **Preliminary Patient**, click **+ Create**.
2. Fill in name, phone, e-mail.
3. Save → "Send questionnaire" — copies a public URL with `QuestionnaireToken`.
4. Patient opens the link, fills the **Health Questionnaire** in the browser.
5. When they arrive, click **Convert** on the preliminary patient — it becomes
   a full `Patient` linked to the same questionnaire.

### 1.1 Предварительная регистрация (анонимная ссылка)

1. Открыть **Preliminary Patient → Создать**.
2. Имя, телефон, e-mail.
3. Кнопка "Отправить анкету" — копирует публичный URL c `QuestionnaireToken`.
4. Пациент проходит **Health Questionnaire** в браузере.
5. По приходу — кнопка **Convert** превращает запись в полноценного `Patient`,
   анкета остаётся связанной.

### 1.2 Booking an appointment

1. Open **Resource Calendar** dashlet on the Home dashboard.
2. Click the **Find slot** button, enter desired duration (e.g. 30 min).
3. Pick a slot from the list, click it to open the cabinet/time cell.
4. Fill in patient, doctor, service. The conflict-detection hook
   (`CheckConflicts`) prevents overlapping bookings.

### 1.2 Запись на приём

1. Дашлет **Resource Calendar**.
2. Кнопка **Find slot**, ввести длительность.
3. Выбрать слот, кликнуть по нужной ячейке кабинета.
4. Заполнить пациента, доктора, услугу. Конфликты блокируются автоматически.

### 1.3 Drag-and-drop reschedule

- Drag an appointment card to a new cell — moves time and cabinet.
- Drag the bottom edge — resizes duration.
- Click an empty cell — opens "Create appointment" pre-filled.

### 1.3 Перенос приёма

- Тянуть карточку в новую ячейку — меняется время и кабинет.
- Тянуть нижний край — меняется длительность.
- Клик по пустой ячейке — открывает форму создания приёма с подставленными
  параметрами.

---

## 2. Doctor / Доктор

### 2.1 Tooth chart

`ToothChartSnapshot` keeps a versioned snapshot of teeth state. Open the
patient, scroll to "Tooth charts", create a new snapshot from a visit and
fill conditions per tooth (caries, root canal, etc.). Old snapshots are
read-only and serve as history.

`ToothChartSnapshot` — версионированный снимок состояния зубов. У пациента
открыть раздел "Tooth charts", создать новый снимок из визита, проставить
состояния. Прошлые снимки read-only — служат историей.

### 2.2 Orthodontic case

1. From `Patient` → **+ Orthodontic Card** (auto-number `ORTHO-YYYY-NNNNN`).
2. Fill diagnosis: malocclusion class, skeletal class, growth stage,
   apparatus type.
3. Plan the treatment in **Treatment Stage** (sequence + duration).
4. For each tooth that should move, add **Tooth Movement Plan**
   with target millimetres / degrees.
5. Capture **Ortho Photos** (14 photo types × 4 phases) — optionally store
   Orthanc UID so X-ray viewer can fetch the original DICOM.
6. Add **Cephalometric Measurement** entries — normal ranges show next to
   the value.
7. On finish: click **Close Card** → choose final status `completed` / `cancelled`.

1. От пациента — **+ Orthodontic Card** (авто-номер).
2. Диагноз: класс окклюзии, скелетный класс, стадия роста, аппарат.
3. План — **Treatment Stage**.
4. На каждый зуб — **Tooth Movement Plan** с целевыми мм/градусами.
5. **Ortho Photo** (14 типов × 4 фазы), при необходимости — Orthanc UID.
6. **Cephalometric Measurement** — рядом виден диапазон нормы.
7. По завершению — **Close Card**.

### 2.3 Visit & invoice

1. Open the appointment → **Start Visit** → fill `VisitServiceLine` items
   (services + materials).
2. Click **Finish Visit** — auto-creates an `Invoice` in `draft` state.
3. The cashier confirms the invoice → `Payment` records (cash / card).
4. Visit goes into the patient timeline; salary entries pick it up next month.

1. Открыть приём → **Начать визит** → строки `VisitServiceLine`.
2. **Завершить визит** — создаётся `Invoice` в статусе `draft`.
3. Кассир подтверждает счёт → `Payment`.
4. Визит уходит в историю; ЗП-начисления подхватят его в конце месяца.

---

## 3. Cash desk / Касса

- **Today's Appointments** dashlet — list of bookings filtered to today.
- **Open Invoices** dashlet — invoices in `draft` / `unpaid` / `partially_paid`.
- Click an invoice → **Register Payment** → choose method (cash / card / bank).
- Monthly Revenue dashlet plots a per-day SVG bar chart by `Payment` records.

- Дашлет **Today's Appointments** — приёмы на сегодня.
- Дашлет **Open Invoices** — счета `draft` / `unpaid` / `partially_paid`.
- В счёте — **Зарегистрировать оплату** → способ (наличные / карта / банк).
- Дашлет **Monthly Revenue** — столбчатый SVG-график по `Payment`.

---

## 4. Inventory / Склад

1. **Material** records hold name, unit, current balance, threshold.
2. Each **Service** has a list of **Service Materials** with consumption rate
   per visit.
3. Finishing a visit triggers `StockMovement(out)` for every consumed material.
4. Cron `CheckStockThresholds` (default 1 / hour) creates `LowStockAlert`
   when balance < threshold; dashlet shows them with the option to acknowledge.

1. **Material** — название, единица, остаток, порог.
2. **Service** содержит **Service Material** — расход на услугу.
3. Завершение визита создаёт `StockMovement(out)`.
4. Cron `CheckStockThresholds` (раз в час) создаёт `LowStockAlert`; в дашлете
   их можно закрывать.

---

## 5. Manager / Менеджер

### 5.1 Salary

1. **Salary Profile** per employee with rate type:
   `fixed` / `per_visit` / `percent_revenue` / `mixed`.
2. End of month: **Salary Entry** is built (button "Build entry" in toolbar)
   from the doctor's visits in the period.
3. Manager reviews, adjusts `SalaryBonus` items (kind: `bonus` / `penalty` /
   `correction`) and clicks **Approve**.
4. Click **Pay** — auto-creates `Payment(direction=out)`.
5. Use the **Payroll This Month** dashlet to track unpaid totals.

1. **Salary Profile** на сотрудника, тип ставки:
   `fixed` / `per_visit` / `percent_revenue` / `mixed`.
2. В конце месяца — **Salary Entry** (кнопка "Построить"); считает по визитам.
3. Корректировки через **Salary Bonus** (`bonus` / `penalty` / `correction`).
4. **Утвердить → Выплатить** → создаётся `Payment(out)`.
5. Дашлет **Payroll This Month** — мониторинг невыплаченных сумм.

### 5.2 Reports

The module ships 18 saved bool-filters that you can combine in list views.
Five dashlets cover the most common questions:

| Dashlet | Purpose |
| --- | --- |
| Today's Appointments | front-desk daily plan |
| Open Invoices | unpaid AR |
| Low-Stock Materials | items below threshold |
| Recent Visits | last N visits across clinics |
| Monthly Revenue | daily revenue chart for current month |
| Payroll This Month | unpaid salary entries |
| Active Ortho Cases | open / in-treatment / retention orthodontic cards |

18 готовых bool-фильтров комбинируются в списках. Дашлеты — см. таблицу выше.

---

## 6. Tips / Подсказки

- **Keyboard:** in any list, `Ctrl+/` opens search; `n` opens "create new".
- **Bulk actions:** check several rows → **Mass Action → Set Field Value**.
- **Stream:** stay subscribed to your patients to receive event updates.
- **Export:** any list can be exported to XLSX / CSV from the **⋮** menu.

- **Клавиатура:** в списке `Ctrl+/` — поиск, `n` — создать.
- **Массовые действия:** отметить строки → **Mass Action → Set Field Value**.
- **Stream:** подписка на пациента — все события приходят в ленту.
- **Экспорт:** любой список — XLSX / CSV из меню **⋮**.
