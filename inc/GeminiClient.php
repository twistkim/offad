<?php
// inc/GeminiClient.php
declare(strict_types=1);

class GeminiClient {
  private string $apiKey;
  private string $extractModel;
  private string $generateModel;

  public function __construct(string $apiKey, string $extractModel = GEMINI_MODEL_EXTRACT, string $generateModel = GEMINI_MODEL_GENERATE) {
    $this->apiKey = $apiKey;
    $this->extractModel = $extractModel;
    $this->generateModel = $generateModel;
  }

  /** 내부 cURL 호출 */
  private function call(string $model, array $payload): array {
    $url = sprintf(GEMINI_ENDPOINT, $model) . '?key=' . urlencode($this->apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 120,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($res === false || $code >= 400) {
      $msg = "[Gemini] HTTP {$code} {$err}";
      gemini_log($msg . ' | payload=' . substr(json_encode($payload, JSON_UNESCAPED_UNICODE), 0, 1000));
      throw new RuntimeException('Gemini API 호출 실패');
    }

    $data = json_decode($res, true);
    gemini_log('[Gemini OK] ' . substr($res, 0, 5000));
    return is_array($data) ? $data : [];
  }

  /** PDF에서 매체 목록 추출 */
  public function extractFromPdf(string $pdfPath): array {
    if (!is_file($pdfPath)) throw new InvalidArgumentException('PDF 파일을 찾을 수 없습니다.');
    $pdfB64 = base64_encode((string)file_get_contents($pdfPath));

    $prompt = <<<PROMPT
첨부된 광고 매체 소개서(PDF)를 분석해 아래 JSON 배열로만 응답하세요.
각 항목 스키마:
{
  "name": "매체명(필수)",
  "description": "간단 설명",
  "specifications": ["규격/게재형식/노출시간 등 리스트"],
  "target_audience": "타깃",
  "pricing": "가격/상품구성 요약"
}
한국어로, 코드블록 없이 JSON만.
PROMPT;

    $payload = [
      'contents' => [[
        'role'  => 'user',
        'parts' => [
          ['text' => $prompt],
          ['inline_data' => [
            'mime_type' => 'application/pdf',
            'data'      => $pdfB64
          ]]
        ]
      ]]
    ];

    $data = $this->call($this->extractModel, $payload);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // 모델이 코드블록을 붙이는 경우 대비
    $json = $this->extractJsonArray($text);
    return is_array($json) ? $json : [];
  }

  /** 블로그 원고 생성 */
  public function generateBlog(array $userInfo, array $mediaList, string $keywords, string $tone='formal', int $length=3000): string {
    $mediaJson = json_encode($mediaList, JSON_UNESCAPED_UNICODE);
    $prompt = <<<PROMPT
아래 정보를 바탕으로 약 {$length}자 분량의 한국어 블로그 홍보 원고를 작성하세요.
- 회사명: {$userInfo['company_name']} / 담당자: {$userInfo['contact_name']} / 연락처: {$userInfo['contact_phone']}
- 주요 키워드: {$keywords}
- 활용 매체(JSON): {$mediaJson}
요구사항:
1) 제목과 소제목 포함, 2) 전문적이고 신뢰감 있는 톤({$tone}), 3) 구체적 활용 예시,
4) 과장/허위 금지, 5) 마지막에 명확한 CTA 포함.
PROMPT;

    $payload = [
      'contents' => [[ 'role' => 'user', 'parts' => [ ['text' => $prompt] ] ]]
    ];

    $data = $this->call($this->generateModel, $payload);
    return (string)($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
  }

  /** 응답 텍스트에서 첫 JSON 배열만 추출 */
  private function extractJsonArray(string $text): array {
    if (preg_match('/\[[\s\S]*\]/u', $text, $m)) {
      $arr = json_decode($m[0], true);
      if (is_array($arr)) return $arr;
    }
    // 혹시 객체로 줄 경우
    if (preg_match('/\{[\s\S]*\}/u', $text, $m)) {
      $obj = json_decode($m[0], true);
      if (isset($obj[0])) return $obj; // 배열로 감싼 케이스
    }
    return [];
  }
}