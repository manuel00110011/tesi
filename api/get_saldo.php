

<?php
require_once 'connessione.php';

header('Content-Type: application/json');

if (!isset($_GET['id_conto'])) {
    echo json_encode(['success' => false, 'message' => 'ID conto non fornito']);
    exit;
}

$idConto = intval($_GET['id_conto']);

$query = "
    SELECT tm.segno, m.importo 
    FROM movimenti m 
    JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id 
    WHERE m.id_conto = ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query']);
    exit;
}

$stmt->bind_param("i", $idConto);
$stmt->execute();
$result = $stmt->get_result();

$saldo = 0;
while ($row = $result->fetch_assoc()) {
    $segno = $row['segno'] === '+' ? 1 : -1;
    $saldo += $segno * $row['importo'];
}

echo json_encode(['success' => true, 'saldo' => number_format($saldo, 2, '.', '')]);
?>