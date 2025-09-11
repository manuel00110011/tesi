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

$conti = [];
$stmt = $conn->prepare("SELECT id, iban FROM conto WHERE user_id = ?");
$stmt->bind_param("i", $idUtente);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $conti[] = $r;
}
$stmt->close();
// API Yahoo Finance 
function getPrezzoAzioneYahoo($simbolo) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$simbolo}";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $maxTentativi = 3;
    $response = false;
    
    for ($i = 0; $i < $maxTentativi; $i++) {
        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            break;
        }
        
        if ($i < $maxTentativi - 1) {
            error_log("Tentativo " . ($i + 1) . " fallito per simbolo {$simbolo}, riprovo...");
            sleep(1);
        }
    }
    
    if ($response === false) {
        error_log("Errore: Impossibile ottenere dati per il simbolo {$simbolo} dopo {$maxTentativi} tentativi");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Errore JSON per simbolo {$simbolo}: " . json_last_error_msg());
        return null;
    }
    
    if (isset($data['chart']['result'][0]['meta'])) {
        $meta = $data['chart']['result'][0]['meta'];
        $prezzoAttuale = $meta['regularMarketPrice'] ?? $meta['previousClose'] ?? null;
        $prezzoChiusura = $meta['previousClose'] ?? null;
        
        if ($prezzoAttuale === null || $prezzoChiusura === null) {
            error_log("Dati di prezzo mancanti per simbolo {$simbolo}");
            return null;
        }
        
        return [
            'prezzo' => $prezzoAttuale,
            'cambio' => $prezzoAttuale - $prezzoChiusura,
            'cambio_percent' => $prezzoChiusura > 0 ? (($prezzoAttuale - $prezzoChiusura) / $prezzoChiusura) * 100 : 0
        ];
    }
    
    error_log("Struttura dati inaspettata per simbolo {$simbolo}");
    return null;
}

