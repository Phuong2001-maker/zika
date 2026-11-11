<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { ManHoc } from '$lib/api/types';
  import Icon from './Icon.svelte';
  import Switcher from './Switcher.svelte';
  import Avatar from './Avatar.svelte';
  import NotificationBell from './NotificationBell.svelte';
  import MonthlyCommunity from '$lib/learn/MonthlyCommunity.svelte';
  import DiemChip from '$lib/learn/DiemChip.svelte';
  import FeatureGate from './FeatureGate.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { homePath, loginUrl } from '$lib/auth/routes';
  import { page } from '$app/state';

  // Thanh trên người học, bố cục điện thoại — một thanh dùng chung cho mọi màn (người dùng yêu cầu 08/11, trước đó
  // 21 màn 21 kiểu: màn có Quỹ tháng, màn không; màn có Chuyển nhanh, màn không).
  // Dòng 1 luôn giống nhau và **cùng thứ tự với Topbar máy tính**: điểm mở bài, Quỹ tháng, Top chăm học, chuông,
  // Chuyển nhanh, tài khoản.
  // Dòng 2 chỉ có khi màn cần nút lùi / tên màn: 375px không đủ chỗ cho cả nút lùi, tên bài và 6 nút trên một dòng.
  // Ngoại lệ: màn đang thi (TestRunner) giữ thanh riêng. Thanh dính trên cùng (.mtop); màn nào cần biết thanh cao bao
  // nhiêu (thanh công cụ sách dính ngay dưới) thì bind:cao.
  let {
    current = 'lt',
    accountCurrent = false,
    back,
    backLabel = 'Quay lại',
    onback,
    title,
    sub,
    children,
    cao = $bindable(0),
  }: {
    current?: ManHoc;
    accountCurrent?: boolean;
    /** Đường dẫn nút lùi. */
    back?: string;
    backLabel?: string;
    /** Nút lùi chạy hàm thay vì đi đường dẫn (trang Thông báo: quay lại trang trước). */
    onback?: () => void;
    title?: string;
    sub?: string;
    /** Nội dung dòng 2 tự dựng (ví dụ nhãn cấp + "Chọn bài"), thay cho title/sub. */
    children?: Snippet;
    cao?: number;
  } = $props();

  const coDong2 = $derived(!!(back || onback || title || children));
</script>

<header class="mtop mtb" bind:clientHeight={cao}>
  <div class="mtb-dong1">
    {#if session.user}<FeatureGate name="header.points"><DiemChip gon /></FeatureGate>{/if}
    <MonthlyCommunity />
    <!-- Thứ tự người dùng chốt 08/11: chuông rồi mới tới Chuyển nhanh, cuối cùng là tài khoản. -->
    <FeatureGate name="header.notifications"><FeatureGate name="community.notifications"><NotificationBell size={32} /></FeatureGate></FeatureGate>
    {#if session.can('header.switcher')}<Switcher {current} />{:else}<a class="btn btn-sm" href={homePath(session.user?.role, session.quyen)}>Bàn học</a>{/if}
    <!-- Khách học thử: chỗ tài khoản là nút Đăng nhập, đăng nhập xong quay lại đúng màn đang xem. -->
    {#if session.user}<Avatar size={32} current={accountCurrent} bell={false} />{:else}<a class="btn btn-son btn-sm" href={loginUrl(page.url.pathname + page.url.search)}>Đăng nhập</a>{/if}
  </div>
  {#if coDong2}
    <div class="mtb-dong2">
      {#if onback}
        <button type="button" class="btn btn-bare mtb-lui" aria-label={backLabel} onclick={onback}><Icon name="chev" size={20} style="transform: rotate(180deg)" /></button>
      {:else if back}
        <a href={back} class="btn btn-bare mtb-lui" aria-label={backLabel}><Icon name="chev" size={20} style="transform: rotate(180deg)" /></a>
      {/if}
      {#if children}
        <div class="mtb-tu-dung">{@render children()}</div>
      {:else if title}
        <span class="mtb-ten">
          <b>{title}</b>
          {#if sub}<span class="muted">{sub}</span>{/if}
        </span>
        <!-- Ô trống cân với nút lùi để tên nằm giữa màn. -->
        {#if back || onback}<span class="mtb-can" aria-hidden="true"></span>{/if}
      {/if}
    </div>
  {/if}
</header>

<style>
  .mtb { flex-direction: column; align-items: stretch; gap: 4px; padding: 8px 12px; }
  .mtb-dong1 { display: flex; align-items: center; justify-content: flex-end; gap: 6px; min-height: 40px; }
  .mtb-dong2 { display: flex; align-items: center; gap: 6px; min-height: 40px; margin: 0 -4px; }
  .mtb-lui { flex: none; width: 40px; height: 40px; padding: 0; }
  .mtb-can { flex: none; width: 40px; }
  .mtb-ten { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; text-align: center; line-height: 1.25; }
  .mtb-ten b { max-width: 100%; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .mtb-ten .muted { max-width: 100%; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .mtb-tu-dung { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; }
</style>
