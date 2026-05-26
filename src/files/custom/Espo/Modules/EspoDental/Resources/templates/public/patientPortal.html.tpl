<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{title}}</title>
<style>
:root { color-scheme: light; --green: #438f7e; --bg: #edf3ef; --line: #dce3df; }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: #21312d; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
.wrap { max-width: 980px; margin: 0 auto; padding: 24px 16px 56px; }
.top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
.brand { font-size: 20px; font-weight: 750; color: #21312d; }
.panel { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 16px; box-shadow: 0 1px 2px rgba(33,49,45,.04); }
.grid { display: grid; grid-template-columns: 320px 1fr; gap: 16px; }
.field { display: block; margin: 0 0 10px; }
.field span { display: block; font-size: 12px; font-weight: 700; color: #5e6b66; margin-bottom: 4px; }
input, textarea { width: 100%; border: 1.5px solid var(--line); border-radius: 8px; padding: 10px 12px; font: inherit; background: #fff; }
button { border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 700; cursor: pointer; }
.primary { background: var(--green); color: #fff; }
.ghost { background: #fff; color: var(--green); border: 1.5px solid var(--green); }
.muted { color: #6b7974; font-size: 13px; }
.list { display: grid; gap: 10px; }
.appointment { border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: #fff; }
.appointment-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
.badge { display: inline-flex; border-radius: 999px; padding: 3px 8px; font-size: 12px; background: #eef6f3; color: var(--green); }
.reschedule { margin-top: 12px; display: none; grid-template-columns: 1fr; gap: 8px; }
.reschedule.open { display: grid; }
.error { background: #ffe7e7; color: #9b1c1c; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
.success { background: #e6f4ed; color: #17663f; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
.hidden { display: none; }
@media (max-width: 760px) { .grid { grid-template-columns: 1fr; } .top { align-items: flex-start; flex-direction: column; } }
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <div class="brand">Patient Portal</div>
            <div class="muted">Future appointments and reschedule requests</div>
        </div>
        <button type="button" class="ghost hidden" id="logout">Log out</button>
    </div>
    <div id="message"></div>
    <div class="grid">
        <div class="panel" id="login-panel">
            <label class="field"><span>Email</span><input id="email" type="email" autocomplete="email"></label>
            <button type="button" class="primary" id="request-code">Request code</button>
            <div id="code-step" class="hidden" style="margin-top:12px">
                <label class="field"><span>6-digit code</span><input id="code" inputmode="numeric" maxlength="6"></label>
                <button type="button" class="primary" id="verify-code">Enter portal</button>
                <div class="muted" id="debug-otp"></div>
            </div>
        </div>
        <div class="panel">
            <div class="appointment-head" style="margin-bottom:12px">
                <strong id="patient-name">Appointments</strong>
                <button type="button" class="ghost hidden" id="refresh">Refresh</button>
            </div>
            <div class="list" id="appointments">
                <div class="muted">Log in to view future appointments.</div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
'use strict';
var apiBase = '{{apiBase}}';
var storageKey = 'espoDental.patientPortalToken';
var token = localStorage.getItem(storageKey) || '';
var selectedEmail = '';
var message = document.getElementById('message');
var appointmentsEl = document.getElementById('appointments');

function showMessage(text, type) {
    message.innerHTML = text ? '<div class="' + (type || 'success') + '">' + escapeHtml(text) + '</div>' : '';
}
function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}
function requestJson(path, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['Content-Type'] = 'application/json';
    if (token) options.headers['X-Patient-Portal-Token'] = token;
    return fetch(apiBase + path, options).then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    });
}
function setLoggedIn(isLoggedIn) {
    document.getElementById('login-panel').classList.toggle('hidden', isLoggedIn);
    document.getElementById('logout').classList.toggle('hidden', !isLoggedIn);
    document.getElementById('refresh').classList.toggle('hidden', !isLoggedIn);
}
function loadAppointments() {
    if (!token) return;
    appointmentsEl.innerHTML = '<div class="muted">Loading...</div>';
    requestJson('/appointments', {method: 'GET'}).then(function (data) {
        setLoggedIn(true);
        document.getElementById('patient-name').textContent = data.patient && data.patient.name
            ? data.patient.name
            : 'Appointments';
        renderAppointments(data.appointments || []);
    }).catch(function () {
        localStorage.removeItem(storageKey);
        token = '';
        setLoggedIn(false);
        appointmentsEl.innerHTML = '<div class="muted">Log in to view future appointments.</div>';
    });
}
function renderAppointments(rows) {
    if (!rows.length) {
        appointmentsEl.innerHTML = '<div class="muted">No future appointments.</div>';
        return;
    }
    appointmentsEl.innerHTML = rows.map(function (item) {
        var can = item.canRequestReschedule && !item.activeRescheduleRequestId;
        return '<div class="appointment" data-id="' + escapeHtml(item.appointmentId) + '">' +
            '<div class="appointment-head"><div><strong>' + escapeHtml(item.startAt) + '</strong>' +
            '<div class="muted">' + escapeHtml(item.doctorDisplayName || '') + ' ' +
            escapeHtml(item.clinicName || '') + '</div></div>' +
            '<span class="badge">' + escapeHtml(item.activeRescheduleRequestStatus || item.status) + '</span></div>' +
            (can ? '<button type="button" class="ghost" data-action="open-reschedule">Request reschedule</button>' : '') +
            '<div class="reschedule"><label class="field"><span>Requested start</span>' +
            '<input type="datetime-local" data-name="requestedStartAt"></label>' +
            '<label class="field"><span>Comment for clinic</span><textarea data-name="patientComment"></textarea></label>' +
            '<button type="button" class="primary" data-action="submit-reschedule">Send request</button></div>' +
            '</div>';
    }).join('');
}

document.getElementById('request-code').onclick = function () {
    selectedEmail = document.getElementById('email').value || '';
    requestJson('/requestCode', {method: 'POST', body: JSON.stringify({email: selectedEmail})}).then(function (data) {
        document.getElementById('code-step').classList.remove('hidden');
        document.getElementById('debug-otp').textContent = data.debugOtp ? 'Dev code: ' + data.debugOtp : '';
        showMessage('Code requested. Check email or dev response.');
    }).catch(function () { showMessage('Could not request code.', 'error'); });
};
document.getElementById('verify-code').onclick = function () {
    requestJson('/verifyCode', {
        method: 'POST',
        body: JSON.stringify({email: selectedEmail || document.getElementById('email').value, code: document.getElementById('code').value})
    }).then(function (data) {
        token = data.token;
        localStorage.setItem(storageKey, token);
        showMessage('');
        loadAppointments();
    }).catch(function () { showMessage('Invalid or expired code.', 'error'); });
};
appointmentsEl.onclick = function (event) {
    var button = event.target.closest('button');
    if (!button) return;
    var card = event.target.closest('.appointment');
    if (!card) return;
    if (button.dataset.action === 'open-reschedule') {
        card.querySelector('.reschedule').classList.toggle('open');
        return;
    }
    if (button.dataset.action === 'submit-reschedule') {
        requestJson('/rescheduleRequests', {
            method: 'POST',
            body: JSON.stringify({
                appointmentId: card.dataset.id,
                requestedStartAt: card.querySelector('[data-name="requestedStartAt"]').value,
                patientComment: card.querySelector('[data-name="patientComment"]').value
            })
        }).then(function () {
            showMessage('Request sent. The clinic will confirm the new time.');
            loadAppointments();
        }).catch(function () { showMessage('Could not send request.', 'error'); });
    }
};
document.getElementById('refresh').onclick = loadAppointments;
document.getElementById('logout').onclick = function () {
    requestJson('/logout', {method: 'POST'}).finally(function () {
        localStorage.removeItem(storageKey);
        token = '';
        setLoggedIn(false);
        document.getElementById('patient-name').textContent = 'Appointments';
        appointmentsEl.innerHTML = '<div class="muted">Log in to view future appointments.</div>';
    });
};
if (token) loadAppointments();
})();
</script>
</body>
</html>
