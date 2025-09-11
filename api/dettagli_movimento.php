<?php
require_once 'connessione.php';

$idMovimento = $_GET['id_movimento'] ?? null;
$fonte = $_GET['fonte'] ?? 'conto';

if (!isset($_GET['id_movimento']) || !is_numeric($_GET['id_movimento'])) {
    echo "<div class='alert alert-danger'>ID movimento non valido</div>";
    exit;
}

$idMovimento = intval($_GET['id_movimento']);

if ($fonte === 'carta') {
    $queryDett = $conn->prepare("
        SELECT 
            cm.data,
            cm.importo,
            c.numero_carta,
            c.circuito,
            c.tipo AS tipo_carta,
            c.data_scadenza,
            u.nome AS nome_titolare,
            u.cognome AS cognome_titolare
        FROM carte_movimenti cm
        JOIN carta c ON cm.id_carta = c.id
        JOIN conto ct ON c.conto_id = ct.id
        JOIN user u ON ct.user_id = u.id
        WHERE cm.id = ?
    ");
    
    $queryDett->bind_param("i", $idMovimento);
    $queryDett->execute();
    $res = $queryDett->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $numeroCartaMascherato = substr($row['numero_carta'], 0, 4) . ' **** **** ' . substr($row['numero_carta'], -4);
        
        echo "<div class='container-fluid'>";
        echo "<div class='row'>";
        echo "<div class='col-md-6'>";
        echo "<h6>Informazioni Pagamento</h6>";
        echo "<p><strong>Data:</strong> " . htmlspecialchars($row['data']) . "</p>";
        echo "<p><strong>Tipo:</strong> Pagamento con Carta</p>";
        echo "<p><strong>Importo:</strong> <span class='text-danger'>";
        echo "- € " . number_format($row['importo'], 2, ',', '.') . "</span></p>";
        
        if (!empty($row['commerciante'])) {
            echo "<p><strong>Commerciante:</strong> " . htmlspecialchars($row['commerciante']) . "</p>";
        }
        if (!empty($row['descrizione'])) {
            echo "<p><strong>Descrizione:</strong> " . htmlspecialchars($row['descrizione']) . "</p>";
        }
        echo "</div>";
        
        echo "<div class='col-md-6'>";
        echo "<h6>Carta Utilizzata</h6>";
        echo "<p><strong>Circuito:</strong> " . htmlspecialchars($row['circuito']) . "</p>";
        echo "<p><strong>Tipo:</strong> " . htmlspecialchars($row['tipo_carta']) . "</p>";
        echo "<p><strong>Numero:</strong> " . htmlspecialchars($numeroCartaMascherato) . "</p>";
        echo "<p><strong>Scadenza:</strong> " . htmlspecialchars($row['data_scadenza']) . "</p>";
        echo "<p><strong>Titolare:</strong> " . htmlspecialchars($row['nome_titolare'] . ' ' . $row['cognome_titolare']) . "</p>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>Movimento carta non trovato.</div>";
    }
    $queryDett->close();
    
} else {
    $queryTipo = $conn->prepare("
        SELECT tm.nome AS tipo_nome
        FROM movimenti m
        JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
        WHERE m.id = ?
    ");
    $queryTipo->bind_param("i", $idMovimento);
    $queryTipo->execute();
    $resTipo = $queryTipo->get_result();
    $tipo = $resTipo->fetch_assoc();
    $queryTipo->close();

    if (!$tipo) {
        echo "<div class='alert alert-warning'>Movimento non trovato.</div>";
        exit;
    }

    $tipoNome = strtolower($tipo['tipo_nome']);

    echo "<div class='container-fluid'>";

    if ($tipoNome === 'bonifico') {
        $queryDett = $conn->prepare("
            SELECT 
                b.destinatario, 
                b.iban_destinatario, 
                b.causale,
                m.importo, 
                m.data, 
                b.istantaneo,
                tm.segno,
                u.nome AS nome_titolare,
                u.cognome AS cognome_titolare,
                c.iban AS iban_mittente
            FROM bonifici b
            JOIN movimenti m ON b.id_movimento = m.id
            JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
            JOIN conto c ON m.id_conto = c.id
            JOIN user u ON c.user_id = u.id
            WHERE b.id_movimento = ?
        ");
        $queryDett->bind_param("i", $idMovimento);
        $queryDett->execute();
        $res = $queryDett->get_result();

        if ($row = $res->fetch_assoc()) {
            echo "<div class='row'>";
            echo "<div class='col-md-6'>";
            echo "<h6>Informazioni Generali</h6>";
            echo "<p><strong>Data:</strong> " . htmlspecialchars($row['data']) . "</p>";
            echo "<p><strong>Tipo:</strong> Bonifico</p>";
            echo "<p><strong>Importo:</strong> <span class='" . ($row['segno'] === '+' ? 'text-success' : 'text-danger') . "'>";
            echo $row['segno'] . " € " . number_format($row['importo'], 2, ',', '.') . "</span></p>";
            echo "<p><strong>Istantaneo:</strong> " . ($row['istantaneo'] ? 'Sì' : 'No') . "</p>";
            echo "</div>";
            
            echo "<div class='col-md-6'>";
            echo "<h6>Dettagli Bonifico</h6>";
            echo "<p><strong>Destinatario:</strong> " . htmlspecialchars($row['destinatario']) . "</p>";
            echo "<p><strong>IBAN Destinatario:</strong> " . htmlspecialchars($row['iban_destinatario']) . "</p>";
            echo "<p><strong>IBAN Mittente:</strong> " . htmlspecialchars($row['iban_mittente']) . "</p>";
            echo "<p><strong>Titolare:</strong> " . htmlspecialchars($row['nome_titolare'] . ' ' . $row['cognome_titolare']) . "</p>";
            if (!empty($row['causale'])) {
                echo "<p><strong>Causale:</strong> " . htmlspecialchars($row['causale']) . "</p>";
            }
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-warning'>Nessun dettaglio disponibile per questo bonifico.</div>";
        }
        $queryDett->close();
        
    } elseif ($tipoNome === 'bonifico in entrata') {        //bonifici in entrata
        $queryDett = $conn->prepare("
            SELECT 
                m.importo, 
                m.data,
                tm.segno,
                u.nome AS nome_titolare,
                u.cognome AS cognome_titolare,
                c.iban AS iban_destinatario
            FROM movimenti m
            JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
            JOIN conto c ON m.id_conto = c.id
            JOIN user u ON c.user_id = u.id
            WHERE m.id = ?
        ");
        $queryDett->bind_param("i", $idMovimento);
        $queryDett->execute();
        $res = $queryDett->get_result();

        if ($row = $res->fetch_assoc()) {
            echo "<div class='row'>";
            echo "<div class='col-md-12'>";
            echo "<h6>Informazioni Bonifico in Entrata</h6>";
            echo "<p><strong>Data:</strong> " . htmlspecialchars($row['data']) . "</p>";
            echo "<p><strong>Tipo:</strong> Bonifico in Entrata</p>";
            echo "<p><strong>Importo:</strong> <span class='text-success'>";
            echo $row['segno'] . " € " . number_format($row['importo'], 2, ',', '.') . "</span></p>";
            echo "<p><strong>IBAN Destinatario:</strong> " . htmlspecialchars($row['iban_destinatario']) . "</p>";
            echo "<p><strong>Beneficiario:</strong> " . htmlspecialchars($row['nome_titolare'] . ' ' . $row['cognome_titolare']) . "</p>";
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-warning'>Nessun dettaglio disponibile per questo bonifico in entrata.</div>";
        }
        $queryDett->close();
        
    } elseif ($tipoNome === 'investimenti') {       //investimento
        $queryDett = $conn->prepare("
            SELECT 
                m.importo, 
                m.data,
                tm.segno,
                u.nome AS nome_titolare,
                u.cognome AS cognome_titolare,
                c.iban,
                i.nome AS nome_investimento,
                i.tipo AS tipo_investimento,
                i.isin,
                i.rendimento_annuo,
                i.interesse
            FROM movimenti m
            JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
            JOIN conto c ON m.id_conto = c.id
            JOIN user u ON c.user_id = u.id
            LEFT JOIN investimento i ON i.conto_id = c.id
            WHERE m.id = ?
        ");
        $queryDett->bind_param("i", $idMovimento);
        $queryDett->execute();
        $res = $queryDett->get_result();

        if ($row = $res->fetch_assoc()) {
            echo "<div class='row'>";
            echo "<div class='col-md-6'>";
            echo "<h6>Informazioni Movimento</h6>";
            echo "<p><strong>Data:</strong> " . htmlspecialchars($row['data']) . "</p>";
            echo "<p><strong>Tipo:</strong> Investimento</p>";
            echo "<p><strong>Importo:</strong> <span class='" . ($row['segno'] === '+' ? 'text-success' : 'text-danger') . "'>";
            echo $row['segno'] . " € " . number_format($row['importo'], 2, ',', '.') . "</span></p>";
            echo "<p><strong>Titolare:</strong> " . htmlspecialchars($row['nome_titolare'] . ' ' . $row['cognome_titolare']) . "</p>";
            echo "</div>";
            
            echo "<div class='col-md-6'>";
            echo "<h6>Dettagli Investimento</h6>";
            if (!empty($row['nome_investimento'])) {
                echo "<p><strong>Nome:</strong> " . htmlspecialchars($row['nome_investimento']) . "</p>";
                echo "<p><strong>Tipo:</strong> " . htmlspecialchars($row['tipo_investimento']) . "</p>";
                if (!empty($row['isin'])) {
                    echo "<p><strong>ISIN:</strong> " . htmlspecialchars($row['isin']) . "</p>";
                }
                if (!empty($row['rendimento_annuo'])) {
                    echo "<p><strong>Rendimento Annuo:</strong> " . htmlspecialchars($row['rendimento_annuo']) . "%</p>";
                }
                echo "<p><strong>Interesse:</strong> " . htmlspecialchars($row['interesse']) . "</p>";
            }
            echo "<p><strong>IBAN Conto:</strong> " . htmlspecialchars($row['iban']) . "</p>";
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-warning'>Nessun dettaglio disponibile per questo investimento.</div>";
        }
        $queryDett->close();
        
    } elseif ($tipoNome === 'polizza') {        //polizze
        $queryDett = $conn->prepare("
            SELECT 
                m.importo, 
                m.data,
                tm.segno,
                u.nome AS nome_titolare,
                u.cognome AS cognome_titolare,
                c.iban,
                p.nome AS nome_polizza,
                p.premio,
                p.spesa_annua,
                p.copertura,
                p.stato AS stato_polizza
            FROM movimenti m
            JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
            JOIN conto c ON m.id_conto = c.id
            JOIN user u ON c.user_id = u.id
            LEFT JOIN polizza p ON p.conto_id = c.id
            WHERE m.id = ?
        ");
        $queryDett->bind_param("i", $idMovimento);
        $queryDett->execute();
        $res = $queryDett->get_result();

        if ($row = $res->fetch_assoc()) {
            echo "<div class='row'>";
            echo "<div class='col-md-6'>";
            echo "<h6>Informazioni Movimento</h6>";
            echo "<p><strong>Data:</strong> " . htmlspecialchars($row['data']) . "</p>";
            echo "<p><strong>Tipo:</strong> Polizza</p>";
            echo "<p><strong>Importo:</strong> <span class='" . ($row['segno'] === '+' ? 'text-success' : 'text-danger') . "'>";
            echo $row['segno'] . " € " . number_format($row['importo'], 2, ',', '.') . "</span></p>";
            echo "<p><strong>Titolare:</strong> " . htmlspecialchars($row['nome_titolare'] . ' ' . $row['cognome_titolare']) . "</p>";
            echo "</div>";
            
            echo "<div class='col-md-6'>";
            echo "<h6>Dettagli Polizza</h6>";
            if (!empty($row['nome_polizza'])) {
                echo "<p><strong>Nome Polizza:</strong> " . htmlspecialchars($row['nome_polizza']) . "</p>";
                echo "<p><strong>Premio:</strong> € " . number_format($row['premio'], 2, ',', '.') . "</p>";
                if (!empty($row['spesa_annua'])) {
                    echo "<p><strong>Spesa Annua:</strong> € " . number_format($row['spesa_annua'], 2, ',', '.') . "</p>";
                }
                echo "<p><strong>Stato:</strong> " . htmlspecialchars($row['stato_polizza']) . "</p>";
                if (!empty($row['copertura'])) {
                    echo "<p><strong>Copertura:</strong> " . htmlspecialchars($row['copertura']) . "</p>";
                }
            }
            echo "<p><strong>IBAN Conto:</strong> " . htmlspecialchars($row['iban']) . "</p>";
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-warning'>Nessun dettaglio disponibile per questa polizza.</div>";
        }
        $queryDett->close();
        
    } elseif ($tipoNome === 'ricarica' || $tipoNome === 'versamento') {
        //ricariche e versamenti
        $queryDett = $conn->prepare("
            SELECT 
                m.importo, 
                m.data,
                tm.nome AS tipo_nome,
                tm.segno,
                u.nome AS nome_titolare,
                u.cognome AS cognome_titolare,
                c.iban
            FROM movimenti m
            JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
            JOIN conto c ON m.id_conto = c.id
            JOIN user u ON c.user_id = u.id
            WHERE m.id = ?
        ");
        $queryDett->bind_param("i", $idMovimento);
        $queryDett->execute();
        $res = $queryDett->get_result();

        if ($row = $res->fetch_assoc()) {
            echo "<div class='row'>";
            echo "<div class='col-md-12'>";
            echo "<h6>Informazioni " . htmlspecialchars($row['tipo_nome']) . "</h6>";
            echo "<p><strong>Data:</strong> " . htmlspecialchars($row['data']) . "</p>";
            echo "<p><strong>Tipo:</strong> " . htmlspecialchars($row['tipo_nome']) . "</p>";
            echo "<p><strong>Importo:</strong> <span class='text-success'>";
            echo $row['segno'] . " € " . number_format($row['importo'], 2, ',', '.') . "</span></p>";
            echo "<p><strong>IBAN Conto:</strong> " . htmlspecialchars($row['iban']) . "</p>";
            echo "<p><strong>Beneficiario:</strong> " . htmlspecialchars($row['nome_titolare'] . ' ' . $row['cognome_titolare']) . "</p>";
            if ($tipoNome === 'ricarica') {
                echo "<p><em>Ricarica effettuata sul conto corrente</em></p>";
            } else {
                echo "<p><em>Versamento effettuato sul conto corrente</em></p>";
            }
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-warning'>Nessun dettaglio disponibile per questo " . htmlspecialchars($tipoNome) . ".</div>";
        }
        $queryDett->close();
        
    } else {
        // Altri tipi di movimento generici
        $queryDett = $conn->prepare("
            SELECT 
                m.importo, 
                m.data,
                tm.nome AS tipo_nome,
                tm.segno,
                u.nome AS nome_titolare,
                u.cognome AS cognome_titolare,
                c.iban
            FROM movimenti m
            JOIN tipologia_movimento tm ON m.tipo_movimento = tm.id
            JOIN conto c ON m.id_conto = c.id
            JOIN user u ON c.user_id = u.id
            WHERE m.id = ?
        ");
        $queryDett->bind_param("i", $idMovimento);
        $queryDett->execute();
        $res = $queryDett->get_result();

        if ($row = $res->fetch_assoc()) {
            echo "<div class='row'>";
            echo "<div class='col-md-12'>";
            echo "<h6>Informazioni Movimento</h6>";
            echo "<p><strong>Data:</strong> " . htmlspecialchars($row['data']) . "</p>";
            echo "<p><strong>Tipo:</strong> " . htmlspecialchars($row['tipo_nome']) . "</p>";
            echo "<p><strong>Importo:</strong> <span class='" . ($row['segno'] === '+' ? 'text-success' : 'text-danger') . "'>";
            echo $row['segno'] . " € " . number_format($row['importo'], 2, ',', '.') . "</span></p>";
            echo "<p><strong>IBAN:</strong> " . htmlspecialchars($row['iban']) . "</p>";
            echo "<p><strong>Titolare:</strong> " . htmlspecialchars($row['nome_titolare'] . ' ' . $row['cognome_titolare']) . "</p>";
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-warning'>Nessun dettaglio disponibile per questo movimento.</div>";
        }
        $queryDett->close();
    }
    
    echo "</div>";
}

$conn->close();
?>