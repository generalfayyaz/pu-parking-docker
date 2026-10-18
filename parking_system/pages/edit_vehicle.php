<?php
// ============================================================
//  API: Edit / Update Vehicle
//  File: pages/edit_vehicle.php
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: fetch single vehicle for pre-filling edit form ──
if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Vehicle ID required']); exit; }

    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT v.vehicle_id, v.plate_number, v.type_id, v.brand, v.model, v.color,
                v.owner_id, o.full_name AS owner_name, o.phone AS owner_phone, o.email AS owner_email
         FROM vehicles v
         LEFT JOIN owners o ON v.owner_id = o.owner_id
         WHERE v.vehicle_id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'Vehicle not found']); exit; }
    echo json_encode(['success'=>true,'data'=>$row]);
    exit;
}

// ── POST: apply update ──
if ($method !== 'POST') { echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

$vehicleId = (int)($_POST['vehicle_id'] ?? 0);
$plate     = strtoupper(trim($_POST['plate_number'] ?? ''));
$typeId    = (int)($_POST['type_id'] ?? 0);
$brand     = trim($_POST['brand']  ?? '');
$model     = trim($_POST['model']  ?? '');
$color     = trim($_POST['color']  ?? '');
$ownerName = trim($_POST['owner_name']  ?? '');
$ownerPhone= trim($_POST['owner_phone'] ?? '');
$ownerEmail= trim($_POST['owner_email'] ?? '');

if (!$vehicleId || !$plate || !$typeId) {
    echo json_encode(['success'=>false,'message'=>'Vehicle ID, plate, and type are required']); exit;
}

try {
    $db = getDB();

    // Check plate uniqueness (exclude self)
    $chk = $db->prepare("SELECT vehicle_id FROM vehicles WHERE plate_number = ? AND vehicle_id != ?");
    $chk->execute([$plate, $vehicleId]);
    if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'Plate number already used by another vehicle']); exit; }

    $db->beginTransaction();

    // Fetch current owner_id
    $cur = $db->prepare("SELECT owner_id FROM vehicles WHERE vehicle_id = ?");
    $cur->execute([$vehicleId]);
    $curOwner = (int)($cur->fetchColumn() ?: 0);

    $ownerId = $curOwner;

    if ($ownerName) {
        if ($curOwner) {
            // Update existing owner
            $db->prepare("UPDATE owners SET full_name=?, phone=?, email=? WHERE owner_id=?")
               ->execute([$ownerName, $ownerPhone ?: null, $ownerEmail ?: null, $curOwner]);
        } else {
            // Create new owner
            $ins = $db->prepare("INSERT INTO owners (full_name, phone, email) VALUES (?,?,?)");
            $ins->execute([$ownerName, $ownerPhone ?: null, $ownerEmail ?: null]);
            $ownerId = (int)$db->lastInsertId();
        }
    }

    $db->prepare(
        "UPDATE vehicles SET plate_number=?, type_id=?, brand=?, model=?, color=?, owner_id=? WHERE vehicle_id=?"
    )->execute([$plate, $typeId, $brand ?: null, $model ?: null, $color ?: null, $ownerId ?: null, $vehicleId]);

    $db->commit();
    echo json_encode(['success'=>true,'message'=>'Vehicle updated successfully']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}