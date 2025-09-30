// 공통 헬퍼
const $$ = (sel, p = document) => Array.from(p.querySelectorAll(sel));
const $ = (sel, p = document) => p.querySelector(sel);
const csrfToken = () => (document.querySelector('meta[name="csrf"]')?.content || '');

async function postJSON(url, bodyObj) {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(csrfToken() ? { 'X-CSRF-Token': csrfToken() } : {})
    },
    body: JSON.stringify(bodyObj || {})
  });
  if (!res.ok) {
    const t = await res.text().catch(()=>'');
    throw new Error(`HTTP ${res.status} ${t?.slice(0,200) || ''}`);
  }
  return res.json();
}

// 상태라인 출력
function setStatus(tr, msg, error = false) {
  const s = tr?.querySelector('.status-line');
  if (!s) return;
  s.textContent = msg || '';
  s.className = 'status-line text-xs ' + (error ? 'text-red-600' : 'text-gray-500');
}

// 승인만
async function onApprove(e) {
  const id = Number(e.currentTarget.dataset.id);
  const tr = e.currentTarget.closest('tr');
  if (!id || !tr) return;
  try {
    setStatus(tr, '승인 중…');
    const r = await postJSON('/api/change_media_status.php', { media_kit_id: id, action: 'approve' });
    setStatus(tr, '승인 완료');
  } catch (err) {
    setStatus(tr, err.message || '승인 실패', true);
  }
}

// 승인 + 분석
async function onApproveAnalyze(e) {
  const id = Number(e.currentTarget.dataset.id);
  const tr = e.currentTarget.closest('tr');
  if (!id || !tr) return;
  try {
    setStatus(tr, '승인 처리 중…');
    await postJSON('/api/change_media_status.php', { media_kit_id: id, action: 'approve' });
    setStatus(tr, '분석 중…');
    await postJSON('/api/process_pdf.php', { media_kit_id: id });
    setStatus(tr, '승인 및 분석 완료');
    // 완료 시 행 제거(대기 목록에서)
    tr.remove();
  } catch (err) {
    setStatus(tr, err.message || '승인/분석 실패', true);
  }
}

// 반려
async function onReject(e) {
  const id = Number(e.currentTarget.dataset.id);
  const tr = e.currentTarget.closest('tr');
  if (!id || !tr) return;

  const reason = prompt('반려 사유를 입력하세요 (선택)');
  try {
    setStatus(tr, '반려 처리 중…');
    await postJSON('/api/change_media_status.php', { media_kit_id: id, action: 'reject', reason });
    setStatus(tr, '반려 완료');
    tr.remove();
  } catch (err) {
    setStatus(tr, err.message || '반려 실패', true);
  }
}

// 승인 완료 화면: 다시 분석
async function onReprocess(e) {
  const id = Number(e.currentTarget.dataset.id);
  const tr = e.currentTarget.closest('tr');
  if (!id || !tr) return;
  try {
    setStatus(tr, '다시 분석 중…');
    await postJSON('/api/process_pdf.php', { media_kit_id: id, reprocess: true });
    setStatus(tr, '재분석 완료');
  } catch (err) {
    setStatus(tr, err.message || '재분석 실패', true);
  }
}

function bindAdminEvents() {
  $$('#pending-tbody .btn-approve').forEach(btn => btn.addEventListener('click', onApprove));
  $$('#pending-tbody .btn-approve-analyze').forEach(btn => btn.addEventListener('click', onApproveAnalyze));
  $$('#pending-tbody .btn-reject').forEach(btn => btn.addEventListener('click', onReject));

  $$('#approved-tbody .btn-reprocess').forEach(btn => btn.addEventListener('click', onReprocess));

  $('#btn-refresh')?.addEventListener('click', () => window.location.reload());
}

document.addEventListener('DOMContentLoaded', bindAdminEvents);