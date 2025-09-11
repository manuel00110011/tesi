<?php
session_start();

require_once 'api/connessione.php';

$id_utente = $_SESSION['id_utente'] ?? null;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: autenticazione.html');
    exit;
}

$errore = '';
$successo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stipula_polizza'])) {
    $nome = trim($_POST['nome']);
    $premio = floatval($_POST['premio']);
    $spesa_annua = floatval($_POST['spesa_annua']);
    $data_inizio = $_POST['data_inizio'];
    $data_fine = !empty($_POST['data_fine']) ? $_POST['data_fine'] : null;
    $copertura = trim($_POST['copertura']);
    $importo_copertura = floatval($_POST['importo_copertura']);
    
    if (empty($nome) || $premio <= 0 || $spesa_annua <= 0 || empty($data_inizio) || empty($copertura) || $importo_copertura <= 0) {
        $errore = 'Tutti i campi sono obbligatori e i valori numerici devono essere positivi.';
    } else {
        $oggi = new DateTime();
        $data_inizio_obj = new DateTime($data_inizio);
        $data_fine_obj = $data_fine ? new DateTime($data_fine) : null;
        
        if ($data_inizio_obj > $oggi) {
            $stato = 'in_attesa';
        } elseif ($data_fine_obj && $data_fine_obj < $oggi) {
            $stato = 'scaduta';
        } else {
            $stato = 'attiva';
        }
        
        
        $stmt = $conn->prepare("INSERT INTO Polizza (conto_id, nome, premio, spesa_annua, data_inizio, data_fine, copertura, stato, importo_copertura) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("isddssssd", $id_utente, $nome, $premio, $spesa_annua, $data_inizio, $data_fine, $copertura, $stato, $importo_copertura);
            
            if ($stmt->execute()) {
                $successo = 'Polizza stipulata con successo!';
                header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                exit;
            } else {
                $errore = 'Errore durante la stipula della polizza: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errore = 'Errore nella preparazione della query: ' . $conn->error;
        }
    }
}

