define('espo-dental:tooth-chart/renderer', [], function () {

    var ADULT_QUADRANTS = {
        UR: ['18','17','16','15','14','13','12','11'],
        UL: ['21','22','23','24','25','26','27','28'],
        LL: ['38','37','36','35','34','33','32','31'],
        LR: ['41','42','43','44','45','46','47','48']
    };
    var CHILD_QUADRANTS = {
        UR: ['55','54','53','52','51'],
        UL: ['61','62','63','64','65'],
        LL: ['75','74','73','72','71'],
        LR: ['81','82','83','84','85']
    };

    var CONDITION_COLORS = {
        healthy: '#ffffff',
        caries: '#c0392b',
        filling: '#7f8c8d',
        root_canal: '#f1c40f',
        crown: '#d4a017',
        bridge: '#b07d2b',
        veneer: '#e8d5b7',
        implant: '#3498db',
        extracted: '#2c3e50',
        missing: '#95a5a6',
        sealant: '#16a085'
    };

    var CONDITIONS = Object.keys(CONDITION_COLORS);

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var TOOTH_W = 28;
    var TOOTH_H = 38;
    var GAP = 2;
    var ROW_GAP = 28;

    function svg(tag, attrs) {
        var el = document.createElementNS(SVG_NS, tag);
        if (attrs) {
            for (var k in attrs) {
                if (Object.prototype.hasOwnProperty.call(attrs, k)) {
                    el.setAttribute(k, attrs[k]);
                }
            }
        }
        return el;
    }

    function buildRow(numbers, yTop, teeth, opts, root) {
        var startX = 10;
        for (var i = 0; i < numbers.length; i++) {
            var num = numbers[i];
            var x = startX + i * (TOOTH_W + GAP);
            var state = teeth[num] || {};
            var cond = state.c || 'healthy';
            var color = CONDITION_COLORS[cond] || '#fff';

            var rect = svg('rect', {
                'x': x,
                'y': yTop,
                'width': TOOTH_W,
                'height': TOOTH_H,
                'rx': 4,
                'fill': color,
                'stroke': '#333',
                'stroke-width': '1',
                'data-tooth': num,
                'cursor': opts.readOnly ? 'default' : 'pointer'
            });
            if (state.n) {
                rect.setAttribute('class', 'has-note');
                rect.setAttribute('stroke-width', '2');
                rect.setAttribute('stroke', '#000');
            }
            if (!opts.readOnly) {
                rect.addEventListener('click', function (number, evt) {
                    evt.preventDefault();
                    openEditor(opts, number);
                }.bind(null, num));
            }
            root.appendChild(rect);

            var label = svg('text', {
                'x': x + TOOTH_W / 2,
                'y': yTop + TOOTH_H + 12,
                'text-anchor': 'middle',
                'font-size': '10',
                'font-family': 'monospace',
                'fill': '#333'
            });
            label.textContent = num;
            root.appendChild(label);
        }
    }

    function openEditor(opts, toothNumber) {
        var state = (opts.teeth || {})[toothNumber] || {};
        var current = state.c || 'healthy';
        var note = state.n || '';

        var html = '<div style="padding:12px"><div style="margin-bottom:8px"><b>' +
            opts.translate('Tooth', 'labels') + ' ' + toothNumber + '</b></div>' +
            '<div style="margin-bottom:8px;display:grid;grid-template-columns:repeat(2,1fr);gap:4px">';
        CONDITIONS.forEach(function (c) {
            var checked = (c === current) ? 'checked' : '';
            html += '<label style="display:flex;align-items:center;gap:6px;cursor:pointer">' +
                '<input type="radio" name="cond" value="' + c + '" ' + checked + '>' +
                '<span style="display:inline-block;width:14px;height:14px;background:' +
                CONDITION_COLORS[c] + ';border:1px solid #333;border-radius:2px"></span>' +
                '<span>' + opts.translate(c) + '</span>' +
                '</label>';
        });
        html += '</div>' +
            '<div style="margin-bottom:8px"><label>' +
            opts.translate('Note', 'labels') +
            ': <input type="text" name="note" value="' + escapeHtml(note) +
            '" style="width:100%"></label></div>' +
            '<div style="text-align:right">' +
            '<button class="btn btn-default" data-action="cancel">' + opts.translate('Cancel', 'labels', 'Global') + '</button> ' +
            '<button class="btn btn-primary" data-action="save">' + opts.translate('Save', 'labels', 'Global') + '</button>' +
            '</div></div>';

        var $dialog = window.jQuery('<div title="' + opts.translate('Edit Tooth', 'labels') + '">' + html + '</div>');
        document.body.appendChild($dialog.get(0));

        var modal;
        if (window.bootbox) {
            modal = window.bootbox.dialog({
                title: opts.translate('Edit Tooth', 'labels'),
                message: html,
                onEscape: true
            });
            modal.find('[data-action="cancel"]').on('click', function () { modal.modal('hide'); });
            modal.find('[data-action="save"]').on('click', function () {
                var cond = modal.find('input[name="cond"]:checked').val();
                var noteVal = modal.find('input[name="note"]').val();
                applyChange(opts, toothNumber, cond, noteVal);
                modal.modal('hide');
            });
            $dialog.remove();
            return;
        }

        $dialog.find('[data-action="cancel"]').on('click', function () { $dialog.remove(); });
        $dialog.find('[data-action="save"]').on('click', function () {
            var cond = $dialog.find('input[name="cond"]:checked').val();
            var noteVal = $dialog.find('input[name="note"]').val();
            applyChange(opts, toothNumber, cond, noteVal);
            $dialog.remove();
        });
    }

    function applyChange(opts, toothNumber, condition, note) {
        var next = window.jQuery.extend(true, {}, opts.teeth || {});
        if (!condition || condition === 'healthy') {
            if (note) {
                next[toothNumber] = {c: 'healthy', n: note};
            } else {
                delete next[toothNumber];
            }
        } else {
            next[toothNumber] = note ? {c: condition, n: note} : {c: condition};
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
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function buildLegend(opts) {
        var html = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;font-size:12px">';
        CONDITIONS.forEach(function (c) {
            html += '<span style="display:inline-flex;align-items:center;gap:4px">' +
                '<span style="display:inline-block;width:12px;height:12px;background:' +
                CONDITION_COLORS[c] + ';border:1px solid #333"></span>' +
                opts.translate(c) + '</span>';
        });
        html += '</div>';
        return html;
    }

    function doRender(container, opts) {
        container.innerHTML = '';

        var quadrants;
        if (opts.dentition === 'child') {
            quadrants = CHILD_QUADRANTS;
        } else {
            quadrants = ADULT_QUADRANTS;
        }

        var keysTop = Object.keys(quadrants).filter(function (k) { return k[0] === 'U'; });
        var keysBottom = Object.keys(quadrants).filter(function (k) { return k[0] === 'L'; });

        var topCount = keysTop.reduce(function (s, k) { return s + quadrants[k].length; }, 0);
        var width = 20 + topCount * (TOOTH_W + GAP) + 40;
        var height = 2 * (TOOTH_H + 14) + ROW_GAP + 10;

        var root = svg('svg', {
            'width': width,
            'height': height,
            'viewBox': '0 0 ' + width + ' ' + height,
            'xmlns': SVG_NS
        });

        var midY = 14;
        var topRow = [];
        keysTop.forEach(function (k) {
            quadrants[k].forEach(function (n) { topRow.push(n); });
        });
        var bottomRow = [];
        keysBottom.forEach(function (k) {
            quadrants[k].forEach(function (n) { bottomRow.push(n); });
        });

        buildRow(topRow, midY, opts.teeth || {}, opts, root);
        buildRow(bottomRow, midY + TOOTH_H + 14 + ROW_GAP, opts.teeth || {}, opts, root);

        var sep = svg('line', {
            'x1': 10,
            'y1': midY + TOOTH_H + 14 + ROW_GAP / 2,
            'x2': width - 30,
            'y2': midY + TOOTH_H + 14 + ROW_GAP / 2,
            'stroke': '#bbb',
            'stroke-dasharray': '4 4'
        });
        root.appendChild(sep);

        container.appendChild(root);
        var legend = document.createElement('div');
        legend.innerHTML = buildLegend(opts);
        container.appendChild(legend.firstChild);
    }

    return {
        render: function (container, opts) {
            opts = opts || {};
            opts._container = container;
            doRender(container, opts);
        }
    };
});
