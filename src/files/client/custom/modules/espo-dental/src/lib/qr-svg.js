/*
 * Minimal QR Code generator (byte mode, ECC level L, fixed version 10 = 57x57 modules).
 * Sufficient for URLs up to ~270 ASCII chars. MIT-licensed.
 *
 * Reference: ISO/IEC 18004 and https://www.thonky.com/qr-code-tutorial/
 *
 * Returns an SVG string for embedding in the DOM.
 */
define('espo-dental:lib/qr-svg', [], function () {

    var VERSION = 10;
    var SIZE = 17 + VERSION * 4;
    var TOTAL_CODEWORDS = 274;
    var DATA_CODEWORDS = 216;
    var ECC_CODEWORDS = TOTAL_CODEWORDS - DATA_CODEWORDS;
    var FORMAT_INFO_L_MASK0 = 0x77c4;

    var EXP = new Array(512);
    var LOG = new Array(256);
    (function () {
        var x = 1;
        for (var i = 0; i < 255; i++) {
            EXP[i] = x;
            LOG[x] = i;
            x <<= 1;
            if (x & 0x100) x ^= 0x11d;
        }
        for (var j = 255; j < 512; j++) EXP[j] = EXP[j - 255];
    })();

    function gfMul(a, b) {
        if (a === 0 || b === 0) return 0;
        return EXP[LOG[a] + LOG[b]];
    }

    function rsGenerator(degree) {
        var result = [1];
        for (var i = 0; i < degree; i++) {
            var newPoly = new Array(result.length + 1).fill(0);
            for (var j = 0; j < result.length; j++) {
                newPoly[j] ^= result[j];
                newPoly[j + 1] ^= gfMul(result[j], EXP[i]);
            }
            result = newPoly;
        }
        return result;
    }

    function rsEncode(data, ecLen) {
        var gen = rsGenerator(ecLen);
        var residue = new Array(ecLen).fill(0);
        for (var i = 0; i < data.length; i++) {
            var factor = data[i] ^ residue[0];
            residue.shift();
            residue.push(0);
            if (factor !== 0) {
                for (var j = 0; j < gen.length - 1; j++) {
                    residue[j] ^= gfMul(gen[j + 1], factor);
                }
            }
        }
        return residue;
    }

    function encodeData(text) {
        var bytes = [];
        for (var i = 0; i < text.length; i++) {
            var code = text.charCodeAt(i);
            if (code < 0x80) {
                bytes.push(code);
            } else if (code < 0x800) {
                bytes.push(0xc0 | (code >> 6));
                bytes.push(0x80 | (code & 0x3f));
            } else {
                bytes.push(0xe0 | (code >> 12));
                bytes.push(0x80 | ((code >> 6) & 0x3f));
                bytes.push(0x80 | (code & 0x3f));
            }
        }
        if (bytes.length > DATA_CODEWORDS - 3) {
            throw new Error('Payload too long for QR version 10-L (max ' + (DATA_CODEWORDS - 3) + ' bytes, got ' + bytes.length + ')');
        }

        var bits = [];
        function pushBits(value, count) {
            for (var k = count - 1; k >= 0; k--) bits.push((value >> k) & 1);
        }

        pushBits(0x4, 4);
        pushBits(bytes.length, 16);
        for (var b = 0; b < bytes.length; b++) pushBits(bytes[b], 8);
        for (var t = 0; t < 4 && bits.length < DATA_CODEWORDS * 8; t++) bits.push(0);
        while (bits.length % 8 !== 0) bits.push(0);

        var codewords = [];
        for (var p = 0; p < bits.length; p += 8) {
            var v = 0;
            for (var q = 0; q < 8; q++) v = (v << 1) | bits[p + q];
            codewords.push(v);
        }

        var pad = [0xec, 0x11];
        var padIdx = 0;
        while (codewords.length < DATA_CODEWORDS) {
            codewords.push(pad[padIdx % 2]);
            padIdx++;
        }

        var ecc = rsEncode(codewords, ECC_CODEWORDS);
        return codewords.concat(ecc);
    }

    function createMatrix() {
        var m = [];
        for (var i = 0; i < SIZE; i++) {
            m.push(new Array(SIZE).fill(null));
        }
        return m;
    }

    function placeFinder(m, r, c) {
        for (var i = -1; i <= 7; i++) {
            for (var j = -1; j <= 7; j++) {
                var rr = r + i, cc = c + j;
                if (rr < 0 || rr >= SIZE || cc < 0 || cc >= SIZE) continue;
                var inside = (i >= 0 && i <= 6 && j >= 0 && j <= 6);
                if (inside) {
                    var isDark = (i === 0 || i === 6 || j === 0 || j === 6 || (i >= 2 && i <= 4 && j >= 2 && j <= 4));
                    m[rr][cc] = isDark ? 1 : 0;
                } else {
                    m[rr][cc] = 0;
                }
            }
        }
    }

    function placeAlignment(m, r, c) {
        for (var i = -2; i <= 2; i++) {
            for (var j = -2; j <= 2; j++) {
                var dark = (Math.abs(i) === 2 || Math.abs(j) === 2 || (i === 0 && j === 0));
                m[r + i][c + j] = dark ? 1 : 0;
            }
        }
    }

    var ALIGNMENT_BY_VERSION = {
        10: [6, 28, 50]
    };

    function placeTiming(m) {
        for (var i = 8; i < SIZE - 8; i++) {
            if (m[6][i] === null) m[6][i] = (i % 2 === 0) ? 1 : 0;
            if (m[i][6] === null) m[i][6] = (i % 2 === 0) ? 1 : 0;
        }
    }

    function reserveFormat(m) {
        for (var i = 0; i < 9; i++) {
            if (m[8][i] === null && i !== 6) m[8][i] = 0;
            if (m[i][8] === null && i !== 6) m[i][8] = 0;
        }
        for (var j = 0; j < 8; j++) {
            if (m[SIZE - 1 - j][8] === null) m[SIZE - 1 - j][8] = 0;
            if (m[8][SIZE - 1 - j] === null) m[8][SIZE - 1 - j] = 0;
        }
        m[SIZE - 8][8] = 1; // dark module
    }

    function placeData(m, codewords) {
        var bits = [];
        for (var i = 0; i < codewords.length; i++) {
            for (var j = 7; j >= 0; j--) bits.push((codewords[i] >> j) & 1);
        }
        var idx = 0;
        var dir = -1;
        var col = SIZE - 1;
        while (col > 0) {
            if (col === 6) col--;
            for (var k = 0; k < SIZE; k++) {
                var row = (dir === -1) ? (SIZE - 1 - k) : k;
                for (var c = 0; c < 2; c++) {
                    var cc = col - c;
                    if (m[row][cc] === null) {
                        m[row][cc] = (idx < bits.length) ? bits[idx] : 0;
                        idx++;
                    }
                }
            }
            dir = -dir;
            col -= 2;
        }
    }

    function maskFn(mask, r, c) {
        switch (mask) {
            case 0: return (r + c) % 2 === 0;
            case 1: return r % 2 === 0;
            case 2: return c % 3 === 0;
            case 3: return (r + c) % 3 === 0;
            case 4: return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0;
            case 5: return ((r * c) % 2) + ((r * c) % 3) === 0;
            case 6: return (((r * c) % 2) + ((r * c) % 3)) % 2 === 0;
            case 7: return (((r + c) % 2) + ((r * c) % 3)) % 2 === 0;
        }
        return false;
    }

    function applyMask(m, mask, reserved) {
        for (var r = 0; r < SIZE; r++) {
            for (var c = 0; c < SIZE; c++) {
                if (reserved[r][c]) continue;
                if (maskFn(mask, r, c)) m[r][c] ^= 1;
            }
        }
    }

    function placeFormatBits(m, bits) {
        for (var i = 0; i < 15; i++) {
            var bit = bits[i];
            if (i < 6) m[8][i] = bit;
            else if (i < 8) m[8][i + 1] = bit;
            else if (i === 8) m[7][8] = bit;
            else m[14 - i][8] = bit;

            if (i < 8) m[SIZE - 1 - i][8] = bit;
            else m[8][SIZE - 15 + i] = bit;
        }
        m[SIZE - 8][8] = 1;
    }

    function scoreMask(m) {
        var penalty = 0;
        for (var r = 0; r < SIZE; r++) {
            var run = 1;
            for (var c = 1; c < SIZE; c++) {
                if (m[r][c] === m[r][c - 1]) {
                    run++;
                } else {
                    if (run >= 5) penalty += 3 + (run - 5);
                    run = 1;
                }
            }
            if (run >= 5) penalty += 3 + (run - 5);
        }
        return penalty;
    }

    function chooseMask(matrix, reserved) {
        var best = 0;
        var bestScore = Infinity;
        for (var mask = 0; mask < 8; mask++) {
            var m = matrix.map(function (row) { return row.slice(); });
            applyMask(m, mask, reserved);
            var bits = encodeFormatBits(mask);
            placeFormatBits(m, bits);
            var score = scoreMask(m);
            if (score < bestScore) {
                bestScore = score;
                best = mask;
            }
        }
        return best;
    }

    function encodeFormatBits(mask) {
        var data = (0 << 3) | mask;
        var encoded = data << 10;
        var g = 0x537;
        var x = encoded;
        for (var i = 14; i >= 10; i--) {
            if ((x >> i) & 1) x ^= g << (i - 10);
        }
        var final = ((data << 10) | x) ^ 0x5412;
        var bits = [];
        for (var b = 14; b >= 0; b--) bits.push((final >> b) & 1);
        return bits;
    }

    function generate(text) {
        var codewords = encodeData(text);
        var m = createMatrix();
        placeFinder(m, 0, 0);
        placeFinder(m, 0, SIZE - 7);
        placeFinder(m, SIZE - 7, 0);
        var aligns = ALIGNMENT_BY_VERSION[VERSION] || [];
        for (var i = 0; i < aligns.length; i++) {
            for (var j = 0; j < aligns.length; j++) {
                var r = aligns[i], c = aligns[j];
                if ((r === 6 && c === 6) || (r === 6 && c === SIZE - 7) || (r === SIZE - 7 && c === 6)) continue;
                placeAlignment(m, r, c);
            }
        }
        placeTiming(m);
        reserveFormat(m);
        var reserved = m.map(function (row) { return row.map(function (v) { return v !== null; }); });
        placeData(m, codewords);

        var bestMask = chooseMask(m, reserved);
        applyMask(m, bestMask, reserved);
        placeFormatBits(m, encodeFormatBits(bestMask));

        return m;
    }

    function render(text, opts) {
        opts = opts || {};
        var matrix = generate(String(text));
        var moduleSize = opts.size ? Math.max(2, Math.floor(opts.size / SIZE)) : 6;
        var margin = opts.margin != null ? opts.margin : moduleSize * 2;
        var px = matrix.length * moduleSize + margin * 2;
        var parts = ['<svg xmlns="http://www.w3.org/2000/svg" width="' + px + '" height="' + px + '" viewBox="0 0 ' + px + ' ' + px + '" shape-rendering="crispEdges">'];
        parts.push('<rect width="100%" height="100%" fill="#fff"/>');
        for (var r = 0; r < matrix.length; r++) {
            for (var c = 0; c < matrix[r].length; c++) {
                if (matrix[r][c]) {
                    parts.push('<rect x="' + (margin + c * moduleSize) + '" y="' + (margin + r * moduleSize) + '" width="' + moduleSize + '" height="' + moduleSize + '" fill="#000"/>');
                }
            }
        }
        parts.push('</svg>');
        return parts.join('');
    }

    return {
        render: render,
        size: SIZE,
        version: VERSION,
        maxBytes: DATA_CODEWORDS - 3
    };
});
