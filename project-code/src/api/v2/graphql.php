<?php
// On charge l'autoloader généré par Composer dans le Dockerfile
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php';

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\GraphQL;

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Définition du Type "Compte" (VULNERABILITE : On expose trop de champs)
$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::int(),
        'name' => Type::string(),
        'email' => Type::string(),
        'role' => Type::string(),      // Champ sensible exposé
        'password' => Type::string(),  // TRES sensible, exposé par erreur
    ]
]);

// Définition de la Query
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'user' => [
            'type' => Type::listOf($userType), //$userType,
            'args' => [
                'id' => Type::int(),
                'name' => Type::string(),
            ],
            'resolve' => function ($rootValue, $args) use ($conn) {
                // Utilisation de ta connexion mysqli globale
                if (isset($args['id'])) {
                    $id = $args['id'];
                    $result = $conn->query("SELECT * FROM account WHERE id = $id");
                } else if (isset($args['name'])) {
                    $name = $args['name'];
                    // VULNÉRABILITÉ : Injection SQL possible ici si $name n'est pas protégé
                    $result = $conn->query("SELECT * FROM account WHERE name = '$name'");
                }
                $all_users = [];
                while ($row = $result->fetch_assoc()) {
                    $all_users[] = $row;
                }
                return $all_users;
            }
        ],
    ],
]);

$schema = new Schema(['query' => $queryType]);

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $query = $input['query'] ?? '';
    
    $result = GraphQL::executeQuery($schema, $query);
    $output = $result->toArray();
} catch (\Exception $e) {
    $output = ['error' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($output);