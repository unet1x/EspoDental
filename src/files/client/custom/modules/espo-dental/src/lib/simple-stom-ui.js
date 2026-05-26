define('espo-dental:lib/simple-stom-ui', [], function () {
    var styleId = 'espo-dental-simple-stom-ui';

    var tokens = {
        background: '#edf3ef',
        surface: '#ffffff',
        surfaceAlt: '#f7faf8',
        border: '#d8e3dd',
        borderStrong: '#bfd0c8',
        primary: '#438f7e',
        primaryDark: '#2f705f',
        text: '#1f2f2b',
        muted: '#65756f',
        danger: '#b94a48',
        warning: '#b7831f',
        success: '#3f8d62',
        info: '#3b7e9f',
        radius: '8px',
        compactRadius: '6px',
        shadow: '0 1px 2px rgba(31,47,43,0.08)'
    };

    var statusClasses = {
        primary: 'espo-dental-stom-badge--primary',
        success: 'espo-dental-stom-badge--success',
        warning: 'espo-dental-stom-badge--warning',
        danger: 'espo-dental-stom-badge--danger',
        info: 'espo-dental-stom-badge--info',
        muted: 'espo-dental-stom-badge--muted',
        planned: 'espo-dental-stom-badge--info',
        waiting: 'espo-dental-stom-badge--warning',
        arrived: 'espo-dental-stom-badge--success',
        inProgress: 'espo-dental-stom-badge--primary',
        in_progress: 'espo-dental-stom-badge--primary',
        completed: 'espo-dental-stom-badge--success',
        finished: 'espo-dental-stom-badge--success',
        cancelled: 'espo-dental-stom-badge--muted',
        noShow: 'espo-dental-stom-badge--danger',
        no_show: 'espo-dental-stom-badge--danger',
        waiting_confirmation: 'espo-dental-stom-badge--warning',
        reschedule_requested: 'espo-dental-stom-badge--warning',
        entered: 'espo-dental-stom-badge--info',
        booked: 'espo-dental-stom-badge--primary',
        processed: 'espo-dental-stom-badge--success',
        patient: 'espo-dental-stom-badge--primary',
        active: 'espo-dental-stom-badge--success',
        inactive: 'espo-dental-stom-badge--muted',
        draft: 'espo-dental-stom-badge--muted',
        issued: 'espo-dental-stom-badge--warning',
        partially_paid: 'espo-dental-stom-badge--warning',
        partial_paid: 'espo-dental-stom-badge--warning',
        paid: 'espo-dental-stom-badge--success',
        storno: 'espo-dental-stom-badge--danger',
        reversed: 'espo-dental-stom-badge--danger',
        normal: 'espo-dental-stom-badge--muted',
        high: 'espo-dental-stom-badge--warning',
        urgent: 'espo-dental-stom-badge--danger',
        ok: 'espo-dental-stom-badge--success',
        low: 'espo-dental-stom-badge--warning',
        out: 'espo-dental-stom-badge--danger',
        critical: 'espo-dental-stom-badge--danger'
    };

    var riskClasses = {
        low: 'espo-dental-stom-badge--success',
        medium: 'espo-dental-stom-badge--warning',
        high: 'espo-dental-stom-badge--danger',
        critical: 'espo-dental-stom-badge--danger'
    };

    var badgeLabels = {
        planned: 'запланировано',
        scheduled: 'не подтверждена',
        confirmed: 'подтверждена',
        waiting: 'ожидает',
        arrived: 'в клинике',
        inProgress: 'у врача',
        in_progress: 'у врача',
        completed: 'завершено',
        finished: 'завершено',
        cancelled: 'отменено',
        noShow: 'неявка',
        no_show: 'неявка',
        waiting_confirmation: 'ждет подтверждения',
        reschedule_requested: 'запрошен перенос',
        entered: 'предварительный',
        booked: 'записан',
        processed: 'обработан',
        patient: 'карта пациента',
        active: 'активен',
        inactive: 'неактивен',
        draft: 'черновик',
        issued: 'выставлен',
        partially_paid: 'частично оплачен',
        partial_paid: 'частично оплачен',
        paid: 'оплачен',
        storno: 'сторно',
        reversed: 'сторнирован',
        normal: 'обычная',
        high: 'важно',
        urgent: 'срочно',
        ok: 'норма',
        low: 'низкий',
        medium: 'средний',
        critical: 'критично',
        out: 'нет остатка',
        open: 'открыто',
        done: 'выполнено',
        task: 'задача',
        selected: 'выбран'
    };

    var labelGroups = {
        entity: {
            Patient: 'карта пациента',
            PreliminaryPatient: 'предварительный пациент',
            Appointment: 'запись',
            Visit: 'прием',
            Invoice: 'счет',
            Payment: 'платеж'
        },
        field: {
            lastName: 'Фамилия',
            firstName: 'Имя',
            middleName: 'Отчество',
            gender: 'Пол',
            dateOfBirth: 'Дата рождения',
            phone: 'Телефон',
            emailAddress: 'Email',
            cardNumber: 'Номер карты',
            status: 'Статус',
            balance: 'Баланс',
            isChild: 'Ребенок',
            lastQuestionnaireAt: 'Последняя анкета',
            questionnaireExpired: 'Анкета устарела',
            futureAppointmentCount: 'Будущие записи',
            visitCount: 'Завершенные приемы',
            latestVisitId: 'Последний прием',
            visitPhotoCount: 'Фотографии приемов',
            questionnaireCount: 'Анкеты',
            openInvoiceCount: 'Открытые счета',
            paymentCount: 'Платежи',
            parentPatientId: 'ID представителя',
            parentPatientName: 'Представитель',
            childCount: 'Связанные дети'
        },
        dentition: {
            adult: 'взрослый',
            child: 'детский',
            mixed: 'смешанный'
        },
        surface: {
            '': 'весь зуб',
            whole: 'весь зуб',
            O: 'окклюзионная',
            M: 'мезиальная',
            D: 'дистальная',
            B: 'вестибулярная',
            L: 'язычная',
            o: 'окклюзионная',
            m: 'мезиальная',
            d: 'дистальная',
            b: 'вестибулярная',
            l: 'язычная'
        },
        condition: {
            healthy: 'здоров',
            caries: 'кариес',
            filling: 'пломба',
            filling_caries: 'пломба + кариес',
            root_canal: 'канал',
            crown: 'коронка',
            bridge: 'мост',
            bridge_pontic: 'промежуточная часть моста',
            veneer: 'винир',
            implant: 'имплант',
            implant_crown: 'коронка на импланте',
            removed: 'удален',
            missing: 'отсутствует',
            sealant: 'герметик',
            foreign_filling: 'чужая пломба'
        },
        paymentMethod: {
            cash: 'наличные',
            card: 'карта',
            bank_transfer: 'банковский перевод',
            crypto: 'криптовалюта',
            advance: 'аванс'
        }
    };

    function ensureStyles(doc) {
        doc = doc || (typeof document !== 'undefined' ? document : null);

        if (!doc || doc.getElementById(styleId)) {
            return;
        }

        var css = [
            '.espo-dental-stom{background:' + tokens.background + ';color:' + tokens.text + ';padding:12px;line-height:1.35;}',
            '.espo-dental-stom *{box-sizing:border-box;}',
            '.espo-dental-stom a{color:' + tokens.primaryDark + ';}',
            '.espo-dental-stom-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 10px;}',
            '.espo-dental-stom-toolbar__spacer{flex:1 1 auto;}',
            '.espo-dental-stom-layout{display:grid;gap:12px;align-items:start;}',
            '.espo-dental-stom-layout--two{grid-template-columns:minmax(260px,360px) minmax(0,1fr);}',
            '.espo-dental-stom-layout--three{grid-template-columns:minmax(220px,280px) minmax(0,1fr) minmax(240px,320px);}',
            '.espo-dental-stom-panel{background:' + tokens.surface + ';border:1px solid ' + tokens.border + ';border-radius:' + tokens.radius + ';box-shadow:' + tokens.shadow + ';min-width:0;}',
            '.espo-dental-stom-panel__header{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;border-bottom:1px solid ' + tokens.border + ';}',
            '.espo-dental-stom-panel__title{margin:0;font-size:16px;font-weight:700;color:' + tokens.text + ';}',
            '.espo-dental-stom-panel__body{padding:12px;}',
            '.espo-dental-stom-panel--compact .espo-dental-stom-panel__body{padding:8px 10px;}',
            '.espo-dental-stom-muted{color:' + tokens.muted + ';}',
            '.espo-dental-stom-kpi{display:grid;gap:6px;min-height:82px;padding:12px 14px;background:' + tokens.surface + ';border:1px solid ' + tokens.border + ';border-radius:' + tokens.radius + ';box-shadow:' + tokens.shadow + ';}',
            '.espo-dental-stom-kpi__value{font-size:26px;font-weight:800;color:' + tokens.text + ';line-height:1;}',
            '.espo-dental-stom-kpi__label{font-size:12px;color:' + tokens.muted + ';font-weight:700;text-transform:uppercase;}',
            '.espo-dental-stom-table{width:100%;border-collapse:separate;border-spacing:0;font-size:12px;}',
            '.espo-dental-stom-table th{background:' + tokens.surfaceAlt + ';color:' + tokens.muted + ';font-weight:600;border-bottom:1px solid ' + tokens.border + ';padding:7px 8px;text-align:left;}',
            '.espo-dental-stom-table td{border-bottom:1px solid ' + tokens.border + ';padding:7px 8px;vertical-align:middle;}',
            '.espo-dental-stom-table tr:last-child td{border-bottom:0;}',
            '.espo-dental-stom-list{display:grid;gap:6px;margin:0;padding:0;list-style:none;}',
            '.espo-dental-stom-list__item{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;border:1px solid ' + tokens.border + ';border-radius:' + tokens.compactRadius + ';background:' + tokens.surface + ';}',
            '.espo-dental-stom-list__item>span:first-child{min-width:0;}',
            '.espo-dental-stom-list__item a{font-weight:700;}',
            '.espo-dental-stom-badge{display:inline-flex;align-items:center;gap:4px;max-width:100%;border-radius:999px;padding:2px 7px;font-size:11px;font-weight:600;line-height:1.35;white-space:normal;}',
            '.espo-dental-stom-badge--primary{background:#dfeeea;color:' + tokens.primaryDark + ';}',
            '.espo-dental-stom-badge--success{background:#e2f1e8;color:' + tokens.success + ';}',
            '.espo-dental-stom-badge--warning{background:#f7edd8;color:' + tokens.warning + ';}',
            '.espo-dental-stom-badge--danger{background:#f4dddd;color:' + tokens.danger + ';}',
            '.espo-dental-stom-badge--info{background:#ddebf1;color:' + tokens.info + ';}',
            '.espo-dental-stom-badge--muted{background:#eef2f0;color:' + tokens.muted + ';}',
            '.espo-dental-stom-button{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:30px;border:1px solid ' + tokens.borderStrong + ';border-radius:' + tokens.compactRadius + ';background:' + tokens.surface + ';color:' + tokens.text + ';padding:5px 10px;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;}',
            '.espo-dental-stom-button:hover,.espo-dental-stom-button:focus{text-decoration:none;border-color:' + tokens.primary + ';color:' + tokens.primaryDark + ';}',
            '.espo-dental-stom-button--primary{background:' + tokens.primary + ';border-color:' + tokens.primary + ';color:#fff;}',
            '.espo-dental-stom-button--primary:hover,.espo-dental-stom-button--primary:focus{background:' + tokens.primaryDark + ';border-color:' + tokens.primaryDark + ';color:#fff;}',
            '.espo-dental-stom-button--quiet{background:' + tokens.surfaceAlt + ';}',
            '.espo-dental-stom-button--danger{border-color:#dfb4b3;color:' + tokens.danger + ';}',
            '.espo-dental-stom-empty{display:flex;align-items:center;justify-content:center;min-height:72px;padding:12px;border:1px dashed ' + tokens.borderStrong + ';border-radius:' + tokens.radius + ';color:' + tokens.muted + ';background:' + tokens.surfaceAlt + ';font-size:12px;text-align:center;}',
            '@media (max-width:900px){.espo-dental-stom-layout--two,.espo-dental-stom-layout--three{grid-template-columns:1fr;}.espo-dental-stom{padding:8px;}}'
        ].join('\n');

        var style = doc.createElement('style');
        style.id = styleId;
        style.type = 'text/css';
        style.appendChild(doc.createTextNode(css));

        (doc.head || doc.getElementsByTagName('head')[0] || doc.documentElement).appendChild(style);
    }

    function escapeHtml(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
    }

    function renderAttributes(attrs) {
        var html = '';
        attrs = attrs || {};

        Object.keys(attrs).forEach(function (name) {
            var value = attrs[name];

            if (value === null || typeof value === 'undefined' || value === false) {
                return;
            }

            if (value === true) {
                html += ' ' + name;
                return;
            }

            html += ' ' + name + '="' + escapeAttribute(value) + '"';
        });

        return html;
    }

    function normalizeClasses(classes) {
        if (!classes) {
            return '';
        }

        if (Array.isArray(classes)) {
            return classes.filter(Boolean).join(' ');
        }

        return String(classes);
    }

    function workspace(content, options) {
        options = options || {};
        ensureStyles();

        var classes = ['espo-dental-stom'].concat(options.classes || []);

        return '<div class="' + normalizeClasses(classes) + '">' + (content || '') + '</div>';
    }

    function panel(options) {
        options = options || {};

        var classes = ['espo-dental-stom-panel'].concat(options.classes || []);
        var header = '';

        if (options.title || options.actions) {
            header = '<div class="espo-dental-stom-panel__header">' +
                '<h3 class="espo-dental-stom-panel__title">' + escapeHtml(options.title || '') + '</h3>' +
                (options.actions || '') +
                '</div>';
        }

        return '<section class="' + normalizeClasses(classes) + '"' + renderAttributes(options.attrs) + '>' +
            header +
            '<div class="espo-dental-stom-panel__body">' + (options.body || '') + '</div>' +
            '</section>';
    }

    function badge(label, tone, attrs) {
        var toneClass = statusClasses[tone] || riskClasses[tone] || 'espo-dental-stom-badge--muted';
        var displayLabel = translateLabel(label) || translateLabel(tone) || label;

        return '<span class="espo-dental-stom-badge ' + toneClass + '"' + renderAttributes(attrs) + '>' +
            escapeHtml(displayLabel) +
            '</span>';
    }

    function translateLabel(value, group) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        var key = String(value);

        if (group && labelGroups[group] && Object.prototype.hasOwnProperty.call(labelGroups[group], key)) {
            return labelGroups[group][key];
        }

        return badgeLabels[key] || key;
    }

    function formatValue(value, group) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return '';
        }

        if (typeof value === 'boolean') {
            return value ? 'Да' : 'Нет';
        }

        if (group) {
            return translateLabel(value, group);
        }

        return translateLabel(value);
    }

    function button(label, options) {
        options = options || {};

        var tone = options.tone ? ' espo-dental-stom-button--' + options.tone : '';
        var icon = options.iconClass ? '<span class="' + escapeAttribute(options.iconClass) + '" aria-hidden="true"></span>' : '';
        var attrs = options.attrs || {};

        attrs.type = attrs.type || 'button';

        return '<button class="espo-dental-stom-button' + tone + '"' + renderAttributes(attrs) + '>' +
            icon +
            '<span>' + escapeHtml(label) + '</span>' +
            '</button>';
    }

    function emptyState(message) {
        return '<div class="espo-dental-stom-empty">' + escapeHtml(message) + '</div>';
    }

    return {
        styleId: styleId,
        tokens: tokens,
        statusClasses: statusClasses,
        riskClasses: riskClasses,
        badgeLabels: badgeLabels,
        labelGroups: labelGroups,
        ensureStyles: ensureStyles,
        escapeHtml: escapeHtml,
        workspace: workspace,
        panel: panel,
        badge: badge,
        button: button,
        label: translateLabel,
        formatValue: formatValue,
        emptyState: emptyState
    };
});
