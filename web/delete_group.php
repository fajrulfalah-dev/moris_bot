<?php
session_start();
require "../config/Database.php";

// Cek role admin
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access!']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['group_id'])) {
    try {
        $groupId = $_POST['group_id'];
        
        // Hapus group
        $stmt = $pdo->prepare("DELETE FROM groups WHERE group_id = ?");
        $stmt->execute([$groupId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Group berhasil dihapus!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Group tidak ditemukan!']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus group: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request!']);
}
?>