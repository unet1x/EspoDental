# EspoDental — Administrator Guide / Руководство администратора

> EN sections come first, RU follow. Cross-reference by section number.

---

## 1. System requirements / Системные требования

| Component | Minimum |
| --- | --- |
| EspoCRM | **9.2.7** or newer |
| PHP | 8.2+ with `intl`, `pdo_mysql`, `gd`, `zip`, `curl` |
| MariaDB | 10.6+ (10.11 LTS recommended) |
| Disk | 5 GB for app + 1 GB / 1k patients |
| RAM | 1 GB for the EspoCRM container, +512 MB MariaDB |

For Synology DSM, the only supported runtime is **Container Manager**
(docker-compose v2). Bare-metal install on DSM packages is **not** supported.

| Компонент | Минимум |
| --- | --- |
| EspoCRM | **9.2.7** или новее |
| PHP | 8.2+ с `intl`, `pdo_mysql`, `gd`, `zip`, `curl` |
| MariaDB | 10.6+ (рекомендуется 10.11 LTS) |
| Диск | 5 ГБ под приложение + 1 ГБ / 1000 пациентов |
| RAM | 1 ГБ для контейнера EspoCRM, +512 МБ MariaDB |

---

## 2. Installation / Установка

### 2.1 Docker Compose on Synology DSM

The stack lives in [`deploy/docker-compose.yml`](../deploy/docker-compose.yml).
Steps:

1. Clone module sources to `/volume1/espomodule`.
2. Copy `deploy/docker-compose.yml` + `deploy/.env.example` to
   `/volume1/docker/espodental/` and edit `.env` (passwords, URL).
3. Create host folders and set ownership (see comments in the compose file).
4. `cd /volume1/docker/espodental && docker compose up -d`.
5. Open the site URL, complete the EspoCRM installer (the database is already
   provisioned via env), log in as the admin you specified.
6. **Administration → Extensions → Upload extension** →
   `build/EspoDental-X.Y.Z.zip`.

### 2.2 Bare-metal install

```bash
git clone https://github.com/<you>/EspoDental.git
cd EspoDental
composer install
make build
# upload build/EspoDental-X.Y.Z.zip in Espo Admin → Extensions
```

### 2.3 What `AfterInstall.php` does

When you install the module via **Administration → Extensions**, the bundled
`AfterInstall.php` runs automatically and prepares a ready-to-work workspace:

- Creates 5 teams (`EspoDental Doctors`, `EspoDental Assistants`, …).
- Creates 5 ACL roles (`EspoDental Manager`, `Doctor`, `Assistant`,
  `Administrator`, `Stock Manager`) with the full permission matrix across
  the 29 module scopes.
- Creates starter clinic data: one clinic, 5 cabinets, service categories,
  price-list services, material categories, starter stock, scheduled jobs,
  dashboard and menu layout.

The operation is **idempotent** — re-running it on existing installs is safe
and skips records that already exist.

For **Docker volume-mount installs** the Extensions UI flow is not used.
Run the same seeder from CLI:

```bash
docker compose exec espocrm php command.php espo-dental-bootstrap
```

The CLI command writes a summary of how many records were created.
`espo-dental-bootstrap` is the preferred alias; `espo-dental-seed-roles`
is kept for backwards compatibility.

### 2.1 Установка на Synology DSM

Стек лежит в [`deploy/docker-compose.yml`](../deploy/docker-compose.yml).
Шаги:

1. Клонировать модуль в `/volume1/espomodule`.
2. Положить `deploy/docker-compose.yml` и `deploy/.env.example` в
   `/volume1/docker/espodental/`, переименовать `.env.example` → `.env`,
   заполнить пароли и URL.
3. Создать каталоги под данные, выставить права (см. комментарии в compose).
4. `cd /volume1/docker/espodental && docker compose up -d`.
5. Открыть сайт, пройти установщик Espo (БД уже сконфигурирована из env),
   зайти под admin.
6. **Администрирование → Расширения → Загрузить** →
   `build/EspoDental-X.Y.Z.zip`.

