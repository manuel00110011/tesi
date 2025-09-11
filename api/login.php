<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include("connessione.php");

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST["username"];
    $password = $_POST["password"];
   $password_hash = hash('sha256', $password);
    $sql = "SELECT * FROM User WHERE numero_conto = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password_hash);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {

            $_SESSION['loggedin'] = true;
            $user = $result->fetch_assoc();
            $_SESSION['id_utente'] = $user['id'];
            $_SESSION['nome_utente'] = $user['nome'];
            echo json_encode(array(
                "success" => true,
                "id_utente" => $_SESSION['id_utente'],
                "nome_utente" => $_SESSION['nome_utente']
            ));
        } else {
            echo json_encode(array("success" => false, "error" => "Credenziali errate"));
        }
    } else {
        echo json_encode(array("success" => false, "error" => "Errore durante l'autenticazione"));
    }

    $stmt->close();
} else {
    echo json_encode(array("success" => false, "error" => "Metodo non supportato"));
}
$conn->close();
?>

