<?php
$successoFoto = '';
$erroreFoto = '';

if (isset($_SESSION['successoFoto'])) {
    $successoFoto = $_SESSION['successoFoto'];
    unset($_SESSION['successoFoto']);
}

if (isset($_SESSION['erroreFoto'])) {
    $erroreFoto = $_SESSION['erroreFoto'];
    unset($_SESSION['erroreFoto']);
}

if (isset($_POST['carica_foto']) && isset($_FILES['nuova_foto'])) {
    $file = $_FILES['nuova_foto'];
    $idUtente = $_SESSION['id_utente'];
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (in_array($mimeType, $allowedTypes) && $file['size'] <= $maxSize) {
            $estensione = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nomeFile = 'user_' . $idUtente . '_' . time() . '.' . strtolower($estensione);
            $percorsoCompleto = 'uploads/foto_profilo/' . $nomeFile;
            $percorsoRelativo = 'uploads/foto_profilo/' . $nomeFile;
            
            if (!is_dir('uploads/foto_profilo/')) {
                mkdir('uploads/foto_profilo/', 0755, true);
            }
        
            $stmtOld = $conn->prepare("SELECT foto FROM user WHERE id = ?");
            $stmtOld->bind_param("i", $idUtente);
            $stmtOld->execute();
            $stmtOld->bind_result($fotoVecchia);
            $stmtOld->fetch();
            $stmtOld->close();
            
            if ($fotoVecchia && file_exists($fotoVecchia)) {
                unlink($fotoVecchia);
            }
            
            if (move_uploaded_file($file['tmp_name'], $percorsoCompleto)) {
                $stmt = $conn->prepare("UPDATE user SET foto = ? WHERE id = ?");
                $stmt->bind_param("si", $percorsoRelativo, $idUtente);
                
                if ($stmt->execute()) {
                    $_SESSION['successoFoto'] = "Foto profilo aggiornata!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $_SESSION['erroreFoto'] = "Errore nel salvataggio del percorso.";
                    unlink($percorsoCompleto);
                }
                $stmt->close();
            } else {
                $_SESSION['erroreFoto'] = "Errore nel caricamento del file.";
            }
        } else {
            $_SESSION['erroreFoto'] = "Formato non supportato o file troppo grande (max 5MB).";
        }
    } else {
        $_SESSION['erroreFoto'] = "Errore nell'upload del file.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>