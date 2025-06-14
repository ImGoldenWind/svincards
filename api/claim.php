<?php
// claim.php — стабильная версия, совместимая с telegram_id
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$telegram_id = $data['telegram_id'] ?? null;

if (!$telegram_id) {
  http_response_code(400);
  echo json_encode(["error" => "Missing telegram_id"]);
  exit;
}

require_once 'db.php';

try {
  // находим user_id по telegram_id
  $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
  $stmt->execute([$telegram_id]);
  $user_id = $stmt->fetchColumn();

  if (!$user_id) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit;
  }

  // проверка кулдауна
  $stmt = $pdo->prepare("SELECT last_claim_at FROM claim_cooldowns WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $row = $stmt->fetch();

  if ($row && strtotime($row['last_claim_at']) + 6 * 60 * 60 > time()) {
    http_response_code(429);
    echo json_encode(["error" => "Cooldown active"]);
    exit;
  }

  // выбираем случайную карточку по drop_rate
  $cards = $pdo->query("SELECT * FROM cards")->fetchAll();
  $total = array_sum(array_column($cards, 'drop_rate'));
  $rand = mt_rand() / mt_getrandmax() * $total;

  $selected = null;
  $acc = 0;
  foreach ($cards as $card) {
    $acc += $card['drop_rate'];
    if ($rand <= $acc) {
      $selected = $card;
      break;
    }
  }

  if (!$selected) {
    http_response_code(500);
    echo json_encode(["error" => "No card selected"]);
    exit;
  }

  // сохраняем карточку
  $stmt = $pdo->prepare("INSERT INTO user_cards (user_id, card_id) VALUES (?, ?)");
  $stmt->execute([$user_id, $selected['id']]);

  // обновляем кулдаун
  $stmt = $pdo->prepare("INSERT INTO claim_cooldowns (user_id, last_claim_at)
    VALUES (?, NOW())
    ON DUPLICATE KEY UPDATE last_claim_at = NOW()");
  $stmt->execute([$user_id]);

  echo json_encode([
    "name" => $selected['name'],
    "rarity" => $selected['rarity']
  ]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "Server error", "message" => $e->getMessage()]);
}
