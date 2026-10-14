// Đọc và ghi file Excel (.xlsx) ngay trong trình duyệt, không thêm thư viện.
//
// Vì sao tự viết: khu quản trị chỉ cần đọc ô chữ và ghi file mẫu — khoảng 200 dòng. Thêm
// SheetJS/ExcelJS vào bản build chỉ cho vài màn soạn bài là không đáng (service-worker.ts đã
// chọn cách tương tự). .xlsx vốn là file ZIP chứa XML nên chỉ cần ZIP + vài biểu thức.
//
// Giải nén dùng DecompressionStream('deflate-raw') — có ở Chrome/Edge 103+, Firefox 113+,
// Safari 16.4+ và Node 18+. Trình duyệt cũ hơn nhận lỗi XLSX_UNSUPPORTED để màn hình mời dùng .csv.

export interface Sheet {
  name: string;
  /** Ô đã cắt khoảng trắng hai đầu. */
  rows: string[][];
}

export class XlsxError extends Error {
  constructor(
    public code: 'XLSX_BAD_FILE' | 'XLSX_UNSUPPORTED' | 'XLSX_EMPTY',
    message: string,
  ) {
    super(message);
  }
}

/* ===================================================================== */
/* ZIP                                                                    */
/* ===================================================================== */

const EOCD_SIG = 0x06054b50;
const CEN_SIG = 0x02014b50;
const LOC_SIG = 0x04034b50;

interface ZipEntry {
  method: number;
  size: number;
  offset: number;
}

/** Quét ngược tìm End Of Central Directory (phần chú thích cuối file tối đa 65535 byte). */
function findEocd(view: DataView): number {
  const start = Math.max(0, view.byteLength - 65557);
  for (let i = view.byteLength - 22; i >= start; i--) {
    if (view.getUint32(i, true) === EOCD_SIG) return i;
  }
  throw new XlsxError('XLSX_BAD_FILE', 'Không đọc được file: đây không phải file Excel (.xlsx).');
}

/** Danh mục file trong ZIP, đọc từ central directory (chắc hơn lần theo từng local header). */
function listEntries(buf: Uint8Array): Map<string, ZipEntry> {
  const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);
  const eocd = findEocd(view);
  const count = view.getUint16(eocd + 10, true);
  let p = view.getUint32(eocd + 16, true);

  const out = new Map<string, ZipEntry>();
  for (let i = 0; i < count; i++) {
    if (p + 46 > buf.byteLength || view.getUint32(p, true) !== CEN_SIG) break;
    const method = view.getUint16(p + 10, true);
    const size = view.getUint32(p + 20, true);
    const nameLen = view.getUint16(p + 28, true);
    const extraLen = view.getUint16(p + 30, true);
    const commentLen = view.getUint16(p + 32, true);
    const offset = view.getUint32(p + 42, true);
    const name = new TextDecoder().decode(buf.subarray(p + 46, p + 46 + nameLen));
    out.set(name, { method, size, offset });
    p += 46 + nameLen + extraLen + commentLen;
  }
  return out;
}

async function inflate(data: Uint8Array): Promise<Uint8Array> {
  if (typeof DecompressionStream === 'undefined') {
    throw new XlsxError('XLSX_UNSUPPORTED', 'Trình duyệt này chưa mở được .xlsx. Hãy lưu bảng thành .csv rồi nhập lại.');
  }
  const stream = new Blob([data as BlobPart]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
  return new Uint8Array(await new Response(stream).arrayBuffer());
}

/** Nội dung một file trong ZIP, đã giải nén. */
async function readEntry(buf: Uint8Array, e: ZipEntry): Promise<string> {
  const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);
  if (view.getUint32(e.offset, true) !== LOC_SIG) {
    throw new XlsxError('XLSX_BAD_FILE', 'File .xlsx bị hỏng.');
  }
  // Local header có thể dài khác central (phần extra), nên phải đọc lại độ dài tại chỗ.
  const nameLen = view.getUint16(e.offset + 26, true);
  const extraLen = view.getUint16(e.offset + 28, true);
  const start = e.offset + 30 + nameLen + extraLen;
  const raw = buf.subarray(start, start + e.size);
  const data = e.method === 0 ? raw : await inflate(raw);
  return new TextDecoder().decode(data);
}

/* ===================================================================== */
/* XML                                                                    */
/* ===================================================================== */

const ENTITY: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" };

