<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

$pdo = getConnexion();

header('Content-Type: application/json');

$idService = (int)($_GET['service'] ?? 0);

if ($idService == 0) {
    echo json_encode(['success' => false, 'error' => 'Service not found']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM service WHERE idService = ?");
$stmt->execute([$idService]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    echo json_encode(['success' => false, 'error' => 'Service not found']);
    exit;
}

$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$qrData  = $baseUrl . '/Sprint1_AGL/detail_service.php?id=' . $idService;

// Use qrserver.com API — no composer dependency needed
$qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrData);

echo json_encode([
    'success' => true,
    'qr'      => $qrImageUrl
], JSON_UNESCAPED_SLASHES);
?>