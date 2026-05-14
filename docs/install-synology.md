# EspoDental — установка на Synology с нуля

> **Для кого:** этот документ написан для администратора клиники без
> специального технического образования. Если ты знаком с Linux и Docker —
> используй `admin-guide.md` (он короче и техничнее).
>
> **Что ты получишь в конце:**
>
> * Рабочую CRM по адресу `https://dental.example.ru/` (или по IP).
> * Тестовый стенд по адресу `https://staging-dental.example.ru/` для
>   репетиции обновлений.
> * Автоматический ночной бэкап + проверку, что бэкап рабочий.
> * Уведомления в Telegram и на e-mail, если что-то ночью пошло не так.
>
> **Сколько займёт:** ~2 часа в первый раз. Все шаги имеют чек-листы — не
> переходи к следующему пункту, пока не сделан текущий.
>
> _Document is Russian-only on purpose: target audience is non-technical
> clinic admins. See `admin-guide.md` for the bilingual ops manual._

---

## Содержание

1. [Что должно быть готово до старта](#0-prerequisites)
2. [Часть 1 — Подготовить Synology (15 минут)](#1-prepare-synology)
3. [Часть 2 — Создать папки в File Station (5 минут)](#2-create-folders)
4. [Часть 3 — Скачать сорсы модуля (5 минут)](#3-clone-sources)
5. [Часть 4 — Запустить продакшн (15 минут)](#4-prod-stack)
6. [Часть 5 — Завершить установку EspoCRM (5 минут)](#5-espocrm-installer)
7. [Часть 6 — Активировать модуль EspoDental (3 минуты)](#6-activate-module)
8. [Часть 7 — Раздать роли админу (3 минуты)](#7-grant-roles)
9. [Часть 8 — HTTPS через Reverse Proxy (10 минут)](#8-https)
10. [Часть 9 — Поднять staging-стенд (15 минут)](#9-staging-stack)
11. [Часть 10 — Настроить ночной бэкап (15 минут)](#10-nightly-backup)
12. [Часть 11 — Финальная проверка (5 минут)](#11-final-check)
13. [Если что-то пошло не так](#12-troubleshooting)

---

<a id="0-prerequisites"></a>
## Что должно быть готово до старта

- [ ] **Synology NAS** с DSM 7.2 или новее.
- [ ] На NAS свободно **минимум 2 ГБ оперативной памяти** и **20 ГБ
      места** на одном из томов.
- [ ] У тебя есть **аккаунт администратора DSM** — тот, под которым ты
      входишь по адресу `http://<ip-NAS>:5000`.
- [ ] Твой компьютер находится **в той же сети**, что и NAS (или
      доступен по VPN).
- [ ] **Доменное имя** для клиники, например `dental.example.ru`. Без
      него можно работать по IP, но загружать персональные данные в
      сеть без HTTPS — небезопасно.
- [ ] **Менеджер паролей** (KeePass, Bitwarden — любой) — тебе нужно
      будет сохранить 4–5 длинных паролей.

---

<a id="1-prepare-synology"></a>
## Часть 1 — Подготовить Synology

### 1.1 Установить пакет Container Manager

1. Зайти в DSM как администратор: открыть в браузере `http://<ip-NAS>:5000`.
2. Кликнуть на меню `≡` (в левом верхнем углу) → **Центр пакетов**.
3. В строке поиска ввести `Container Manager`.
4. Нажать **Установить**. Дождаться завершения — это ~ 2 минуты.

**Проверка:** в главном меню DSM (`≡`) должен появиться значок
**Container Manager**. Открой его, окошко должно загрузиться без ошибок.

### 1.2 Включить SSH (одноразово, для финальной настройки)

1. **Панель управления → Терминал и SNMP → Терминал**.
2. Поставить галку **Включить службу SSH**.
3. Порт оставить 22.
4. Кнопка **Применить** внизу окна.

> Когда вся установка закончится, SSH **рекомендуется выключить** (для
> безопасности). Включается обратно — той же галкой за 5 секунд.

### 1.3 Установить терминальный клиент

На своём компьютере (не на NAS):

- **Windows:** скачать [PuTTY](https://www.putty.org/) (бесплатно).
  Запустить, в поле _Host Name_ ввести IP NAS, нажать **Open**, ввести
  логин/пароль администратора DSM.
- **macOS / Linux:** открыть приложение **Терминал**, ввести
  `ssh <логин-админа-DSM>@<ip-NAS>`, нажать Enter, ввести пароль.

> Когда увидишь приглашение вида `<user>@<NAS-имя>:~$` — терминал
> работает. Не закрывай это окно, оно понадобится до конца установки.

**Чек-лист части 1:**
- [ ] Container Manager установлен и открывается.
- [ ] SSH включён.
- [ ] Терминал на компьютере открывает сессию на NAS.

---

<a id="2-create-folders"></a>
## Часть 2 — Создать папки в File Station

Открой в DSM **File Station** (значок «папка с лупой»).

### 2.1 На томе volume1

Выбери `volume1` в левой колонке. Создай следующие папки (правый клик →
**Создать → Создать папку** или кнопка **Создать**):

```
volume1/
├── docker/
│   ├── espodental/
│   │   └── bd/
│   └── espodental-staging/
│       └── bd/
├── espomodule-prod/         (создавать заранее НЕ нужно — заведётся
└── espomodule-staging/       сама командой git в части 3)
```

То есть руками создаём только пять папок: `docker`, `docker/espodental`,
`docker/espodental/bd`, `docker/espodental-staging`,
`docker/espodental-staging/bd`. Две папки `espomodule-prod` и
`espomodule-staging` создадутся автоматически на следующем шаге.

### 2.2 На томе volume2 (если он есть)

Если у твоего NAS только один том — пропусти «volume2», вместо него
будем использовать тот же `volume1`. Но укажешь это позже в .env.

Создай:

```
volume2/
├── espodental/
│   ├── data/
│   ├── backups/
│   └── logs/
└── espodental-staging/
    └── data/
```

**Чек-лист части 2:**
- [ ] Папки `docker/espodental/bd` и `docker/espodental-staging/bd`
      созданы на volume1.
- [ ] Папки `espodental/data`, `espodental/backups`, `espodental/logs`,
      `espodental-staging/data` созданы на volume2 (или на volume1,
      если volume2 нет).

---

<a id="3-clone-sources"></a>
## Часть 3 — Скачать сорсы модуля

Перейди в терминал (PuTTY / Terminal), который остался открытым с части 1.

Ввести по очереди (после каждой команды — Enter):

```bash
sudo git clone https://github.com/unet1x/EspoDental.git /volume1/espomodule-prod
sudo git clone https://github.com/unet1x/EspoDental.git /volume1/espomodule-staging
```

Терминал спросит:
1. **Пароль sudo** — введи пароль своей учётки DSM.
2. После каждой команды должно показать:  
   `Cloning into '/volume1/espomodule-...'... done.`

Теперь выставь правильного владельца для всех созданных папок:

```bash
sudo chown -R 33:33 /volume1/espomodule-prod /volume1/espomodule-staging
sudo chown -R 999:999 /volume1/docker/espodental/bd /volume1/docker/espodental-staging/bd
sudo chown -R 33:33 /volume2/espodental/data /volume2/espodental-staging/data
sudo mkdir -p /volume2/espodental/backups /volume2/espodental/logs
```

> Цифры 33 и 999 — это внутренние ID пользователей в Docker-контейнерах
> (www-data и mysql). Они одинаковы на всех Synology, ничего менять не нужно.

**Чек-лист части 3:**
- [ ] В File Station видны папки `/volume1/espomodule-prod/src/...`
      и `/volume1/espomodule-staging/src/...` — там много файлов.
- [ ] Команды `chown` отработали без ошибок.

---

<a id="4-prod-stack"></a>
## Часть 4 — Запустить продакшн стек

### 4.1 Скопировать конфиг-файлы

В терминале:

```bash
sudo cp /volume1/espomodule-prod/deploy/docker-compose.yml /volume1/docker/espodental/
sudo cp /volume1/espomodule-prod/deploy/.env.example /volume1/docker/espodental/.env
```

### 4.2 Отредактировать `.env`

Открыть файл встроенным редактором:

```bash
sudo nano /volume1/docker/espodental/.env
```

Стрелками вниз пройди по файлу. **Обязательно** замени значения у
следующих строк:

```ini
TZ=Europe/Moscow                              # твой часовой пояс
MARIADB_ROOT_PASSWORD=change-me-root  # 16+ символов
ESPOCRM_DATABASE_PASSWORD=change-me-db
ESPOCRM_ADMIN_USERNAME=admin
ESPOCRM_ADMIN_PASSWORD=change-me-admin
ESPOCRM_SITE_URL=http://192.168.1.10:8080     # сейчас IP, потом домен
ESPOCRM_WEBSOCKET_URL=ws://192.168.1.10:8081
MODULE_HOST_PATH=/volume1/espomodule-prod     # уже правильно по умолчанию
```

> **Как сгенерировать пароли.** В отдельной вкладке терминала выполни
> `openssl rand -base64 24` — получишь стойкую случайную строку.
> Скопируй её и вставь в `.env`. Не забудь сохранить пароли в
> KeePass — они тебе ещё понадобятся.

Сохранить файл: `Ctrl+O`, `Enter`. Выйти из nano: `Ctrl+X`.

### 4.3 Запустить контейнеры

**Вариант А (через Container Manager UI — рекомендуется):**

1. Открой **Container Manager** в DSM.
2. Слева — **Проект → Создать**.
3. Имя проекта: `espodental`.
4. Путь: нажми `Установить путь` и выбери `/docker/espodental`.
5. Источник: **Использовать существующий docker-compose.yml**.
6. Веб-портал: **Не использовать веб-портал**, **Далее**.
7. **Готово**. Дождись, пока все 4 сервиса станут зелёными:
   - `espodental-db` (MariaDB)
   - `espodental-web` (EspoCRM)
   - `espodental-daemon` (cron-задачи)
   - `espodental-websocket` (живые уведомления)

Первый запуск займёт 3–5 минут — DSM скачает образ EspoCRM 9.2.7 (~ 800 МБ).

**Вариант Б (через терминал):**

```bash
cd /volume1/docker/espodental
sudo docker compose up -d
```

Проверка:

```bash
sudo docker compose ps
```

Должен вывести 4 строки, у всех в колонке STATUS — `Up` или
`running (healthy)`.

**Чек-лист части 4:**
- [ ] В Container Manager проект `espodental` показан в состоянии
      **Выполняется**.
- [ ] Открыв `http://<ip-NAS>:8080/` в браузере, видишь стартовую
      страницу установщика EspoCRM.

---

<a id="5-espocrm-installer"></a>
## Часть 5 — Завершить установку EspoCRM

1. В браузере открой `http://<ip-NAS>:8080/`.
2. Появится мастер _EspoCRM Installation Wizard_.
3. **Шаг 1 «License»** — отметь _I agree_, нажми **Next**.
4. **Шаг 2 «Settings»**:
   - _Default Language_: **Russian**
   - _Default Timezone_: твой часовой пояс
   - Остальное по умолчанию.
   - **Next**.
5. **Шаг 3 «Database»** — поля заполнены автоматически из `.env`:
   - Host: `mariadb`
   - Database name: `espodental`
   - User / Password: подставлены из `.env`
   - **Test Connection** → должно вернуть зелёную галку.
   - **Next**.
6. **Шаг 4 «Administrator»** — поля заполнены из `.env`:
   - Username: `admin`
   - Password: подставлен (тот, что ты записал в KeePass)
   - **Next**.
7. **Шаг 5 «Finish»** — нажми **Login**.
8. Войди под `admin` + твой пароль.

**Чек-лист части 5:**
- [ ] Видишь главный экран EspoCRM на русском.
- [ ] Слева в меню есть «Аккаунты», «Контакты» и т.д. (это пока штатные
      сущности Espo — модуль ещё не активирован).

---

<a id="6-activate-module"></a>
## Часть 6 — Активировать модуль EspoDental

В терминале:

```bash
cd /volume1/docker/espodental
sudo docker compose exec espocrm php rebuild.php
```

Команда выводит много текста, заканчивается строкой `Done.` или похожей.

Далее подготовить рабочее место: команды, роли, стартовую клинику,
кабинеты, прайс, склад, регламентные задания, dashboard и меню:

```bash
sudo docker compose exec espocrm php command.php espo-dental-bootstrap
```

Должен напечатать:

```
Seeding EspoDental workspace...
Done. Created ... clinic(s), ... cabinet(s), ... service(s), ... material(s) ...
Re-run is safe: the command is idempotent.
```

> Если на второй команде увидишь `Command 'espo-dental-bootstrap' is not
> found` — перезапусти EspoCRM и подожди 30 сек:  
> `sudo docker compose restart espocrm`, затем повтори команду.

В браузере **обнови страницу EspoCRM (F5)**. В левом меню должны
появиться новые пункты: **Patient**, **Appointment**, **Cabinet**,
**Visit**, **Service**, **Invoice**, **Material** и др.

**Чек-лист части 6:**
- [ ] `rebuild.php` прошёл без ошибок.
- [ ] `espo-dental-bootstrap` отчитался о подготовке рабочего места.
- [ ] В левом меню EspoCRM видны Patient и Appointment.

---

<a id="7-grant-roles"></a>
## Часть 7 — Раздать роли первому администратору

В EspoCRM:

1. Сверху-справа щёлкни **аватар → Administration**.
2. **Users → admin** (пользователь, под которым залогинен).
3. Открой запись на редактирование (карандашик справа).
4. Прокрути вниз до раздела **Teams**: добавь `EspoDental Doctors`
   (или любую другую команду EspoDental — пока не важно).
5. Раздел **Roles**: добавь `EspoDental Manager` — он даёт полные права
   на все сущности модуля.
6. **Save**.

Выйди и войди заново (это нужно, чтобы права применились).

**Чек-лист части 7:**
- [ ] При создании нового пациента (Patient → Создать) ты можешь
      сохранить запись без ошибок про права.

---

<a id="8-https"></a>
## Часть 8 — HTTPS через Reverse Proxy

> Без HTTPS ввод персональных данных в браузер по сети **запрещён**
> ФЗ-152. Этот раздел обязателен, если у тебя есть доменное имя.

### 8.1 Получить SSL-сертификат

1. **Панель управления → Безопасность → Сертификат**.
2. **Добавить → Добавить новый сертификат**.
3. **Получить сертификат от Let's Encrypt**.
4. Доменное имя: `dental.example.ru`. Email: твой.
5. **Применить**. DSM получит сертификат за 30–60 секунд.

> Для этого порты **80 и 443** на роутере должны пробрасываться на
> NAS (Let's Encrypt должен достучаться извне).

### 8.2 Reverse Proxy для веб-интерфейса

1. **Панель управления → Портал входа → Расширенные → Обратный
   прокси-сервер**.
2. **Создать**.
3. Заполнить:
   - **Имя:** `EspoDental prod`
   - **Источник:**
     - Протокол: **HTTPS**
     - Имя хоста: `dental.example.ru`
     - Порт: `443`
   - **Назначение:**
     - Протокол: **HTTP**
     - Имя хоста: `localhost`
     - Порт: `8080`
4. На вкладке **Пользовательский заголовок → Создать**:
   - `X-Real-IP` → `$remote_addr`
   - `X-Forwarded-For` → `$proxy_add_x_forwarded_for`
   - `Upgrade` → `$http_upgrade`
   - `Connection` → `Upgrade`
5. **Сохранить**.

### 8.3 Reverse Proxy для WebSocket

Снова **Создать**:
- **Имя:** `EspoDental websocket prod`
- **Источник:**
  - Протокол: **HTTPS**
  - Имя хоста: `dental.example.ru`
  - Порт: `443`
  - **Расположение источника (Source location):** `/wss`
- **Назначение:**
  - Протокол: **HTTP**
  - Имя хоста: `localhost`
  - Порт: `8081`
- Заголовки те же, что в 8.2.

### 8.4 Обновить .env под HTTPS

```bash
sudo nano /volume1/docker/espodental/.env
```

Изменить:
```ini
ESPOCRM_SITE_URL=https://dental.example.ru
ESPOCRM_WEBSOCKET_URL=wss://dental.example.ru/wss
```

Сохранить (`Ctrl+O`, `Enter`, `Ctrl+X`). Перезапустить:

```bash
cd /volume1/docker/espodental
sudo docker compose restart espocrm espocrm-websocket
```

Открой в браузере `https://dental.example.ru/` — должен быть зелёный замок.

**Чек-лист части 8:**
- [ ] Сертификат отображается в _Безопасность → Сертификат_.
- [ ] `https://dental.example.ru/` открывает EspoCRM с зелёным замком.
- [ ] Уведомления в правом верхнем углу обновляются «вживую» (значит
      WebSocket работает).

---

<a id="9-staging-stack"></a>
## Часть 9 — Поднять staging-стенд

Тот же сценарий, что в части 4, но для второй папки. Все команды
выполняй именно с указанными ниже путями — staging должен быть
строго изолирован от прода.

### 9.1 Скопировать конфиги

```bash
sudo cp /volume1/espomodule-prod/deploy/staging/docker-compose.yml \
        /volume1/docker/espodental-staging/
sudo cp /volume1/espomodule-prod/deploy/staging/.env.example \
        /volume1/docker/espodental-staging/.env
```

### 9.2 Отредактировать staging `.env`

```bash
sudo nano /volume1/docker/espodental-staging/.env
```

**Обязательно укажи ДРУГИЕ пароли**, не те, что в prod:

```ini
TZ=Europe/Moscow
MARIADB_ROOT_PASSWORD=change-me-root
ESPOCRM_DATABASE_PASSWORD=change-me-db
ESPOCRM_ADMIN_PASSWORD=change-me-admin
ESPOCRM_SITE_URL=http://192.168.1.10:8090   # IP NAS + порт 8090
ESPOCRM_WEBSOCKET_URL=ws://192.168.1.10:8091
MODULE_HOST_PATH=/volume1/espomodule-staging
```

Сохранить.

### 9.3 Запустить staging-стек

В Container Manager: **Проект → Создать**, имя `espodental-staging`,
путь `/docker/espodental-staging`. **Использовать существующий
docker-compose.yml**, далее как в части 4.

Или через терминал:

```bash
cd /volume1/docker/espodental-staging
sudo docker compose up -d
```

### 9.4 Пройти инсталлер EspoCRM на staging

Открой `http://<ip-NAS>:8090/` (или `https://staging-dental.example.ru/`
если настроил отдельный Reverse Proxy).

Пройди инсталлер так же, как в части 5. Активировать модуль через
`espo-dental-bootstrap` **не нужно** — после первой ночной репликации
staging будет переписан копией прода.

> **Важно: ограничь доступ к staging.** Это вторая копия реальных
> персональных данных пациентов. Варианты:
> 1. **HTTP basic auth** в Reverse Proxy для staging (DSM Reverse Proxy
>    → правая кнопка по правилу → Расширенные → Пользователь/пароль).
> 2. **Файрвол DSM** ограничить доступ по IP (Панель управления →
>    Безопасность → Брандмауэр).
> 3. **VPN-only** через DSM VPN Server.

**Чек-лист части 9:**
- [ ] Открывается `http://<ip-NAS>:8090/` и видна стартовая страница
      Espo (или баннер «STAGING — test environment»).
- [ ] Доступ к staging закрыт паролем / файрволом / VPN.

---

<a id="10-nightly-backup"></a>
## Часть 10 — Настроить ночной бэкап

### 10.1 Создать Telegram-бота для алертов

1. В Telegram найди **@BotFather**.
2. `/newbot` → задай имя (например, `EspoDental Alerts`) и username
   (`espodental_alerts_bot`).
3. Скопируй **токен** (длинная строка вида `123456:ABC-DEF-...`).
4. Запусти своего бота, нажми **Start**.
5. Чтобы узнать свой `chat_id`: открой в Telegram **@userinfobot**,
   нажми **Start**. Бот пришлёт твой ID — это число вида `987654321`.

### 10.2 Дополнить prod `.env` алертами

```bash
sudo nano /volume1/docker/espodental/.env
```

Найти секцию `# ---------- Nightly pipeline alerts ----------` и
заполнить:

```ini
ALERT_TELEGRAM_BOT_TOKEN=123456:ABC-...     # токен от BotFather
ALERT_TELEGRAM_CHAT_ID=987654321            # твой ID от userinfobot
ALERT_EMAIL_TO=admin@example.ru
ALERT_EMAIL_FROM=alerts@example.ru
ALERT_SMTP_URL=smtps://smtp.yandex.ru:465   # или smtp.gmail.com:465
ALERT_SMTP_USER=alerts@example.ru
ALERT_SMTP_PASS=change-me-smtp          # НЕ обычный пароль почты!
```

> Для Yandex/Gmail обычный пароль не подойдёт — заведи **пароль
> приложения** в настройках безопасности почтового ящика.

Сохранить.

### 10.3 Проверить руками

```bash
sudo bash /volume1/espomodule-prod/deploy/scripts/nightly.sh
```

Скрипт работает 1–3 минуты. В конце должен показать:

```
2026-MM-DD HH:MM:SS [INFO] [<pipeline_id>] nightly: SUCCESS
```

Параллельно в Telegram **никаких** сообщений приходить не должно —
алерты включаются только при ошибке.

Если упал — посмотри лог:

```bash
ls -lt /volume2/espodental/logs/ | head -3
sudo less /volume2/espodental/logs/nightly-<pipeline_id>.log
```

### 10.4 Поставить cron-задачу в DSM

1. **Панель управления → Планировщик задач → Создать →
   Запланированная задача → Пользовательский сценарий**.
2. **Общее:**
   - Задача: `EspoDental nightly backup`
   - Пользователь: `root`
3. **Расписание:**
   - Дата: ежедневно
   - Время: 02:00
4. **Параметры задачи:**
   - Запустить команду:  
     `bash /volume1/espomodule-prod/deploy/scripts/nightly.sh`
5. **OK**.

### 10.5 Тест алерта (опционально, но желательно)

Чтобы убедиться, что Telegram-канал реально работает, временно сломаем
бэкап:

```bash
# Временно подменим пароль БД — бэкап упадёт
sudo cp /volume1/docker/espodental/.env /volume1/docker/espodental/.env.bak
sudo sed -i 's|^MARIADB_ROOT_PASSWORD=.*|MARIADB_ROOT_PASSWORD=WRONG|' \
        /volume1/docker/espodental/.env
sudo bash /volume1/espomodule-prod/deploy/scripts/nightly.sh
# Ждём 1–2 мин — приходит сообщение "Backup FAILED twice" в Telegram + email
sudo mv /volume1/docker/espodental/.env.bak /volume1/docker/espodental/.env
```

**Чек-лист части 10:**
- [ ] `nightly.sh` отрабатывает без ошибок, в логе `SUCCESS`.
- [ ] Тестовый сбой (10.5) прислал сообщение в Telegram.
- [ ] В DSM Планировщике задача `EspoDental nightly backup` стоит на
      02:00 ежедневно.

---

<a id="11-final-check"></a>
## Часть 11 — Финальная проверка

Пройди этот короткий чек-лист — если все галки зелёные, установка
полностью завершена:

- [ ] Открывается `https://dental.example.ru/`, виден логин EspoCRM
      с зелёным замком HTTPS.
- [ ] Залогинен как admin, в левом меню видны **Patient**,
      **Appointment**, **Cabinet**, **Invoice**, **Material**.
- [ ] Зашёл в **Administration → Users**, у admin прописаны team и
      role `EspoDental Manager`.
- [ ] Создал тестового пациента — сохранился без ошибок.
- [ ] Открывается `https://staging-dental.example.ru/` (или
      `http://<ip>:8090/`), доступ ограничен (basic auth / VPN).
- [ ] Команда `sudo bash /volume1/espomodule-prod/deploy/scripts/nightly.sh`
      отрабатывает за 1–3 минуты без ошибок.
- [ ] В Telegram у тебя есть бот, отвечающий командой `/start`.
- [ ] В DSM Планировщике видна задача `EspoDental nightly backup`.
- [ ] **Все пароли (MariaDB root prod, MariaDB root staging, Espo db
      prod, Espo db staging, Espo admin prod, Espo admin staging,
      SMTP) сохранены в менеджере паролей.**

Если все галки стоят — поздравляю, EspoDental в проде.

---

<a id="12-troubleshooting"></a>
## Если что-то пошло не так

### Контейнер не стартует

```bash
sudo docker compose -f /volume1/docker/espodental/docker-compose.yml logs --tail=50 espocrm
```

— смотри последние 50 строк логов EspoCRM.

| Сообщение в логе | Что делать |
|---|---|
| `Connection refused` к MariaDB | Подожди 30 сек, MariaDB ещё не успела подняться. Если через 2 минуты не стартует — проверь, что папка `/volume1/docker/espodental/bd` пустая при первом запуске и принадлежит `999:999`. |
| `Permission denied` на `/var/www/html/...` | `sudo chown -R 33:33 /volume1/espomodule-prod` |
| `Error establishing connection to database` | Пароль в `.env` не совпадает с тем, что записан в БД. Если БД совсем новая (свежий первый запуск) — удали папку `/volume1/docker/espodental/bd`, пересоздай пустую с правами 999:999 и заново подними проект. |

### Браузер показывает 502 Bad Gateway

Reverse Proxy не достучался до контейнера. Проверь:
- В DSM Reverse Proxy в поле **Имя хоста назначения** написано
  `localhost`, не `espodental-web`.
- Порт в Reverse Proxy совпадает с `ESPOCRM_HTTP_PORT` в `.env` (8080).
- Контейнер `espodental-web` действительно запущен:
  `sudo docker compose ps`.

### WebSocket не работает (живые обновления)

В DSM Reverse Proxy для правила `/wss` должны быть заголовки:
- `Upgrade` → `$http_upgrade`
- `Connection` → `Upgrade`

Без них браузер не сможет проапгрейдить соединение в WebSocket.

### `espo-dental-bootstrap` не найдено

```bash
sudo docker compose -f /volume1/docker/espodental/docker-compose.yml restart espocrm
sleep 20
sudo docker compose -f /volume1/docker/espodental/docker-compose.yml \
     exec espocrm php command.php espo-dental-bootstrap
```

Если после рестарта команда всё ещё не находится — проверь, что
`MODULE_HOST_PATH` в `.env` указывает на правильный путь и что в этой
папке есть `src/files/custom/Espo/Modules/EspoDental/`.

### Telegram-алерт не приходит

Проверь токен бота:

```bash
curl https://api.telegram.org/bot<ТОКЕН>/getMe
```

Должен вернуть `{"ok":true,"result":{...}}`. Если `{"ok":false}` —
неверный токен. Если `ok:true` — проверь, что **ты сам открыл диалог
с ботом и нажал /start**, иначе бот не сможет тебе писать (Telegram
блокирует исходящие до первого сообщения от пользователя).

### Email-алерт не приходит

Большинство SMTP-провайдеров (Yandex, Gmail, Mail.ru) **блокируют
обычный пароль для приложений** — нужен «пароль приложения», который
заводится в настройках безопасности почты.

Проверка SMTP вручную:

```bash
curl -v --url smtps://smtp.yandex.ru:465 \
    --mail-from alerts@example.ru \
    --mail-rcpt admin@example.ru \
    --user 'alerts@example.ru:ПАРОЛЬ-ПРИЛОЖЕНИЯ' \
    --upload-file <(echo -e "Subject: test\n\nhi") \
    --ssl-reqd
```

Вернёт «250 2.0.0 Ok: queued» — значит SMTP работает.

### nightly.sh упал на этапе sanity check

Сообщение `Staging sanity check FAILED` означает: бэкап восстановился
в staging, но проверка не прошла. Возможные причины:

1. **Staging не успел подняться** — увеличь `STAGING_HEALTH_TIMEOUT_SEC`
   в prod `.env` до 300.
2. **Количество пациентов не совпадает** — staging получил не тот дамп,
   что был в проде на момент сравнения. Самое частое — кто-то редактирует
   prod ровно в момент бэкапа. Повтори вручную:  
   `sudo bash /volume1/espomodule-prod/deploy/scripts/nightly.sh`.
3. **Имя таблицы пациентов отличается** — проверь, что
   `PATIENT_TABLE_NAME` в `.env` равно `patient` (по умолчанию).

### Куда писать, если ничего не помогло

- **Issues на GitHub:** https://github.com/unet1x/EspoDental/issues
- **Чтобы быстрее ответили — приложи:**
  - Версию EspoDental: `cat /volume1/espomodule-prod/src/manifest.json`
  - Версию DSM: `Панель управления → Информация о DSM`
  - Свежий лог: `tail -200 /volume2/espodental/logs/nightly-*.log`

---

## Что дальше

- **Документация для повседневной работы** (регистратура, доктор,
  менеджер): [user-guide.md](user-guide.md).
- **Документация для администратора** (обновления, откаты, мульти-клиника):
  [admin-guide.md](admin-guide.md), особенно раздел 9 «Staging +
  nightly pipeline».
- **История версий:** [release-notes.md](release-notes.md).
