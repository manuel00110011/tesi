<?php
session_start();
require_once '../api/connessione.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$idUtente = $_SESSION['id_utente'];

try {
    $stmt = $conn->prepare("SELECT foto FROM user WHERE id = ?");
    $stmt->bind_param("i", $idUtente);
    $stmt->execute();
    $stmt->bind_result($fotoPath);
    $stmt->fetch();
    $stmt->close();
    
    if ($fotoPath && file_exists('../' . $fotoPath)) {
        unlink('../' . $fotoPath);
    }
    
    $updateStmt = $conn->prepare("UPDATE user SET foto = NULL WHERE id = ?");
    $updateStmt->bind_param("i", $idUtente);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Foto rimossa con successo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nel database']);
    }
    $updateStmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
}
?>