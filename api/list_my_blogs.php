<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/_bootstrap.php';
require_login();

$q      = trim((string)($_GET['q'] ?? ''));
$limit  = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$uid    = (int)current_user()['id'];

$where = ["br.user_id = :uid"];
$args  = [':uid' => $uid];

if ($q !== '') {
  $where[] = "(br.company_name LIKE :kw OR br.contact_name LIKE :kw OR br.keywords LIKE :kw)";
  $args[':kw'] = '%'.$q.'%';
}

$whereSql = 'WHERE '.implode(' AND ', $where);

// total
$sqlCnt = "SELECT COUNT(*) FROM blog_requests br $whereSql";
$st = $pdo->prepare($sqlCnt);
$st->execute($args);
$total = (int)$st->fetchColumn();

// rows (가장 최신 먼저) + output 일부 미리보기
$sql = "
  SELECT br.id, br.company_name, br.contact_name, br.keywords,
         br.status, br.created_at,
         SUBSTRING(bo.content,1,200) AS preview
  FROM blog_requests br
  LEFT JOIN blog_outputs bo ON bo.blog_request_id = br.id
  $whereSql
  ORDER BY br.created_at DESC, br.id DESC
  LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sql);
foreach ($args as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
$st->bindValue(':limit', $limit, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

json_response(true, [
  'items'  => $rows ?: [],
  'total'  => $total,
  'limit'  => $limit,
  'offset' => $offset,
], 200);