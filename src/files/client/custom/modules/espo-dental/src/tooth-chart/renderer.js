define('espo-dental:tooth-chart/renderer', [], function () {

    var ADULT_TOP = ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'];
    var ADULT_BOTTOM = ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'];
    var CHILD_TOP = ['55', '54', '53', '52', '51', '61', '62', '63', '64', '65'];
    var CHILD_BOTTOM = ['85', '84', '83', '82', '81', '71', '72', '73', '74', '75'];

    var CONDITION_COLORS = {
        healthy: '#ffffff',
        caries: '#c83e58',
        filling: '#92a7b4',
        root_canal: '#ffc533',
        crown: '#91b400',
        bridge: '#a069c2',
        veneer: '#eecfb8',
        implant: '#1885b3',
        extracted: '#59606f',
        missing: '#d8e3eb',
        sealant: '#8a9b2f'
    };

    var CONDITIONS = [
        'healthy',
        'caries',
        'filling',
        'root_canal',
        'crown',
        'bridge',
        'veneer',
        'implant',
        'extracted',
        'missing',
        'sealant'
    ];

    var WHOLE_TOOTH_CONDITIONS = [
        'healthy',
        'crown',
        'bridge',
        'implant',
        'extracted',
        'missing'
    ];

    var SURFACE_CONDITION_RULES = {
        veneer: ['b'],
        sealant: ['o']
    };

    var SURFACES = [
        {key: 'o', label: 'surface_o', fallback: 'Occlusal / Incisal'},
        {key: 'm', label: 'surface_m', fallback: 'Mesial'},
        {key: 'd', label: 'surface_d', fallback: 'Distal'},
        {key: 'b', label: 'surface_b', fallback: 'Buccal / Vestibular'},
        {key: 'l', label: 'surface_l', fallback: 'Lingual / Palatal'}
    ];

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var CELL_W = 58;
    var SURFACE_R = 19;
    var TOOTH_W = 44;

    function svg(tag, attrs) {
        var el = document.createElementNS(SVG_NS, tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                el.setAttribute(k, attrs[k]);
            });
        }
        return el;
    }

    function parseArray(value) {
        if (typeof value === 'string' && value !== '') {
            try {
                value = JSON.parse(value);
            } catch (e) {
                value = [];
            }
        }

        return Array.isArray(value) ? value : [];
    }

    function pickConfiguredLabel(item, opts) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        var lang = opts && (opts.language || opts.locale);
        if (lang && item.labels && typeof item.labels === 'object' && typeof item.labels[lang] === 'string') {
            return item.labels[lang];
        }

        if (typeof item.label === 'string' && item.label !== '') {
            return item.label;
        }

        if (typeof item.name === 'string' && item.name !== '') {
            return item.name;
        }

        return '';
    }

    function defaultConditionItems() {
        return CONDITIONS.map(function (id) {
            return {
                id: id,
                color: normalizeColor(CONDITION_COLORS[id], CONDITION_COLORS.healthy)
            };
        });
    }

    function normalizeColor(value, fallback) {
        value = String(value || '').trim();
        if (/^#[0-9a-f]{3}([0-9a-f]{3})?([0-9a-f]{2})?$/i.test(value)) {
            return value;
        }

        return fallback;
    }

    function getConditionItems(opts) {
        if (opts && opts._conditionItems) {
            return opts._conditionItems;
        }

        var configured = parseArray(opts && opts.conditions);
        var seen = {};
        var items = configured.map(function (item) {
            if (typeof item === 'string') {
                item = {id: item};
            }
            item = item || {};

            var id = String(item.id || item.value || item.name || '').trim();
            if (!id || seen[id]) {
                return null;
            }

            seen[id] = true;

            return {
                id: id,
                label: pickConfiguredLabel(item, opts),
                color: normalizeColor(item.color, CONDITION_COLORS[id] || '#d8e3eb')
            };
        }).filter(function (item) {
            return !!item;
        });

        if (!items.length) {
            items = defaultConditionItems();
        }

        if (!seen.healthy && !items.some(function (item) { return item.id === 'healthy'; })) {
            items.unshift({id: 'healthy', color: CONDITION_COLORS.healthy});
        }

        if (opts) {
            opts._conditionItems = items;
        }

        return items;
    }

    function getConditionMap(opts) {
        if (opts && opts._conditionMap) {
            return opts._conditionMap;
        }

        var map = {};
        getConditionItems(opts).forEach(function (item) {
            map[item.id] = item;
        });

        if (opts) {
            opts._conditionMap = map;
        }

        return map;
    }

    function isWholeToothCondition(condition) {
        return WHOLE_TOOTH_CONDITIONS.indexOf(condition) !== -1;
    }

    function isSurfaceConditionAllowed(condition, surface) {
        if (condition === 'healthy') {
            return true;
        }

        if (SURFACE_CONDITION_RULES[condition]) {
            return SURFACE_CONDITION_RULES[condition].indexOf(surface) !== -1;
        }

        return !isWholeToothCondition(condition);
    }

    function normalizeSurfaceCondition(condition, surface) {
        condition = condition || 'healthy';

        return isSurfaceConditionAllowed(condition, surface) ? condition : 'healthy';
    }

    function getWholeToothConditionItems(opts) {
        return getConditionItems(opts).filter(function (condition) {
            return isWholeToothCondition(condition.id);
        });
    }

    function getSurfaceConditionItems(surface, opts) {
        return getConditionItems(opts).filter(function (condition) {
            return isSurfaceConditionAllowed(condition.id, surface);
        });
    }

    function getSurfaceItems(opts) {
        if (opts && opts._surfaceItems) {
            return opts._surfaceItems;
        }

        var items = SURFACES.map(function (surface) {
            return {
                key: surface.key,
                label: surface.label,
                fallback: surface.fallback
            };
        });
        var byKey = {};
        items.forEach(function (surface) {
            byKey[surface.key] = surface;
        });

        parseArray(opts && opts.surfaces).forEach(function (item) {
            if (typeof item === 'string') {
                item = {key: item};
            }
            item = item || {};

            var key = String(item.key || item.id || item.value || '').trim();
            if (!byKey[key]) {
                return;
            }

            var label = pickConfiguredLabel(item, opts);
            if (label !== '') {
                byKey[key].configuredLabel = label;
            }
        });

        if (opts) {
            opts._surfaceItems = items;
        }

        return items;
    }

    function normalizeTeeth(value) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return {};
        }
        return value;
    }

    function toothState(teeth, number) {
        var state = teeth[number];
        if (!state || typeof state !== 'object' || Array.isArray(state)) {
            return {};
        }
        return state;
    }

    function surfaceState(state, surface) {
        if (state.surfaces && state.surfaces[surface]) {
            return state.surfaces[surface];
        }

        if (surface === 'o' && state.c && !isWholeToothCondition(state.c)) {
            return {c: state.c, n: state.n || ''};
        }

        return {};
    }

    function surfaceCondition(state, surface) {
        return normalizeSurfaceCondition(surfaceState(state, surface).c || 'healthy', surface);
    }

    function dominantCondition(state, opts) {
        if (state.c && state.c !== 'healthy') {
            return state.c;
        }

        if (!state.surfaces) {
            return 'healthy';
        }

        var surfaces = getSurfaceItems(opts);
        for (var i = 0; i < surfaces.length; i++) {
            var c = surfaceCondition(state, surfaces[i].key);
            if (c !== 'healthy') {
                return c;
            }
        }

        return 'healthy';
    }

    function hasAnyNote(state, opts) {
        if (state.n) {
            return true;
        }
        if (!state.surfaces) {
            return false;
        }

        return getSurfaceItems(opts).some(function (surface) {
            return !!surfaceState(state, surface.key).n;
        });
    }

    function conditionColor(condition, opts) {
        var item = getConditionMap(opts)[condition];
        return (item && item.color) || CONDITION_COLORS[condition] || CONDITION_COLORS.healthy;
    }

    function translate(opts, key, category, scope) {
        if (!opts || typeof opts.translate !== 'function') {
            return key;
        }

        return opts.translate(key, category, scope);
    }

    function translateSurface(opts, surface) {
        if (surface.configuredLabel) {
            return surface.configuredLabel;
        }

        var label = translate(opts, surface.label, 'labels');
        return label === surface.label ? surface.fallback : label;
    }

    function translateCondition(opts, condition) {
        var item = getConditionMap(opts)[condition];
        var configuredLabel = pickConfiguredLabel(item, opts);
        if (configuredLabel !== '') {
            return configuredLabel;
        }

        var label = translate(opts, condition);
        return label === condition ? condition.replace(/_/g, ' ') : label;
    }

    function isMolar(number) {
        var digit = number.slice(-1);
        if (number.charAt(0) === '5' || number.charAt(0) === '6' || number.charAt(0) === '7' || number.charAt(0) === '8') {
            return digit === '4' || digit === '5';
        }
        return digit === '6' || digit === '7' || digit === '8';
    }

    function isAnterior(number) {
        var digit = number.slice(-1);
        return digit === '1' || digit === '2' || digit === '3';
    }

    function addClick(target, opts, toothNumber) {
        target.setAttribute('cursor', opts.readOnly ? 'default' : 'pointer');
        if (opts.readOnly) {
            return;
        }

        target.addEventListener('click', function (evt) {
            evt.preventDefault();
            evt.stopPropagation();
            openEditor(opts, toothNumber);
        });
    }

    function buildToothSilhouette(root, x, y, number, upper, state, opts) {
        var g = svg('g', {
            'data-tooth': number
        });
        addClick(g, opts, number);
        var condition = dominantCondition(state, opts);
        var isRemoved = condition === 'extracted' || condition === 'missing';

        var d;
        var pulpD;
        if (isMolar(number)) {
            d = upper ?
                'M6 68 C3 56 6 43 14 39 L10 5 C15 2 20 36 22 38 C25 35 29 2 34 5 L30 39 C39 43 42 57 37 68 C29 73 15 73 6 68 Z' :
                'M6 10 C3 22 6 35 14 39 L10 73 C15 76 20 42 22 40 C25 43 29 76 34 73 L30 39 C39 35 42 21 37 10 C29 5 15 5 6 10 Z';
            pulpD = upper ?
                'M15 46 L18 13 L22 45 L26 13 L30 46 Z' :
                'M15 32 L18 65 L22 33 L26 65 L30 32 Z';
        } else if (isAnterior(number)) {
            d = upper ?
                'M13 70 C9 60 11 45 17 39 L19 5 C23 2 28 35 31 40 C37 48 35 61 30 70 C25 73 18 73 13 70 Z' :
                'M13 8 C9 18 11 33 17 39 L19 73 C23 76 28 43 31 38 C37 30 35 17 30 8 C25 5 18 5 13 8 Z';
            pulpD = upper ?
                'M21 43 L24 15 L27 43 Z' :
                'M21 35 L24 63 L27 35 Z';
        } else {
            d = upper ?
                'M10 69 C6 58 8 45 16 39 L15 5 C20 3 23 35 24 39 C27 35 30 3 35 5 L32 39 C40 45 40 58 35 69 C29 73 16 73 10 69 Z' :
                'M10 9 C6 20 8 33 16 39 L15 73 C20 75 23 43 24 39 C27 43 30 75 35 73 L32 39 C40 33 40 20 35 9 C29 5 16 5 10 9 Z';
            pulpD = upper ?
                'M21 44 L23 14 L26 44 Z' :
                'M21 34 L23 64 L26 34 Z';
        }

        var outline = svg('path', {
            d: d,
            transform: 'translate(' + x + ' ' + y + ')',
            fill: '#fff',
            stroke: '#9dafba',
            'stroke-width': '1.6',
            'stroke-linejoin': 'round',
            opacity: isRemoved ? '0.42' : '1'
        });
        g.appendChild(outline);

        var pulp = svg('path', {
            d: pulpD,
            transform: 'translate(' + x + ' ' + y + ')',
            fill: '#ff6fa8',
            stroke: 'none',
            opacity: isRemoved ? '0.22' : '0.9'
        });
        g.appendChild(pulp);

        if (condition === 'bridge') {
            outline.setAttribute('stroke', conditionColor(condition, opts));
            outline.setAttribute('stroke-width', '2.4');
            outline.setAttribute('stroke-dasharray', '5 3');
        }

        if (condition !== 'healthy') {
            var overlay = svg('circle', {
                cx: x + 24,
                cy: y + (upper ? 53 : 25),
                r: isMolar(number) ? 12 : 10,
                fill: conditionColor(condition, opts),
                opacity: isRemoved ? '0.55' : '0.9',
                stroke: condition === 'missing' ? '#8ea1ad' : 'none'
            });
            g.appendChild(overlay);
        }

        if (hasAnyNote(state, opts)) {
            outline.setAttribute('stroke', '#2185d0');
            outline.setAttribute('stroke-width', '2.2');
        }

        root.appendChild(g);
    }

    function wedgePath(cx, cy, outer, inner, side) {
        if (side === 'm') {
            return 'M' + (cx - outer) + ' ' + cy + ' A' + outer + ' ' + outer + ' 0 0 1 ' + (cx - 5) + ' ' + (cy - outer + 5) +
                ' L' + (cx - inner + 3) + ' ' + (cy - inner + 3) + ' A' + inner + ' ' + inner + ' 0 0 0 ' + (cx - inner) + ' ' + cy +
                ' A' + inner + ' ' + inner + ' 0 0 0 ' + (cx - inner + 3) + ' ' + (cy + inner - 3) +
                ' L' + (cx - 5) + ' ' + (cy + outer - 5) + ' A' + outer + ' ' + outer + ' 0 0 1 ' + (cx - outer) + ' ' + cy + ' Z';
        }
        if (side === 'd') {
            return 'M' + (cx + outer) + ' ' + cy + ' A' + outer + ' ' + outer + ' 0 0 0 ' + (cx + 5) + ' ' + (cy - outer + 5) +
                ' L' + (cx + inner - 3) + ' ' + (cy - inner + 3) + ' A' + inner + ' ' + inner + ' 0 0 1 ' + (cx + inner) + ' ' + cy +
                ' A' + inner + ' ' + inner + ' 0 0 1 ' + (cx + inner - 3) + ' ' + (cy + inner - 3) +
                ' L' + (cx + 5) + ' ' + (cy + outer - 5) + ' A' + outer + ' ' + outer + ' 0 0 0 ' + (cx + outer) + ' ' + cy + ' Z';
        }
        if (side === 'b') {
            return 'M' + cx + ' ' + (cy - outer) + ' A' + outer + ' ' + outer + ' 0 0 1 ' + (cx + outer - 5) + ' ' + (cy - 5) +
                ' L' + (cx + inner - 3) + ' ' + (cy - inner + 3) + ' A' + inner + ' ' + inner + ' 0 0 0 ' + cx + ' ' + (cy - inner) +
                ' A' + inner + ' ' + inner + ' 0 0 0 ' + (cx - inner + 3) + ' ' + (cy - inner + 3) +
                ' L' + (cx - outer + 5) + ' ' + (cy - 5) + ' A' + outer + ' ' + outer + ' 0 0 1 ' + cx + ' ' + (cy - outer) + ' Z';
        }
        return 'M' + cx + ' ' + (cy + outer) + ' A' + outer + ' ' + outer + ' 0 0 0 ' + (cx + outer - 5) + ' ' + (cy + 5) +
            ' L' + (cx + inner - 3) + ' ' + (cy + inner - 3) + ' A' + inner + ' ' + inner + ' 0 0 1 ' + cx + ' ' + (cy + inner) +
            ' A' + inner + ' ' + inner + ' 0 0 1 ' + (cx - inner + 3) + ' ' + (cy + inner - 3) +
            ' L' + (cx - outer + 5) + ' ' + (cy + 5) + ' A' + outer + ' ' + outer + ' 0 0 0 ' + cx + ' ' + (cy + outer) + ' Z';
    }

    function buildSurfaceDiagram(root, cx, cy, number, state, opts) {
        var g = svg('g', {
            'data-tooth': number
        });
        addClick(g, opts, number);

        var outer = SURFACE_R;
        var inner = 9;
        ['m', 'd', 'b', 'l'].forEach(function (surface) {
            var path = svg('path', {
                d: wedgePath(cx, cy, outer, inner, surface),
                fill: conditionColor(surfaceCondition(state, surface), opts),
                stroke: '#9dafba',
                'stroke-width': '1.2'
            });
            g.appendChild(path);
        });

        var center = svg('circle', {
            cx: cx,
            cy: cy,
            r: inner,
            fill: conditionColor(surfaceCondition(state, 'o'), opts),
            stroke: '#9dafba',
            'stroke-width': '1.2'
        });
        g.appendChild(center);

        var outline = svg('circle', {
            cx: cx,
            cy: cy,
            r: outer,
            fill: 'none',
            stroke: '#9dafba',
            'stroke-width': '1.5'
        });
        g.appendChild(outline);

        if (hasAnyNote(state, opts)) {
            outline.setAttribute('stroke', '#2185d0');
            outline.setAttribute('stroke-width', '2.3');
        }

        root.appendChild(g);
    }

    function addLabel(root, x, y, text) {
        var label = svg('text', {
            x: x,
            y: y,
            'text-anchor': 'middle',
            'font-size': '15',
            'font-family': 'Arial, sans-serif',
            fill: '#1d3142'
        });
        label.textContent = text;
        root.appendChild(label);
    }

    function buildChart(root, teeth, layout, opts, yOffset) {
        var count = layout.top.length;
        var width = 20 + count * CELL_W;
        var x0 = 10;
        var topToothY = yOffset + 8;
        var topSurfaceY = yOffset + 120;
        var topLabelY = yOffset + 168;
        var bottomLabelY = yOffset + 192;
        var bottomSurfaceY = yOffset + 238;
        var bottomToothY = yOffset + 292;

        var topBand = svg('rect', {
            x: 2,
            y: yOffset,
            width: width,
            height: 88,
            rx: 10,
            fill: '#fde6c9'
        });
        root.appendChild(topBand);

        var bottomBand = svg('rect', {
            x: 2,
            y: yOffset + 275,
            width: width,
            height: 100,
            rx: 10,
            fill: '#fde6c9'
        });
        root.appendChild(bottomBand);

        layout.top.forEach(function (number, i) {
            var cx = x0 + i * CELL_W + CELL_W / 2;
            var state = toothState(teeth, number);
            buildToothSilhouette(root, cx - TOOTH_W / 2, topToothY, number, true, state, opts);
            buildSurfaceDiagram(root, cx, topSurfaceY, number, state, opts);
            addLabel(root, cx, topLabelY, number);
        });

        layout.bottom.forEach(function (number, i) {
            var cx = x0 + i * CELL_W + CELL_W / 2;
            var state = toothState(teeth, number);
            addLabel(root, cx, bottomLabelY, number);
            buildSurfaceDiagram(root, cx, bottomSurfaceY, number, state, opts);
            buildToothSilhouette(root, cx - TOOTH_W / 2, bottomToothY, number, false, state, opts);
        });
    }

    function chartLayouts(dentition) {
        if (dentition === 'child') {
            return [{top: CHILD_TOP, bottom: CHILD_BOTTOM}];
        }
        if (dentition === 'mixed') {
            return [
                {top: ADULT_TOP, bottom: ADULT_BOTTOM},
                {top: CHILD_TOP, bottom: CHILD_BOTTOM}
            ];
        }
        return [{top: ADULT_TOP, bottom: ADULT_BOTTOM}];
    }

    function allowedTeeth(dentition) {
        var layouts = chartLayouts(dentition);
        var teeth = [];

        layouts.forEach(function (layout) {
            teeth = teeth.concat(layout.top, layout.bottom);
        });

        return teeth;
    }

    function openEditor(opts, toothNumber) {
        var state = toothState(normalizeTeeth(opts.teeth), toothNumber);
        var toothNote = state.n || '';
        var surfaces = getSurfaceItems(opts);
        var wholeCondition = isWholeToothCondition(state.c) ? state.c : 'healthy';
        var wholeConditions = getWholeToothConditionItems(opts);
        var html = '<div class="espo-dental-tooth-editor" style="padding:12px">' +
            '<div style="margin-bottom:10px"><b>' +
            escapeHtml(translate(opts, 'Tooth', 'labels')) + ' ' + escapeHtml(toothNumber) +
            '</b></div>' +
            '<table class="table table-condensed" style="margin-bottom:10px"><tbody>';

        html += '<tr>' +
            '<td style="vertical-align:middle;width:28%">' + escapeHtml(translate(opts, 'Whole Tooth', 'labels')) + '</td>' +
            '<td colspan="2"><select class="form-control input-sm" name="tooth-condition">';
        wholeConditions.forEach(function (condition) {
            var selected = condition.id === wholeCondition ? ' selected' : '';
            html += '<option value="' + escapeHtml(condition.id) + '"' + selected + '>' +
                escapeHtml(translateCondition(opts, condition.id)) +
                '</option>';
        });
        html += '</select></td></tr>';

        surfaces.forEach(function (surface) {
            var current = normalizeSurfaceCondition(surfaceCondition(state, surface.key), surface.key);
            var note = surfaceState(state, surface.key).n || '';
            var conditions = getSurfaceConditionItems(surface.key, opts);
            html += '<tr>' +
                '<td style="vertical-align:middle;width:28%">' + escapeHtml(translateSurface(opts, surface)) + '</td>' +
                '<td><select class="form-control input-sm" name="surface-' + surface.key + '-condition">';
            conditions.forEach(function (condition) {
                var selected = condition.id === current ? ' selected' : '';
                html += '<option value="' + escapeHtml(condition.id) + '"' + selected + '>' +
                    escapeHtml(translateCondition(opts, condition.id)) +
                    '</option>';
            });
            html += '</select></td>' +
                '<td><input class="form-control input-sm" type="text" name="surface-' + surface.key + '-note" value="' + escapeHtml(note) + '" placeholder="' + escapeHtml(translate(opts, 'Note', 'labels')) + '"></td>' +
                '</tr>';
        });

        html += '</tbody></table>' +
            '<div style="margin-bottom:10px"><label style="width:100%">' +
            escapeHtml(translate(opts, 'Note', 'labels')) +
            '<input class="form-control input-sm" type="text" name="tooth-note" value="' + escapeHtml(toothNote) + '">' +
            '</label></div>' +
            '<div style="text-align:right">' +
            '<button class="btn btn-default" data-action="cancel">' + escapeHtml(translate(opts, 'Cancel', 'labels', 'Global')) + '</button> ' +
            '<button class="btn btn-primary" data-action="save">' + escapeHtml(translate(opts, 'Save', 'labels', 'Global')) + '</button>' +
            '</div></div>';

        if (window.bootbox) {
            var modal = window.bootbox.dialog({
                title: translate(opts, 'Edit Tooth', 'labels'),
                message: html,
                onEscape: true,
                className: 'espo-dental-tooth-editor-modal'
            });
            fitEditorModal(modal);
            modal.on('shown.bs.modal', function () {
                fitEditorModal(modal);
            });
            modal.on('hidden.bs.modal', function () {
                window.jQuery(window).off('resize.espoDentalToothEditor');
            });
            window.jQuery(window)
                .off('resize.espoDentalToothEditor')
                .on('resize.espoDentalToothEditor', function () {
                    fitEditorModal(modal);
                });
            modal.find('[data-action="cancel"]').on('click', function () {
                modal.modal('hide');
            });
            modal.find('[data-action="save"]').on('click', function () {
                applyEditorChange(opts, toothNumber, modal);
                modal.modal('hide');
            });
            return;
        }

        var $dialog = window.jQuery('<div title="' + escapeHtml(translate(opts, 'Edit Tooth', 'labels')) + '">' + html + '</div>');
        document.body.appendChild($dialog.get(0));
        fitFallbackEditor($dialog);
        window.jQuery(window)
            .off('resize.espoDentalToothEditor')
            .on('resize.espoDentalToothEditor', function () {
                fitFallbackEditor($dialog);
            });
        $dialog.find('[data-action="cancel"]').on('click', function () {
            window.jQuery(window).off('resize.espoDentalToothEditor');
            $dialog.remove();
        });
        $dialog.find('[data-action="save"]').on('click', function () {
            applyEditorChange(opts, toothNumber, $dialog);
            window.jQuery(window).off('resize.espoDentalToothEditor');
            $dialog.remove();
        });
    }

    function fitEditorModal(modal) {
        var viewportWidth = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        var width = Math.max(296, Math.min(720, viewportWidth - 24));
        var $dialog = modal.find('.modal-dialog');
        var $body = modal.find('.modal-body');
        var isNarrow = viewportWidth < 560;

        modal.css({
            'padding-left': '0',
            'padding-right': '0'
        });
        $dialog.css({
            'position': 'fixed',
            'top': '12px',
            'left': '50%',
            'right': 'auto',
            'width': width + 'px',
            'max-width': 'calc(100vw - 24px)',
            'margin': '0',
            'transform': 'translateX(-50%)'
        });
        modal.find('.modal-content').css({
            'max-height': 'calc(100vh - 24px)',
            'display': 'flex',
            'flex-direction': 'column'
        });
        $body.css({
            'max-height': 'calc(100vh - 130px)',
            'overflow-y': 'auto',
            'overflow-x': 'hidden'
        });

        if (!isNarrow) {
            return;
        }

        var $editor = modal.find('.espo-dental-tooth-editor');
        $editor.css('padding', '0');
        $editor.find('table, tbody, tr, td').css({
            'display': 'block',
            'width': '100%'
        });
        $editor.find('td').css({
            'padding': '3px 0',
            'word-break': 'break-word'
        });
    }

    function fitFallbackEditor($dialog) {
        var viewportWidth = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        var width = Math.max(296, Math.min(720, viewportWidth - 24));

        $dialog.css({
            'position': 'fixed',
            'top': '12px',
            'left': '50%',
            'width': width + 'px',
            'max-width': 'calc(100vw - 24px)',
            'max-height': 'calc(100vh - 24px)',
            'overflow-y': 'auto',
            'overflow-x': 'hidden',
            'transform': 'translateX(-50%)',
            'z-index': '1060',
            'background': '#fff',
            'border': '1px solid #cfd6df',
            'border-radius': '6px',
            'box-shadow': '0 14px 40px rgba(0, 0, 0, 0.28)'
        });

        if (viewportWidth >= 560) {
            return;
        }

        var $editor = $dialog.find('.espo-dental-tooth-editor');
        $editor.css('padding', '12px');
        $editor.find('table, tbody, tr, td').css({
            'display': 'block',
            'width': '100%'
        });
        $editor.find('td').css({
            'padding': '3px 0',
            'word-break': 'break-word'
        });
    }

    function applyEditorChange(opts, toothNumber, $root) {
        var next = window.jQuery.extend(true, {}, normalizeTeeth(opts.teeth));
        var surfaces = {};
        var toothCondition = $root.find('[name="tooth-condition"]').val() || 'healthy';

        if (!isWholeToothCondition(toothCondition)) {
            toothCondition = 'healthy';
        }

        getSurfaceItems(opts).forEach(function (surface) {
            var condition = normalizeSurfaceCondition(
                $root.find('[name="surface-' + surface.key + '-condition"]').val() || 'healthy',
                surface.key
            );
            var note = ($root.find('[name="surface-' + surface.key + '-note"]').val() || '').trim();
            if (condition !== 'healthy' || note !== '') {
                surfaces[surface.key] = note !== '' ? {c: condition, n: note} : {c: condition};
            }
        });

        var toothNote = ($root.find('[name="tooth-note"]').val() || '').trim();
        var state = {};
        if (toothCondition !== 'healthy') {
            state.c = toothCondition;
        }
        if (Object.keys(surfaces).length) {
            state.surfaces = surfaces;
        }
        if (toothNote !== '') {
            state.n = toothNote;
        }

        if (Object.keys(state).length) {
            next[toothNumber] = state;
        } else {
            delete next[toothNumber];
        }

        opts.teeth = next;
        if (typeof opts.onChange === 'function') {
            opts.onChange(next);
        }
        if (opts._container) {
            doRender(opts._container, opts);
        }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildLegend(opts) {
        var html = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;font-size:12px">';
        getConditionItems(opts).forEach(function (condition) {
            html += '<span style="display:inline-flex;align-items:center;gap:4px">' +
                '<span style="display:inline-block;width:12px;height:12px;background:' +
                conditionColor(condition.id, opts) + ';border:1px solid #9dafba;border-radius:2px"></span>' +
                escapeHtml(translateCondition(opts, condition.id)) + '</span>';
        });
        html += '</div>';
        return html;
    }

    function doRender(container, opts) {
        container.innerHTML = '';
        opts.teeth = normalizeTeeth(opts.teeth);

        var layouts = chartLayouts(opts.dentition || 'adult');
        var chartHeight = 385;
        var gap = 22;
        var maxCount = layouts.reduce(function (max, layout) {
            return Math.max(max, layout.top.length);
        }, 0);
        var width = 20 + maxCount * CELL_W;
        var height = layouts.length * chartHeight + (layouts.length - 1) * gap;

        var scroller = document.createElement('div');
        scroller.style.overflowX = 'auto';

        var root = svg('svg', {
            width: width,
            height: height,
            viewBox: '0 0 ' + width + ' ' + height,
            xmlns: SVG_NS
        });
        root.setAttribute('style', 'max-width:100%;height:auto;display:block;');

        layouts.forEach(function (layout, index) {
            buildChart(root, opts.teeth, layout, opts, index * (chartHeight + gap));
        });

        scroller.appendChild(root);
        container.appendChild(scroller);

        var legend = document.createElement('div');
        legend.innerHTML = buildLegend(opts);
        container.appendChild(legend.firstChild);
    }

    return {
        allowedTeeth: allowedTeeth,
        isSurfaceConditionAllowed: isSurfaceConditionAllowed,
        wholeToothConditions: WHOLE_TOOTH_CONDITIONS,
        surfaceConditionRules: SURFACE_CONDITION_RULES,
        render: function (container, opts) {
            opts = opts || {};
            opts._container = container;
            doRender(container, opts);
        }
    };
});
