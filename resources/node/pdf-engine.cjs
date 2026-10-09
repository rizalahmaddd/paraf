#!/usr/bin/env node
// PDF helper called from PHP (App\Services\PdfEngine) through Laravel Process.
// Usage: node pdf-engine.cjs inspect <file> | bake <job.json> | append <job.json>
// Always prints a single JSON object to stdout; exit code 0 even for "rejected" PDFs.

const fs = require('fs');
const { PDFDocument, PDFDict, PDFName, PDFStream, StandardFonts, rgb, degrees } = require('pdf-lib');

const UNSAFE_KEYS = ['JavaScript', 'JS', 'EmbeddedFile', 'EmbeddedFiles', 'RichMedia'];
const UNSAFE_ACTIONS = ['JavaScript', 'Launch', 'ImportData', 'RichMediaExecute'];

function out(payload) {
    process.stdout.write(JSON.stringify(payload));
}

function normalizeRotation(angle) {
    return (((Math.round(angle / 90) * 90) % 360) + 360) % 360;
}

async function load(bytes) {
    return PDFDocument.load(bytes, { updateMetadata: false });
}

function findUnsafeFeatures(doc) {
    const found = new Set();

    for (const [, object] of doc.context.enumerateIndirectObjects()) {
        const dict = object instanceof PDFDict ? object : object instanceof PDFStream ? object.dict : null;
        if (!dict) continue;

        for (const key of UNSAFE_KEYS) {
            if (dict.has(PDFName.of(key))) found.add(key);
        }

        const type = dict.get(PDFName.of('Type'));
        if (type instanceof PDFName && type.decodeText() === 'EmbeddedFile') found.add('EmbeddedFile');

        const action = dict.get(PDFName.of('S'));
        if (action instanceof PDFName && UNSAFE_ACTIONS.includes(action.decodeText())) found.add(action.decodeText());
    }

    return [...found];
}

async function inspect(file) {
    const bytes = fs.readFileSync(file);
    const head = bytes.subarray(0, 1024).toString('latin1');

    if (!head.includes('%PDF-')) {
        return out({ ok: false, reason: 'invalid', message: 'Bukan file PDF yang valid.' });
    }

    let doc;
    try {
        doc = await load(bytes);
    } catch (error) {
        if (error && (error.name === 'EncryptedPDFError' || /encrypt/i.test(String(error.message)))) {
            return out({ ok: false, reason: 'encrypted' });
        }
        return out({ ok: false, reason: 'invalid', message: String(error && error.message) });
    }

    if (doc.isEncrypted) {
        return out({ ok: false, reason: 'encrypted' });
    }

    const unsafe = findUnsafeFeatures(doc);
    if (unsafe.length > 0) {
        return out({ ok: false, reason: 'unsafe', features: unsafe });
    }

    const pages = doc.getPages().map((page) => {
        const box = page.getCropBox();
        return {
            width: round(box.width),
            height: round(box.height),
            rotation: normalizeRotation(page.getRotation().angle),
        };
    });

    if (pages.length === 0) {
        return out({ ok: false, reason: 'invalid', message: 'PDF tidak memiliki halaman.' });
    }

    out({ ok: true, pages });
}

function round(value) {
    return Math.round(value * 100) / 100;
}

// Maps a point in "display space" (origin top-left, as the browser shows the rotated page)
// back to the page's unrotated PDF user space (origin bottom-left).
function displayToUser(box, rotation, u, v) {
    const W = box.width;
    const H = box.height;
    let x;
    let y;

    switch (rotation) {
        case 90: x = v; y = u; break;
        case 180: x = W - u; y = v; break;
        case 270: x = W - v; y = H - u; break;
        default: x = u; y = H - v;
    }

    return { x: box.x + x, y: box.y + y };
}

function toWinAnsi(font, text) {
    let safe = '';
    for (const char of String(text)) {
        try {
            font.encodeText(char);
            safe += char;
        } catch (e) {
            safe += '?';
        }
    }
    return safe;
}

