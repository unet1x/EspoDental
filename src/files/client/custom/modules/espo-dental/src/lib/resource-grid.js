define('espo-dental:lib/resource-grid', [], function () {
    'use strict';

    var STATUS_COLORS = {
        planned: '#4b7eaa',
        confirmed: '#438f7e',
        checked_in: '#c88f2c',
        arrived: '#438f7e',
        in_progress: '#2f705f',
        finished: '#7F7F7F',
        completed: '#7F7F7F',
        cancelled: '#c45f52',
        no_show: '#a84838'
    };

    var STATUS_LABELS = {
        planned: 'не подтверждена',
        confirmed: 'подтверждена',
        checked_in: 'ожидает',
        arrived: 'в клинике',
        in_progress: 'у врача',
        finished: 'завершена',
        completed: 'завершена',
        cancelled: 'отменена',
        no_show: 'неявка'
    };

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function fmt(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':00';
    }
    function ymd(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }
    function parseIso(s) {
        if (!s) return null;
        var parts = s.replace('T', ' ').split(/[- :]/);
        return new Date(
            parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]),
            parseInt(parts[3] || 0), parseInt(parts[4] || 0), parseInt(parts[5] || 0)
        );
    }
    function localAt(date, minutes) {
        return date + ' ' + pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60) + ':00';
    }
    function addMinutes(value, minutes) {
        var d = parseIso(value);
        if (!d) return value;
        return fmt(new Date(d.getTime() + minutes * 60000));
    }
    function sameDay(a, b) {
        return a && b && ymd(a) === ymd(b);
    }

    function ResourceGrid(host, payload, options) {
        this.host = host;
        this.payload = payload;
        this.options = options || {};
        this.startHour = this.options.startHour;
        this.endHour = this.options.endHour;
        this.rowMinutes = this.options.rowMinutes;
        this.rowHeight = 28;
        this.colWidth = 160;
        this.headerHeight = 36;
        this.timeColumnWidth = 56;
        this.dayCount = (payload.view === 'week') ? 7 : 1;
        this.dayStart = parseIso((payload.date || ymd(new Date())) + ' 00:00:00');
        this.timezone = payload.timezone || this.options.timezone || '';
    }

    ResourceGrid.prototype.dayList = function () {
        var out = [];
        for (var i = 0; i < this.dayCount; i++) {
            var d = new Date(this.dayStart.getTime());
            d.setDate(d.getDate() + i);
            out.push(d);
        }
        return out;
    };

    ResourceGrid.prototype.render = function () {
        var cabinets = this.payload.cabinets || [];
        if (cabinets.length === 0) {
            this.host.innerHTML = '<div class="text-muted small" style="padding:10px">Нет кабинетов.</div>';
            return;
        }
        var totalRows = ((this.endHour - this.startHour) * 60) / this.rowMinutes;
        var totalCols = cabinets.length * this.dayCount;
        var gridWidth = this.timeColumnWidth + totalCols * this.colWidth;
        var gridHeight = (this.dayCount > 1 ? this.headerHeight : 0) + this.headerHeight + totalRows * this.rowHeight;

        var html = '<div class="rc-grid" style="position:relative;width:' + gridWidth + 'px;'
            + 'height:' + gridHeight + 'px;font-size:11px">';
        html += this.renderHeader(cabinets);
        html += this.renderTimeColumn(totalRows);
        html += this.renderCells(cabinets, totalRows);
        html += '</div>';
        this.host.innerHTML = html;

        this.attachAppointments(cabinets);
        this.attachHandlers();
    };

    ResourceGrid.prototype.renderHeader = function (cabinets) {
        var days = this.dayList();
        var dayHeaderHeight = (this.dayCount > 1) ? this.headerHeight : 0;
        var top = 0;
        var h = '';
        if (this.dayCount > 1) {
            h += '<div class="rc-day-row" style="position:absolute;left:0;top:0;display:flex;'
                + 'height:' + this.headerHeight + 'px;border-bottom:1px solid #ccc;background:#fafafa">';
            h += '<div style="width:' + this.timeColumnWidth + 'px;border-right:1px solid #ddd"></div>';
            for (var d = 0; d < days.length; d++) {
                var dayBlockWidth = cabinets.length * this.colWidth;
                h += '<div style="width:' + dayBlockWidth + 'px;border-right:1px solid #ccc;'
                    + 'display:flex;align-items:center;justify-content:center;font-weight:600">'
                    + ymd(days[d]) + '</div>';
            }
            h += '</div>';
            top = this.headerHeight;
        }
        h += '<div class="rc-header" style="position:absolute;left:0;top:' + top + 'px;display:flex;'
            + 'height:' + this.headerHeight + 'px;border-bottom:1px solid #ccc;background:#f7f7f7">';
        h += '<div style="width:' + this.timeColumnWidth + 'px;border-right:1px solid #ddd"></div>';
        for (var dd = 0; dd < days.length; dd++) {
            for (var i = 0; i < cabinets.length; i++) {
                var c = cabinets[i];
                h += '<div style="width:' + this.colWidth + 'px;border-right:1px solid #ddd;'
                    + 'display:flex;align-items:center;justify-content:center;font-weight:600;'
                    + 'text-align:center">' + this.escape(c.name) + '</div>';
            }
        }
        h += '</div>';
        return h;
    };

    ResourceGrid.prototype.renderTimeColumn = function (totalRows) {
        var topOffset = (this.dayCount > 1 ? this.headerHeight : 0) + this.headerHeight;
        var h = '<div class="rc-time-col" style="position:absolute;left:0;top:' + topOffset + 'px;'
            + 'width:' + this.timeColumnWidth + 'px">';
        for (var i = 0; i < totalRows; i++) {
            var minutes = this.startHour * 60 + i * this.rowMinutes;
            var label = pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60);
            var hourlyMark = (i % (60 / this.rowMinutes) === 0) ? label : '';
            h += '<div style="height:' + this.rowHeight + 'px;border-bottom:1px dashed #eee;'
                + 'border-right:1px solid #ddd;text-align:right;padding-right:4px;color:#888">'
                + hourlyMark + '</div>';
        }
        h += '</div>';
        return h;
    };

    ResourceGrid.prototype.renderCells = function (cabinets, totalRows) {
        var days = this.dayList();
        var topOffset = (this.dayCount > 1 ? this.headerHeight : 0) + this.headerHeight;
        var h = '';
        var colIndex = 0;
        for (var di = 0; di < days.length; di++) {
            var dStr = ymd(days[di]);
            for (var ci = 0; ci < cabinets.length; ci++, colIndex++) {
                var cabinet = cabinets[ci];
                var left = this.timeColumnWidth + colIndex * this.colWidth;
                h += '<div class="rc-col" data-cabinet-id="' + cabinet.id + '" data-date="' + dStr + '" '
                    + 'style="position:absolute;left:' + left + 'px;top:' + topOffset + 'px;'
                    + 'width:' + this.colWidth + 'px">';
                for (var r = 0; r < totalRows; r++) {
                    h += '<div class="rc-cell" data-row="' + r + '" data-cabinet-id="' + cabinet.id + '" '
                        + 'data-date="' + dStr + '" '
                        + 'style="height:' + this.rowHeight + 'px;border-bottom:1px dashed #eee;'
                        + 'border-right:1px solid #ddd;background:transparent;cursor:pointer"></div>';
                }
                h += '</div>';
            }
        }
        return h;
    };

    ResourceGrid.prototype.attachAppointments = function (cabinets) {
        var grid = this.host.querySelector('.rc-grid');
        if (!grid) return;
        var appointments = this.payload.appointments || [];
        var topOffset = (this.dayCount > 1 ? this.headerHeight : 0) + this.headerHeight;
        var dayStartMinutes = this.startHour * 60;
        var dayEndMinutes = this.endHour * 60;
        var days = this.dayList();
        var dayIdx = {};
        for (var i = 0; i < days.length; i++) {
            dayIdx[ymd(days[i])] = i;
        }
        var cabIdx = {};
        for (var k = 0; k < cabinets.length; k++) {
            cabIdx[cabinets[k].id] = k;
        }
        for (var ai = 0; ai < appointments.length; ai++) {
            var a = appointments[ai];
            var displayStart = a.localStart || a.dateStart;
            var displayEnd = a.localEnd || a.dateEnd;
            var startDt = parseIso(displayStart);
            var endDt = parseIso(displayEnd);
            if (!startDt || !endDt) continue;
            var di = dayIdx[ymd(startDt)];
            var ci = cabIdx[a.cabinetId];
            if (di === undefined || ci === undefined) continue;
            var startMin = startDt.getHours() * 60 + startDt.getMinutes();
            var endMin = endDt.getHours() * 60 + endDt.getMinutes();
            if (ymd(endDt) !== ymd(startDt)) {
                endMin = dayEndMinutes;
            }
            if (endMin <= dayStartMinutes || startMin >= dayEndMinutes) continue;
            startMin = Math.max(startMin, dayStartMinutes);
            endMin = Math.min(endMin, dayEndMinutes);
            var globalCol = di * cabinets.length + ci;
            var top = topOffset + ((startMin - dayStartMinutes) / this.rowMinutes) * this.rowHeight;
            var height = ((endMin - startMin) / this.rowMinutes) * this.rowHeight - 2;
            var left = this.timeColumnWidth + globalCol * this.colWidth + 2;
            var w = this.colWidth - 4;
            var bg = STATUS_COLORS[a.status] || '#888';
            var patientName = a.parentName || a.name || '';
            var statusLabel = STATUS_LABELS[a.status] || a.status || '';
            var metaParts = [];
            if (statusLabel) metaParts.push(this.escape(statusLabel));
            if (a.doctorName) metaParts.push(this.escape(a.doctorName));
            var card = document.createElement('div');
            card.className = 'rc-appointment';
            card.setAttribute('data-id', a.id);
            card.setAttribute('data-duration', String(endMin - startMin));
            card.setAttribute('data-start', displayStart);
            card.setAttribute('data-end', displayEnd);
            card.setAttribute('data-timezone', a.timezone || this.timezone || '');
            card.setAttribute('draggable', 'true');
            card.style.cssText = 'position:absolute;left:' + left + 'px;top:' + top + 'px;'
                + 'width:' + w + 'px;height:' + height + 'px;background:' + bg + ';color:#fff;'
                + 'border-radius:3px;padding:3px 6px;cursor:move;overflow:hidden;font-size:11px;'
                + 'line-height:1.2;box-shadow:0 1px 2px rgba(0,0,0,0.15)';
            card.innerHTML = '<strong>' + this.escape(patientName || 'Запись') + '</strong><br>' +
                '<span>' + metaParts.join(' · ') + '</span>';
            var resizer = document.createElement('div');
            resizer.className = 'rc-resizer';
            resizer.style.cssText = 'position:absolute;left:0;right:0;bottom:0;height:6px;'
                + 'cursor:ns-resize;background:rgba(255,255,255,0.25)';
            card.appendChild(resizer);
            grid.appendChild(card);
        }
    };

    ResourceGrid.prototype.attachHandlers = function () {
        var self = this;
        var grid = this.host.querySelector('.rc-grid');
        if (!grid) return;
        var dragData = null;
        var resizeData = null;

        var cards = grid.querySelectorAll('.rc-appointment');
        for (var i = 0; i < cards.length; i++) {
            (function (card) {
                card.addEventListener('dragstart', function (e) {
                    dragData = {
                        id: card.getAttribute('data-id'),
                        durationMinutes: parseInt(card.getAttribute('data-duration')) || self.rowMinutes
                    };
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', dragData.id);
                });
                card.addEventListener('click', function (e) {
                    if (e.target && e.target.classList && e.target.classList.contains('rc-resizer')) return;
                    self.options.onAppointmentClick && self.options.onAppointmentClick(card.getAttribute('data-id'));
                });

                var resizer = card.querySelector('.rc-resizer');
                if (resizer) {
                    resizer.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        resizeData = {
                            card: card,
                            id: card.getAttribute('data-id'),
                            startY: e.clientY,
                            startHeight: card.offsetHeight
                        };
                        document.body.style.cursor = 'ns-resize';
                    });
                }
            })(cards[i]);
        }

        document.addEventListener('mousemove', this._mouseMoveHandler = function (e) {
            if (!resizeData) return;
            var dy = e.clientY - resizeData.startY;
            var newH = Math.max(self.rowHeight, resizeData.startHeight + dy);
            newH = Math.round(newH / self.rowHeight) * self.rowHeight - 2;
            resizeData.card.style.height = newH + 'px';
        });

        document.addEventListener('mouseup', this._mouseUpHandler = function () {
            if (!resizeData) return;
            document.body.style.cursor = '';
            var card = resizeData.card;
            var finalH = card.offsetHeight;
            var newDuration = Math.max(
                self.rowMinutes,
                Math.round((finalH + 2) / self.rowHeight * self.rowMinutes)
            );
            var startStr = card.getAttribute('data-start');
            if (!startStr) {
                var leftPx = parseInt(card.style.left);
                var topPx = parseInt(card.style.top);
                var topOffset = (self.dayCount > 1 ? self.headerHeight : 0) + self.headerHeight;
                var rowIdx = Math.round((topPx - topOffset) / self.rowHeight);
                var startMin = self.startHour * 60 + rowIdx * self.rowMinutes;
                var colIdx = Math.round((leftPx - 2 - self.timeColumnWidth) / self.colWidth);
                var dayIdx = Math.floor(colIdx / (self.payload.cabinets.length));
                var days = self.dayList();
                var day = days[dayIdx] || days[0];
                startStr = localAt(ymd(day), startMin);
            }
            self.options.onResize && self.options.onResize(resizeData.id, {
                localStart: startStr,
                localEnd: addMinutes(startStr, newDuration),
                timezone: card.getAttribute('data-timezone') || self.timezone || ''
            });
            resizeData = null;
        });

        var cells = grid.querySelectorAll('.rc-cell');
        for (var j = 0; j < cells.length; j++) {
            (function (cell) {
                cell.addEventListener('dragover', function (e) { e.preventDefault(); });
                cell.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (!dragData) return;
                    var row = parseInt(cell.getAttribute('data-row'));
                    var cabinetId = cell.getAttribute('data-cabinet-id');
                    var date = cell.getAttribute('data-date');
                    var startMinutes = self.startHour * 60 + row * self.rowMinutes;
                    var startStr = localAt(date, startMinutes);
                    self.options.onMove && self.options.onMove(
                        dragData.id,
                        {
                            localStart: startStr,
                            localEnd: addMinutes(startStr, dragData.durationMinutes),
                            timezone: self.timezone || '',
                            cabinetId: cabinetId
                        }
                    );
                    dragData = null;
                });
                cell.addEventListener('click', function () {
                    var row = parseInt(cell.getAttribute('data-row'));
                    var cabinetId = cell.getAttribute('data-cabinet-id');
                    var date = cell.getAttribute('data-date');
                    var startMinutes = self.startHour * 60 + row * self.rowMinutes;
                    var start = localAt(date, startMinutes);
                    self.options.onCellClick && self.options.onCellClick(
                        cabinetId,
                        start,
                        self.timezone || '',
                        self.calculateFreeWindowMinutes(cabinetId, start)
                    );
                });
            })(cells[j]);
        }
    };

    ResourceGrid.prototype.calculateFreeWindowMinutes = function (cabinetId, localStart) {
        var start = parseIso(localStart);
        if (!start) return 180;

        var end = new Date(start.getTime());
        end.setHours(this.endHour, 0, 0, 0);

        var endTs = end.getTime();
        var appointments = this.payload.appointments || [];
        var selectedDoctorId = this.options.doctorId || null;

        for (var i = 0; i < appointments.length; i++) {
            var appointment = appointments[i] || {};
            var sameCabinet = String(appointment.cabinetId || '') === String(cabinetId || '');
            var sameDoctor = selectedDoctorId &&
                String(appointment.doctorId || '') === String(selectedDoctorId);

            if (!sameCabinet && !sameDoctor) {
                continue;
            }

            var appointmentStart = parseIso(appointment.localStart || appointment.dateStart);
            var appointmentEnd = parseIso(appointment.localEnd || appointment.dateEnd);

            if (
                !appointmentStart ||
                !appointmentEnd ||
                (!sameDay(start, appointmentStart) && !sameDay(start, appointmentEnd))
            ) {
                continue;
            }

            if (appointmentEnd.getTime() <= start.getTime()) {
                continue;
            }

            if (appointmentStart.getTime() <= start.getTime()) {
                return 0;
            }

            endTs = Math.min(endTs, appointmentStart.getTime());
        }

        return Math.max(0, Math.floor((endTs - start.getTime()) / 60000));
    };

    ResourceGrid.prototype.dispose = function () {
        if (this._mouseMoveHandler) document.removeEventListener('mousemove', this._mouseMoveHandler);
        if (this._mouseUpHandler) document.removeEventListener('mouseup', this._mouseUpHandler);
    };

    ResourceGrid.prototype.escape = function (s) {
        if (!s) return '';
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    };

    return ResourceGrid;
});
