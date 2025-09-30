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

// GET JSON 헬퍼
async function getJSON(url) {
  const res = await fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json'
    }
  });
  if (!res.ok) {
    const t = await res.text().catch(()=> '');
    throw new Error(`HTTP ${res.status} ${t?.slice(0,200) || ''}`);
  }
  return res.json();
}

// 행 잠금/해제 (중복 클릭 방지)
function lockRow(tr, locked = true) {
  if (!tr) return;
  const btns = tr.querySelectorAll('button, a');
  btns.forEach(b => {
    if (locked) {
      b.setAttribute('data-prev-disabled', b.disabled ? '1' : '0');
      b.disabled = true;
    } else {
      if (b.getAttribute('data-prev-disabled') === '0') b.disabled = false;
      b.removeAttribute('data-prev-disabled');
    }
  });
  tr.style.pointerEvents = locked ? 'none' : '';
  tr.style.opacity = locked ? '0.6' : '';
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
  lockRow(tr, true);
  try {
    setStatus(tr, '승인 중…');
    await postJSON('/api/change_media_status.php', { media_kit_id: id, action: 'approve' });
    setStatus(tr, '승인 완료');
  } catch (err) {
    setStatus(tr, err.message || '승인 실패', true);
  } finally {
    lockRow(tr, false);
  }
}

// 승인 + 분석 (큐에 넣고 폴링)
async function onApproveAnalyze(e) {
  const id = Number(e.currentTarget.dataset.id);
  const tr = e.currentTarget.closest('tr');
  if (!id || !tr) return;
  lockRow(tr, true);
  try {
    setStatus(tr, '승인 처리 중…');
    await postJSON('/api/change_media_status.php', { media_kit_id: id, action: 'approve' });

    setStatus(tr, '분석 큐 등록 중…');
    const q = await postJSON('/api/queue_process_pdf.php', { media_kit_id: id });
    const jobId = q?.data?.job_id || q?.job_id;
    if (!jobId) throw new Error('큐 등록 실패');

    setStatus(tr, '분석 대기/진행 중… (0%)');
    await pollJob(jobId, (st) => {
      const p = (typeof st.progress === 'number') ? st.progress : 0;
      setStatus(tr, `상태: ${st.status} / 진행률: ${p}%`);
    });

    setStatus(tr, '승인 및 분석 완료');
    tr.remove(); // 대기 목록에서 제거
  } catch (err) {
    setStatus(tr, err.message || '승인/분석 실패', true);
  } finally {
    lockRow(tr, false);
  }
}

// 반려
async function onReject(e) {
  const id = Number(e.currentTarget.dataset.id);
  const tr = e.currentTarget.closest('tr');
  if (!id || !tr) return;

  const reason = prompt('반려 사유를 입력하세요 (선택)');
  lockRow(tr, true);
  try {
    setStatus(tr, '반려 처리 중…');
    await postJSON('/api/change_media_status.php', { media_kit_id: id, action: 'reject', reason });
    setStatus(tr, '반려 완료');
    tr.remove();
  } catch (err) {
    setStatus(tr, err.message || '반려 실패', true);
  } finally {
    lockRow(tr, false);
  }
}

// 승인 완료 화면: 다시 분석
async function onReprocess(e) {
  const id = Number(e.currentTarget.dataset.id);
  const tr = e.currentTarget.closest('tr');
  if (!id || !tr) return;
  lockRow(tr, true);
  try {
    setStatus(tr, '다시 분석 중…');
    await postJSON('/api/process_pdf.php', { media_kit_id: id, reprocess: true });
    setStatus(tr, '재분석 완료');
  } catch (err) {
    setStatus(tr, err.message || '재분석 실패', true);
  } finally {
    lockRow(tr, false);
  }
}

// 작업 상태 폴링
async function pollJob(jobId, onTick) {
  for (;;) {
    const r = await getJSON(`/api/job_status.php?id=${encodeURIComponent(jobId)}&_=${Date.now()}`);
    const st = r?.data || r;
    if (typeof onTick === 'function') onTick(st);
    if (st.status === 'done') return;
    if (st.status === 'failed') throw new Error(st.error_message || '작업 실패');
    // 3초 간격
    await new Promise(res => setTimeout(res, 3000));
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