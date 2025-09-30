/* ===== js/main.js : 공통 fetch 래퍼 & 유틸 ===== */

// 빠른 선택자
const $  = (sel, p = document) => p.querySelector(sel);
const $$ = (sel, p = document) => Array.from(p.querySelectorAll(sel));

/* ---------- CSRF ---------- */
function getCSRF() {
  const meta = document.querySelector('meta[name="csrf"]');
  return meta ? meta.content : '';
}

/* ---------- 토스트(알림) ---------- */
function ensureToastHost() {
  let host = $('#toast-host');
  if (!host) {
    host = document.createElement('div');
    host.id = 'toast-host';
    host.className = 'fixed top-4 right-4 z-[9999] space-y-2';
    document.body.appendChild(host);
  }
  return host;
}
function toast(message, type = 'info', timeout = 2500) {
  const host = ensureToastHost();
  const base = document.createElement('div');
  const colors = {
    info:    'bg-gray-800 text-white',
    ok:      'bg-emerald-600 text-white',
    warn:    'bg-amber-600 text-white',
    error:   'bg-red-600 text-white'
  };
  base.className = `px-4 py-2 rounded shadow ${colors[type] || colors.info}`;
  base.textContent = message || '';
  host.appendChild(base);
  setTimeout(() => base.remove(), timeout);
}

/* ---------- 로딩 오버레이 ---------- */
let _loadingCount = 0;
function ensureLoadingEl() {
  let el = $('#global-loading');
  if (!el) {
    el = document.createElement('div');
    el.id = 'global-loading';
    el.className = 'fixed inset-0 bg-black/20 flex items-center justify-center z-[9998] hidden';
    el.innerHTML = `
      <div class="bg-white rounded-xl shadow px-5 py-3 flex items-center gap-3">
        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <span class="text-sm text-gray-700">처리 중…</span>
      </div>`;
    document.body.appendChild(el);
  }
  return el;
}
function showLoading() {
  _loadingCount++;
  ensureLoadingEl().classList.remove('hidden');
}
function hideLoading() {
  _loadingCount = Math.max(0, _loadingCount - 1);
  if (_loadingCount === 0) ensureLoadingEl().classList.add('hidden');
}

/* ---------- 공통 오류 ---------- */
class HttpError extends Error {
  constructor(message, status, body) {
    super(message);
    this.name = 'HttpError';
    this.status = status;
    this.body = body;
  }
}

/* ---------- 응답 처리 ---------- */
async function parseResponse(res) {
  const ct = res.headers.get('Content-Type') || '';
  const isJson = ct.includes('application/json');
  const data = isJson ? await res.json().catch(() => ({})) : await res.text().catch(() => '');
  if (!res.ok) {
    const msg = (isJson && (data.message || data.error)) ? (data.message || data.error) : `HTTP ${res.status}`;
    throw new HttpError(msg, res.status, data);
  }
  return data;
}

/* ---------- 타임아웃 래퍼 ---------- */
function fetchWithTimeout(resource, options = {}) {
  const { timeout = 30000 } = options;
  const controller = new AbortController();
  const id = setTimeout(() => controller.abort(), timeout);
  const opts = { ...options, signal: controller.signal };
  return fetch(resource, opts).finally(() => clearTimeout(id));
}

/* ---------- 핵심: request() ---------- */
/**
 * api(url, { method, body, headers, json=true, form=false, showLoading, timeout })
 * - body가 객체이고 form=false면 JSON 변환
 * - body가 FormData이거나 form=true면 멀티파트로 전송
 * - CSRF 자동 첨부
 */
async function request(url, opts = {}) {
  const {
    method = 'GET',
    body   = undefined,
    headers = {},
    json = true,
    form = false,
    showLoading: useLoading = false,
    timeout = 30000,
    credentials = 'same-origin'
  } = opts;

  const finalHeaders = { 'Accept': 'application/json', ...headers };
  const csrf = getCSRF();
  if (csrf) finalHeaders['X-CSRF-Token'] = csrf;

  let payload = body;

  // FormData가 아니고 JSON 의도가 있으면 Content-Type 설정
  if (!form && body && !(body instanceof FormData)) {
    if (json) {
      finalHeaders['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    } else if (typeof body === 'string') {
      // raw string (예: querystring)
      // Content-Type은 호출자가 지정
    }
  }
  // FormData면 Content-Type을 명시하지 말 것(브라우저가 boundary 포함 설정)

  try {
    if (useLoading) showLoading();
    const res = await fetchWithTimeout(url, {
      method, body: payload, headers: finalHeaders, credentials, timeout
    });
    return await parseResponse(res);
  } finally {
    if (useLoading) hideLoading();
  }
}

/* ---------- 편의 함수 ---------- */
const getJSON  = (url, opts={}) => request(url, { ...opts, method: 'GET' });
const postJSON = (url, data, opts={}) => request(url, { ...opts, method: 'POST', body: data, json: true });
const postForm = (url, formData, opts={}) => request(url, { ...opts, method: 'POST', body: formData, form: true });

/* ---------- 기타 유틸 ---------- */
function debounce(fn, wait = 300) {
  let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), wait); };
}
function throttle(fn, wait = 300) {
  let last = 0; return (...args) => {
    const now = Date.now(); if (now - last >= wait) { last = now; fn.apply(null, args); }
  };
}
function escapeHtml(str='') {
  return String(str).replaceAll('&','&amp;').replaceAll('<','&lt;')
                    .replaceAll('>','&gt;').replaceAll('"','&quot;')
                    .replaceAll("'",'&#039;');
}

/* ---------- 전역 네임스페이스 ---------- */
window.api = {
  request,                 // 핵심 래퍼 (함수 이름 변경)
  getJSON, postJSON, postForm,
  toast, showLoading, hideLoading,
  debounce, throttle, escapeHtml,
  HttpError,
  // 호환용 별칭
  fetch: request,
  requestFn: request
};
window.apiFetch = request;
window.apiRequest = request;

/* ---------- 페이지 공용: a[data-confirm] ---------- */
document.addEventListener('click', (e) => {
  const a = e.target.closest('a[data-confirm]');
  if (a) {
    const msg = a.getAttribute('data-confirm') || '정말 진행하시겠습니까?';
    if (!confirm(msg)) e.preventDefault();
  }
});

/* ---------- 폼 자동 CSRF 히든 주입(선택적) ---------- */
document.addEventListener('DOMContentLoaded', () => {
  const token = getCSRF();
  if (!token) return;
  $$('form').forEach(f => {
    if (!f.querySelector('input[name="csrf"]')) {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = 'csrf'; i.value = token;
      f.appendChild(i);
    }
  });
});