### 2.2 Установка на железо

Идентично п. 2.2 EN: `composer install` → `make build` → загрузить zip.

### 2.3 Что делает `AfterInstall.php`

При установке через **Администрирование → Расширения** скрипт автоматически:

- Создаёт 5 команд (`EspoDental Doctors`, `EspoDental Assistants`, …).
- Создаёт 5 ролей (`EspoDental Manager / Doctor / Assistant / Administrator /
  Stock Manager`) с полной матрицей прав по 29 scope-ам модуля.
- Создаёт стартовое рабочее место: клинику, 5 кабинетов, категории услуг,
  прайс, складские категории, начальные остатки, регламентные задания,
  dashboard и порядок меню.

Операция **идемпотентна** — повторный запуск безопасен и пропускает
существующие записи.

Для **mount-установки в Docker** (когда модуль не ставится через UI)
можно запустить тот же сидер из CLI:

```bash
docker compose exec espocrm php command.php espo-dental-bootstrap
```

Команда печатает короткую сводку: сколько записей было создано.
Старая команда `espo-dental-seed-roles` оставлена как совместимый алиас.

---

## 3. Multi-clinic setup / Мульти-клиника

1. Create a record in **Clinic** for each branch.
2. Create **Cabinets** linked to each clinic with `order` and `capacity`.
3. Assign every staff **User** to a **Team** that maps to the clinic.
4. In **Administration → EspoDental Settings**, set **Default Clinic** when the
   installation currently works as one clinic. Reception forms will use it
   automatically.
5. Make sure `Patient`, `Appointment`, `Invoice`, `Material`, etc. have
   `clinicId` filled — the resource calendar, low-stock alerts, monthly
   revenue dashlet filter by clinic.

For accountants who need consolidated reports across all clinics, give them a
team that contains **all** clinic teams (Espo teams are sets, not trees).

1. Завести запись **Clinic** для каждого филиала.
2. Создать **Cabinet** с привязкой к клинике и параметрами `order` / `capacity`.
3. Назначить каждому пользователю **Team**, соответствующую его клинике.
4. В **Администрирование → EspoDental Settings** выбрать
   **Клиника по умолчанию**, если установка пока работает как одна клиника.
   Формы регистратуры будут использовать её автоматически.
5. У всех сущностей (`Patient`, `Appointment`, `Invoice`, `Material`…) должно
   быть заполнено `clinicId` — отчёты и календарь фильтруют по клинике.

---

## 4. Health questionnaire schema / Схема анкеты здоровья

Health questionnaire questions are stored in:

```text
src/files/custom/Espo/Modules/EspoDental/Resources/metadata/dental/questionnaireSchema.json
```

The schema contains groups and items. Current item types are:

- `bool` — yes/no medical question. Visible bool items are required on submit.
- `text` — free-text note. Text fields are optional unless a later schema rule
  marks them required.

Use `alert: true` on a bool item when a positive answer should raise a medical
alert flag. Conditional groups, such as female-specific questions, use the
`conditional.showIf.patientGender` rule.

After changing the schema on a mounted Docker install, run:

```bash
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php rebuild.php
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php command.php update-app-timestamp
```

Existing completed questionnaires are not rewritten automatically. The updated
schema applies to newly issued questionnaire forms and generated output.

Вопросы анкеты здоровья находятся в:

```text
src/files/custom/Espo/Modules/EspoDental/Resources/metadata/dental/questionnaireSchema.json
```

Схема состоит из групп и вопросов. Сейчас используются типы:

- `bool` — вопрос Да/Нет. Все видимые bool-вопросы обязательны при отправке.
- `text` — свободный текст. Пока текстовые поля необязательны, если отдельное
  правило схемы позже не сделает их обязательными.

`alert: true` означает, что положительный ответ должен поднимать медицинский
флаг. Условные группы, например женские вопросы, используют правило
`conditional.showIf.patientGender`.

После изменения схемы в Docker/mount-установке нужно выполнить:

