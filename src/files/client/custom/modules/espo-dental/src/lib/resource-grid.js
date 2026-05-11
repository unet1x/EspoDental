define('espo-dental:lib/resource-grid', [], function () {
    'use strict';

    var STATUS_COLORS = {
        planned: '#1F77B4',
        confirmed: '#2CA02C',
        checked_in: '#FFBB22',
        in_progress: '#FF7F0E',
        completed: '#7F7F7F',
        cancelled: '#D62728',
        no_show: '#A0522D'
    };

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function fmt(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':00';
    }
    function parseIso(s) {
        if (!s) return null;
        var parts = s.replace('T', ' ').split(/[- :]/);
        return new Date(
            parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]),
            parseInt(parts[3] || 0), parseInt(parts[4] || 0), parseInt(parts[5] || 0)
        );
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
    }

    ResourceGrid.prototype.render = function () {
        var cabinets = this.payload.cabinets || [];
        if (cabinets.length === 0) {
            this.host.innerHTML = '<div class="text-muted small" style="padding:10px">No cabinets.</div>';
            return;
        }
        var totalRows = ((this.endHour - this.startHour) * 60) / this.rowMinutes;
        var gridWidth = this.timeColumnWidth + cabinets.length * this.colWidth;
        var gridHeight = this.headerHeight + totalRows * this.rowHeight;

        var html = '<div class="rc-grid" style="position:relative;width:' + gridWidth + 'px;height:' + gridHeight + 'px;font-size:11px">';
        html += this.renderHeader(cabinets);
        html += this.renderTimeColumn(totalRows);
        html += this.renderCells(cabinets, totalRows);
        html += '</div>';
        this.host.innerHTML = html;

        this.attachAppointments(cabinets);
        this.attachHandlers();
    };

    ResourceGrid.prototype.renderHeader = function (cabinets) {
        var h = '<div class="rc-header" style="position:absolute;left:0;top:0;display:flex;height:' + this.headerHeight + 'px;border-bottom:1px solid #ccc;background:#f7f7f7">';
        h += '<div style="width:' + this.timeColumnWidth + 'px;border-right:1px solid #ddd"></div>';
        for (var i = 0; i < cabinets.length; i++) {
            var c = cabinets[i];
            h += '<div data-cabinet-id="' + c.id + '" style="width:' + this.colWidth + 'px;'
                + 'border-right:1px solid #ddd;display:flex;align-items:center;justify-content:center;'
                + 'font-weight:600;text-align:center">' + this.escape(c.name) + '</div>';
        }
        h += '</div>';
        return h;
    };

    ResourceGrid.prototype.renderTimeColumn = function (totalRows) {
        var h = '<div class="rc-time-col" style="position:absolute;left:0;top:' + this.headerHeight + 'px;width:' + this.timeColumnWidth + 'px">';
        for (var i = 0; i < totalRows; i++) {
            var minutes = this.startHour * 60 + i * this.rowMinutes;
            var label = pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60);
            h += '<div style="height:' + this.rowHeight + 'px;border-bottom:1px dashed #eee;border-right:1px solid #ddd;'
                + 'text-align:right;padding-right:4px;color:#888">' + (i % (60 / this.rowMinutes) === 0 ? label : '') + '</div>';
        }
        h += '</div>';
        return h;
    };

    ResourceGrid.prototype.renderCells = function (cabinets, totalRows) {
        var h = '';
        for (var col = 0; col < cabinets.length; col++) {
            var cabinet = cabinets[col];
            var left = this.timeColumnWidth + col * this.colWidth;
            h += '<div class="rc-col" data-cabinet-id="' + cabinet.id + '" style="position:absolute;left:' + left + 'px;'
                + 'top:' + this.headerHeight + 'px;width:' + this.colWidth + 'px">';
            for (var r = 0; r < totalRows; r++) {
                h += '<div class="rc-cell" data-row="' + r + '" data-cabinet-id="' + cabinet.id + '" '
                    + 'style="height:' + this.rowHeight + 'px;border-bottom:1px dashed #eee;'
                    + 'border-right:1px solid #ddd;background:transparent;cursor:pointer"></div>';
            }
            h += '</div>';
        }
        return h;
    };

    ResourceGrid.prototype.attachAppointments = function (cabinets) {
        var grid = this.host.querySelector('.rc-grid');
        if (!grid) return;
        var appointments = this.payload.appointments || [];
        var dayStartMinutes = this.startHour * 60;
        var cabinetIndex = {};
        for (var i = 0; i < cabinets.length; i++) {
            cabinetIndex[cabinets[i].id] = i;
        }
        for (var k = 0; k < appointments.length; k++) {
            var a = appointments[k];
            var col = cabinetIndex[a.cabinetId];
            if (col === undefined) continue;
            var startDt = parseIso(a.dateStart);
            var endDt = parseIso(a.dateEnd);
            if (!startDt || !endDt) continue;
            var startMin = startDt.getHours() * 60 + startDt.getMinutes();
            var endMin = endDt.getHours() * 60 + endDt.getMinutes();
            if (endMin <= dayStartMinutes || startMin >= this.endHour * 60) continue;
            startMin = Math.max(startMin, dayStartMinutes);
            endMin = Math.min(endMin, this.endHour * 60);
            var top = this.headerHeight + ((startMin - dayStartMinutes) / this.rowMinutes) * this.rowHeight;
            var height = ((endMin - startMin) / this.rowMinutes) * this.rowHeight - 2;
            var left = this.timeColumnWidth + col * this.colWidth + 2;
            var w = this.colWidth - 4;
            var bg = STATUS_COLORS[a.status] || '#888';
            var $card = document.createElement('div');
            $card.className = 'rc-appointment';
            $card.setAttribute('data-id', a.id);
            $card.setAttribute('draggable', 'true');
            $card.style.cssText = 'position:absolute;left:' + left + 'px;top:' + top + 'px;width:' + w + 'px;'
                + 'height:' + height + 'px;background:' + bg + ';color:#fff;border-radius:3px;padding:3px 6px;'
                + 'cursor:move;overflow:hidden;font-size:11px;line-height:1.2;box-shadow:0 1px 2px rgba(0,0,0,0.15)';
            var titleParts = [];
            if (a.parentName) titleParts.push(this.escape(a.parentName));
            if (a.doctorName) titleParts.push(this.escape(a.doctorName));
            $card.innerHTML = '<strong>' + pad(startDt.getHours()) + ':' + pad(startDt.getMinutes())
                + '</strong><br>' + titleParts.join('<br>');
            grid.appendChild($card);
        }
    };

    ResourceGrid.prototype.attachHandlers = function () {
        var self = this;
        var grid = this.host.querySelector('.rc-grid');
        if (!grid) return;
        var dragData = null;
        var cards = grid.querySelectorAll('.rc-appointment');
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            card.addEventListener('dragstart', function (e) {
                dragData = {id: this.getAttribute('data-id'), height: this.offsetHeight};
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', dragData.id);
            });
            card.addEventListener('click', function (e) {
                if (e.shiftKey) return;
                self.options.onAppointmentClick && self.options.onAppointmentClick(this.getAttribute('data-id'));
            });
        }
        var cells = grid.querySelectorAll('.rc-cell');
        for (var j = 0; j < cells.length; j++) {
            (function (cell) {
                cell.addEventListener('dragover', function (e) { e.preventDefault(); });
                cell.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (!dragData) return;
                    var row = parseInt(cell.getAttribute('data-row'));
                    var cabinetId = cell.getAttribute('data-cabinet-id');
                    var startMinutes = self.startHour * 60 + row * self.rowMinutes;
                    var durationMinutes = Math.round(dragData.height / self.rowHeight * self.rowMinutes);
                    if (durationMinutes < self.rowMinutes) durationMinutes = self.rowMinutes;
                    var startDt = new Date(self.payload.date + 'T00:00:00');
                    startDt.setMinutes(startMinutes);
                    var endDt = new Date(startDt.getTime() + durationMinutes * 60000);
                    self.options.onMove && self.options.onMove(dragData.id, fmt(startDt), fmt(endDt), cabinetId);
                    dragData = null;
                });
                cell.addEventListener('click', function () {
                    var row = parseInt(cell.getAttribute('data-row'));
                    var cabinetId = cell.getAttribute('data-cabinet-id');
                    var startMinutes = self.startHour * 60 + row * self.rowMinutes;
                    var startDt = new Date(self.payload.date + 'T00:00:00');
                    startDt.setMinutes(startMinutes);
                    self.options.onCellClick && self.options.onCellClick(cabinetId, fmt(startDt));
                });
            })(cells[j]);
        }
    };

    ResourceGrid.prototype.escape = function (s) {
        if (!s) return '';
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    };

    return ResourceGrid;
});