if (isset($_GET['success'])) {
    $successo = 'Polizza stipulata con successo!';
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

$polizze = [];
if ($id_utente) {
    $statement = $conn->prepare("SELECT id, nome, premio, spesa_annua, data_inizio, data_fine, copertura, stato, importo_copertura
                            FROM Polizza
                            WHERE conto_id = ? 
                            ORDER BY data_inizio DESC");
    if (!$statement) {
        die("Errore nella query: " . $conn->error);
    }
    $statement->bind_param("i", $id_utente);
    $statement->execute();
    $res = $statement->get_result();
    while ($r = $res->fetch_assoc()) {
        $polizze[] = [
            'id'           => $r['id'],
            'nome'         => $r['nome'],
            'premio'       => $r['premio'],
            'spesa_annua'  => $r['spesa_annua'],
            'data_inizio'  => $r['data_inizio'],
            'data_fine'    => $r['data_fine'],
            'copertura'    => $r['copertura'],
            'stato'        => $r['stato'],
            'importo_copertura' => $r['importo_copertura'] ?? 0
        ];
    }
    $statement->close();
}

$nomi_polizze = [];
$spese_annue = [];
$importi_copertura = [];

foreach ($polizze as $polizza) {
    $nomi_polizze[] = $polizza['nome'];
    $spese_annue[] = floatval($polizza['spesa_annua']);
    $importi_copertura[] = floatval($polizza['importo_copertura']);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Le mie polizze</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container" style="margin-top: 50px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-center mb-0">Le tue polizze</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuovaPolizzaModal">
            <i class="bi bi-plus-circle"></i> Stipula Nuova Polizza
        </button>
    </div>
    
    <?php if ($errore): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errore) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($successo): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successo) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (empty($polizze)): ?>
        <div class="alert alert-info text-center">
            <h5>Nessuna polizza trovata</h5>
            <p>Non hai ancora stipulato nessuna polizza. Clicca su "Stipula Nuova Polizza" per iniziare!</p>
        </div>
    <?php else: ?>
        <!-- Sezione Polizze -->
        <div class="row row-cols-1 row-cols-md-2 g-4 mb-5">
            <?php foreach ($polizze as $polizza): ?>
                <div class="col">
                    <div class="card shadow h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($polizza['nome']) ?></h5>
                            <p class="card-text">
                                <strong>Premio:</strong> €<?= number_format($polizza['premio'], 2, ',', '.') ?><br>
                                <strong>Spesa annua:</strong> €<?= number_format($polizza['spesa_annua'], 2, ',', '.') ?><br>
                                <strong>Inizio:</strong> <?= htmlspecialchars($polizza['data_inizio']) ?><br>
                                <strong>Fine:</strong> <?= htmlspecialchars($polizza['data_fine']) ?: 'N/A' ?><br>
                                <strong>Copertura:</strong> €<?= number_format($polizza['importo_copertura'], 2, ',', '.') ?><br>
                                <strong>Dettagli:</strong> <?= nl2br(htmlspecialchars($polizza['copertura'])) ?><br>
                                <strong>Stato:</strong> 
                                <span class="badge bg-<?= $polizza['stato'] === 'attiva' ? 'success' : ($polizza['stato'] === 'scaduta' ? 'danger' : 'warning') ?>">
                                    <?= htmlspecialchars($polizza['stato']) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr class="my-5">
        <h3 class="text-center mb-4">Analisi Grafici</h3>

        <!-- Sezione Grafici -->
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Spesa Annua per Polizza</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="spesaAnnuaChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Importo Copertura per Polizza</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="importoCoperturaChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafico combinato -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Confronto Spesa Annua vs Importo Copertura</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="confrontoChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal per Nuova Polizza -->
<div class="modal fade" id="nuovaPolizzaModal" tabindex="-1" aria-labelledby="nuovaPolizzaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nuovaPolizzaModalLabel">Stipula Nuova Polizza</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nome" class="form-label">Nome Polizza *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required 
                                   placeholder="es. Polizza Auto, Polizza Casa...">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="premio" class="form-label">Premio (€) *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="premio" name="premio" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="spesa_annua" class="form-label">Spesa Annua (€) *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="spesa_annua" name="spesa_annua" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="importo_copertura" class="form-label">Importo Copertura (€) *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="importo_copertura" name="importo_copertura" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_inizio" class="form-label">Data Inizio *</label>
                            <input type="date" class="form-control" id="data_inizio" name="data_inizio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_fine" class="form-label">Data Fine</label>
                            <input type="date" class="form-control" id="data_fine" name="data_fine">
                            <div class="form-text">Lascia vuoto per polizza a tempo indeterminato</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="copertura" class="form-label">Descrizione Copertura *</label>
                        <textarea class="form-control" id="copertura" name="copertura" rows="4" required
                                  placeholder="Descrivi cosa copre questa polizza..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" name="stipula_polizza" class="btn btn-primary">Stipula Polizza</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
<?php if (!empty($polizze)): ?>
const nomiPolizze = <?= json_encode($nomi_polizze) ?>;
const speseAnnue = <?= json_encode($spese_annue) ?>;
const importiCopertura = <?= json_encode($importi_copertura) ?>;

const coloriSpesa = [
    '#c54141ff', '#2560b9ff', '#a07c08ff', '#81ffffff', 
    '#570debff', '#92561aff', '#000000ff', '#cecfd1ff'
];

const coloriCopertura = [
    '#3bbbbbff', '#f5c447ff', '#db4767ff', '#31a3f0ff',
    '#bb6005ff', '#763cebff', '#c6c9ceff', '#f05b7bff'
];

// Grafico Spesa Annua
const ctxSpesa = document.getElementById('spesaAnnuaChart').getContext('2d');
new Chart(ctxSpesa, {
    type: 'bar',
    data: {
        labels: nomiPolizze,
        datasets: [{
            label: 'Spesa Annua (€)',
            data: speseAnnue,
            backgroundColor: coloriSpesa,
            borderColor: coloriSpesa.map(color => color + '80'),
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '€' + value.toLocaleString('it-IT');
                    }
                }
            }
        }
    }
});

// Grafico Importo Copertura
const ctxCopertura = document.getElementById('importoCoperturaChart').getContext('2d');
new Chart(ctxCopertura, {
    type: 'doughnut',
    data: {
        labels: nomiPolizze,
        datasets: [{
            label: 'Importo Copertura (€)',
            data: importiCopertura,
            backgroundColor: coloriCopertura,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': €' + context.raw.toLocaleString('it-IT');
                    }
                }
            }
        }
    }
});

// Grafico di confronto
const ctxConfronto = document.getElementById('confrontoChart').getContext('2d');
new Chart(ctxConfronto, {
    type: 'bar',
    data: {
        labels: nomiPolizze,
        datasets: [{
            label: 'Spesa Annua (€)',
            data: speseAnnue,
            backgroundColor: '#36A2EB',
            yAxisID: 'y'
        }, {
            label: 'Importo Copertura (€)',
            data: importiCopertura,
            backgroundColor: '#4BC0C0',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Polizze'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Spesa Annua (€)'
                },
                ticks: {
                    callback: function(value) {
                        return '€' + value.toLocaleString('it-IT');
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Importo Copertura (€)'
                },
                grid: {
                    drawOnChartArea: false,
                },
                ticks: {
                    callback: function(value) {
                        return '€' + value.toLocaleString('it-IT');
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': €' + context.raw.toLocaleString('it-IT');
                    }
                }
            }
        }
    }
});
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function() {
    const dataInizio = document.getElementById('data_inizio');
    const dataFine = document.getElementById('data_fine');
    const oggi = new Date().toISOString().split('T')[0];
    
    dataInizio.min = oggi;
    dataInizio.addEventListener('change', function() {
        dataFine.min = this.value;
        if (dataFine.value && dataFine.value < this.value) {
            dataFine.value = '';
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>