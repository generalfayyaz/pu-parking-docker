<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

$plate   = strtoupper(trim($_POST['plate_number'] ?? ''));
$typeId  = (int)($_POST['type_id'] ?? 0);
$brand   = trim($_POST['brand'] ?? '');
$model   = trim($_POST['model'] ?? '');
$color   = trim($_POST['color'] ?? '');
$ownerN  = trim($_POST['owner_name'] ?? '');
$ownerP  = trim($_POST['owner_phone'] ?? '');

if (!$plate || !$typeId) { echo json_encode(['success'=>false,'message'=>'Plate and type required']); exit; }

try {
    $db = getDB();
    // Check duplicate
    $chk = $db->prepare("SELECT vehicle_id FROM vehicles WHERE plate_number = ?");
    $chk->execute([$plate]);
    if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'Plate number already exists']); exit; }

    $ownerId = null;
    if ($ownerN) {
        $ins = $db->prepare("INSERT INTO owners (full_name, phone) VALUES (?,?)");
        $ins->execute([$ownerN, $ownerP ?: null]);
        $ownerId = $db->lastInsertId();
    }

    $stmt = $db->prepare("INSERT INTO vehicles (plate_number, type_id, brand, model, color, owner_id) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$plate, $typeId, $brand ?: null, $model ?: null, $color ?: null, $ownerId]);
    echo json_encode(['success'=>true,'message'=>'Vehicle added']);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