function unescapeXml(s: string): string {
  return s.replace(/&(#x?[0-9A-Fa-f]+|[a-z]+);/g, (all: string, body: string) => {
    if (body[0] === '#') {
      const hex = body[1] === 'x' || body[1] === 'X';
      const code = hex ? parseInt(body.slice(2), 16) : parseInt(body.slice(1), 10);
      return Number.isFinite(code) && code > 0 ? String.fromCodePoint(code) : all;
    }
    return ENTITY[body] ?? all;
  });
}

export function escapeXml(s: string): string {
  const map: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
  // Ký tự điều khiển không hợp lệ trong XML 1.0 — Excel từ chối mở file nếu lọt vào.
  // eslint-disable-next-line no-control-regex -- đây đúng là dải ký tự cần loại bỏ
  const control = /[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g;
  return s.replace(control, '').replace(/[&<>"]/g, (c: string) => map[c]);
}

/** Nối mọi <t> trong một đoạn (chữ có định dạng bị Excel cắt thành nhiều <r><t>). */
function textOf(xml: string): string {
  let out = '';
  for (const m of xml.matchAll(/<t(?:\s[^>]*)?>([\s\S]*?)<\/t>/g)) out += unescapeXml(m[1] ?? '');
  return out;
}

/** "BC12" → 54 (chỉ số cột, tính từ 0). */
function colIndex(ref: string): number {
  let n = 0;
  for (const ch of ref) {
    const c = ch.charCodeAt(0);
    if (c < 65 || c > 90) break;
    n = n * 26 + (c - 64);
  }
  return n - 1;
}

/* ===================================================================== */
/* Đọc workbook                                                           */
/* ===================================================================== */

/** Thứ tự sheet theo workbook.xml; r:id → đường dẫn lấy từ workbook.xml.rels. */
function sheetPaths(workbook: string, rels: string): { name: string; path: string }[] {
  const target = new Map<string, string>();
  for (const m of rels.matchAll(/<Relationship\b[^>]*>/g)) {
    const id = /\bId="([^"]+)"/.exec(m[0])?.[1];
    const to = /\bTarget="([^"]+)"/.exec(m[0])?.[1];
    if (id && to) target.set(id, to.replace(/^\/?xl\//, '').replace(/^\//, ''));
  }

  const out: { name: string; path: string }[] = [];
  for (const m of workbook.matchAll(/<sheet\b[^>]*>/g)) {
    const name = /\bname="([^"]*)"/.exec(m[0])?.[1];
    const rid = /\br:id="([^"]+)"/.exec(m[0])?.[1];
    const to = rid ? target.get(rid) : undefined;
    if (name !== undefined && to) out.push({ name: unescapeXml(name), path: `xl/${to}` });
  }
  return out;
}

function parseSharedStrings(xml: string): string[] {
  return [...xml.matchAll(/<si\b[^>]*>([\s\S]*?)<\/si>|<si\s*\/>/g)].map((m) => textOf(m[1] ?? ''));
}

function parseSheet(xml: string, shared: string[]): string[][] {
  const rows: string[][] = [];
  for (const rm of xml.matchAll(/<row\b[^>]*>([\s\S]*?)<\/row>|<row\b[^>]*\/>/g)) {
    const body = rm[1] ?? '';
    const cells: string[] = [];
    for (const cm of body.matchAll(/<c\b([^>]*?)(?:\/>|>([\s\S]*?)<\/c>)/g)) {
      const attrs = cm[1] ?? '';
      const inner = cm[2] ?? '';
      const ref = /\br="([A-Z]+)\d+"/.exec(attrs)?.[1];
      const type = /\bt="([^"]+)"/.exec(attrs)?.[1] ?? 'n';

      let value: string;
      if (type === 's') {
        const i = Number(/<v>([\s\S]*?)<\/v>/.exec(inner)?.[1] ?? '-1');
        value = shared[i] ?? '';
      } else if (type === 'inlineStr') {
        value = textOf(inner);
      } else if (type === 'b') {
        value = /<v>\s*1\s*<\/v>/.test(inner) ? 'TRUE' : 'FALSE';
      } else {
        value = unescapeXml(/<v>([\s\S]*?)<\/v>/.exec(inner)?.[1] ?? '');
      }

      // Excel bỏ hẳn ô trống khỏi XML, nên phải chèn bù theo địa chỉ ô để cột khỏi lệch.
      const at = ref ? colIndex(ref) : cells.length;
      while (cells.length < at) cells.push('');
      cells[at] = value.trim();
    }
    rows.push(cells);
  }
  return rows;
}

