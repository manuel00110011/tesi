<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controllo login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: autenticazione.html");
    exit;
}

require_once 'api/connessione.php';
include 'foto/gestione_foto_percorso.php';

$idUtente = $_SESSION['id_utente'] ?? null;

// Recupero conti
$conti = [];
$stmt = $conn->prepare("
    SELECT Conto.id, Conto.iban, User.nome AS nome, User.cognome as cognome 
    FROM Conto 
    JOIN User ON Conto.user_id = User.id 
    WHERE Conto.user_id = ?
");
if (!$stmt) {
    die("Errore nella query dei conti: " . $conn->error);
}
$stmt->bind_param("i", $idUtente);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $conti[] = $r;
}
$contoCorrente = $conti[0] ?? null;

if (isset($_GET['conto_selezionato'])) {
    foreach ($conti as $c) {
        if ($c['id'] == $_GET['conto_selezionato']) {
            $contoCorrente = $c;
            break;
        }
    }
}

$filtro = $_GET['filtro'] ?? 'tutti';
$cartaSelezionata = $_GET['carta_selezionata'] ?? 'tutte';
$dataDa = $_GET['data_da'] ?? null;
$dataA = $_GET['data_a'] ?? null;

function getDataInizioFiltro($filtro) {
    switch ($filtro) {
        case 'mese': return date('Y-m-d', strtotime('-1 month'));
        case '3mesi': return date('Y-m-d', strtotime('-3 months'));
        case 'anno': return date('Y-m-d', strtotime('-1 year'));
        default: return null;
    }
}
if ($dataDa || $dataA) {
    $dataInizio = null;
} else {
    $dataInizio = getDataInizioFiltro($filtro);
} 

// Movimenti conto
$movimenti = [];
if ($contoCorrente) {    
    $query = "
        SELECT 
            m.id AS id_movimento,
            m.data,
            t.nome AS tipo,
            m.importo,
            t.segno,
            b.destinatario,
            b.iban_destinatario,
            b.istantaneo,
            b.causale,
            b.data_esecuzione,
            'conto' as fonte
        FROM movimenti m
        JOIN tipologia_movimento t ON m.tipo_movimento = t.id
        LEFT JOIN bonifici b ON b.id_movimento = m.id
        WHERE m.id_conto = ?";
    $types = "i";
    $params = [$contoCorrente['id']];

    // Filtro per date personalizzate
    if ($dataDa && $dataA) {
        $query .= " AND m.data BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = $dataDa;
        $params[] = $dataA;
    } elseif ($dataDa) {
        $query .= " AND m.data >= ?";
        $types .= "s";
        $params[] = $dataDa;
    } elseif ($dataA) {
        $query .= " AND m.data <= ?";
        $types .= "s";
        $params[] = $dataA;
    } elseif ($dataInizio) {
        $query .= " AND m.data >= ?";
        $types .= "s";
        $params[] = $dataInizio;
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Errore nella query dei movimenti conto: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    
    // bonifici programmati
    $dataOggi = date('Y-m-d');
    while ($r = $res->fetch_assoc()) {
        if ($r['tipo'] === 'Bonifico') {
            if ($r['data'] <= $dataOggi) {
                $movimenti[] = $r;
            }
        } else {
            $movimenti[] = $r;
        }
    }
}

// Movimenti carte unificati con movimenti conto
$movimentiCarte = [];
if ($contoCorrente) {
    $query = "
        SELECT 
            cm.id as id_movimento,
            cm.data, 
            'Pagamento con Carta' AS tipo, 
            '-' as segno, 
            cm.importo, 
            c.numero_carta,
            c.circuito,
            'carta' as fonte
        FROM carta c
        JOIN carte_movimenti cm ON cm.id_carta = c.id
        WHERE c.conto_id = ?
    ";
    $types = "i";
    $params = [$contoCorrente['id']];

    if ($cartaSelezionata !== 'tutte') {
        $query .= " AND c.numero_carta = ?";
        $types .= "s";
        $params[] = $cartaSelezionata;
    }

    // Filtro per date personalizzate (stesso filtro dei movimenti conto)
    if ($dataDa && $dataA) {
        $query .= " AND cm.data BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = $dataDa;
        $params[] = $dataA;
    } elseif ($dataDa) {
        $query .= " AND cm.data >= ?";
        $types .= "s";
        $params[] = $dataDa;
    } elseif ($dataA) {
        $query .= " AND cm.data <= ?";
        $types .= "s";
        $params[] = $dataA;
    } elseif ($dataInizio) {
        $query .= " AND cm.data >= ?";
        $types .= "s";
        $params[] = $dataInizio;
    }

    $query .= " ORDER BY cm.data DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Errore nella query dei movimenti carte: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $movimentiCarte[] = $r;
        $movimenti[] = $r;
    }
}

