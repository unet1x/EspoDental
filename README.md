# EspoDental

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)]()
[![EspoCRM](https://img.shields.io/badge/EspoCRM-%E2%89%A59.2-1F77B4)]()
[![Tests](https://img.shields.io/badge/tests-168-2CA02C)]()

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

```bash
# 1. Clone module sources
sudo git clone https://github.com/<you>/EspoDental.git /volume1/espomodule

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

# 4. Start
cd /volume1/docker/espodental && docker compose up -d
```

Open `http://<nas>:8080/`, log in as `admin`, then go to
**Administration → Extensions → Upload extension** and install
`build/EspoDental-X.Y.Z.zip`. The bundled `AfterInstall.php` will create
roles and seed required scopes.

#### Development install (bare metal)

```bash
git clone https://github.com/<you>/EspoDental.git
cd EspoDental
composer install                       # phpcs, phpunit, phpstan
make build                             # produces build/EspoDental-X.Y.Z.zip
```

Then upload the zip via **Administration → Extensions** in your EspoCRM.

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

### Documentation

- [docs/admin-guide.md](docs/admin-guide.md) — installation, upgrade, backup, multi-clinic setup
- [docs/user-guide.md](docs/user-guide.md) — day-to-day workflows for reception / doctors / managers
- [docs/release-notes.md](docs/release-notes.md) — version history (phases 0–14)

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

```bash
# 1. Клон сорсов модуля
sudo git clone https://github.com/<you>/EspoDental.git /volume1/espomodule

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

# 4. Запуск
cd /volume1/docker/espodental && docker compose up -d
```

После старта зайти на `http://<nas>:8080/` под `admin`, далее
**Администрирование → Расширения → Загрузить** и установить
`build/EspoDental-X.Y.Z.zip`. Скрипт `AfterInstall.php` создаст роли и сидирует
ACL.

### Роли

| Роль | Кратко |
| --- | --- |
| EspoDental Manager | полные права на все сущности |
| EspoDental Doctor | командный уровень по клиническим данным; ЗП — свои записи |
| EspoDental Assistant | командные права на чтение, может добавлять визиты, фото, материалы |
| EspoDental Administrator | стойка регистрации + касса, без правок клинической истории |
| EspoDental Stock Manager | только склад и материалы |

### Документация

- [docs/admin-guide.md](docs/admin-guide.md) — установка, обновление, бэкап, мульти-клиника
- [docs/user-guide.md](docs/user-guide.md) — повседневные сценарии: регистратура, доктор, менеджер
- [docs/release-notes.md](docs/release-notes.md) — история версий

### Лицензия

MIT — см. файл [LICENSE](LICENSE).