/** Đọc mọi sheet của một file .xlsx. */
export async function readXlsx(input: ArrayBuffer | Uint8Array): Promise<Sheet[]> {
  const buf = input instanceof Uint8Array ? input : new Uint8Array(input);
  const entries = listEntries(buf);

  const workbook = entries.get('xl/workbook.xml');
  const rels = entries.get('xl/_rels/workbook.xml.rels');
  if (!workbook || !rels) {
    throw new XlsxError('XLSX_BAD_FILE', 'File .xlsx thiếu phần mô tả bảng tính.');
  }

  const ssEntry = entries.get('xl/sharedStrings.xml');
  const shared = ssEntry ? parseSharedStrings(await readEntry(buf, ssEntry)) : [];

  const out: Sheet[] = [];
  for (const s of sheetPaths(await readEntry(buf, workbook), await readEntry(buf, rels))) {
    const e = entries.get(s.path);
    if (e) out.push({ name: s.name, rows: parseSheet(await readEntry(buf, e), shared) });
  }
  if (out.length === 0) throw new XlsxError('XLSX_EMPTY', 'File không có bảng nào.');
  return out;
}

/* ===================================================================== */
/* Đọc CSV / TSV                                                          */
/* ===================================================================== */

/** Dấu ngăn cột: ưu tiên Tab (dán thẳng từ Excel), rồi “;” (Excel tiếng Việt) và “,”. */
function guessSep(text: string): string {
  const head = text.slice(0, 5000);
  const count = (c: string): number => head.split(c).length - 1;
  const tab = count('\t');
  const semi = count(';');
  const comma = count(',');
  if (tab > 0 && tab >= semi && tab >= comma) return '\t';
  return semi > comma ? ';' : ',';
}

/** CSV theo RFC 4180: ô trong ngoặc kép giữ được dấu ngăn và xuống dòng, "" là một dấu nháy. */
export function readDelimited(text: string, sep?: string): Sheet[] {
  const body = text.replace(/^\uFEFF/, '');
  const d = sep ?? guessSep(body);

  const rows: string[][] = [];
  let row: string[] = [];
  let cell = '';
  let quoted = false;

  for (let i = 0; i < body.length; i++) {
    const c = body[i];
    if (quoted) {
      if (c === '"') {
        if (body[i + 1] === '"') {
          cell += '"';
          i++;
        } else {
          quoted = false;
        }
      } else {
        cell += c;
      }
      continue;
    }
    if (c === '"' && cell === '') {
      quoted = true;
    } else if (c === d) {
      row.push(cell.trim());
      cell = '';
    } else if (c === '\n' || c === '\r') {
      if (c === '\r' && body[i + 1] === '\n') i++;
      row.push(cell.trim());
      rows.push(row);
      row = [];
      cell = '';
    } else {
      cell += c;
    }
  }
  if (cell !== '' || row.length > 0) {
    row.push(cell.trim());
    rows.push(row);
  }

  return [{ name: 'Sheet1', rows: rows.filter((r) => r.some((x) => x !== '')) }];
}

/** Nhận cả .xlsx và .csv/.tsv/.txt — nhận dạng theo nội dung thật (chữ ký PK) chứ không theo đuôi tên. */
export async function readWorkbook(file: File): Promise<Sheet[]> {
  const buf = new Uint8Array(await file.arrayBuffer());
  if (buf[0] === 0x50 && buf[1] === 0x4b) return readXlsx(buf);
  return readDelimited(new TextDecoder().decode(buf));
}

/* ===================================================================== */
/* Ghi workbook (file mẫu)                                                */
/* ===================================================================== */

const CRC_TABLE = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c >>> 0;
  }
  return t;
})();

