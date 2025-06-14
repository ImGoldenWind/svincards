<?php

require_once 'db.php';

$bot_token = '8167949468:AAFltt7na_7SfputGkxwE4hh15nN6b69zZQ';
$cooldown_sec = 6 * 60 * 60;

function notify_user_ready($telegram_id, $first_name)
{
  global $bot_token;

  $text = "🔥 Привет, $first_name! Твоя карточка уже доступна для сбора в мини-игре!";
  $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

  $payload = [
    'chat_id' => $telegram_id,
    'text' => $text,
    'disable_notification' => false
  ];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  $result = curl_exec($ch);
  curl_close($ch);

  file_put_contents("notify_log.txt", date('c') . " → $telegram_id → $result" . PHP_EOL, FILE_APPEND);
}

$sql = "
  SELECT u.id, u.telegram_id, u.first_name, c.last_claim_at
  FROM users u
  JOIN claim_cooldowns c ON c.user_id = u.id
  LEFT JOIN notified_users n ON n.user_id = u.id
  WHERE UNIX_TIMESTAMP(c.last_claim_at) + ? <= UNIX_TIMESTAMP(NOW())
    AND n.user_id IS NULL
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$cooldown_sec]);
$users = $stmt->fetchAll();

foreach ($users as $user) {
  $telegram_id = $user['telegram_id'];
  $first_name = $user['first_name'] ?? '';
  $user_id = $user['id'];

  notify_user_ready($telegram_id, $first_name);

  $stmt = $pdo->prepare("INSERT INTO notified_users (user_id, notified_at) VALUES (?, NOW())");
  $stmt->execute([$user_id]);
}

$cleanup = "
  DELETE n FROM notified_users n
  JOIN claim_cooldowns c ON c.user_id = n.user_id
  WHERE UNIX_TIMESTAMP(c.last_claim_at) + ? > UNIX_TIMESTAMP(NOW())
";
$stmt = $pdo->prepare($cleanup);
$stmt->execute([$cooldown_sec]);

echo "Notified: " . count($users) . " users.\n";