async function bake(jobFile) {
    const job = JSON.parse(fs.readFileSync(jobFile, 'utf8'));
    const doc = await load(fs.readFileSync(job.input));
    const font = await doc.embedFont(StandardFonts.Helvetica);
    const pages = doc.getPages();
    const ink = rgb(0.06, 0.09, 0.16);

    for (const field of job.fields) {
        const page = pages[field.page - 1];
        if (!page) throw new Error(`Halaman ${field.page} tidak ada.`);

        const box = page.getCropBox();
        const rotation = normalizeRotation(page.getRotation().angle);
        const sideways = rotation === 90 || rotation === 270;
        const displayW = sideways ? box.height : box.width;
        const displayH = sideways ? box.width : box.height;

        const u0 = field.x * displayW;
        const v0 = field.y * displayH;
        const dw = field.w * displayW;
        const dh = field.h * displayH;

        // (a, b): local offset inside the field, measured from its bottom-left corner as displayed.
        const at = (a, b) => displayToUser(box, rotation, u0 + a, v0 + dh - b);

        if (field.image) {
            const image = await doc.embedPng(fs.readFileSync(field.image));
            const scale = Math.min(dw / image.width, dh / image.height);
            const fw = image.width * scale;
            const fh = image.height * scale;
            const anchor = at((dw - fw) / 2, (dh - fh) / 2);
            page.drawImage(image, { x: anchor.x, y: anchor.y, width: fw, height: fh, rotate: degrees(rotation) });
            continue;
        }

        if (field.type === 'CHECKBOX') {
            if (field.value !== '1') continue;
            const side = Math.min(dw, dh);
            const ox = (dw - side) / 2;
            const oy = (dh - side) / 2;
            const points = [[0.18, 0.5], [0.42, 0.24], [0.84, 0.8]].map(([a, b]) => at(ox + a * side, oy + b * side));
            const thickness = Math.max(1, side * 0.1);
            page.drawLine({ start: points[0], end: points[1], thickness, color: ink });
            page.drawLine({ start: points[1], end: points[2], thickness, color: ink });
            continue;
        }

        const text = toWinAnsi(font, field.value || '');
        if (!text) continue;

        const padding = Math.min(4, dw * 0.05);
        let size = Math.min(dh * 0.62, 14);
        while (size > 5 && font.widthOfTextAtSize(text, size) > dw - padding * 2) size -= 0.5;

        const baseline = (dh - size) / 2 + size * 0.22;
        const anchor = at(padding, baseline);
        page.drawText(text, { x: anchor.x, y: anchor.y, size, font, color: ink, rotate: degrees(rotation) });
    }

    fs.writeFileSync(job.output, await doc.save({ useObjectStreams: false }));
    out({ ok: true, pages: pages.length });
}

async function append(jobFile) {
    const job = JSON.parse(fs.readFileSync(jobFile, 'utf8'));
    const base = await load(fs.readFileSync(job.base));
    const appendix = await load(fs.readFileSync(job.appendix));
    const copied = await base.copyPages(appendix, appendix.getPageIndices());
    copied.forEach((page) => base.addPage(page));

    if (job.title) base.setTitle(job.title);
    base.setProducer('Paraf');
    base.setModificationDate(new Date());

    fs.writeFileSync(job.output, await base.save({ useObjectStreams: false }));
    out({ ok: true, pages: base.getPageCount() });
}

const [command, arg] = process.argv.slice(2);
const commands = { inspect, bake, append };

if (!commands[command] || !arg) {
    out({ ok: false, reason: 'usage', message: 'Usage: pdf-engine.cjs inspect|bake|append <path>' });
    process.exit(2);
}

commands[command](arg).catch((error) => {
    process.stderr.write(String(error && error.stack ? error.stack : error));
    process.exit(1);
});