function crc32(data: Uint8Array): number {
  let c = 0xffffffff;
  for (const b of data) c = CRC_TABLE[(c ^ b) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}

/** (0,0) → "A1". */
function cellRef(row: number, col: number): string {
  let s = '';
  for (let n = col + 1; n > 0; n = Math.floor((n - 1) / 26)) s = String.fromCharCode(65 + ((n - 1) % 26)) + s;
  return s + (row + 1);
}

function sheetXml(rows: string[][]): string {
  const body = rows
    .map((cells, r) => {
      const cs = cells
        .map((v, c) =>
          v === '' ? '' : `<c r="${cellRef(r, c)}" t="inlineStr"><is><t xml:space="preserve">${escapeXml(v)}</t></is></c>`,
        )
        .join('');
      return `<row r="${r + 1}">${cs}</row>`;
    })
    .join('');
  return (
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
    '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
    `<sheetData>${body}</sheetData></worksheet>`
  );
}

/**
 * Đóng gói .xlsx. Dùng ZIP “stored” (không nén): file mẫu chỉ vài KB, và CompressionStream
 * không có mặt ở đúng những trình duyệt có DecompressionStream.
 */
export function buildXlsx(sheets: Sheet[]): Blob {
  // Excel giới hạn tên sheet 31 ký tự và cấm \ / ? * [ ] :
  const names = sheets.map((s, i) => (s.name.trim() || `Sheet${i + 1}`).replace(/[\\/?*[\]:]/g, ' ').slice(0, 31));
  const ns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

  const files: { path: string; text: string }[] = [
    {
      path: '[Content_Types].xml',
      text:
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' +
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' +
        '<Default Extension="xml" ContentType="application/xml"/>' +
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' +
        names
          .map(
            (_, i) =>
              `<Override PartName="/xl/worksheets/sheet${i + 1}.xml" ` +
              'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
          )
          .join('') +
        '</Types>',
    },
    {
      path: '_rels/.rels',
      text:
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
        `<Relationship Id="rId1" Type="${ns}/officeDocument" Target="xl/workbook.xml"/></Relationships>`,
    },
    {
      path: 'xl/workbook.xml',
      text:
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' +
        `xmlns:r="${ns}"><sheets>` +
        names.map((n, i) => `<sheet name="${escapeXml(n)}" sheetId="${i + 1}" r:id="rId${i + 1}"/>`).join('') +
        '</sheets></workbook>',
    },
    {
      path: 'xl/_rels/workbook.xml.rels',
      text:
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
        names
          .map((_, i) => `<Relationship Id="rId${i + 1}" Type="${ns}/worksheet" Target="worksheets/sheet${i + 1}.xml"/>`)
          .join('') +
        '</Relationships>',
    },
    ...sheets.map((s, i) => ({ path: `xl/worksheets/sheet${i + 1}.xml`, text: sheetXml(s.rows) })),
  ];

  const enc = new TextEncoder();
  const chunks: Uint8Array[] = [];
  const central: Uint8Array[] = [];
  let offset = 0;

  for (const f of files) {
    const name = enc.encode(f.path);
    const data = enc.encode(f.text);
    const crc = crc32(data);

    const local = new Uint8Array(30 + name.length);
    const lv = new DataView(local.buffer);
    lv.setUint32(0, LOC_SIG, true);
    lv.setUint16(4, 20, true);
    lv.setUint16(6, 0x0800, true); // tên file là UTF-8
    lv.setUint16(8, 0, true); // stored
    lv.setUint32(14, crc, true);
    lv.setUint32(18, data.length, true);
    lv.setUint32(22, data.length, true);
    lv.setUint16(26, name.length, true);
    local.set(name, 30);

    const cen = new Uint8Array(46 + name.length);
    const cv = new DataView(cen.buffer);
    cv.setUint32(0, CEN_SIG, true);
    cv.setUint16(4, 20, true);
    cv.setUint16(6, 20, true);
    cv.setUint16(8, 0x0800, true);
    cv.setUint16(10, 0, true);
    cv.setUint32(16, crc, true);
    cv.setUint32(20, data.length, true);
    cv.setUint32(24, data.length, true);
    cv.setUint16(28, name.length, true);
    cv.setUint32(42, offset, true);
    cen.set(name, 46);

    chunks.push(local, data);
    central.push(cen);
    offset += local.length + data.length;
  }

  const cenSize = central.reduce((n, c) => n + c.length, 0);
  const end = new Uint8Array(22);
  const ev = new DataView(end.buffer);
  ev.setUint32(0, EOCD_SIG, true);
  ev.setUint16(8, files.length, true);
  ev.setUint16(10, files.length, true);
  ev.setUint32(12, cenSize, true);
  ev.setUint32(16, offset, true);

  return new Blob([...chunks, ...central, end] as BlobPart[], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });
}

/** Tải file xuống máy (nút “Tải file mẫu”). */
export function download(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
