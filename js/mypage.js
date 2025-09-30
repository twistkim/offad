// helpers (guarded: reuse from main.js if present)
if (typeof window.$ === 'undefined') {
  window.$ = (sel, p = document) => p.querySelector(sel);
}
if (typeof window.$$ === 'undefined') {
  window.$$ = (sel, p = document) => Array.from(p.querySelectorAll(sel));
}

let state = { q:'', limit:10, offset:0, total:0 };

async function fetchList() {
  const u = new URL('/api/list_my_blogs.php', location.origin);
  if (state.q) u.searchParams.set('q', state.q);
  u.searchParams.set('limit', state.limit);
  u.searchParams.set('offset', state.offset);

  const res = await fetch(u.toString(), { credentials:'same-origin' });
  if (!res.ok) throw new Error('목록 조회 실패');
  const j = await res.json();
  state.total  = j?.data?.total ?? 0;
  renderRows(j?.data?.items || []);
  renderPager();
}

function renderRows(items) {
  const tbody = $('#my-rows');
  if (!tbody) return;
  if (!items.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="p-4 text-center text-gray-500">데이터가 없습니다.</td></tr>`;
    return;
  }
  tbody.innerHTML = items.map(r => `
    <tr class="border-t">
      <td class="p-3">${r.id}</td>
      <td class="p-3">${escape(r.company_name)} / ${escape(r.contact_name)}</td>
      <td class="p-3">${escape(r.keywords)}</td>
      <td class="p-3">${escape(r.status)}</td>
      <td class="p-3">${escape(r.created_at)}</td>
      <td class="p-3">
        <button data-id="${r.id}" class="btn-view px-2 py-1 border rounded">보기</button>
      </td>
    </tr>
  `).join('');

  $$('.btn-view').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      try {
        const id = btn.dataset.id;
        const res = await fetch(`/api/get_blog.php?id=${id}`, { credentials:'same-origin' });
        const j = await res.json();
        openDialog(j?.data?.content || '');
      } catch(e) {
        openDialog('불러오기 실패');
      }
    });
  });
}

function renderPager() {
  const info = $('#pageinfo');
  const prev = $('#prev'), next = $('#next');
  const start = state.offset + 1;
  const end   = Math.min(state.offset + state.limit, state.total);
  info.textContent = `${state.total ? start : 0}–${end} / 총 ${state.total}건`;
  prev.disabled = state.offset <= 0;
  next.disabled = state.offset + state.limit >= state.total;

  prev.onclick = ()=>{ if (state.offset>0){ state.offset = Math.max(0, state.offset - state.limit); fetchList(); } };
  next.onclick = ()=>{ if (state.offset + state.limit < state.total){ state.offset += state.limit; fetchList(); } };
}

function openDialog(text) {
  $('#dlg-body').textContent = text || '(내용 없음)';
  $('#dlg').classList.remove('hidden');
  $('#dlg').classList.add('flex');
}
function closeDialog() {
  $('#dlg').classList.add('hidden');
  $('#dlg').classList.remove('flex');
}
function escape(s){ return (s??'').toString().replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;'); }

document.addEventListener('DOMContentLoaded', ()=>{
  $('#btn-search')?.addEventListener('click', ()=>{
    state.q = ($('#q')?.value || '').trim(); state.offset=0; fetchList();
  });
  $('#dlg-close')?.addEventListener('click', closeDialog);
  fetchList();
});