function getDatiStorici($simbolo, $giorni = 30) {
    $dataFine = time();
    $dataInizio = $dataFine - ($giorni * 24 * 60 * 60);
    
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$simbolo}?period1={$dataInizio}&period2={$dataFine}&interval=1d";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $maxTentativi = 3;
    $response = false;
    
    for ($i = 0; $i < $maxTentativi; $i++) {
        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            break;
        }
        
        if ($i < $maxTentativi - 1) {
            error_log("Tentativo " . ($i + 1) . " fallito per dati storici {$simbolo}, riprovo...");
            sleep(2);
        }
    }
    
    if ($response === false) {
        error_log("Errore: Impossibile ottenere dati storici per {$simbolo} dopo {$maxTentativi} tentativi");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Errore JSON per dati storici {$simbolo}: " . json_last_error_msg());
        return null;
    }
    
    if (!isset($data['chart']['result'][0]['timestamp']) || !isset($data['chart']['result'][0]['indicators']['quote'][0]['close'])) {
        error_log("Dati storici mancanti o malformati per simbolo {$simbolo}");
        return null;
    }
    
    $timestamps = $data['chart']['result'][0]['timestamp'];
    $prezzi = $data['chart']['result'][0]['indicators']['quote'][0]['close'];
    
    if (count($timestamps) !== count($prezzi)) {
        error_log("Mismatch tra timestamps e prezzi per simbolo {$simbolo}");
        return null;
    }
    
    $datiStorici = [];
    for ($i = 0; $i < count($timestamps); $i++) {
        if ($prezzi[$i] !== null && is_numeric($prezzi[$i])) {
            $datiStorici[] = [
                'data' => date('Y-m-d', $timestamps[$i]),
                'prezzo' => (float)$prezzi[$i]
            ];
        }
    }
    
    if (empty($datiStorici)) {
        error_log("Nessun dato storico valido trovato per simbolo {$simbolo}");
        return null;
    }
    
    return $datiStorici;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'nuovo_investimento') {
    $contoOrigine = intval($_POST['conto_origine']);
    $tipoInvestimento = $_POST['tipo_investimento'];
    $simbolo = strtoupper(trim($_POST['simbolo']));
    $quantita = floatval($_POST['quantita']);
    $prezzoAcquisto = floatval($_POST['prezzo_acquisto']);
    $importoTotale = $quantita * $prezzoAcquisto;
    

    if ($quantita <= 0 || $prezzoAcquisto <= 0) {
        $errore = "Quantità e prezzo devono essere maggiori di zero.";
    } elseif (empty($simbolo)) {
        $errore = "Il simbolo è obbligatorio.";
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
        
        if ($importoTotale > $saldo) {
            $errore = "Saldo insufficiente per l'investimento. Saldo disponibile: €" . number_format($saldo, 2, ',', '.');
        } else {
            $conn->begin_transaction();
            try {
                $tipoMovimento = 2; 
                $stmtMov = $conn->prepare("
                    INSERT INTO movimenti (id_conto, data, tipo_movimento, importo)
                    VALUES (?, NOW(), ?, ?)
                ");
                $stmtMov->bind_param("iid", $contoOrigine, $tipoMovimento, $importoTotale);
                
                if (!$stmtMov->execute()) {
                    throw new Exception("Errore nel movimento finanziario.");
                }
                
                $idMovimento = $stmtMov->insert_id;
                $stmtMov->close();
                
                $stmtInv = $conn->prepare("
                    INSERT INTO investimenti (id_movimento, tipo_investimento, simbolo, quantita, prezzo_acquisto, data_acquisto)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmtInv->bind_param("issdd", $idMovimento, $tipoInvestimento, $simbolo, $quantita, $prezzoAcquisto);
                    if (!$stmtInv->execute()) {
                    throw new Exception("Errore nel salvataggio dell'investimento.");
                }
                
                $stmtInv->close();
                $conn->commit();
                $successo = "Investimento registrato con successo! Importo: €" . number_format($importoTotale, 2, ',', '.');
                
            } catch (Exception $e) {
                $conn->rollback();
                $errore = $e->getMessage();
            }
        }
    }
}

$investimenti = [];
$datiGrafici = [];
$stmt = $conn->prepare("
    SELECT i.*, m.data as data_movimento, c.iban
    FROM investimenti i
    JOIN movimenti m ON i.id_movimento = m.id
    JOIN conto c ON m.id_conto = c.id
    WHERE c.user_id = ? AND i.stato = 'attivo'
    ORDER BY i.data_acquisto DESC
");
$stmt->bind_param("i", $idUtente);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $datiMercato = getPrezzoAzioneYahoo($r['simbolo']);
    $r['prezzo_attuale'] = $datiMercato['prezzo'] ?? $r['prezzo_acquisto'];
    $r['cambio'] = $datiMercato['cambio'] ?? 0;
    $r['cambio_percent'] = $datiMercato['cambio_percent'] ?? 0;
    $r['valore_attuale'] = $r['prezzo_attuale'] * $r['quantita'];
    $r['guadagno_perdita'] = $r['valore_attuale'] - ($r['prezzo_acquisto'] * $r['quantita']);
    $r['rendimento_percent'] = $r['prezzo_acquisto'] > 0 ? (($r['prezzo_attuale'] - $r['prezzo_acquisto']) / $r['prezzo_acquisto']) * 100 : 0;
    $datiStoriciAzione = getDatiStorici($r['simbolo'], 30);
    if ($datiStoriciAzione) {
        $datiGrafici[$r['simbolo']] = $datiStoriciAzione;
    }
    
    $investimenti[] = $r;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investimenti - La Mia Banca</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .gain { color: #24a542ff; }
        .loss { color: #db3041ff; }
        .neutral { color: #6e757cff; }
        .investimento-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        .investimento-card.gain { border-left-color: #28a745ff; }
        .investimento-card.loss { border-left-color: #da3041ff; }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .portfolio-chart {
            height: 400px;
        }
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container">
    <h1 class="mb-4"> Portfolio Investimenti</h1>

    <?php if ($errore): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errore) ?></div>
    <?php elseif ($successo): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successo) ?></div>
    <?php endif; ?>

    <!-- Riepilogo Portfolio -->
    <?php if (!empty($investimenti)): ?>
        <?php
        $valorePortfolio = array_sum(array_column($investimenti, 'valore_attuale'));
        $investimentoTotale = array_sum(array_map(function($inv) {
            return $inv['prezzo_acquisto'] * $inv['quantita'];
        }, $investimenti));
        $guadagnoTotale = $valorePortfolio - $investimentoTotale;
        $rendimentoTotale = $investimentoTotale > 0 ? ($guadagnoTotale / $investimentoTotale) * 100 : 0;
        ?>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"> Valore Portfolio</h5>
                        <h3 class="text-primary">€ <?= number_format($valorePortfolio, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"> Investito</h5>
                        <h3>€ <?= number_format($investimentoTotale, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"> Guadagno/Perdita</h5>
                        <h3 class="<?= $guadagnoTotale >= 0 ? 'gain' : 'loss' ?>">
                            <?= $guadagnoTotale >= 0 ? '+' : '' ?>€ <?= number_format($guadagnoTotale, 2, ',', '.') ?>
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"> Rendimento %</h5>
                        <h3 class="<?= $rendimentoTotale >= 0 ? 'gain' : 'loss' ?>">
                            <?= $rendimentoTotale >= 0 ? '+' : '' ?><?= number_format($rendimentoTotale, 2) ?>%
                        </h3>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"> Andamento Portfolio (Ultimi 30 giorni)</h5>
            </div>
            <div class="card-body">
                <div class="portfolio-chart">
                    <canvas id="portfolioChart"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"> Nuovo Investimento</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="azione" value="nuovo_investimento">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Conto di origine:</label>
                        <select name="conto_origine" class="form-select" required>
                            <option value="">Seleziona un conto</option>
                            <?php foreach ($conti as $conto): ?>
                                <option value="<?= $conto['id'] ?>"><?= htmlspecialchars($conto['iban']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo investimento:</label>
                        <select name="tipo_investimento" class="form-select" required>
                            <option value="">Seleziona tipo</option>
                            <option value="Azione">Azione</option>
                            <option value="ETF">ETF</option>
                            <option value="Obbligazione">Obbligazione</option>
                            <option value="Criptovaluta">Criptovaluta</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="form-label">Simbolo (es: AAPL, TSLA, BTC-USD):</label>
                        <input type="text" name="simbolo" class="form-control" placeholder="AAPL" required>
                        <small class="form-text text-muted">Usa simboli Yahoo Finance</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quantità:</label>
                        <input type="number" name="quantita" step="0.00000001" min="0.00000001" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Prezzo di acquisto (€):</label>
                        <input type="number" name="prezzo_acquisto" step="0.0001" min="0.0001" class="form-control" required>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">Aggiungi Investimento</button>
                </div>
            </form>
        </div>
    </div>

    <h3>I Tuoi Investimenti</h3>
    <?php if (empty($investimenti)): ?>
        <div class="alert alert-info">
            <h5>Nessun investimento trovato</h5>
            <p>Non hai ancora investimenti nel tuo portfolio. Inizia creando il tuo primo investimento usando il form sopra!</p>
        </div>
    <?php else: ?>
        <?php foreach ($investimenti as $inv): ?>
            <div class="card investimento-card <?= $inv['guadagno_perdita'] >= 0 ? 'gain' : 'loss' ?>">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <h5 class="mb-0"><?= htmlspecialchars($inv['simbolo']) ?></h5>
                            <small class="text-muted"><?= htmlspecialchars($inv['tipo_investimento']) ?></small>
                        </div>
                        <div class="col-md-2">
                            <strong>Quantità:</strong><br>
                            <?= number_format($inv['quantita'], 8) ?>
                        </div>
                        <div class="col-md-2">
                            <strong>Prezzo acquisto:</strong><br>
                            € <?= number_format($inv['prezzo_acquisto'], 4, ',', '.') ?>
                        </div>
                        <div class="col-md-2">
                            <strong>Prezzo attuale:</strong><br>
                            € <?= number_format($inv['prezzo_attuale'], 4, ',', '.') ?>
                            <br>
                            <small class="<?= $inv['cambio'] >= 0 ? 'gain' : 'loss' ?>">
                                (<?= $inv['cambio'] >= 0 ? '+' : '' ?><?= number_format($inv['cambio_percent'], 2) ?>%)
                            </small>
                        </div>
                        <div class="col-md-2">
                            <strong>Valore attuale:</strong><br>
                            € <?= number_format($inv['valore_attuale'], 2, ',', '.') ?>
                        </div>
                        <div class="col-md-2">
                            <strong>Guadagno/Perdita:</strong><br>
                            <span class="<?= $inv['guadagno_perdita'] >= 0 ? 'gain' : 'loss' ?>">
                                <?= $inv['guadagno_perdita'] >= 0 ? '+' : '' ?>€ <?= number_format($inv['guadagno_perdita'], 2, ',', '.') ?>
                                <br>
                                <small>(<?= $inv['rendimento_percent'] >= 0 ? '+' : '' ?><?= number_format($inv['rendimento_percent'], 2) ?>%)</small>
                            </span>
                        </div>
                    </div>
                    <small class="text-muted">Acquistato il: <?= date('d/m/Y H:i', strtotime($inv['data_acquisto'])) ?></small>
                    
                    <!-- Grafico singolo investimento -->
                    <?php if (isset($datiGrafici[$inv['simbolo']])): ?>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#grafico<?= $inv['id'] ?>" aria-expanded="false">
                                Mostra Andamento
                            </button>
                            <div class="collapse mt-2" id="grafico<?= $inv['id'] ?>">
                                <div class="chart-container">
                                    <canvas id="chart<?= $inv['id'] ?>"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script>
const datiGrafici = <?= json_encode($datiGrafici) ?>;
const investimenti = <?= json_encode($investimenti) ?>;

<?php if (!empty($investimenti)): ?>
function creaGraficoPortfolio() {
    try {
        const dateUniche = new Set();
        Object.values(datiGrafici).forEach(dati => {
            if (Array.isArray(dati)) {
                dati.forEach(punto => {
                    if (punto && punto.data) {
                        dateUniche.add(punto.data);
                    }
                });
            }
        });
        
        if (dateUniche.size === 0) {
            console.warn('Nessun dato storico disponibile per il portfolio');
            document.getElementById('portfolioChart').style.display = 'none';
            return;
        }
        
        const dateArray = Array.from(dateUniche).sort();
        const valoriPortfolio = [];
        const labelsGrafico = [];
        
        dateArray.forEach(data => {
            let valoreGiorno = 0;
            let hasValidData = false;
            
            investimenti.forEach(inv => {
                const datiAzione = datiGrafici[inv.simbolo];
                if (datiAzione && Array.isArray(datiAzione)) {
                    let prezzoGiorno = parseFloat(inv.prezzo_acquisto);
                    
                    for (let i = datiAzione.length - 1; i >= 0; i--) {
                        if (datiAzione[i] && datiAzione[i].data <= data && datiAzione[i].prezzo !== null) {
                            prezzoGiorno = parseFloat(datiAzione[i].prezzo);
                            hasValidData = true;
                            break;
                        }
                    }
                    
                    valoreGiorno += prezzoGiorno * parseFloat(inv.quantita);
                }
            });
            
            if (hasValidData && valoreGiorno > 0) {
                valoriPortfolio.push(valoreGiorno);
                labelsGrafico.push(new Date(data).toLocaleDateString('it-IT'));
            }
        });
        
        if (valoriPortfolio.length === 0) {
            console.warn('Nessun valore valido calcolato per il portfolio');
            document.getElementById('portfolioChart').style.display = 'none';
            return;
        }
        
        const ctx = document.getElementById('portfolioChart').getContext('2d');
        
        // Calcola variazione per determinare il colore
        const primoValore = valoriPortfolio[0];
        const ultimoValore = valoriPortfolio[valoriPortfolio.length - 1];
        const variazione = ultimoValore - primoValore;
        const coloreLinea = variazione >= 0 ? '#28a745ff' : '#df3041ff';
        const coloreSfondo = variazione >= 0 ? 'rgba(38, 160, 66, 0.1)' : 'rgba(220, 53, 69, 0.1)';
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labelsGrafico,
                datasets: [{
                    label: 'Valore Portfolio (€)',
                    data: valoriPortfolio,
                    borderColor: coloreLinea,
                    backgroundColor: coloreSfondo,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.2,
                    pointBackgroundColor: coloreLinea,
                    pointBorderColor: '#fffcfcff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '€' + value.toLocaleString('it-IT', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                const valore = context.parsed.y;
                                const indice = context.dataIndex;
                                let variazioneTesto = '';
                                
                                if (indice > 0) {
                                    const precedente = context.dataset.data[indice - 1];
                                    const variazione = valore - precedente;
                                    const percentuale = ((variazione / precedente) * 100);
                                    const simbolo = variazione >= 0 ? '+' : '';
                                    variazioneTesto = ` (${simbolo}€${variazione.toFixed(2)} / ${simbolo}${percentuale.toFixed(2)}%)`;
                                }
                                
                                return `Valore: €${valore.toLocaleString('it-IT', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                })}${variazioneTesto}`;
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
        
        console.log(`Grafico portfolio creato con ${valoriPortfolio.length} punti dati`);
        
    } catch (error) {
        console.error('Errore nella creazione del grafico portfolio:', error);
        document.getElementById('portfolioChart').style.display = 'none';
    }
}

function creaGraficiIndividuali() {
    investimenti.forEach(inv => {
        try {
            const datiAzione = datiGrafici[inv.simbolo];
            if (!datiAzione || !Array.isArray(datiAzione) || datiAzione.length === 0) {
                console.warn(`Nessun dato storico per ${inv.simbolo}`);
                return;
            }
            
            const ctx = document.getElementById('chart' + inv.id);
            if (!ctx) {
                console.warn(`Canvas non trovato per investimento ${inv.id}`);
                return;
            }
            const datiValidi = datiAzione.filter(d => d && d.prezzo !== null && !isNaN(parseFloat(d.prezzo)));
            
            if (datiValidi.length === 0) {
                console.warn(`Nessun dato valido per ${inv.simbolo}`);
                return;
            }
            
            const prezzi = datiValidi.map(d => parseFloat(d.prezzo));
            const date = datiValidi.map(d => new Date(d.data).toLocaleDateString('it-IT'));
            const prezzoAcquisto = parseFloat(inv.prezzo_acquisto);
            
            const prezzoAttuale = prezzi[prezzi.length - 1];
            const guadagnoPerdita = prezzoAttuale - prezzoAcquisto;
            const colore = guadagnoPerdita >= 0 ? '#28a745' : '#dc3545';
            const coloreSfondo = guadagnoPerdita >= 0 ? 'rgba(40, 167, 69, 0.1)' : 'rgba(220, 53, 69, 0.1)';
            
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: date,
                    datasets: [{
                        label: `${inv.simbolo} - Prezzo (€)`,
                        data: prezzi,
                        borderColor: colore,
                        backgroundColor: coloreSfondo,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.2,
                        pointBackgroundColor: colore,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 1,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }, {
                        label: 'Prezzo Acquisto',
                        data: new Array(prezzi.length).fill(prezzoAcquisto),
                        borderColor: '#ffc107',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [8, 4],
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        y: {
                            beginAtZero: false,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '€' + value.toFixed(4);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    const valore = context.parsed.y;
                                    const dataset = context.dataset.label;
                                    
                                    if (dataset.includes('Prezzo Acquisto')) {
                                        const differenza = prezzoAttuale - valore;
                                        const percentuale = ((differenza / valore) * 100);
                                        const simbolo = differenza >= 0 ? '+' : '';
                                        return `${dataset}: €${valore.toFixed(4)} (${simbolo}€${differenza.toFixed(4)} / ${simbolo}${percentuale.toFixed(2)}%)`;
                                    }
                                    
                                    return `${dataset}: €${valore.toFixed(4)}`;
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
            
            console.log(`Grafico creato per ${inv.simbolo} con ${prezzi.length} punti`);
            
        } catch (error) {
            console.error(`Errore nella creazione del grafico per ${inv.simbolo}:`, error);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (investimenti && investimenti.length > 0) {
        console.log(`Inizializzazione grafici per ${investimenti.length} investimenti`);
        creaGraficoPortfolio();
        creaGraficiIndividuali();
    } else {
        console.log('Nessun investimento da visualizzare');
    }
});
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function() {
    const quantitaInput = document.querySelector('input[name="quantita"]');
    const prezzoInput = document.querySelector('input[name="prezzo_acquisto"]');
    
    function calcolaImportoTotale() {
        const quantita = parseFloat(quantitaInput.value) || 0;
        const prezzo = parseFloat(prezzoInput.value) || 0;
        const totale = quantita * prezzo;
        
        if (quantita > 0 && prezzo > 0) {
            let infoDiv = document.getElementById('importo-totale');
            if (!infoDiv) {
                infoDiv = document.createElement('div');
                infoDiv.id = 'importo-totale';
                infoDiv.className = 'alert alert-info mt-2';
                prezzoInput.parentNode.appendChild(infoDiv);
            }
            infoDiv.innerHTML = `<strong> Importo totale: €${totale.toFixed(2).replace('.', ',')}</strong>`;
        } else {
            const infoDiv = document.getElementById('importo-totale');
            if (infoDiv) {
                infoDiv.remove();
            }
        }
    }
    
    if (quantitaInput && prezzoInput) {
        quantitaInput.addEventListener('input', calcolaImportoTotale);
        prezzoInput.addEventListener('input', calcolaImportoTotale);
    }
});


if (Object.keys(datiGrafici).length === 0 && investimenti.length > 0) {
    console.warn('Impossibile caricare i dati storici per alcuni investimenti.');
}

document.querySelector('form').addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<span class="loading-spinner"></span> Elaborazione...';
        submitBtn.disabled = true;
    }
});


const simboliPopulari = {
    'AAPL': 'Apple Inc.',
    'GOOGL': 'Alphabet Inc.',
    'MSFT': 'Microsoft Corporation',
    'AMZN': 'Amazon.com Inc.',
    'TSLA': 'Tesla Inc.',
    'NVDA': 'NVIDIA Corporation',
    'META': 'Meta Platforms Inc.',
    'NFLX': 'Netflix Inc.',
    'BTC-USD': 'Bitcoin',
    'ETH-USD': 'Ethereum',
    'SPY': 'SPDR S&P 500 ETF',
    'QQQ': 'Invesco QQQ ETF'
};
const simboloInput = document.querySelector('input[name="simbolo"]');
if (simboloInput) {
    const suggerimentiDiv = document.createElement('div');
    suggerimentiDiv.className = 'mt-1';
    suggerimentiDiv.innerHTML = '<small class="text-muted">💡 Simboli popolari: ' + 
        Object.keys(simboliPopulari).slice(0, 6).join(', ') + '...</small>';
    simboloInput.parentNode.appendChild(suggerimentiDiv);
}
</script>
</body>
</html>