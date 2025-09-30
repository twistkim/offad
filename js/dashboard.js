// 간단한 유틸
if (typeof window.$ === 'undefined') {
  window.$ = (sel, p = document) => p.querySelector(sel);
}
if (typeof window.$$ === 'undefined') {
  window.$$ = (sel, p = document) => Array.from(p.querySelectorAll(sel));
}

// prevent duplicate binds if this script is loaded twice
if (window.__dashboardUploadBound) {
  console.warn('[dashboard] duplicate script/binds prevented');
} else {
  window.__dashboardUploadBound = true;
}

function getCSRF() {
  const meta = document.querySelector('meta[name="csrf"]');
  return meta ? meta.content : '';
}

function toastStatus(msg, isError = false) {
  const el = $('#gen-status');
  if (!el) return;
  el.textContent = msg || '';
  el.className = isError ? 'text-sm text-red-600' : 'text-sm text-gray-600';
}

// single-flight flag for upload to avoid double requests
let _uploading = false;

// 승인된 매체 목록 로드 & 렌더
async function loadMediaList() {
  const search = ($('#search')?.value || '').trim();
  try {
    const url = new URL('/api/list_processed_media.php', window.location.origin);
    if (search) url.searchParams.set('q', search);
    const res = await fetch(url.toString(), { credentials: 'same-origin' });
    if (!res.ok) throw new Error('승인된 매체 목록을 불러오지 못했습니다.');
    const data = await res.json();
    const items = (data && data.data && Array.isArray(data.data.items)) ? data.data.items
                : (Array.isArray(data?.items) ? data.items : []);
    renderMediaList(items);
  } catch (e) {
    renderMediaList([]);
    toastStatus(e.message, true);
  }
}

function renderMediaList(items) {
  const wrap = $('#media-list');
  const count = $('#media-count');
  if (!wrap) return;
  wrap.innerHTML = '';

  if (!items.length) {
    wrap.innerHTML = `<div class="text-gray-500 col-span-full">표시할 매체가 없습니다.</div>`;
    if (count) count.textContent = '0';
    return;
  }

  const frag = document.createDocumentFragment();
  items.forEach((it) => {
    // 예상 구조: { id, name, description, target_audience, pricing, specifications_json }
    const card = document.createElement('div');
    card.className = 'border rounded-lg p-4 hover:shadow transition';

    const specs = safeParseJSON(it.specifications_json) || [];
    const specsHtml = specs.slice(0, 5).map(s => `<li class="list-disc ml-5 text-sm text-gray-700">${escapeHtml(String(s))}</li>`).join('');

    card.innerHTML = `
      <div class="flex items-start justify-between gap-3">
        <div>
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" class="mt-1 media-check" value="${it.id}">
            <div>
              <div class="font-semibold">${escapeHtml(it.name || '')}</div>
              <div class="text-sm text-gray-600 mt-1 line-clamp-2">${escapeHtml(it.description || '')}</div>
            </div>
          </label>
        </div>
      </div>
      <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="text-sm">
          <div class="text-gray-500 mb-1">타깃</div>
          <div class="text-gray-800">${escapeHtml(it.target_audience || '-')}</div>
        </div>
        <div class="text-sm">
          <div class="text-gray-500 mb-1">가격/구성</div>
          <div class="text-gray-800">${escapeHtml(it.pricing || '-')}</div>
        </div>
      </div>
      ${specsHtml ? `<ul class="mt-3">${specsHtml}</ul>` : ''}
    `;
    frag.appendChild(card);
  });

  wrap.appendChild(frag);
  if (count) count.textContent = String(items.length);
}

function safeParseJSON(s) {
  try { return JSON.parse(s); } catch { return null; }
}

function escapeHtml(str) {
  return str
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

// 글 생성
async function onGenerate() {
  const btn = $('#btn-generate');
  const form = $('#blog-form');
  if (!btn || !form) return;

  // 유효성 체크(프론트)
  const company = $('#company_name')?.value.trim();
  const contact = $('#contact_name')?.value.trim();
  const keywords = $('#keywords')?.value.trim();
  if (!company || !contact || !keywords) {
    toastStatus('회사명, 담당자, 키워드는 필수입니다.', true);
    return;
  }

  const checked = $$('.media-check:checked').map(chk => Number(chk.value)).filter(Boolean);
  if (!checked.length) {
    toastStatus('최소 1개 이상의 매체를 선택하세요.', true);
    return;
  }

  // 요청 본문 구성
  const payload = {
    company_name: company,
    contact_name: contact,
    contact_phone: $('#contact_phone')?.value.trim() || '',
    keywords,
    tone: $('#tone')?.value || 'formal',
    length_hint: Number($('#length_hint')?.value || 3000),
    media_ids: checked
  };

  btn.disabled = true;
  toastStatus('생성 중입니다… 잠시만 기다려주세요.');

  try {
    const csrf = getCSRF();
    const res = await fetch('/api/generate_blog.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(csrf ? { 'X-CSRF-Token': csrf } : {})
      },
      body: JSON.stringify(payload)
    });

    if (!res.ok) {
      const t = await res.text().catch(() => '');
      throw new Error(`생성 실패(HTTP ${res.status}) ${t?.slice(0, 200) || ''}`);
    }
    const resp = await res.json();
    const text = (resp && resp.data && typeof resp.data.content === 'string')
      ? resp.data.content
      : (typeof resp.content === 'string' ? resp.content : '');

    $('#result-raw').value = text;
    $('#result-prose').innerHTML = mdToProseHtml(text);
    updateCharCount(text);
    toastStatus('생성이 완료되었습니다.');
  } catch (e) {
    toastStatus(e.message || '생성 중 오류가 발생했습니다.', true);
  } finally {
    btn.disabled = false;
  }
}

