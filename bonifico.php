<?php
session_start();
require_once 'api/connessione.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: autenticazione.html');
    exit;
}

$idUtente = $_SESSION['id_utente'];
$errore = '';
$successo = '';

// Carica conti utente
$conti = [];
$stmt = $conn->prepare("SELECT id, iban FROM Conto WHERE user_id = ?");
$stmt->bind_param("i", $idUtente);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $conti[] = $r;
}
$contoSelezionato = $conti[0]['id'] ?? null;

// Invio bonifico
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contoOrigine = $_POST['conto_origine'];
    $ibanDest = $_POST['iban_destinatario'];
    $destinatario = $_POST['destinatario'];
    $importo = floatval($_POST['importo']);
    $causale = $_POST['causale'];
    $istantaneo = isset($_POST['istantaneo']) ? 1 : 0;
    if ($istantaneo) {
        $dataEsecuzione = date('Y-m-d');
    } else {
        $dataEsecuzione = $_POST['data'] ?? date('Y-m-d', strtotime('+1 day'));
    }

    // Verifica IBAN destinatario nel sistema
    $contoDestinatario = null;
    $stmtDest = $conn->prepare("SELECT id, user_id FROM Conto WHERE iban = ?");
    $stmtDest->bind_param("s", $ibanDest);
    $stmtDest->execute();
    $resDest = $stmtDest->get_result();
    if ($resDest->num_rows > 0) {
        $contoDestinatario = $resDest->fetch_assoc();
    }
    $stmtDest->close();

    if (!$contoDestinatario) {
        $errore = "IBAN destinatario non trovato nel sistema.";
    } elseif ($contoDestinatario['id'] == $contoOrigine) {
        $errore = "Non puoi effettuare un bonifico verso il tuo stesso conto.";
    } else {
        $saldo = 0;
        $stmtSaldo = $conn->prepare("
            SELECT COALESCE(SUM(CASE WHEN tm.segno = '+' THEN m.importo ELSE -m.importo END), 0) AS saldo
            FROM movimenti m
            JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
            WHERE m.id_conto = ?
        ");
        $stmtSaldo->bind_param("i", $contoOrigine);
        $stmtSaldo->execute();
        $stmtSaldo->bind_result($saldo);
        $stmtSaldo->fetch();
        $stmtSaldo->close();
        
        $dataOggi = date('Y-m-d');

        if (!$istantaneo && $dataEsecuzione < $dataOggi) {
            $errore = "La data di esecuzione non può essere precedente a oggi.";
        } elseif ($importo > $saldo) {
            $errore = "Saldo insufficiente per completare il bonifico.";
        } else {
            $conn->autocommit(false);
            
            try {
                $tipoBonificoUscita = 1;
                $tipoBonificoEntrata = 6; 
                $stmtMovUscita = $conn->prepare("
                    INSERT INTO movimenti (id_conto, data, tipo_movimento, importo)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtMovUscita->bind_param("isid", $contoOrigine, $dataEsecuzione, $tipoBonificoUscita, $importo);
                
                if (!$stmtMovUscita->execute()) {
                    throw new Exception("Errore nell'inserimento del movimento in uscita");
                }
                $idMovimentoUscita = $stmtMovUscita->insert_id;
                $stmtMovUscita->close();
                $stmtMovEntrata = $conn->prepare("
                    INSERT INTO movimenti (id_conto, data, tipo_movimento, importo)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtMovEntrata->bind_param("isid", $contoDestinatario['id'], $dataEsecuzione, $tipoBonificoEntrata, $importo);
                
                if (!$stmtMovEntrata->execute()) {
                    throw new Exception("Errore nell'inserimento del movimento in entrata");
                }
                $idMovimentoEntrata = $stmtMovEntrata->insert_id;
                $stmtMovEntrata->close();
                $stmtBonUscita = $conn->prepare("
                    INSERT INTO bonifici (id_movimento, iban_destinatario, destinatario, causale, istantaneo, data_esecuzione)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmtBonUscita->bind_param("isssss", $idMovimentoUscita, $ibanDest, $destinatario, $causale, $istantaneo, $dataEsecuzione);
                
                if (!$stmtBonUscita->execute()) {
                    throw new Exception("Errore nel salvataggio dei dettagli del bonifico in uscita");
                }
                $stmtBonUscita->close();
                $stmtMittente = $conn->prepare("
                    SELECT c.iban, u.nome, u.cognome 
                    FROM Conto c 
                    JOIN User u ON c.user_id = u.id 
                    WHERE c.id = ?
                ");
                $stmtMittente->bind_param("i", $contoOrigine);
                $stmtMittente->execute();
                $stmtMittente->bind_result($ibanMittente, $nomeMittente, $cognomeMittente);
                $stmtMittente->fetch();
                $stmtMittente->close();

                $stmtBonEntrata = $conn->prepare("
                    INSERT INTO bonifici (id_movimento, iban_destinatario, destinatario, causale, istantaneo, data_esecuzione)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $mittenteName = "Da: " . $nomeMittente . " " . $cognomeMittente;
                $stmtBonEntrata->bind_param("isssss", $idMovimentoEntrata, $ibanMittente, $mittenteName, $causale, $istantaneo, $dataEsecuzione);
                
                if (!$stmtBonEntrata->execute()) {
                    throw new Exception("Errore nel salvataggio dei dettagli del bonifico in entrata");
                }
                $stmtBonEntrata->close();
                $conn->commit();
                $successo = "Bonifico effettuato con successo! Il destinatario riceverà l'importo di €" . number_format($importo, 2, ',', '.') . ".";
                
            } catch (Exception $e) {
                $conn->rollback();
                $errore = "Errore durante l'elaborazione del bonifico: " . $e->getMessage();
            }
            $conn->autocommit(true);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
    <head>
    <meta charset="UTF-8">
    <title>Bonifico</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
     <script>
    function aggiornaSaldo() {
   const conto = document.getElementById("conto_origine").value;
   fetch("api/get_saldo.php?id_conto=" + conto)
       .then(res => res.json())
       .then(data => {
           const saldo = parseFloat(data.saldo).toFixed(2).replace('.', ',');
           document.getElementById("saldo").innerText = "€ " + saldo;
       });
}

function toggleData() {
   const istantaneo = document.getElementById("istantaneo").checked;
   const dataInput = document.getElementById("data");
   const today = new Date().toISOString().split("T")[0];
   if (istantaneo) {
       dataInput.value = today;
       dataInput.readOnly = true;
   } else {
       dataInput.readOnly = false;
       dataInput.value = "";
   }
}

document.addEventListener('DOMContentLoaded', function() {
   const dataInput = document.getElementById("data");
   if (dataInput) {
       const domani = new Date();
       domani.setDate(domani.getDate() + 1);
       dataInput.min = domani.toISOString().split("T")[0];
   }
});
    </script>
    </head>
    <body class="bg-light" onload="aggiornaSaldo()">
    <?php include 'navbar.php'; ?>
  <div class="container mt-5">
    <h1 class="mb-4">Bonifico</h1>

    <?php if ($errore): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errore) ?></div>
    <?php elseif ($successo): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successo) ?></div>
    <?php endif; ?>

    <form method="post" class="card p-4 shadow">
        <div class="mb-3">
            <label class="form-label">Conto di origine:</label>
            <select name="conto_origine" id="conto_origine" class="form-select" onchange="aggiornaSaldo()" required>
                <?php foreach ($conti as $conto): ?>
                    <option value="<?= $conto['id'] ?>"><?= htmlspecialchars($conto['iban']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p><strong>Saldo disponibile:</strong> <span id="saldo">€ ...</span></p>

        <div class="mb-3">
            <label class="form-label">Destinatario:</label>
            <input type="text" name="destinatario" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">IBAN destinatario:</label>
            <input type="text" name="iban_destinatario" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Importo (€):</label>
            <input type="number" name="importo" step="0.01" min="0.01" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Causale:</label>
            <input type="text" name="causale" class="form-control">
        </div>
       <div class="mb-3">
            <label for="data" class="form-label">Data esecuzione:</label>
            <input type="date" id="data" name="data" class="form-control" required>
        </div>
        <div class="form-check mb-4">
            <input type="checkbox" class="form-check-input" id="istantaneo" name="istantaneo" onchange="toggleData()">
            <label class="form-check-label" for="istantaneo">Bonifico istantaneo</label>
        </div>
        <div class="text-center">
            <button type="submit" class="btn btn-primary">Invia Bonifico</button>
        </div>
    </form>
</body>
</html>