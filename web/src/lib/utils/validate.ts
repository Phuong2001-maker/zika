// Kiểm tra ở máy trước khi gửi (07 §2.3). Server vẫn kiểm lại; câu lỗi giống bên server.

export function emailProblem(email: string): string | null {
  const s = email.trim();
  if (!s) return 'Hãy nhập Email.';
  if (s.length > 190 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s)) return 'Email không hợp lệ.';
  return null;
}

/** Quy tắc mật khẩu (02 §6.1): ≥ 8 ký tự, có cả chữ và số, ≤ 72 byte. */
export function passwordProblem(password: string): string | null {
  if (password.length < 8 || !/\p{L}/u.test(password) || !/\d/.test(password)) {
    return 'Mật khẩu cần ít nhất 8 ký tự, có cả chữ và số.';
  }
  if (new TextEncoder().encode(password).length > 72) return 'Mật khẩu dài tối đa 72 ký tự.';
  return null;
}

export function nameProblem(name: string): string | null {
  const n = name.trim().length;
  if (n === 0) return 'Hãy nhập Tên hiển thị.';
  if (n < 2) return 'Tên hiển thị cần ít nhất 2 ký tự.';
  if (n > 60) return 'Tên hiển thị dài tối đa 60 ký tự.';
  return null;
}
