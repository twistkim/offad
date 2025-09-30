<?php
declare(strict_types=1);

/**
 * 승인된 매체 목록 조회 (로그인 필요)
 * Method: GET
 * Query Params:
 *  - q: 검색어(옵션) name/description/target_audience/pricing에서 LIKE
 *  - media_kit_id: 특정 PDF에 속한 매체만(옵션, int)
 *  - limit: 페이지 사이즈(기본 50, 최대 200)
 *  - offset: 시작 오프셋(기본 0)
 *
 * Response: {
 *   ok: true,
 *   data: {
 *     items: [{ id, media_kit_id, name, description, target_audience, pricing, specifications_json, created_at }, ...],
 *     total: 123,
 *     limit: 50,
 *     offset: 0
 *   }
 * }
 */

require_once __DIR__ . '/../inc/_bootstrap.php';

require_login();

// 입력 파라미터
$q            = trim((string)($_GET['q'] ?? ''));
$mediaKitId   = isset($_GET['media_kit_id']) ? (int)$_GET['media_kit_id'] : 0;
$limit        = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset       = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// 가드
$limit  = ($limit > 0 && $limit <= 200) ? $limit : 50;
$offset = max(0, $offset);

// 베이스 WHERE: 승인된 PDF만
$where = ["mk.status = 'approved'"];
$args  = [];

// media_kit_id 필터
if ($mediaKitId > 0) {
  $where[] = "pm.media_kit_id = :mkid";
  $args[':mkid'] = $mediaKitId;
}

// 검색어
if ($q !== '') {
  $where[] =
    "(pm.name LIKE :kw OR pm.description LIKE :kw OR pm.target_audience LIKE :kw OR pm.pricing LIKE :kw)";
  $args[':kw'] = '%' . $q . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// 총 개수
$sqlCount = "
  SELECT COUNT(*)
  FROM processed_media pm
  JOIN media_kits mk ON mk.id = pm.media_kit_id
  $whereSql
";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($args);
$total = (int)$stmt->fetchColumn();

// 목록
$sql = "
  SELECT
    pm.id, pm.media_kit_id, pm.name, pm.description,
    pm.target_audience, pm.pricing, pm.specifications_json, pm.created_at
  FROM processed_media pm
  JOIN media_kits mk ON mk.id = pm.media_kit_id
  $whereSql
  ORDER BY pm.created_at DESC, pm.id DESC
  LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);

// 바인딩 (숫자형은 정수 타입)
foreach ($args as $k => $v) {
  $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$rows = $stmt->fetchAll();

// 응답
json_response(true, [
  'items'  => $rows ?: [],
  'total'  => $total,
  'limit'  => $limit,
  'offset' => $offset,
], 200);