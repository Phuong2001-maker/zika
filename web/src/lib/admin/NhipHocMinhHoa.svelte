<script lang="ts">
  import { api } from '$lib/api/client';
  import Select from '$lib/components/Select.svelte';

  let { nguoi, revision }: { nguoi: { id: number; ten: string }[]; revision: number } = $props();
  interface Activity {
    week: { day: string; target: number; credited: number; sessions: { at: number; minutes: number }[] }[];
    months: { month: string; minutes: number; days: number; parts: number }[];
    enabled: boolean;
    updatedAt: string;
  }
  let id = $state(0);
  let data = $state<Activity | null>(null);
  let loading = $state(false);
  let error = $state(false);
  let request = 0;
  $effect(() => { if (!nguoi.some((n) => n.id === id)) id = nguoi[0]?.id ?? 0; });
  async function tai() {
    const current = ++request;
    const selected = id;
    if (!selected) { data = null; return; }
    loading = true;
    error = false;
    try {
      const result = await api.get<Activity>(`/admin/demo-learners/${selected}/activity`);
      if (current === request) data = result;
    } catch { if (current === request) { error = true; data = null; } }
    finally { if (current === request) loading = false; }
  }
  $effect(() => { void revision; void id; void tai(); });
  const thu = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('vi-VN', { weekday: 'short' });
  const gio = (n: number) => `${String(Math.floor(n / 60)).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
</script>

<section class="block nm-khung">
  <span class="nm-nhan">NHỊP HOẠT ĐỘNG</span>
  <h3>Lịch học & số liệu theo tháng</h3>
  {#if nguoi.length}
    <Select ariaLabel="Chọn người xem nhịp học" bind:value={id} options={nguoi.map((n) => ({ v: n.id, label: n.ten }))} />
    {#if loading}<p class="nm-note">Đang cập nhật lịch học...</p>{:else if error}<p class="nm-note">Chưa tải được lịch học. <button class="btn btn-sm" onclick={tai}>Thử lại</button></p>{:else if data}
      <div class="nm-trang-thai"><b>{data.enabled ? 'Tự động đang bật' : 'Tự động đang tắt'}</b><small>{data.enabled ? 'Tăng dần theo lịch dự kiến' : 'Giữ số liệu, không cộng thời gian tắt'}</small></div>
      <div class="nm-tuan">{#each data.week as d (d.day)}<div class:nm-nghi={!d.target}><small>{thu(d.day)}</small><b>{d.target ? `${d.target}′` : 'Nghỉ'}</b><span>Đã cộng {d.credited}′</span></div>{/each}</div>
      <details class="nm-chi-tiet"><summary>Giờ học dự kiến trong tuần</summary>{#each data.week as d (d.day)}<p><b>{thu(d.day)}</b><span>{d.sessions.length ? d.sessions.map((s) => `${gio(s.at)} · ${s.minutes} phút`).join(' / ') : 'Ngày nghỉ'}</span></p>{/each}</details>
      <h4>Lịch sử theo tháng</h4>
      <div class="nm-bang"><table><thead><tr><th>Tháng</th><th>Phút</th><th>Ngày</th><th>Phần xong</th></tr></thead><tbody>{#each data.months as m (m.month)}<tr><td>{m.month.split('-').reverse().join('/')}</td><td>{m.minutes}</td><td>{m.days}</td><td>{m.parts}</td></tr>{/each}</tbody></table></div>
      <p class="nm-note">Lịch dự kiến gồm cả ngày nghỉ. Đổi nhịp học áp dụng từ ngày chưa có lịch; tắt rồi bật lại không cộng bù thời gian tắt.</p>
    {/if}
  {:else}<p class="nm-note">Thêm người học để cài nhịp tự động và xem lịch học.</p>{/if}
</section>

<style>
  .nm-khung { padding: 20px; margin-top: 20px; } .nm-nhan { font-size: 11px; color: var(--muted); font-weight: 800; letter-spacing: .08em; } .nm-khung h3 { font-size: 17px; margin: 8px 0 18px; } .nm-trang-thai { display: flex; flex-direction: column; gap: 6px; margin: 18px 0 14px; font-size: 13px; } .nm-trang-thai small { color: var(--muted); font-weight: 400; }
  .nm-tuan { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; } .nm-tuan > div { display: flex; flex-direction: column; align-items: center; gap: 8px; background: #effbf4; border-radius: 10px; padding: 12px 4px; } .nm-tuan small, .nm-tuan span { font-size: 10px; color: var(--muted); } .nm-tuan b { font-size: 16px; color: #167344; } .nm-tuan .nm-nghi { background: var(--ground); } .nm-nghi b { color: var(--muted); }
  .nm-chi-tiet { font-size: 12px; margin-top: 16px; } .nm-chi-tiet summary { cursor: pointer; padding: 8px 0; } .nm-chi-tiet p { display: flex; gap: 14px; line-height: 1.7; } .nm-chi-tiet span { color: var(--muted); } .nm-khung h4 { font-size: 14px; margin: 20px 0 8px; } .nm-bang { overflow-x: auto; } .nm-bang table { width: 100%; font-size: 12px; } .nm-bang th, .nm-bang td { padding: 10px 6px; text-align: right; white-space: nowrap; } .nm-bang th:first-child, .nm-bang td:first-child { text-align: left; } .nm-note { font-size: 12px; line-height: 1.8; color: var(--muted); margin: 14px 0 0; }
</style>
