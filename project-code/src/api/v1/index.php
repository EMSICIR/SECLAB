<?php

header("Content-Type: application/json");

// Inclusion de ton fichier de configuration
require_once __DIR__ . '/../config.php'; 

// Initialisation de la connexion PDO avec tes variables
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Connexion échouée : " . $e->getMessage()]);
    exit;
}

// Récupération de l'URL via le .htaccess
$urlPath = $_GET['url'] ?? '';
$urlSegments = explode('/', rtrim($urlPath, '/'));

$resource = $urlSegments[0] ?? null; 
$id = $urlSegments[1] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// --- ROUTAGE ---
if ($resource === 'product') {
    handleProduct($conn, $method, $id);
} elseif ($resource === 'user') {
    handleAccount($conn, $method, $id);
} else {
    http_response_code(404);
    echo json_encode(["message" => "Ressource non reconnue"]);
}

// --- LOGIQUE PRODUITS ---
function handleProduct($conn, $method, $id) {
    if ($method === 'GET') {
        // VULNÉRABILITÉ SQLi : Injection possible dans l'URL
        $sql = $id ? "SELECT * FROM product WHERE id = $id" : "SELECT * FROM product";
        $result = $conn->query($sql);
        if ($result) {
            // fetch_all permet de voir TOUTES les lignes (indispensable pour l'UNION)
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } else {
            // On affiche l'erreur SQL : très utile pour aider l'attaquant à ajuster son injection
            echo json_encode(["sql_error" => $conn->error, "query" => $sql]);
        }
    } 
    elseif ($method === 'DELETE') {
        // VULNÉRABILITÉ BOLA + SQLi : 
        // 1. N'importe qui peut supprimer (BOLA)
        // 2. On peut injecter du SQL dans le DELETE (ex: id=1 OR 1=1 pour tout supprimer)
        $sql = "DELETE FROM product WHERE id = $id";
        
        if ($conn->query($sql)) {
            echo json_encode(["message" => "Requête exécutée : $sql"]);
        } else {
            echo json_encode(["error" => $conn->error]);
        }
    }
}

// --- LOGIQUE account ---
function handleAccount($conn, $method, $id) {
    if ($method === 'POST') {
        // Récupération du JSON brut
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(["error" => "JSON invalide"]);
            return;
        }

        // --- VULNÉRABILITÉ : MASS ASSIGNMENT ---
        // On construit la liste des colonnes à partir des clés du JSON
        $columns = implode(", ", array_keys($input));
        
        // --- VULNÉRABILITÉ : SQL INJECTION (POST) ---
        // On entoure chaque valeur par des simples quotes sans aucun nettoyage
        $values = "'" . implode("', '", array_values($input)) . "'";
        
        $sql = "INSERT INTO account ($columns) VALUES ($values)";
        
        // Exécution directe avec mysqli (pas de prepare)
        if ($conn->query($sql)) {
            echo json_encode([
                "message" => "Utilisateur créé",
                "debug_query" => $sql // Pour que l'étudiant voie ce qu'il a généré
            ]);
        } else {
            echo json_encode(["error" => $conn->error]);
        }
    } 
    elseif ($method === 'GET') {
        // --- VULNÉRABILITÉ : SQL INJECTION (GET) ---
        // Concaténation directe de l'ID dans la requête
        $sql = $id ? "SELECT * FROM account WHERE id = $id" : "SELECT * FROM account";
        
        $result = $conn->query($sql);
        
        if ($result) {
            // Avec mysqli, on utilise fetch_all pour récupérer toutes les lignes
            // (Utile pour voir le résultat d'un UNION SELECT)
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } else {
            echo json_encode(["sql_error" => $conn->error]);
        }
    }
    elseif ($method === 'PUT') {
        // 1. Récupération de l'ID depuis l'URL (ex: /api/user/5)
        if (!$id) {
            echo json_encode(["error" => "ID utilisateur manquant"]);
            exit;
        }

        // 2. Lecture du JSON envoyé par Postman
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(["error" => "JSON invalide"]);
            exit;
        }

        // --- LA FAILLE BOPLA / MASS ASSIGNMENT ---
        // On construit dynamiquement la requête SET à partir de TOUTES les clés du JSON
        $updates = [];
        foreach ($input as $key => $value) {
            // VULNÉRABILITÉ : On ne filtre pas les colonnes autorisées !
            $updates[] = "$key = '$value'"; 
        }
        $queryStr = implode(", ", $updates);

        $sql = "UPDATE account SET $queryStr WHERE id = $id";

        // 3. Exécution
        if ($conn->query($sql)) {
            echo json_encode([
                "status" => "Profil mis à jour",
                "debug_query" => $sql // Très important pour que l'étudiant comprenne
            ]);
        } else {
            echo json_encode(["error" => $conn->error]);
        }
    }
}