```bash
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php rebuild.php
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php command.php update-app-timestamp
```

Уже заполненные анкеты автоматически не переписываются. Новая схема применяется
к новым формам и новой генерации документов.

---

## 5. Telegram reminders / Уведомления через Telegram

1. Create a bot in @BotFather, copy the token.
2. Put it into `.env` as `TELEGROM_BOT_TOKEN` (the variable is read at runtime
   by `TelegramSender`).
3. In EspoCRM open **Administration → Settings → EspoDental** and ensure:
   - `reminderTemplate` is configured for `appointment`.
   - `notificationChannels` includes `telegram`.
4. Pacients store their chat ID in `Patient.telegramChatId`. The reminder
   cron job `SendAppointmentReminders` runs every 15 min (configurable in the
   scheduled jobs UI) and writes results into `NotificationLog`.

1. Создать бота у @BotFather, скопировать токен.
2. Записать его в `.env` как `TELEGROM_BOT_TOKEN`.
3. В **Администрирование → Настройки → EspoDental**:
   - настроить шаблон `appointment`;
   - включить канал `telegram`.
4. Chat ID хранится в `Patient.telegramChatId`. Cron-задача
   `SendAppointmentReminders` (по умолчанию 1 раз в 15 минут) пишет результат
   в `NotificationLog`.

---

## 6. Backup / Резервное копирование

The recommended backup pipeline now lives in [`deploy/scripts/`](../deploy/scripts/):

| Script | Purpose |
| --- | --- |
| `backup-prod.sh`        | mysqldump + gzip + retention pruning (default 14 days) |
| `restore-to-staging.sh` | drops staging DB, imports the latest dump, rsyncs uploads, runs sanity check |
| `nightly.sh`            | orchestrator (cron entrypoint), retries the backup once on failure, sends Telegram + email alerts |
| `lib/common.sh`         | logging helpers, pipeline-id, `load_env` |
| `lib/alert.sh`          | `alert_telegram` (Bot API), `alert_email` (curl-SMTP) |

The legacy single-file `deploy/backup.sh` is left in place for backwards
compatibility but **`nightly.sh` is now the recommended cron entry**.

See **section 10** for the full staging + nightly workflow.

Пайплайн бэкапов теперь живёт в [`deploy/scripts/`](../deploy/scripts/):

| Скрипт | Назначение |
| --- | --- |
| `backup-prod.sh`        | mariadb-dump + gzip + ротация (по умолчанию 14 дней) |
| `restore-to-staging.sh` | роняет БД staging, импортирует свежий дамп, rsync загрузок, sanity-check |
| `nightly.sh`            | оркестратор (точка входа для cron), повторяет бэкап при сбое, шлёт Telegram + email |
| `lib/common.sh`         | логирование, pipeline-id, `load_env` |
| `lib/alert.sh`          | `alert_telegram`, `alert_email` |

Старый одиночный `deploy/backup.sh` оставлен для совместимости, но
**`nightly.sh` — рекомендуемая cron-задача**. Полная схема — в **разделе 10**.

---

## 7. Upgrade / Обновление

1. **Always back up first** (section 6).
2. Pull the new sources: `cd /volume1/espomodule && git pull`.
3. Rebuild the zip: `make build`.
4. In Espo: **Administration → Extensions** → upload the new zip
   (existing extension is replaced; `AfterInstall.php` runs again and is
   idempotent on ACL).
5. **Administration → Rebuild** (Espo will also offer it automatically).
6. Bounce the daemon container so cron picks up new job definitions:
   `docker compose restart espocrm-daemon`.

1. **Сначала бэкап** (п. 6).
2. Обновить сорсы: `cd /volume1/espomodule && git pull`.
3. Пересобрать zip: `make build`.
4. В Espo: **Администрирование → Расширения** → загрузить новый zip.
5. **Администрирование → Перестроить**.
6. Перезапустить daemon-контейнер: `docker compose restart espocrm-daemon`.

---

## 8. Troubleshooting / Решение проблем

