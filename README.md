# EspoDental

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)]()
[![EspoCRM](https://img.shields.io/badge/EspoCRM-%E2%89%A59.2-1F77B4)]()
[![Tests](https://img.shields.io/badge/tests-180-2CA02C)]()

> A free, MIT-licensed **dental clinic information system** built on top of
> [EspoCRM](https://www.espocrm.com/) 9.2+. Multi-clinic from day one, RU / EN / ES,
> designed for self-hosting on a Synology NAS or any Linux host.

---

## English

### Why EspoDental

Most affordable dental MIS solutions are SaaS-only with monthly per-doctor pricing
or proprietary on-prem licenses. EspoDental rides on the back of EspoCRM (free) and
adds **only** the bits a small / mid-sized clinic actually needs:

- Patient cards with tooth charts and treatment journals.
- Resource calendar with drag-and-drop, week view, free-slot finder.
- Invoices, payments, cash desk reports.
- Inventory of materials with low-stock alerts.
- Appointment reminders via Telegram (and email).
- Salary calculation with per-doctor / per-assistant rates and bonuses.
- Orthodontic module with cephalometry, tooth-movement plans and Orthanc UID
  references for CBCT/X-ray.

Multi-clinic out of the box — every entity is scoped by `Clinic`.

### Highlights

| Area | What you get |
| --- | --- |
| Patients | `Patient`, `Visit`, `ToothChartSnapshot`, `HealthQuestionnaire`, anonymous self-fill links via `QuestionnaireToken` |
| Scheduling | `Appointment` (+ status log), `Cabinet`, conflict-prevention hook, week view, drag-resize, slot finder |
| Cash desk | `Invoice` / `InvoiceLine`, `Payment` (in/out), `Service`, monthly revenue dashlet, SVG chart |
| Inventory | `Material`, `MaterialCategory`, `StockMovement`, `ServiceMaterial`, `LowStockAlert`, threshold cron |
| Notifications | `NotificationLog`, Telegram bot sender, reminder templates, cron job |
| Reports | 18 saved bool-filters, 5 dashlets (today, OPEN invoices, low-stock, recent visits, monthly revenue) |
| Salary | `SalaryProfile`, `SalaryEntry`, `SalaryBonus`, 4 rate types, payout via `Payment(direction=out)` |
| Orthodontics | `OrthodonticCard` (auto-number `ORTHO-YYYY-NNNNN`), `TreatmentStage`, `ToothMovementPlan` (FDI + 8 movement axes), `OrthoPhoto`, `CephalometricMeasurement` |
| Localisation | Full RU / EN / ES |

### Install

#### Production: Docker (Synology DSM / Linux)

The Compose stack mounts the module sources directly into the EspoCRM
container — **no zip upload is needed**.

```bash
# 1. Clone module sources
sudo git clone https://github.com/unet1x/EspoDental.git /volume1/espomodule

# 2. Copy stack and env
mkdir -p /volume1/docker/espodental
cp /volume1/espomodule/deploy/docker-compose.yml /volume1/docker/espodental/
cp /volume1/espomodule/deploy/.env.example       /volume1/docker/espodental/.env
$EDITOR /volume1/docker/espodental/.env          # set passwords, URLs

# 3. Prepare host folders
sudo mkdir -p /volume1/docker/espodental/bd /volume2/espodental/data
sudo chown -R 999:999 /volume1/docker/espodental/bd
sudo chown -R 33:33   /volume2/espodental/data
sudo chown -R 33:33   /volume1/espomodule

# 4. Start the stack
cd /volume1/docker/espodental && docker compose up -d
docker compose ps                                # wait until both healthy

# 5. Finish EspoCRM installer in a browser: open http://<nas>:8080/
#    Database / admin fields are pre-filled from .env, click Next.

# 6. Register the module and seed teams + roles + service categories.
docker compose exec espocrm php rebuild.php
docker compose exec espocrm php command.php espo-dental-seed-roles
```

The last command is idempotent — safe to re-run after every upgrade. It
creates the five `EspoDental ...` teams, the five `EspoDental ...` roles
with the full ACL matrix, and the eight starter service categories
(Therapy, Surgery, Orthopedics, …).

Assign the roles to users in **Administration → Users**, then move on to
[docs/user-guide.md](docs/user-guide.md) for day-to-day workflows.

#### Production: install via Extensions UI

If you prefer the classic Extension-installer flow (e.g. you manage EspoCRM
on bare-metal and don't use the Compose stack):

```bash
git clone https://github.com/unet1x/EspoDental.git
cd EspoDental
bash bin/build                          # produces build/EspoDental-X.Y.Z.zip
```

Then upload the zip via **Administration → Extensions → Upload extension**.
`AfterInstall.php` runs automatically and seeds teams, roles and service
categories.

You can also download a pre-built zip from the
[Releases page](https://github.com/unet1x/EspoDental/releases) instead of
running `bin/build`.

#### Development install

```bash
git clone https://github.com/unet1x/EspoDental.git
cd EspoDental
composer install                       # phpcs, phpunit, phpstan
vendor/bin/phpunit tests --no-coverage # run the test suite
bash bin/build                         # build the zip
```

### Roles created on install

| Role | Scope |
| --- | --- |
| **EspoDental Manager** | all entities, full CRUD |
| **EspoDental Doctor** | team-level for clinical data; own salary / payroll |
| **EspoDental Assistant** | team-level reads, can add visits, photos, materials |
| **EspoDental Administrator** | front-desk + cash-desk, no edit on clinical history |
| **EspoDental Stock Manager** | inventory only |

### Development

```bash
composer install
vendor/bin/phpunit tests --no-coverage   # 168 tests / 1916 assertions
vendor/bin/phpcs --standard=phpcs.xml    # PSR-12
make build                               # build/EspoDental-X.Y.Z.zip
```

A Docker-Compose smoke test that boots MariaDB + EspoCRM + this module is at
[`deploy/smoke/`](deploy/smoke/). Run `bash deploy/smoke/smoke.sh` to verify
the module loads with a fresh stack.

### Staging + nightly pipeline

EspoDental ships a second Compose stack
[`deploy/staging/docker-compose.yml`](deploy/staging/docker-compose.yml) that
hosts a **staging clone** on the same Synology (ports 8090/8091). A cron-driven
pipeline at [`deploy/scripts/nightly.sh`](deploy/scripts/nightly.sh):

1. `backup-prod.sh` — `mariadb-dump` of prod, gzip, 14-day retention.
2. `restore-to-staging.sh` — recreates staging DB from the latest dump,
   rsyncs uploads, runs a sanity check (HTTP 200 + matching `patient`
   row count between prod and staging).
3. On failure → **Telegram + email** alerts with differentiated messages
   per stage. Backups are retried once on transient failure.

Promotion is **git-only**: after staging passes the smoke test, `git pull`
the same tag in `/volume1/espomodule-prod` and re-run `rebuild.php` +
`espo-dental-seed-roles`. Full procedure in
[`docs/admin-guide.md` §9](docs/admin-guide.md).

### Documentation

- [docs/admin-guide.md](docs/admin-guide.md) — installation, upgrade, backup,
  multi-clinic setup, staging + nightly pipeline (§9)
- [docs/user-guide.md](docs/user-guide.md) — day-to-day workflows for reception / doctors / managers
- [docs/release-notes.md](docs/release-notes.md) — version history (phases 0–16)

### License

MIT — see [LICENSE](LICENSE).

---

## Русский

### Что это

**EspoDental** — бесплатная медицинская информационная система (МИС) для
стоматологических клиник, построенная на базе EspoCRM 9.2+. С первого дня
поддерживает **несколько клиник** в одной установке, локализована на RU / EN / ES.

Целевая инфраструктура — Synology NAS, но запускается на любом Linux-хосте.

### Что внутри

- Карты пациентов с зубной формулой, визитами и анкетами здоровья.
- Календарь ресурсов (day/week, drag-and-drop, перетаскивание границ, поиск свободных слотов).
- Касса: счета, платежи, оборот за месяц с SVG-графиком.
- Склад материалов с уведомлениями о низком остатке.
- Напоминания о приёмах через Telegram-бот.
- Зарплата: 4 типа ставок, бонусы, выплаты через `Payment(out)`.
- Ортодонтия: карта с авто-номером `ORTHO-YYYY-NNNNN`, этапы лечения,
  планы перемещения зубов (FDI, 8 осей), фото 14 видов × 4 фазы, цефалометрия.

### Установка

#### Synology DSM (Container Manager)

Compose-стек **монтирует исходники модуля прямо в контейнер EspoCRM** —
zip загружать **не нужно**.

```bash
# 1. Клон сорсов модуля
sudo git clone https://github.com/unet1x/EspoDental.git /volume1/espomodule

# 2. Положить стек и env
mkdir -p /volume1/docker/espodental
cp /volume1/espomodule/deploy/docker-compose.yml /volume1/docker/espodental/
cp /volume1/espomodule/deploy/.env.example       /volume1/docker/espodental/.env
$EDITOR /volume1/docker/espodental/.env

# 3. Подготовить директории
sudo mkdir -p /volume1/docker/espodental/bd /volume2/espodental/data
sudo chown -R 999:999 /volume1/docker/espodental/bd
sudo chown -R 33:33   /volume2/espodental/data
sudo chown -R 33:33   /volume1/espomodule

# 4. Старт
cd /volume1/docker/espodental && docker compose up -d
docker compose ps                                # дождаться healthy

# 5. Пройти установщик EspoCRM в браузере: http://<nas>:8080/
#    Поля БД/админа подставлены из .env, жми Next.

# 6. Зарегистрировать модуль и засеять команды, роли, категории услуг.
docker compose exec espocrm php rebuild.php
docker compose exec espocrm php command.php espo-dental-seed-roles
```

Последняя команда идемпотентна — её можно перезапускать после каждого
обновления. Она создаёт 5 команд `EspoDental ...`, 5 ролей с матрицей
прав и 8 стартовых категорий услуг.

Назначь роли пользователям в **Администрирование → Пользователи** и
переходи к [docs/user-guide.md](docs/user-guide.md).

#### Установка через UI Extensions

Если хочется классической установки расширения (например, без compose):

```bash
git clone https://github.com/unet1x/EspoDental.git
cd EspoDental
bash bin/build         # build/EspoDental-X.Y.Z.zip
```

Загрузить zip в **Администрирование → Расширения**. `AfterInstall.php`
сработает автоматически и сделает то же самое, что CLI-команда выше.

Готовые zip-файлы доступны в разделе
[Releases](https://github.com/unet1x/EspoDental/releases).

### Роли

| Роль | Кратко |
| --- | --- |
| EspoDental Manager | полные права на все сущности |
| EspoDental Doctor | командный уровень по клиническим данным; ЗП — свои записи |
| EspoDental Assistant | командные права на чтение, может добавлять визиты, фото, материалы |
| EspoDental Administrator | стойка регистрации + касса, без правок клинической истории |
| EspoDental Stock Manager | только склад и материалы |

### Staging + ночной конвейер

EspoDental поставляется со вторым compose-стеком
[`deploy/staging/docker-compose.yml`](deploy/staging/docker-compose.yml) —
зеркало прод-стенда на том же Synology (порты 8090/8091). Через cron
запускается [`deploy/scripts/nightly.sh`](deploy/scripts/nightly.sh):

1. `backup-prod.sh` — `mariadb-dump` прода + gzip + retention 14 дней.
2. `restore-to-staging.sh` — пересоздаёт БД staging из свежего дампа,
   синхронизирует загрузки, делает sanity-check (HTTP 200 + равенство
   `COUNT(patient)` на проде и в staging).
3. При сбое — алерты в **Telegram + email**, разные сообщения по этапам;
   бэкап повторяется один раз на транзиентной ошибке.

Раскатка строго через **git**: после ручной приёмки на staging — `git pull`
того же тега в `/volume1/espomodule-prod`, затем `rebuild.php` +
`espo-dental-seed-roles`. Подробный сценарий — в
[`docs/admin-guide.md`, раздел 9](docs/admin-guide.md).

### Документация

- [docs/admin-guide.md](docs/admin-guide.md) — установка, обновление,
  бэкап, мульти-клиника, staging + ночной конвейер (раздел 9)
- [docs/user-guide.md](docs/user-guide.md) — повседневные сценарии: регистратура, доктор, менеджер
- [docs/release-notes.md](docs/release-notes.md) — история версий

### Лицензия

MIT — см. файл [LICENSE](LICENSE).
