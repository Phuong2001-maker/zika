-- =====================================================================
-- Zìkǎ · seed-base.sql — dữ liệu BẮT BUỘC (chạy như migration 002 trên mọi môi trường)
-- 6 cấp HSK (đều đang ẩn), cài đặt hệ thống mặc định, email chào mừng tự động.
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- Cấp HSK
INSERT INTO levels (id, name, target_words, sample_text, is_visible) VALUES
  (1, 'HSK 1', 150, '你好 · 喜欢', 0),
  (2, 'HSK 2', 150, '旅游 · 鸡蛋', 0),
  (3, 'HSK 3', 300, '环境 · 习惯', 0),
  (4, 'HSK 4', 600, '经验 · 尊重', 0),
  (5, 'HSK 5', 1300, '资源 · 媒体', 0),
  (6, 'HSK 6', 2500, '辩论 · 倡导', 0);

-- Cài đặt hệ thống mặc định
INSERT INTO site_settings (k, v) VALUES
  ('site', '{"name":"Zìkǎ","slogan":"Lật thẻ học chữ Hán, miễn phí cho mọi người","logo_path":null,"contact_email":""}'),
  ('auth', '{"google":true,"email":true,"require_email_verify":true,"allow_signup":true}'),
  ('content', '{"default_voice":"xiaoxiao","show_feedback_button":true}'),
  ('privacy', '{"visit_log_days":90,"mask_emails":true,"privacy_page":"# Chính sách riêng tư\\n\\nZìkǎ lưu email, tên hiển thị, tiến độ học và thời gian học của bạn để hiển thị tiến độ và thống kê. Chúng tôi không bán dữ liệu cho bên thứ ba. Bạn có thể xoá tài khoản bất cứ lúc nào trong trang Tài khoản.","terms_page":"# Điều khoản sử dụng\\n\\nZìkǎ miễn phí cho mục đích học tập cá nhân. Không sao chép nội dung để bán lại. Một số link trên web là liên kết tiếp thị, giúp duy trì web miễn phí."}'),
  ('backup', '{"daily":true,"last_at":null,"last_size":0}'),
  ('support', '{"enabled":false}'),
  ('donate', '{"enabled":true,"qr_path":null,"bank":"","owner":"","account":"","memo":"UNG HO ZIKA"}');

-- Email chào mừng tự động (không bao giờ gửi hàng loạt; chỉ gửi cho từng người khi đăng ký)
INSERT INTO announcements (title, body, kind, audience, status, is_auto_welcome) VALUES
  ('Chào mừng bạn mới', 'Chào mừng bạn đến với Zìkǎ! Bắt đầu với một bài HSK, mỗi ngày lật một ít thẻ là đủ. Chúc bạn học vui!', 'email', 'all', 'published', 1);
