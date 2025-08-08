<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, GET");

$db = new PDO("sqlite:messages.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// GET all messages from SQLite
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $stmt = $db->query("SELECT * FROM messages ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// POST new message to SQLite + Neon API
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!isset($input["content"])) {
        http_response_code(400);
        echo json_encode(["error" => "Content is required"]);
        exit;
    }

    $content = $input["content"];

    // 1. Save to SQLite (local)
    $stmt = $db->prepare("INSERT INTO messages (content) VALUES (:content)");
    $stmt->execute(["content" => $content]);

    $localResponse = [
        "id" => $db->lastInsertId(),
        "content" => $content,
        "created_at" => date("Y-m-d H:i:s")
    ];

    // 2. Send to Neon API (online)
    $neon_api_url = "https://academic-project-2-met4.onrender.com/messages";
    $neon_payload = json_encode(["content" => $content]);

    $ch = curl_init($neon_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Content-Length: " . strlen($neon_payload)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $neon_payload);
    $neon_response = curl_exec($ch);
    $neon_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($neon_http_code !== 201) {
        // If Neon API fails, return partial success
        echo json_encode([
            "warning" => "Saved locally, but failed to sync to Neon API.",
            "local" => $localResponse,
            "neon_response" => $neon_response
        ]);
        exit;
    }

    echo json_encode([
        "message" => "Saved locally and synced to Neon!",
        "local" => $localResponse,
        "neon" => json_decode($neon_response, true)
    ]);
    exit;
}
?>