| Symptom | What to check |
| --- | --- |
| Module pages 404 after install | Run **Administration → Clear cache + Rebuild** |
| Telegram not sending | Check `TELEGROM_BOT_TOKEN` env, look at `NotificationLog` |
| Resource calendar empty | Cabinet records exist and `clinicId` set on Appointments |
| `AfterInstall` errors on upgrade | Run `docker compose exec espocrm php rebuild.php` |
| Low-stock alerts not appearing | Cron job `CheckStockThresholds` enabled |

| Симптом | Что проверить |
| --- | --- |
| 404 на страницах модуля | **Очистить кэш + Перестроить** |
| Telegram не шлёт | Токен в env, лог `NotificationLog` |
| Пустой календарь ресурсов | Кабинеты заведены, `clinicId` в Appointment заполнен |
| Ошибки `AfterInstall` | `docker compose exec espocrm php rebuild.php` |
| Не приходят алерты по складу | Включён cron `CheckStockThresholds` |

---

## 9. Security checklist / Чек-лист безопасности

- [ ] Strong `MARIADB_ROOT_PASSWORD` (16+ chars, mixed case).
- [ ] HTTPS via DSM Reverse Proxy (Let's Encrypt).
- [ ] No public exposure of `ESPOCRM_HTTP_PORT` — only via reverse proxy.
- [ ] Daily `backup.sh` to a separate volume.
- [ ] Hyper Backup off-site (USB / Backblaze B2).
- [ ] Two-factor auth in EspoCRM for all admin and doctor users.
- [ ] Update EspoCRM image at least once a quarter.

- [ ] Стойкий `MARIADB_ROOT_PASSWORD`.
- [ ] HTTPS через Reverse Proxy DSM.
- [ ] Порты не торчат наружу напрямую.
- [ ] Ежедневный `backup.sh`.
- [ ] Off-site бэкап (Hyper Backup).
- [ ] 2FA для всех админов и докторов.
- [ ] Обновление образа EspoCRM не реже раза в квартал.

---

## 10. Staging environment + nightly pipeline / Staging-стенд и ночной конвейер

### 10.1 Why two stacks / Зачем два стека

EspoDental ships with a second compose file
[`deploy/staging/docker-compose.yml`](../deploy/staging/docker-compose.yml)
that brings up an **identical** EspoCRM stack on the same Synology, on
different ports (`8090/8091`). Purpose:

- **Test upgrades** of the EspoDental module before they hit prod.
- **Rehearse restores** — staging is rebuilt every night from the latest prod
  dump, so a failed restore on staging means the prod backup is invalid and
  alerts fire immediately.
- **Crash dummy** for risky operations (cabinet renaming, scheduled-job
  changes, role tweaks) before applying them to live patients.

Staging holds **real patient data** (no sanitization) and must therefore be
treated as production-sensitive: limit access to the same person who has
prod admin rights (single-admin model).

EspoDental поставляется со вторым compose-файлом
[`deploy/staging/docker-compose.yml`](../deploy/staging/docker-compose.yml) —
он поднимает идентичный стек EspoCRM на том же Synology, но на других портах
(`8090/8091`). Назначение:

- **Репетиция обновлений** модуля до раскатки на прод.
- **Репетиция бэкапа** — staging пересобирается каждую ночь из свежего
  дампа прода; провал восстановления = плохой бэкап = алерт.
- **Песочница** для рискованных операций.

В staging лежат **реальные ПДн** (без обезличивания), поэтому доступ
ограничен тем же админом, что и к проду.

### 10.2 Directory layout / Раскладка по дискам

```
/volume1/docker/espodental/             ← prod compose + .env
/volume1/docker/espodental-staging/     ← staging compose + .env
/volume1/docker/espodental-staging/bd/  ← staging MariaDB data

/volume1/espomodule-prod/               ← module sources for PROD (git clone)
/volume1/espomodule-staging/            ← module sources for STAGING (git clone)

/volume2/espodental/data/               ← prod EspoCRM 'data' (incl. upload/)
/volume2/espodental-staging/data/       ← staging EspoCRM 'data'
/volume2/espodental/backups/            ← gzipped dumps + tar archives
/volume2/espodental/logs/               ← nightly.sh logs
```

The **two separate git clones** are critical: prod and staging carry
different commits during testing, so a `git pull` in one does not affect
the other.

Два независимых git-клона критически важны: prod и staging держат разные
коммиты во время тестирования, и `git pull` в одном не трогает другой.

### 10.3 First-time staging setup / Первая настройка staging

```bash
# 1. Create host dirs and fix ownership
sudo mkdir -p /volume1/docker/espodental-staging/bd \
              /volume2/espodental-staging/data
sudo chown -R 999:999 /volume1/docker/espodental-staging/bd
sudo chown -R 33:33   /volume2/espodental-staging/data

# 2. Clone module sources for staging (separate from prod!)
sudo git clone https://github.com/<you>/EspoDental.git \
    /volume1/espomodule-staging
sudo chown -R 33:33 /volume1/espomodule-staging

# 3. Drop compose + env into place
sudo cp deploy/staging/docker-compose.yml /volume1/docker/espodental-staging/
sudo cp deploy/staging/.env.example       /volume1/docker/espodental-staging/.env
sudo nano /volume1/docker/espodental-staging/.env   # set strong passwords

# 4. Bring it up empty (will be overwritten by tonight's restore)
cd /volume1/docker/espodental-staging
sudo docker compose up -d
```

Once the stack is up, the first nightly run (or a manual
`bash deploy/scripts/restore-to-staging.sh`) will replace the empty schema
with a copy of prod.

### 10.4 Cron schedule / Расписание cron

In DSM **Control Panel → Task Scheduler** add a *user-defined script*:

| Field | Value |
| --- | --- |
| Run as | `root` (or a user with Docker socket access) |
| Schedule | Daily, 02:00 |
| Command | `bash /volume1/espomodule-prod/deploy/scripts/nightly.sh` |
| On error | (Mail tab) — already covered by `alert_email`, no DSM mail needed |

The script writes a self-contained log to `/volume2/espodental/logs/nightly-*.log`.

В DSM **Панель управления → Планировщик задач** добавь *скрипт пользователя*:

| Поле | Значение |
| --- | --- |
| Выполнять от имени | `root` |
| Расписание | Ежедневно, 02:00 |
| Команда | `bash /volume1/espomodule-prod/deploy/scripts/nightly.sh` |

Лог пишется в `/volume2/espodental/logs/nightly-*.log`.

### 10.5 What the pipeline does each night / Что делает конвейер каждую ночь

1. `backup-prod.sh` → `mariadb-dump --single-transaction --quick --routines
   --triggers --events` of the prod DB, gzip into
   `db-YYYYMMDD-HHMMSS.sql.gz`. Optional `tar` of `data/upload`. Updates
   `db-latest.sql.gz` and `files-latest.tar.gz` symlinks. Prunes dumps older
   than `BACKUP_RETENTION_DAYS=14`.
2. If step 1 failed — wait 60 s, retry **once**.
3. If step 1 failed twice — Telegram + email alert *"Backup FAILED twice"*,
   pipeline aborts with exit 1.
4. `restore-to-staging.sh` → stop staging web tier, `DROP DATABASE` + recreate,
   `gunzip | mariadb`, `rsync` uploads, restart staging, `rebuild.php`.
5. **Sanity check**: poll `STAGING_HEALTH_URL` until HTTP 200 (up to
   `STAGING_HEALTH_TIMEOUT_SEC=180`), then compare `SELECT COUNT(*) FROM
   patient` on prod and staging — they must be **equal**.
6. On sanity-check failure → Telegram + email *"Staging sanity check FAILED"*
   with explicit recommended action (re-run backup manually). Exit 3.
7. On other restore failure → Telegram + email *"Restore to staging FAILED"*.
   Exit 2.

Все шаги протоколируются с единым `pipeline_id` (формат `YYYYMMDDTHHMMSS-PID`),
который попадает в каждую строку лога и в текст алерта.

### 10.6 Promotion workflow / Раскатка изменений со staging на prod

The supported flow is **git-pull-only** — staging is the rehearsal, prod
gets the same commits **after** staging has been verified by hand.

```bash
# === 1. Promote a tag/branch to STAGING ===
cd /volume1/espomodule-staging
sudo git fetch --tags
sudo git checkout v0.17.0          # or 'main' for trunk

cd /volume1/docker/espodental-staging
sudo docker compose exec espocrm php rebuild.php
sudo docker compose exec espocrm php command.php espo-dental-bootstrap

# Open https://staging-dental.example.com/ → smoke-test as admin:
#   - dashboard loads
#   - create/edit a test appointment
#   - check the resource calendar
#   - run an invoice through, mark it paid
#   - verify reports render

# === 2. If anything is off — stay on staging, fix, retest ===
# === 3. Once staging is green — promote IDENTICAL ref to PROD ===

cd /volume1/espomodule-prod
sudo git fetch --tags
sudo git checkout v0.17.0          # same ref as staging

cd /volume1/docker/espodental
sudo docker compose exec espocrm php rebuild.php
sudo docker compose exec espocrm php command.php espo-dental-bootstrap
```

The next nightly run will reset staging back to a *fresh prod copy*, so any
manual tweaks made directly inside staging during smoke-testing are
intentionally **lost**.

Поддерживаемый сценарий — **только git-pull**: staging — это репетиция,
после ручной приёмки прод получает те же коммиты.

```bash
# === 1. Раскатываем тег/ветку в STAGING ===
cd /volume1/espomodule-staging
sudo git fetch --tags
sudo git checkout v0.17.0

cd /volume1/docker/espodental-staging
sudo docker compose exec espocrm php rebuild.php
sudo docker compose exec espocrm php command.php espo-dental-bootstrap

# Открыть https://staging-dental.example.com/ и пройти приёмку:
#   - дашборд грузится
#   - создать/изменить тестовую запись
#   - проверить календарь кабинетов
#   - провести инвойс, отметить оплату
#   - убедиться, что отчёты строятся

# === 2. Если что-то не так — остаёмся на staging, чиним, перетестируем ===
# === 3. Когда staging зелёный — катим ТУ ЖЕ ревизию на PROD ===

cd /volume1/espomodule-prod
sudo git fetch --tags
sudo git checkout v0.17.0

cd /volume1/docker/espodental
sudo docker compose exec espocrm php rebuild.php
sudo docker compose exec espocrm php command.php espo-dental-bootstrap
```

Следующий ночной прогон сбросит staging до свежей копии прода — все ручные
правки, сделанные в staging во время приёмки, **сознательно теряются**.

### 10.7 Rollback / Откат

If a promotion turns out to be bad on prod:

```bash
cd /volume1/espomodule-prod
sudo git checkout v0.16.0          # previous tag

cd /volume1/docker/espodental
sudo docker compose exec espocrm php rebuild.php
sudo docker compose exec espocrm php command.php espo-dental-bootstrap
```

If schema changes were applied that the previous tag does not understand —
restore the latest pre-upgrade dump manually:

```bash
ls /volume2/espodental/backups/db-*.sql.gz | tail -2
# pick the older one
DUMP=/volume2/espodental/backups/db-20260510-020013.sql.gz
sudo gunzip -c "${DUMP}" | sudo docker compose exec -T mariadb \
    mariadb -uroot -p${MARIADB_ROOT_PASSWORD} ${ESPOCRM_DATABASE_NAME}
```

Если апдейт сломал прод:

```bash
cd /volume1/espomodule-prod
sudo git checkout v0.16.0

cd /volume1/docker/espodental
sudo docker compose exec espocrm php rebuild.php
sudo docker compose exec espocrm php command.php espo-dental-bootstrap
```

Если изменения схемы несовместимы с откатываемым тегом — восстанови
предыдущий дамп вручную (см. EN-блок выше).
