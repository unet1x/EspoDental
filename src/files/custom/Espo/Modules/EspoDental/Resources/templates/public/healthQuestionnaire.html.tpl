<!doctype html>
<html lang="{{lang}}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>{{title}}</title>
<style>
:root { color-scheme: light; }
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background: #f5f5f7; color: #1f1f1f; }
.wrap { max-width: 760px; margin: 0 auto; padding: 24px 18px 64px; }
h1 { font-size: 22px; margin: 0 0 4px; }
.subtitle { color: #666; margin: 0 0 18px; font-size: 14px; }
.patient { font-weight: 600; margin-bottom: 16px; }
.card { background: #fff; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
.group-title { font-size: 16px; font-weight: 600; margin: 0 0 10px; }
.item { padding: 10px 0; border-bottom: 1px solid #eee; }
.item:last-child { border-bottom: none; }
.label { font-size: 15px; margin-bottom: 6px; display: block; }
.choices { display: flex; gap: 8px; }
.choice { flex: 1; padding: 12px; border: 1.5px solid #d0d0d0; border-radius: 10px; text-align: center; font-size: 15px; cursor: pointer; user-select: none; background: #fafafa; }
.choice.active { border-color: #2a73e8; background: #e7f0ff; color: #2a73e8; font-weight: 600; }
textarea { width: 100%; min-height: 60px; padding: 10px; border: 1.5px solid #d0d0d0; border-radius: 10px; font-size: 15px; font-family: inherit; resize: vertical; }
.signature-card { background: #fff; border-radius: 12px; padding: 16px; }
.sig-prompt { font-weight: 600; margin: 0 0 10px; }
.sig-canvas { width: 100%; height: 220px; background: #fafafa; border: 1.5px dashed #c0c0c0; border-radius: 10px; display: block; touch-action: none; }
.sig-actions { display: flex; gap: 10px; margin-top: 10px; }
.btn { padding: 14px 18px; border-radius: 10px; border: none; font-size: 16px; font-weight: 600; cursor: pointer; }
.btn-primary { background: #2a73e8; color: #fff; flex: 1; }
.btn-primary[disabled] { background: #9bb6e6; cursor: not-allowed; }
.btn-ghost { background: #fff; color: #2a73e8; border: 1.5px solid #2a73e8; }
.alert { background: #ffe4e4; color: #a32020; padding: 14px; border-radius: 10px; margin-bottom: 16px; }
.success { background: #e6f7ee; color: #1f7a3f; padding: 18px; border-radius: 12px; font-size: 16px; text-align: center; }
.hidden { display: none; }
.sticky-submit { position: sticky; bottom: 0; padding-top: 12px; background: linear-gradient(180deg, rgba(245,245,247,0) 0%, rgba(245,245,247,1) 30%); }
</style>
</head>
<body>
<div class="wrap">
<h1>{{title}}</h1>
<p class="subtitle" id="subtitle"></p>
<div class="patient">{{patientFullName}}</div>
{{errorBanner}}
<form id="hq-form" autocomplete="off">
    <div id="groups"></div>
    <div class="signature-card card">
        <div class="sig-prompt" id="sig-prompt"></div>
        <canvas id="sig-canvas" class="sig-canvas" width="700" height="220"></canvas>
        <div class="sig-actions">
            <button type="button" class="btn btn-ghost" id="sig-clear"></button>
        </div>
    </div>
    <div class="sticky-submit">
        <button type="submit" id="hq-submit" class="btn btn-primary"></button>
    </div>
</form>
<div id="hq-done" class="success hidden"></div>
</div>
<script id="hq-bootstrap" type="application/json">{{bootstrapJson}}</script>
<script>
(function () {
'use strict';
var bootstrap = JSON.parse(document.getElementById('hq-bootstrap').textContent);
var schema = bootstrap.schema;
var s = bootstrap.strings;
document.getElementById('subtitle').textContent = s.subtitle;
document.getElementById('sig-prompt').textContent = s.signaturePrompt;
document.getElementById('sig-clear').textContent = s.clear;
document.getElementById('hq-submit').textContent = s.submit;

var answers = Object.create(null);

var groupsEl = document.getElementById('groups');
(schema.groups || []).forEach(function (group) {
    if (group.conditional && group.conditional.showIf && group.conditional.showIf.patientGender) {
        if (group.conditional.showIf.patientGender !== bootstrap.patientGender) {
            return;
        }
    }
    var card = document.createElement('div');
    card.className = 'card';
    var h = document.createElement('div');
    h.className = 'group-title';
    h.textContent = group.label;
    card.appendChild(h);

    (group.items || []).forEach(function (item) {
        var wrap = document.createElement('div');
        wrap.className = 'item';
        var label = document.createElement('span');
        label.className = 'label';
        label.textContent = item.label;
        wrap.appendChild(label);

        if (item.type === 'bool') {
            var row = document.createElement('div');
            row.className = 'choices';
            ['no', 'yes'].forEach(function (key) {
                var ch = document.createElement('div');
                ch.className = 'choice';
                ch.textContent = key === 'yes' ? s.yes : s.no;
                ch.dataset.value = key === 'yes' ? 'true' : 'false';
                ch.onclick = function () {
                    row.querySelectorAll('.choice').forEach(function (c) { c.classList.remove('active'); });
                    ch.classList.add('active');
                    answers[item.id] = key === 'yes';
                };
                row.appendChild(ch);
            });
            wrap.appendChild(row);
        } else {
            var ta = document.createElement('textarea');
            ta.oninput = function () { answers[item.id] = ta.value; };
            wrap.appendChild(ta);
        }
        card.appendChild(wrap);
    });
    groupsEl.appendChild(card);
});

// Minimal signature pad: vanilla canvas, MIT-clean, no dependencies.
var canvas = document.getElementById('sig-canvas');
var ctx = canvas.getContext('2d');
var dpr = Math.max(1, window.devicePixelRatio || 1);
function resize() {
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#111';
}
resize();
window.addEventListener('resize', resize);

var drawing = false;
var hasInk = false;
var last = null;
function point(e) {
    var rect = canvas.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - rect.left, y: t.clientY - rect.top };
}
function start(e) { e.preventDefault(); drawing = true; last = point(e); hasInk = true; }
function move(e) {
    if (!drawing) return;
    e.preventDefault();
    var p = point(e);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
}
function end() { drawing = false; }
canvas.addEventListener('mousedown', start);
canvas.addEventListener('mousemove', move);
canvas.addEventListener('mouseup', end);
canvas.addEventListener('mouseleave', end);
canvas.addEventListener('touchstart', start, {passive: false});
canvas.addEventListener('touchmove', move, {passive: false});
canvas.addEventListener('touchend', end);

document.getElementById('sig-clear').onclick = function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasInk = false;
};

var submitBtn = document.getElementById('hq-submit');
document.getElementById('hq-form').onsubmit = function (e) {
    e.preventDefault();
    if (!hasInk) { alert(s.signatureRequired); return; }
    submitBtn.disabled = true;
    submitBtn.textContent = s.submitting;
    var dataUri = canvas.toDataURL('image/png');
    fetch(bootstrap.submitUrl, {
        method: 'POST',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: answers, signature: dataUri })
    }).then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }).then(function () {
        document.getElementById('hq-form').classList.add('hidden');
        var d = document.getElementById('hq-done');
        d.textContent = s.thankYou;
        d.classList.remove('hidden');
    }).catch(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = s.submit;
        alert(s.submitFailed);
    });
};
})();
</script>
</body>
</html>
