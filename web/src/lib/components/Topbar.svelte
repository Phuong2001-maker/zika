<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { Part } from '$lib/api/types';
  import Switcher from './Switcher.svelte';
  import Avatar from './Avatar.svelte';
  import NotificationBell from './NotificationBell.svelte';
  import MonthlyCommunity from '$lib/learn/MonthlyCommunity.svelte';
  import DiemChip from '$lib/learn/DiemChip.svelte';
  import FeatureGate from './FeatureGate.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { homePath } from '$lib/auth/routes';

  // Thanh trên người học, bố cục máy tính (05 §7.1) — một thanh dùng chung cho mọi màn: đường dẫn (nếu có),
  // điểm mở bài, quỹ tháng, top chăm học, chuông, chuyển nhanh, tài khoản. Trước đây mỗi màn tự viết thanh riêng nên
  // màn có logo, màn không; màn có Quỹ tháng, màn không (người dùng báo 08/11). Thêm màn mới thì dùng component này.
  // Riêng màn đang thi (TestRunner) giữ thanh riêng: đang trong giờ thi thì không cho bấm đi chỗ khác.
  // Không có logo "字 Zìkǎ" (người dùng bỏ 08/11): về Bàn học bằng Chuyển nhanh hoặc đường dẫn.
  let {
    current = 'lt',
    accountCurrent = false,
    children,
  }: {
    current?: Part;
    accountCurrent?: boolean;
    /** Đường dẫn ở đầu thanh, ví dụ `<nav class="crumb">Lật thẻ / Quyển 1</nav>`. */
    children?: Snippet;
  } = $props();
</script>

<header class="topbar">
  {#if children}{@render children()}{/if}
  <div class="tb-phai">
    <FeatureGate name="header.points"><DiemChip /></FeatureGate>
    <MonthlyCommunity />
    <!-- Thứ tự người dùng chốt 08/11: chuông rồi mới tới Chuyển nhanh, cuối cùng là tài khoản. -->
    <FeatureGate name="header.notifications"><FeatureGate name="community.notifications"><NotificationBell size={44} /></FeatureGate></FeatureGate>
    {#if session.can('header.switcher')}<Switcher {current} />{:else}<a class="btn btn-sm" href={homePath(session.user?.role, session.features)}>Bàn học</a>{/if}
    <Avatar size={44} current={accountCurrent} bell={false} />
  </div>
</header>

<style>
  .tb-phai { margin-left: auto; display: flex; align-items: center; gap: 12px; }
</style>
