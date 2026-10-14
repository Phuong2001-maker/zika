import { deflateRawSync } from 'node:zlib';
import { describe, expect, it } from 'vitest';
import { buildXlsx, readDelimited, readXlsx, XlsxError, type Sheet } from './xlsx';

/** buildXlsx trả Blob; đọc lại cần mảng byte. */
async function bytes(blob: Blob): Promise<Uint8Array> {
  return new Uint8Array(await blob.arrayBuffer());
}

/**
 * Dựng một .xlsx nén thật (deflate, method 8) — Excel luôn ghi kiểu này, còn buildXlsx của ta
 * ghi “stored”, nên nếu chỉ test vòng ghi–đọc thì nhánh giải nén không bao giờ chạy.
 */
function zipDeflate(files: { path: string; text: string }[]): Uint8Array {
  const enc = new TextEncoder();
  const table = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    table[n] = c >>> 0;
  }
  const crc32 = (d: Uint8Array): number => {
    let c = 0xffffffff;
    for (const b of d) c = table[(c ^ b) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
  };

  const local: Uint8Array[] = [];
  const central: Uint8Array[] = [];
  let offset = 0;

  for (const f of files) {
    const name = enc.encode(f.path);
    const raw = enc.encode(f.text);
    const data = new Uint8Array(deflateRawSync(raw));
    const crc = crc32(raw);

    const lh = new Uint8Array(30 + name.length);
    const lv = new DataView(lh.buffer);
    lv.setUint32(0, 0x04034b50, true);
    lv.setUint16(8, 8, true); // deflate
    lv.setUint32(14, crc, true);
    lv.setUint32(18, data.length, true);
    lv.setUint32(22, raw.length, true);
    lv.setUint16(26, name.length, true);
    lh.set(name, 30);

    const ch = new Uint8Array(46 + name.length);
    const cv = new DataView(ch.buffer);
    cv.setUint32(0, 0x02014b50, true);
    cv.setUint16(10, 8, true);
    cv.setUint32(16, crc, true);
    cv.setUint32(20, data.length, true);
    cv.setUint32(24, raw.length, true);
    cv.setUint16(28, name.length, true);
    cv.setUint32(42, offset, true);
    ch.set(name, 46);

    local.push(lh, data);
    central.push(ch);
    offset += lh.length + data.length;
  }

  const cenSize = central.reduce((n, c) => n + c.length, 0);
  const end = new Uint8Array(22);
  const ev = new DataView(end.buffer);
  ev.setUint32(0, 0x06054b50, true);
  ev.setUint16(8, files.length, true);
  ev.setUint16(10, files.length, true);
  ev.setUint32(12, cenSize, true);
  ev.setUint32(16, offset, true);

  const parts = [...local, ...central, end];
  const total = parts.reduce((n, p) => n + p.length, 0);
  const out = new Uint8Array(total);
  let at = 0;
  for (const p of parts) {
    out.set(p, at);
    at += p.length;
  }
  return out;
}

const RELS =
  '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
  '<Relationship Id="rId1" Type="x/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';

const WORKBOOK =
  '<?xml version="1.0"?><workbook xmlns="x" xmlns:r="y"><sheets>' +
  '<sheet name="Từ vựng" sheetId="1" r:id="rId1"/></sheets></workbook>';

describe('đọc .xlsx', () => {
  it('ghi rồi đọc lại giữ nguyên nội dung, kể cả ký tự phải thoát', async () => {
    const sheets: Sheet[] = [
      { name: 'Từ vựng', rows: [['Chữ Hán', 'Nghĩa'], ['鸡蛋', 'trứng & "gà" <ngon>'], ['牛奶', 'sữa bò']] },
      { name: 'Ví dụ', rows: [['A'], ['我喜欢']] },
    ];
    const back = await readXlsx(await bytes(buildXlsx(sheets)));

    expect(back).toHaveLength(2);
    expect(back[0].name).toBe('Từ vựng');
    expect(back[0].rows[1]).toEqual(['鸡蛋', 'trứng & "gà" <ngon>']);
    expect(back[1].rows[1]).toEqual(['我喜欢']);
  });

  it('đọc được file nén deflate (kiểu Excel thật ghi ra)', async () => {
    const sheet =
      '<?xml version="1.0"?><worksheet xmlns="x"><sheetData>' +
      '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>' +
      '<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>12.5</v></c></row>' +
      '</sheetData></worksheet>';
    const shared =
      '<?xml version="1.0"?><sst xmlns="x"><si><t>Chữ Hán</t></si><si><t>Điểm</t></si>' +
      '<si><r><t>鸡</t></r><r><t>蛋</t></r></si></sst>';

    const zip = zipDeflate([
      { path: 'xl/workbook.xml', text: WORKBOOK },
      { path: 'xl/_rels/workbook.xml.rels', text: RELS },
      { path: 'xl/sharedStrings.xml', text: shared },
      { path: 'xl/worksheets/sheet1.xml', text: sheet },
    ]);

    const back = await readXlsx(zip);
    expect(back[0].rows[0]).toEqual(['Chữ Hán', 'Điểm']);
    // Chữ bị Excel cắt thành nhiều <r><t> phải được nối lại.
    expect(back[0].rows[1]).toEqual(['鸡蛋', '12.5']);
  });

  it('ô trống giữa bảng không làm lệch cột', async () => {
    const sheet =
      '<?xml version="1.0"?><worksheet xmlns="x"><sheetData>' +
      '<row r="1"><c r="A1" t="inlineStr"><is><t>a</t></is></c>' +
      '<c r="C1" t="inlineStr"><is><t>c</t></is></c></row></sheetData></worksheet>';
    const zip = zipDeflate([
      { path: 'xl/workbook.xml', text: WORKBOOK },
      { path: 'xl/_rels/workbook.xml.rels', text: RELS },
      { path: 'xl/worksheets/sheet1.xml', text: sheet },
    ]);

    const back = await readXlsx(zip);
    expect(back[0].rows[0]).toEqual(['a', '', 'c']);
  });

  it('file không phải .xlsx thì báo lỗi rõ ràng', async () => {
    await expect(readXlsx(new TextEncoder().encode('xin chào'))).rejects.toBeInstanceOf(XlsxError);
  });
});

describe('đọc CSV / TSV', () => {
  it('tự nhận dấu ngăn cột', () => {
    expect(readDelimited('a\tb\tc\n1\t2\t3')[0].rows).toEqual([
      ['a', 'b', 'c'],
      ['1', '2', '3'],
    ]);
    expect(readDelimited('a;b\n1;2')[0].rows).toEqual([
      ['a', 'b'],
      ['1', '2'],
    ]);
    expect(readDelimited('a,b\n1,2')[0].rows).toEqual([
      ['a', 'b'],
      ['1', '2'],
    ]);
  });

  it('ô trong ngoặc kép giữ được dấu phẩy, xuống dòng và dấu nháy', () => {
    const rows = readDelimited('ten,nghia\n"a,b","nói ""thế"""\n"hai\ndòng",x')[0].rows;
    expect(rows[1]).toEqual(['a,b', 'nói "thế"']);
    expect(rows[2]).toEqual(['hai\ndòng', 'x']);
  });

  it('bỏ BOM và dòng trống', () => {
    expect(readDelimited('﻿a,b\n\n1,2\n')[0].rows).toEqual([
      ['a', 'b'],
      ['1', '2'],
    ]);
  });
});
