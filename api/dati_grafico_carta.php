<?php
session_start();
require_once 'connessione.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['errore' => 'Non autenticato']);
    exit;
}

$numeroCarta = $_GET['numero_carta'] ?? null;
$dataInizio = $_GET['data_inizio'] ?? null;
$dataFine = $_GET['data_fine'] ?? null;
$idUtente = $_SESSION['id_utente'] ?? null;

if (!$numeroCarta || !$idUtente) {
    echo json_encode(['errore' => 'Parametri mancanti']);
    exit;
}

$stmt = $conn->prepare("
    SELECT c.id 
    FROM carta c 
    JOIN conto ct ON c.conto_id = ct.id 
    WHERE c.numero_carta = ? AND ct.user_id = ?
");
$stmt->bind_param("si", $numeroCarta, $idUtente);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['errore' => 'Carta non trovata o non autorizzata']);
    exit;
}

$cartaData = $result->fetch_assoc();
$idCarta = $cartaData['id'];

$query = "
    SELECT 
        cm.data,
        cm.importo
    FROM carte_movimenti cm
    WHERE cm.id_carta = ?
";

$types = "i";
$params = [$idCarta];

if ($dataInizio && $dataFine) {
    $query .= " AND cm.data BETWEEN ? AND ?";
    $types .= "ss";
    $params[] = $dataInizio;
    $params[] = $dataFine;
} elseif ($dataInizio) {
    $query .= " AND cm.data >= ?";
    $types .= "s";
    $params[] = $dataInizio;
} elseif ($dataFine) {
    $query .= " AND cm.data <= ?";
    $types .= "s";
    $params[] = $dataFine;
} else {
    $primoGiornoMese = date('Y-m-01');
    $ultimoGiornoMese = date('Y-m-t');
    $query .= " AND cm.data BETWEEN ? AND ?";
    $types .= "ss";
    $params[] = $primoGiornoMese;
    $params[] = $ultimoGiornoMese;
}

$query .= " ORDER BY cm.data ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['errore' => 'Errore nella query: ' . $conn->error]);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$dati = [];
while ($row = $result->fetch_assoc()) {
    $dati[] = [
        'data' => $row['data'],
        'importo' => $row['importo']
    ];
}

echo json_encode($dati);

$conn->close();
?>