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
`AfterInstall.php` runs automatically and does **three** things:

- Creates 5 teams (`EspoDental Doctors`, `EspoDental Assistants`, …).
- Creates 5 ACL roles (`EspoDental Manager`, `Doctor`, `Assistant`,
  `Administrator`, `Stock Manager`) with the full permission matrix across
  the 29 module scopes.
- Creates 8 starter service categories (Therapy, Surgery, Orthopedics,
  Orthodontics, Hygiene, Diagnostics, Implantology, Pediatric).

The operation is **idempotent** — re-running it on existing installs is safe
and skips records that already exist.

For **Docker volume-mount installs** the Extensions UI flow is not used.
Run the same seeder from CLI:

```bash
docker compose exec espocrm php command.php espo-dental-seed-roles
```

The CLI command writes a summary of how many records were created.

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
- Создаёт 8 стартовых категорий услуг (Therapy, Surgery, Orthopedics,
  Orthodontics, Hygiene, Diagnostics, Implantology, Pediatric).

Операция **идемпотентна** — повторный запуск безопасен и пропускает
существующие записи.

Для **mount-установки в Docker** (когда модуль не ставится через UI)
можно запустить тот же сидер из CLI:

```bash
docker compose exec espocrm php command.php espo-dental-seed-roles
```

Команда печатает короткую сводку: сколько записей было создано.

---

## 3. Multi-clinic setup / Мульти-клиника

1. Create a record in **Clinic** for each branch.
2. Create **Cabinets** linked to each clinic with `order` and `capacity`.
3. Assign every staff **User** to a **Team** that maps to the clinic.
4. Make sure `Patient`, `Appointment`, `Invoice`, `Material`, etc. have
   `clinicId` filled — the resource calendar, low-stock alerts, monthly
   revenue dashlet filter by clinic.

For accountants who need consolidated reports across all clinics, give them a
team that contains **all** clinic teams (Espo teams are sets, not trees).

1. Завести запись **Clinic** для каждого филиала.
2. Создать **Cabinet** с привязкой к клинике и параметрами `order` / `capacity`.
3. Назначить каждому пользователю **Team**, соответствующую его клинике.
4. У всех сущностей (`Patient`, `Appointment`, `Invoice`, `Material`…) должно
   быть заполнено `clinicId` — отчёты и календарь фильтруют по клинике.

---

## 4. Telegram reminders / Уведомления через Telegram

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

## 5. Backup / Резервное копирование

The script [`deploy/backup.sh`](../deploy/backup.sh) does a 3-step backup:

1. `mysqldump` of the EspoCRM database.
2. `tar` of `/var/www/html/data` (uploads + config).
3. Rotation: deletes archives older than `BACKUP_RETENTION_DAYS`.

Schedule it via DSM Task Scheduler (daily, 02:00) and point `BACKUP_DIR` at a
volume that is included in your **Hyper Backup** plan.

Cкрипт [`deploy/backup.sh`](../deploy/backup.sh):

1. `mysqldump` БД EspoCRM.
2. `tar` каталога `/var/www/html/data` (загрузки + конфиг).
3. Удаление архивов старше `BACKUP_RETENTION_DAYS`.

Запускать через **DSM → Планировщик задач** (ежедневно в 02:00), `BACKUP_DIR`
направить на том, попадающий в **Hyper Backup**.

---

## 6. Upgrade / Обновление

1. **Always back up first** (section 5).
2. Pull the new sources: `cd /volume1/espomodule && git pull`.
3. Rebuild the zip: `make build`.
4. In Espo: **Administration → Extensions** → upload the new zip
   (existing extension is replaced; `AfterInstall.php` runs again and is
   idempotent on ACL).
5. **Administration → Rebuild** (Espo will also offer it automatically).
6. Bounce the daemon container so cron picks up new job definitions:
   `docker compose restart espocrm-daemon`.

1. **Сначала бэкап** (п. 5).
2. Обновить сорсы: `cd /volume1/espomodule && git pull`.
3. Пересобрать zip: `make build`.
4. В Espo: **Администрирование → Расширения** → загрузить новый zip.
5. **Администрирование → Перестроить**.
6. Перезапустить daemon-контейнер: `docker compose restart espocrm-daemon`.

---

## 7. Troubleshooting / Решение проблем

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

## 8. Security checklist / Чек-лист безопасности

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
