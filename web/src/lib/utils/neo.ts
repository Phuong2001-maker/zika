// Bảng thả xuống (chuông thông báo, chuyển nhanh) trên điện thoại. Nút mở không nằm sát mép phải
// nên bảng neo `right: 0` theo nút sẽ tràn mép trái màn hình. Dưới 768px CSS cho bảng
// `position: fixed` trải gần hết bề ngang; action này chỉ đo đáy thanh trên:
//   --neo-nen  đáy thanh trên — nền mờ bắt đầu từ đây để thanh trên vẫn rõ;
//   --neo-top  mép trên của bảng, chừa 12px dưới đường kẻ của thanh để bảng không dính vào thanh.
export function neo(node: HTMLElement, nut: HTMLElement | null | undefined) {
  const thanh = node.closest<HTMLElement>('.mtop');
  const dat = (): void => {
    const r = nut?.getBoundingClientRect();
    if (!r) return;
    const day = Math.round((thanh ?? nut?.closest('header'))?.getBoundingClientRect().bottom ?? r.bottom);
    node.style.setProperty('--neo-nen', `${day}px`);
    node.style.setProperty('--neo-top', `${day + 12}px`);
  };
  dat();
  // .mtop là sticky z-index 20, ngang .mbar/.msheet ở đáy màn; hai thanh đó đứng sau trong DOM nên
  // đè lên bảng. Lúc bảng mở thì nâng thanh trên lên.
  if (thanh) thanh.style.zIndex = '70';
  window.addEventListener('resize', dat);
  return {
    destroy(): void {
      window.removeEventListener('resize', dat);
      if (thanh) thanh.style.zIndex = '';
    },
  };
}
