// Ghi âm câu nói của Thi thử HSK 3.0. Bản ghi chỉ nằm trên máy người học (người dùng chọn 09/11/2026 để server 8 GB
// không phải chứa file âm thanh): giữ trong IndexedDB để lỡ tải lại trang giữa bài vẫn nghe lại được, tự xoá sau 7 ngày.

/** Định dạng thử lần lượt: Chrome/Edge/Firefox ghi webm-opus, Safari (cả iPhone) chỉ ghi mp4. */
export const DINH_DANG = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];

/** Định dạng đầu tiên trình duyệt hỗ trợ; '' = để trình duyệt tự chọn. */
export function chonDinhDang(hoTro: (type: string) => boolean): string {
  return DINH_DANG.find((t) => {
    try {
      return hoTro(t);
    } catch {
      return false;
    }
  }) ?? '';
}

/** Lý do không ghi âm được trên máy này, null = ghi được. */
interface MoiTruong {
  isSecureContext?: boolean;
  navigator?: { mediaDevices?: { getUserMedia?: unknown } };
  MediaRecorder?: unknown;
}

export function loiGhiAm(w: MoiTruong = globalThis as MoiTruong): string | null {
  if (w.isSecureContext === false) return 'Ghi âm chỉ chạy khi mở web bằng https.';
  if (!w.navigator?.mediaDevices?.getUserMedia || typeof w.MediaRecorder === 'undefined') {
    return 'Trình duyệt này chưa ghi âm được. Hãy mở bằng Chrome, Edge hoặc Safari bản mới.';
  }
  return null;
}

export function khoaBanGhi(attemptId: number, qid: number): string {
  return `${attemptId}:${qid}`;
}

/** Giữ bản ghi 7 ngày: đủ để xem lại kết quả, không làm đầy bộ nhớ máy. */
export const GIU_NGAY = 7;

const DB = 'zk-ghi-am';
const KHO = 'ban-ghi';

function moDb(): Promise<IDBDatabase> {
  return new Promise((ok, loi) => {
    const r = indexedDB.open(DB, 1);
    r.onupgradeneeded = () => r.result.createObjectStore(KHO);
    r.onsuccess = () => ok(r.result);
    r.onerror = () => loi(r.error);
  });
}

async function voiKho<T>(cheDo: IDBTransactionMode, viec: (kho: IDBObjectStore) => IDBRequest<T>): Promise<T | null> {
  try {
    const db = await moDb();
    return await new Promise<T | null>((ok) => {
      const req = viec(db.transaction(KHO, cheDo).objectStore(KHO));
      req.onsuccess = () => ok(req.result ?? null);
      req.onerror = () => ok(null);
    }).finally(() => db.close());
  } catch {
    // Chế độ riêng tư hoặc trình duyệt chặn bộ nhớ: vẫn ghi và nghe được trong lúc trang còn mở.
    return null;
  }
}

interface BanGhi {
  blob: Blob;
  luc: number;
}

export async function luuBanGhi(attemptId: number, qid: number, blob: Blob): Promise<void> {
  await voiKho('readwrite', (kho) => kho.put({ blob, luc: Date.now() } satisfies BanGhi, khoaBanGhi(attemptId, qid)));
}

export async function layBanGhi(attemptId: number, qid: number): Promise<Blob | null> {
  const r = await voiKho<BanGhi | undefined>('readonly', (kho) => kho.get(khoaBanGhi(attemptId, qid)));
  return r?.blob ?? null;
}

/** Xoá bản ghi cũ hơn GIU_NGAY ngày. Gọi khi mở màn làm đề. */
export async function donBanGhiCu(bayGio = Date.now()): Promise<void> {
  try {
    const db = await moDb();
    const tx = db.transaction(KHO, 'readwrite');
    const req = tx.objectStore(KHO).openCursor();
    req.onsuccess = () => {
      const c = req.result;
      if (!c) return;
      if (bayGio - ((c.value as BanGhi).luc ?? 0) > GIU_NGAY * 86400_000) c.delete();
      c.continue();
    };
    tx.oncomplete = () => db.close();
  } catch {
    // Không dọn được thì thôi, lần sau dọn.
  }
}
