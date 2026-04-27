<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php';

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\GraphQL;

$conn = new mysqli($servername, $username, $password, $dbname);

// --- DÉFINITION DES TYPES ---

// Type Produit
$productType = new ObjectType([
    'name' => 'Product',
    'fields' => [
        'id' => Type::int(),
        'label' => Type::string(),
        'price' => Type::float(),
        'description' => Type::string(),
    ]
]);

// Type Panier (Lien entre User et Product)
$cartType = new ObjectType([
    'name' => 'Cart',
    'fields' => [
        'id' => Type::int(),
        'quantity' => Type::int(),
        'product' => [
            'type' => $productType,
            'resolve' => function ($cart, $args, $context) use ($conn) {
                $productId = $cart['product_id'];
                $result = $conn->query("SELECT * FROM product WHERE id = $productId");
                return $result->fetch_assoc();
            }
        ]
    ]
]);

// Type Utilisateur (Modifié pour les Nested Queries)
$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::int(),
        'name' => Type::string(),
        'email' => Type::string(),
        'profile' => Type::string(),
        'password' => Type::string(),
        // NESTED QUERY : On ajoute le panier dans l'utilisateur
        'cart' => [
            'type' => Type::listOf($cartType),
            'resolve' => function ($user) use ($conn) {
                $userId = $user['id'];
                $result = $conn->query("SELECT * FROM cart WHERE user_id = $userId");
                return $result->fetch_all(MYSQLI_ASSOC);
            }
        ]
    ]
]);

// --- DÉFINITION DE LA QUERY RACINE ---

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'users' => [ // Changement de 'user' vers 'users' pour la cohérence
            'type' => Type::listOf($userType),
            'args' => [
                'id' => Type::int(),
                'name' => Type::string(),
            ],
            'resolve' => function ($rootValue, $args) use ($conn) {
                if (isset($args['id'])) {
                    $id = $args['id'];
                    $query = "SELECT * FROM account WHERE id = $id";
                } else if (isset($args['name'])) {
                    $name = $args['name'];
                    // VULNÉRABILITÉ INJECTION SQL : Les guillemets permettent de casser la chaîne
                    $query = "SELECT * FROM account WHERE name = '$name'";
                } else {
                    $query = "SELECT * FROM account";
                }
                
                $result = $conn->query($query);
                return $result->fetch_all(MYSQLI_ASSOC);
            }
        ],
    ],
]);

$schema = new Schema(['query' => $queryType]);

// --- EXÉCUTION ---
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    $result = GraphQL::executeQuery($schema, $query);
    $output = $result->toArray();
} catch (\Exception $e) {
    $output = ['error' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($output);  