usort($movimenti, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});

$carte = [];
if ($contoCorrente) {
    $stmt = $conn->prepare("SELECT numero_carta, circuito, data_scadenza, cvv, tipo, stato FROM Carta WHERE conto_id = ?");
    if (!$stmt) {
        die("Errore nella query delle carte associate: " . $conn->error);
    }
    $stmt->bind_param("i", $contoCorrente['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $carte[] = [
            'numero_carta' => $r['numero_carta'],
            'circuito' => $r['circuito'],
            'data_scadenza' => $r['data_scadenza'],
            'cvv' => $r['cvv'],
            'tipo' => $r['tipo'],
            'stato' => $r['stato']
        ];
    }
}

$cartaDaMostrare = null;
if ($cartaSelezionata && $cartaSelezionata !== 'tutte') {
    foreach ($carte as $carta) {
        if ($carta['numero_carta'] === $cartaSelezionata) {
            $cartaDaMostrare = $carta;
            break;
        }
    }
}

// Anagrafica 
$utente = [];
if ($idUtente) {
    $stmt = $conn->prepare("SELECT nome, cognome, indirizzo, email, numero_telefonico, foto, data_nascita, ruolo, data_registrazione, numero_conto FROM user WHERE id = ?");
    $stmt->bind_param("i", $idUtente); 
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $utente = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Home - La Mia Banca</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">La Mia Banca</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-between" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Operazioni</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="bonifico.php">Bonifico</a></li>
            <li><a class="dropdown-item" href="investimenti.php">Investimenti</a></li>
            <li><a class="dropdown-item" href="polizze.php">Polizze</a></li>
          </ul>
        </li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Il mio profilo</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" onclick="toggleProfilo(); return false;">Anagrafica</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="api\logout.php" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
  <h1 class="mb-4">Benvenuto nella tua Home</h1>

  <!-- Form selezione conto e filtro -->
  <form method="get" class="row g-3 mb-3" id="filtroForm">
    <div class="col-md-3">
      <label class="form-label">Conto:</label>
      <select name="conto_selezionato" class="form-select" onchange="this.form.submit()">
        <?php foreach ($conti as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($contoCorrente && $contoCorrente['id'] == $c['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['nome']." ".$c['cognome']) ?> (<?= htmlspecialchars($c['iban']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Periodo:</label>
      <select name="filtro" class="form-select" onchange="resetDatePersonalizzate(); this.form.submit()">
        <option value="tutti" <?= $filtro === 'tutti' ? 'selected' : '' ?>>Default</option>
        <option value="mese" <?= $filtro === 'mese' ? 'selected' : '' ?>>Ultimo mese</option>
        <option value="3mesi" <?= $filtro === '3mesi' ? 'selected' : '' ?>>Ultimi 3 mesi</option>
        <option value="anno" <?= $filtro === 'anno' ? 'selected' : '' ?>>Ultimo anno</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Data da:</label>
      <input type="date" name="data_da" value="<?= htmlspecialchars($dataDa ?? '') ?>" class="form-control" onchange="resetFiltroPreimpostato(); this.form.submit()">
    </div>
    <div class="col-md-2">
      <label class="form-label">Data a:</label>
      <input type="date" name="data_a" value="<?= htmlspecialchars($dataA ?? '') ?>" class="form-control" onchange="resetFiltroPreimpostato(); this.form.submit()">
    </div>
    <div class="col-md-3">
      <label class="form-label">Carta:</label>
      <select name="carta_selezionata" class="form-select" onchange="this.form.submit()">
        <option value="tutte" <?= $cartaSelezionata === 'tutte' ? 'selected' : '' ?>>Tutte</option>
        <?php foreach ($carte as $carta): ?>
          <option value="<?= htmlspecialchars($carta['numero_carta']) ?>" <?= $cartaSelezionata === $carta['numero_carta'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($carta['circuito'] . ' - ' . $carta['numero_carta']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if (!empty($cartaDaMostrare)): ?>
    <div class="cards-wrapper" style="display: flex; gap: 20px; justify-content: center;">
      <!-- FRONTE -->
      <div class="card-front card-visual" aria-label="Carta di credito" style="width: 350px;">
        <div class="card-content">
          <div class="card-header">MYBANK</div>
          <div>
          <div class="card-number">
          <?= htmlspecialchars(wordwrap(preg_replace('/\D/', '', $cartaDaMostrare['numero_carta']), 4, ' ', true)) ?>
          </div>
          </div>
          <div class="card-footer">
          <div class="tipo"><?= htmlspecialchars($cartaDaMostrare['tipo']) ?></div>
          <div class="circuito"><?= htmlspecialchars($cartaDaMostrare['circuito']) ?></div>
        </div>
      </div>
    </div>

      <!-- RETRO -->
      <div class="card-back card-visual" aria-label="Retro carta di credito" style="width: 350px;">
        <div class="magnetic-strip"></div>
        <div class="signature-strip">
          <div class="signature-placeholder">Spazio firma</div>
          <div class="cvv-code"><?= htmlspecialchars($cartaDaMostrare['cvv']) ?></div>
        </div>
        <div class="expiry-back">Scadenza: <?= htmlspecialchars($cartaDaMostrare['data_scadenza']) ?></div>
      </div>
    </div>
  <?php endif; ?>

  <br><br>

  <?php if ($contoCorrente): ?>
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">Dati del Conto</div>
      <div class="card-body">
        <p><strong>Intestatario:</strong> <?= htmlspecialchars($contoCorrente['nome']." ".$contoCorrente['cognome']) ?></p>
        <p><strong>IBAN:</strong> <?= htmlspecialchars($contoCorrente['iban']) ?></p>
        <p><strong>Saldo Totale:</strong> €
   <?php
    $saldo = 0;
    
    // Movimenti conto
    $query1 = "
        SELECT m.importo, tm.segno
        FROM movimenti m
        JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
        WHERE m.id_conto = ?
    ";
    $stmt1 = $conn->prepare($query1);
    $stmt1->bind_param("i", $contoCorrente['id']);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    while($row = $result1->fetch_assoc()) {
        $segno = ($row['segno'] === '+') ? 1 : -1;
        $saldo += $segno * $row['importo'];
    }
    
    // Movimenti carte
    $query2 = "
        SELECT cm.importo
        FROM carte_movimenti cm
        JOIN Carta c ON cm.id_carta = c.id
        WHERE c.conto_id = ?
    ";
    $stmt2 = $conn->prepare($query2);
    $stmt2->bind_param("i", $contoCorrente['id']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while($row = $result2->fetch_assoc()) {
        $saldo -= $row['importo'];
    }
    
    echo number_format($saldo, 2, ',', '.');
  ?>
        </p>
      </div>
    </div>

    <h3>Movimenti Conto</h3>
    <div class="table-responsive mb-4">
      <table class="table table-bordered table-movimenti">
        <thead><tr><th>Data</th><th>Tipo</th><th>Importo</th><th>Dettagli</th></tr></thead>
        <tbody>
        <?php foreach ($movimenti as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['data']) ?></td>
            <td><?= htmlspecialchars($m['tipo']) ?></td>
            <td class="<?= $m['segno'] === '-' ? 'text-danger' : 'text-success' ?>">
              <?= $m['segno'] ?> € <?= number_format($m['importo'], 2, ',', '.') ?>
            </td>
            <td><button class="btn btn-sm btn-info" onclick="mostraDettagli(<?= $m['id_movimento'] ?? 'null' ?>, '<?= htmlspecialchars($m['tipo'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['fonte'] ?? 'conto', ENT_QUOTES) ?>')">Dettagli</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3>Andamento Spese Carta</h3>
    <?php if (!empty($carte) && $cartaSelezionata !== 'tutte'): ?>
      <div id="statistiche-carta" class="mb-4"> </div>
      <!-- Grafico -->
      <div class="card mb-4">
        <div class="card-body">
          <div style="height: 400px;">
            <canvas id="graficoCartaCanvas"></canvas>
          </div>
        </div>
      </div>
    <?php elseif (!empty($carte)): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        Seleziona una carta specifica dal filtro sopra per visualizzare l'andamento delle spese.
      </div>
    <?php else: ?>
      <div class="alert alert-warning">Nessuna carta disponibile.</div>
    <?php endif; ?>
  <?php else: ?>
    <div class="alert alert-warning">Nessun conto disponibile.</div>
  <?php endif; ?>
</div>

<!-- Modal dettagli -->
<div class="modal fade" id="dettagliModal" tabindex="-1" aria-labelledby="dettagliLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="dettagliLabel">Dettagli Movimento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="contenutoDettagli">Caricamento...</div>
    </div>
  </div>
</div>

<!-- Modal logout -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Conferma logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        Sei sicuro di voler uscire?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <a href="api\logout.php" class="btn btn-danger">Sì, esci</a>
      </div>
    </div>
  </div>
</div>

<!-- Anagrafica -->
<div id="dettagliProfilo" style="display:none; position: fixed; top: 80px; right: 20px; background: white; border: 2px solid blue; border-radius: 4px; padding: 20px; width: 250px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 100;"> 
    
    <!-- Foto profilo con fallback -->
    <?php 
    $fotoSrc = 'img/default.jpg';
    if (!empty($utente['foto']) && file_exists($utente['foto'])) {
        $fotoSrc = $utente['foto'] . '?t=' . time(); 
    }
    ?>
    <img id="dettagliFoto" 
         src="<?= $fotoSrc ?>" 
         alt="Foto Profilo" 
         style="width:80px; height:80px; border-radius:50%; display:block; margin: 0 auto 15px auto; object-fit: cover; border: 2px solid blue;">
    
    <?php include 'foto/form_foto_compatto.php'; ?>
    
    <p><strong>Nome:</strong> <?= htmlspecialchars($utente['nome'] ?? 'N/A') ?></p>
    <p><strong>Cognome:</strong> <?= htmlspecialchars($utente['cognome'] ?? 'N/A') ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($utente['email'] ?? 'N/A') ?></p>
    <p><strong>Indirizzo:</strong> <?= htmlspecialchars($utente['indirizzo'] ?? 'N/A') ?></p>
    <p><strong>Numero telefonico:</strong> <?= htmlspecialchars($utente['numero_telefonico'] ?? 'N/A') ?></p>
    <p><strong>Data di nascita:</strong> <?= htmlspecialchars($utente['data_nascita'] ?? 'N/A') ?></p>
    <p><strong>Data di registrazione:</strong> <?= htmlspecialchars($utente['data_registrazione'] ?? 'N/A') ?></p>
    <p><strong>Numero conto:</strong> <?= htmlspecialchars($utente['numero_conto'] ?? 'N/A') ?></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/grafico_carta.js"></script>
<script>
  function mostraDettagli(id, tipo, fonte) {
    fonte = fonte || 'conto'; 
    let url = 'api/dettagli_movimento.php?id_movimento=' + id + '&fonte=' + fonte;
    fetch(url)
      .then(res => res.text())
      .then(html => {
        document.getElementById('contenutoDettagli').innerHTML = html;
        new bootstrap.Modal(document.getElementById('dettagliModal')).show();
      });
  }

document.addEventListener("DOMContentLoaded", () => {
    const cartaSelezionata = "<?= $cartaSelezionata ?>";
    const filtro = "<?= $filtro ?>";
    const dataDaPhp = "<?= $dataDa ?? '' ?>";
    const dataAPhp = "<?= $dataA ?? '' ?>";
    
    if (cartaSelezionata && cartaSelezionata !== 'tutte') {
        setTimeout(() => {
            let dataInizio = null;
            let dataFine = null;
            
            if (dataDaPhp || dataAPhp) {
                dataInizio = dataDaPhp || null;
                dataFine = dataAPhp || null;
                console.log("Usando date personalizzate:", dataInizio, dataFine);
            } else {
                const oggi = new Date();
                
                if (filtro === 'mese') {
                    const unMeseFa = new Date(oggi);
                    unMeseFa.setMonth(oggi.getMonth() - 1);
                    dataInizio = unMeseFa.toISOString().split('T')[0];
                    dataFine = oggi.toISOString().split('T')[0]; 
                } else if (filtro === '3mesi') {
                    const treeMesiFa = new Date(oggi);
                    treeMesiFa.setMonth(oggi.getMonth() - 3);
                    dataInizio = treeMesiFa.toISOString().split('T')[0];
                    dataFine = oggi.toISOString().split('T')[0]; 
                } else if (filtro === 'anno') {
                    const unAnnoFa = new Date(oggi);
                    unAnnoFa.setFullYear(oggi.getFullYear() - 1);
                    dataInizio = unAnnoFa.toISOString().split('T')[0];
                    dataFine = oggi.toISOString().split('T')[0]; 
                } else {
                    const unMeseFa = new Date(oggi);
                    unMeseFa.setMonth(oggi.getMonth() - 1);
                    dataInizio = unMeseFa.toISOString().split('T')[0];
                    dataFine = oggi.toISOString().split('T')[0];
                }
                console.log("Usando filtro periodo:", filtro, dataInizio, dataFine);
            }
            
            if (typeof creaGraficoSpeseCarta === 'function') {
                creaGraficoSpeseCarta('graficoCartaCanvas', cartaSelezionata, dataInizio, dataFine);
            }
        }, 1000);
    }
});

  function resetDatePersonalizzate() {
    document.querySelector('input[name="data_da"]').value = '';
    document.querySelector('input[name="data_a"]').value = '';
  }

  function resetFiltroPreimpostato() {
    document.querySelector('select[name="filtro"]').value = 'tutti';
  }

  // Paginazione 10 movimenti per pagina
  document.addEventListener("DOMContentLoaded", () => {
    const righe = document.querySelectorAll(".table-movimenti tbody tr");
    const perPagina = 10;
    let pagina = 1;

    function mostraPagina(n) {
      righe.forEach((r, i) => r.style.display = (i >= (n - 1) * perPagina && i < n * perPagina) ? "" : "none");
    }

    function creaPaginazione() {
      const totale = Math.ceil(righe.length / perPagina);
      const container = document.createElement("div");
      container.className = "d-flex justify-content-center mt-3";

      for (let i = 1; i <= totale; i++) {
        const btn = document.createElement("button");
        btn.className = "btn btn-outline-primary mx-1";
        btn.textContent = i;
        btn.onclick = () => {
          pagina = i;
          mostraPagina(pagina);
        };
        container.appendChild(btn);
      }

      document.querySelector(".table-movimenti").after(container);
      mostraPagina(pagina);
    }

    if (righe.length > perPagina) creaPaginazione();
  });

  // Funzioni profilo
  function toggleProfilo() {
    const profilo = document.getElementById('dettagliProfilo');
    if (profilo.style.display === 'none' || profilo.style.display === '') {
      profilo.style.display = 'block';
    } else {
      profilo.style.display = 'none';
    }
  }

  document.addEventListener('click', function(event) {
    const profilo = document.getElementById('dettagliProfilo');
    const link = event.target.closest('.dropdown-item');

    if (!profilo.contains(event.target) && (!link || !link.textContent.includes('Anagrafica'))) {
      profilo.style.display = 'none';
    }
  });
</script>
</body>

</html>