// PDF 업로드 핸들러
async function onUpload() {
  if (_uploading) {
    console.warn('[upload] already in-flight');
    toastStatus('업로드 중입니다… 잠시만요.');
    return;
  }
  _uploading = true;

  const input = document.getElementById('pdf');
  const statusEl = document.getElementById('upload-status');
  if (!input || !input.files || input.files.length === 0) {
    toastStatus('업로드할 PDF를 선택하세요.', true);
    _uploading = false;
    return;
  }
  const file = input.files[0];
  const MAX = 20 * 1024 * 1024; // 20MB (config와 맞춤)
  if (file.type !== 'application/pdf') {
    toastStatus('PDF 파일만 업로드할 수 있습니다.', true);
    _uploading = false;
    return;
  }
  if (file.size > MAX) {
    toastStatus('파일 크기가 20MB를 초과했습니다.', true);
    _uploading = false;
    return;
  }

  const fd = new FormData();
  fd.append('pdf', file);
  const csrf = getCSRF();
  if (csrf) fd.append('csrf', csrf);

  try {
    toastStatus('업로드 중…');
    const doPostForm = (window.api && typeof window.api.postForm === 'function')
      ? (formData) => window.api.postForm('/api/upload_media.php', formData, { showLoading: true })
      : async (formData) => {
          const resp = await fetch('/api/upload_media.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          });
          if (!resp.ok) {
            const t = await resp.text().catch(()=> '');
            throw new Error(`업로드 실패(HTTP ${resp.status}) ${t?.slice(0,200) || ''}`);
          }
          return resp.json();
        };
    const res = await doPostForm(fd);
    toastStatus('업로드 완료! 관리자 승인 후 선택 가능해집니다.');
    input.value = '';
    loadMediaList();
    if (statusEl) {
      const d = res?.data || res;
      statusEl.textContent = `업로드 ID: ${d?.media_kit_id ?? '-'} / 파일: ${d?.original_filename ?? ''}`;
    }
  } catch (err) {
    toastStatus(err?.message || '업로드 실패', true);
  } finally {
    _uploading = false;
  }
}

// 간단한 마크다운 느낌 처리 (헤더/리스트/줄바꿈 정도)
function mdToProseHtml(src) {
  let html = escapeHtml(src);
  // 헤더 패턴 (###, ##, #)
  html = html.replace(/^### (.*)$/gm, '<h3>$1</h3>');
  html = html.replace(/^## (.*)$/gm, '<h2>$1</h2>');
  html = html.replace(/^# (.*)$/gm, '<h1>$1</h1>');
  // 리스트
  html = html.replace(/^\- (.*)$/gm, '<li>$1</li>');
  html = html.replace(/(<li>.*<\/li>\n?)+/gm, (m) => `<ul class="list-disc pl-6">${m}</ul>`);
  // 줄바꿈을 문단으로
  html = html.replace(/\n{2,}/g, '</p><p>');
  html = `<p>${html.replace(/\n/g, '<br>')}</p>`;
  return html;
}

function updateCharCount(text) {
  const n = (text || '').length;
  const el = $('#char-count');
  if (el) el.textContent = `${n}자`;
}

function onCopy() {
  const ta = $('#result-raw');
  if (!ta) return;
  ta.select();
  document.execCommand('copy');
  toastStatus('복사되었습니다.');
}

function onToggleProse() {
  const prose = $('#result-prose');
  const ta = $('#result-raw');
  if (!prose || !ta) return;
  // 단순 토글: textarea 숨기고/보이기
  if (ta.classList.contains('hidden')) {
    ta.classList.remove('hidden');
    prose.classList.add('hidden');
  } else {
    ta.classList.add('hidden');
    prose.classList.remove('hidden');
  }
}

// 이벤트 바인딩
document.addEventListener('DOMContentLoaded', () => {
  // 초기 로드
  loadMediaList();

  // 검색
  $('#search')?.addEventListener('input', debounce(loadMediaList, 300));
  $('#refresh-media')?.addEventListener('click', (e) => { e.preventDefault(); loadMediaList(); });

  // 생성
  $('#btn-generate')?.addEventListener('click', (e) => { e.preventDefault(); onGenerate(); });

  // 결과 UX
  $('#copy-text')?.addEventListener('click', onCopy);
  $('#toggle-prose')?.addEventListener('click', onToggleProse);

  // 원문 변경 시 글자 수 갱신
  $('#result-raw')?.addEventListener('input', (e) => updateCharCount(e.target.value));

  // 업로드 버튼/폼 바인딩
  $('#btn-upload')?.addEventListener('click', (e) => { e.preventDefault(); onUpload(); });
  $('#upload-form')?.addEventListener('submit', (e) => { e.preventDefault(); onUpload(); });
});

// 간단한 디바운스
function debounce(fn, wait = 300) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(null, args), wait);
  };
}