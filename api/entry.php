<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id'])) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid input"]);
  exit;
}

$telegram_id = $data['id'];
$username = $data['username'] ?? null;
$first_name = $data['first_name'] ?? null;
$last_name = $data['last_name'] ?? null;

require_once '../db.php';

$stmt = $pdo->prepare("INSERT INTO users (telegram_id, username, first_name, last_name)
  VALUES (?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE username = VALUES(username), first_name = VALUES(first_name), last_name = VALUES(last_name)");
$stmt->execute([$telegram_id, $username, $first_name, $last_name]);

$stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
$stmt->execute([$telegram_id]);
$user_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT last_claim_at FROM claim_cooldowns WHERE user_id = ?");
$stmt->execute([$user_id]);
$row = $stmt->fetch();

$next = time();
if ($row) {
  $last = strtotime($row['last_claim_at']);
  $next = $last + 6 * 60 * 60;
}

echo json_encode([
  "next_claim_at" => date('c', $next)
]);
