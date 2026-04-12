<?php 

require_once("config.php");
require_once("menu.php"); 

// 1. Récupération de l'ID (Vulnérable IDOR - A01:2021) [cite: 63, 65]
$user_id = isset($_GET['u']) ? intval($_GET['u']) : 0;

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Erreur de connexion");
}

// --- LOGIQUE DE MISE À JOUR (VULNÉRABLE CSRF & SSRF PERSISTANT) [cite: 442, 618] ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Action Email : Cible parfaite pour un atelier CSRF 
    if (isset($_POST['update_email'])) {

        $new_email = $_POST['email'];
        // Pas de token Anti-CSRF : Faille A10:2021 [cite: 619, 648]
        $conn->query("UPDATE account SET email = '$new_email' WHERE id = $user_id");
    }

    // Action Avatar : Cible parfaite pour SSRF (A10:2021) [cite: 576, 582]
    if (isset($_POST['update_avatar'])) {
        $url = $_POST['avatar_url'];
        // On enregistre l'URL brute sans validation : SSRF Persistant 
        $conn->query("UPDATE account SET avatar_url = '$url' WHERE id = $user_id");
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
$sql_user = "SELECT * FROM account WHERE id = $user_id";
$res_user = $conn->query($sql_user);
$user = $res_user->fetch_assoc();

// --- TRAITEMENT SSRF : Le serveur devient l'émetteur de la requête ---
$avatar_content = "";
if ($user && !empty($user['avatar_url'])) {
    // Faille critique : file_get_contents sur une URL utilisateur
    // Permet de scanner le localhost ou d'accéder au cloud metadata 

    $url = $user['avatar_url'];
    $avatar_content = file_get_contents($url);

}

// Articles panier
$sql_cart = "SELECT SUM(quantity) as total_qty FROM cart WHERE user_id = $user_id";
$res_cart = $conn->query($sql_cart);
$cart_data = $res_cart->fetch_assoc();
$nb_articles = $cart_data['total_qty'] ?? 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil de <?php echo htmlspecialchars($user['name'] ?? 'Inconnu'); ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; color: #333; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .profile-header { text-align: center; border-bottom: 2px solid #f4f7f6; margin-bottom: 30px; padding-bottom: 20px; }
        .profile-avatar { width: 120px; height: 120px; background-color: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 15px; overflow: hidden; }
        .info-card { background: #f8f9fa; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #3498db; }
        .label { color: #7f8c8d; font-size: 12px; text-transform: uppercase; display: block; }
        .value { font-size: 18px; font-weight: bold; color: #2c3e50; }
        .stats-banner { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .btn-action { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="text"], input[type="email"] { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 60%; }
        h2 { border-bottom: 1px solid #eee; padding-bottom: 10px; color: #2c3e50; }
    </style>
</head>
<body>

<div class="container">
    <?php if ($user): ?>
        <h2>⚙️ Paramètres du compte</h2>
        
        <form method="POST" class="info-card" style="border-left-color: #3498db;">
            <span class="label">Mettre à jour l'email (Cible CSRF)</span>
            
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            <button type="submit" name="update_email" class="btn-action">Valider</button>
        </form>

        <form method="POST" class="info-card" style="border-left-color: #e67e22;">
            <span class="label">URL de l'avatar (Cible SSRF)</span>
            <input type="text" name="avatar_url" placeholder="http://images.com/photo.jpg" value="<?php echo htmlspecialchars($user['avatar_url'] ?? ''); ?>">
            <button type="submit" name="update_avatar" class="btn-action" style="background:#e67e22;">Actualiser</button>
        </form>

        <hr>

        <div class="profile-header">
            <div class="profile-avatar">
                <?php if ($avatar_content): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($avatar_content); ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <h1><?php echo htmlspecialchars($user['name']); ?></h1>
            <p style="color: #95a5a6;">ID Utilisateur : <?php echo $user_id; ?></p>
        </div>

        <div class="info-card">
            <span class="label">Adresse Email actuelle</span>
            <span class="value"><?php echo htmlspecialchars($user['email']); ?></span>
        </div>

        <div class="stats-banner">
            <span>🛒 <strong><?php echo $nb_articles; ?></strong> article(s) dans le panier</span>
            <a href="panier.php?u=<?php echo $user_id; ?>" style="color: #155724; font-weight: bold; text-decoration:none;">Gérer le panier →</a>
        </div>

    <?php else: ?>
        <div style="text-align: center; padding: 50px;">
            <h2>❌ Utilisateur non trouvé</h2>
            <a href="home.php" class="btn-action">Retour</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>