<?php
session_start();
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

require_once 'vendor/autoload.php';
$pdo = getConnexion();

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// ===== SEARCH & FILTER PARAMETERS =====
$search = isset($_GET['search']) && !empty($_GET['search']) ? trim($_GET['search']) : '';
$categorie = isset($_GET['categorie']) && !empty($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$statut = isset($_GET['statut']) && !empty($_GET['statut']) ? $_GET['statut'] : 'actif';
$page = isset($_GET['page']) && !empty($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// ===== BUILD QUERY =====
$where = "WHERE s.statut = 'actif'";
$params = [];

if (!empty($search)) {
    $where .= " AND (s.nomService LIKE ? OR s.descriptionService LIKE ? OR s.adresse LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($categorie > 0) {
    $where .= " AND s.idCateg = ?";
    $params[] = $categorie;
}

// ===== GET TOTAL COUNT =====
$countQuery = "SELECT COUNT(*) as total FROM service s " . $where;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $perPage);

// ===== GET SERVICES WITH PAGINATION =====
$query = "
    SELECT s.*, c.label AS nomCateg,
           COUNT(sa.idUtilisateur) AS nbAgents
    FROM service s
    LEFT JOIN categorie c ON s.idCateg = c.idCateg
    LEFT JOIN service_agent sa ON s.idService = sa.idService
    $where
    GROUP BY s.idService
    ORDER BY s.nomService
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== GET ALL CATEGORIES FOR FILTER =====
$categories = $pdo->query("
    SELECT idCateg, label
    FROM categorie
    ORDER BY label
")->fetchAll(PDO::FETCH_ASSOC);

// ===== GET STATISTICS =====
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT idCateg) as categories
    FROM service
    WHERE statut = 'actif'
")->fetch(PDO::FETCH_ASSOC);

// ===== SUCCESS/ERROR MESSAGES =====
$successMsg = $_SESSION['success'] ?? '';
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Header config
$headerConfig = [
    'title' => 'Services Municipaux',
    'subtitle' => 'Consultez les services offerts dans votre région',
    'icon' => '🏢',
    'role' => 'Citoyen',
    'profileLink' => './profil.php',
    'bgGradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>


<!-- ===== SUCCESS/ERROR MESSAGES ===== -->
<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success">
        ✅ <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-error">
        ❌ <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- ===== STATISTICS BANNER ===== -->
<div class="stats-banner">
    <div class="stat-item">
        <h3><?= $stats['total'] ?></h3>
        <p>Services disponibles</p>
    </div>
    <div class="stat-item">
        <h3><?= $stats['categories'] ?></h3>
        <p>Catégories</p>
    </div>
</div>
<!-- ===== HISTORIQUE DES DEMANDES ===== -->
<div class="history-button-container">
    <a href="./mes_demandes_services.php" class="btn btn-history">
        📜 Mes demandes
    </a>
</div>


<!-- ===== SEARCH & FILTER BAR ===== -->
<div class="search-filter-section">
    <h2>🔍 Rechercher un Service</h2>
    
    <form method="GET" class="search-form">
        <div class="search-row">
            <div class="search-group">
                <input type="text" name="search" placeholder="Rechercher par nom, description..." 
                       value="<?= htmlspecialchars($search) ?>" class="search-input">
            </div>
            
            <div class="filter-group">
                <label for="categorie">Catégorie:</label>
                <select name="categorie" id="categorie" class="filter-select">
                    <option value="">-- Toutes les catégories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['idCateg'] ?>" 
                                <?= $categorie == $cat['idCateg'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <button type="submit" class="btn btn-search">🔍 Rechercher</button>
                <a href="./services_publiques.php" class="btn btn-reset">✕ Réinitialiser</a>
            </div>
        </div>
    </form>
    
    <?php if (!empty($search) || $categorie > 0): ?>
        <p class="filter-info">
            📊 <?= $total ?> service(s) trouvé(s)
            <?php if (!empty($search)): ?> pour "<strong><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
            <?php if ($categorie > 0): ?> en catégorie "<strong><?= htmlspecialchars($categories[array_search($categorie, array_column($categories, 'idCateg'))]['label'] ?? '') ?></strong>"<?php endif; ?>
        </p>
    <?php endif; ?>
</div>

<!-- ===== SERVICES GRID ===== -->
<div class="services-section">
    <h2>📋 Services Disponibles</h2>
    
    <?php if (empty($services)): ?>
        <div class="no-results">
            <p>📭 Aucun service ne correspond à votre recherche.</p>
            <a href="./services_publiques.php" class="btn btn-primary">Voir tous les services</a>
        </div>
    <?php else: ?>
        <div class="services-grid">
            <?php foreach ($services as $service): 
                $agents = $pdo->query("
                    SELECT u.idUtilisateur, u.nom, u.prenom, u.email
                    FROM service_agent sa
                    JOIN utilisateur u ON sa.idUtilisateur = u.idUtilisateur
                    WHERE sa.idService = {$service['idService']}
                ")->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <div class="service-card">
                    <div class="service-header">
                        <h3><?= htmlspecialchars($service['nomService']) ?></h3>
                        <span class="category-badge"><?= htmlspecialchars($service['nomCateg'] ?? 'Non catégorisé') ?></span>
                    </div>
                    
                    <div class="service-body">
                        <!-- Description -->
                        <p class="description">
                            <?= htmlspecialchars(strlen($service['descriptionService']) > 100 
                                ? substr($service['descriptionService'], 0, 100) . '...' 
                                : $service['descriptionService']) ?>
                        </p>
                        
                        <!-- Details -->
                        <div class="service-details">
                            <?php if (!empty($service['adresse'])): ?>
                                <p><strong>📍 Adresse:</strong> <?= htmlspecialchars($service['adresse']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($service['telephone'])): ?>
                                <p><strong>📞 Téléphone:</strong> 
                                    <a href="tel:<?= htmlspecialchars($service['telephone']) ?>">
                                        <?= htmlspecialchars($service['telephone']) ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($service['email'])): ?>
                                <p><strong>📧 Email:</strong> 
                                    <a href="mailto:<?= htmlspecialchars($service['email']) ?>">
                                        <?= htmlspecialchars($service['email']) ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($service['horaire_debut']) && !empty($service['horaire_fin'])): ?>
                                <p><strong>⏰ Horaires:</strong> <?= htmlspecialchars($service['horaire_debut']) ?> - <?= htmlspecialchars($service['horaire_fin']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($service['jours_ouverture'])): ?>
                                <p><strong>📅 Jours:</strong> <?= htmlspecialchars($service['jours_ouverture']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Agents -->
                        <?php if (!empty($agents)): ?>
                            <details class="agents-details">
                                <summary style="cursor: pointer; color: #667eea; font-weight: 600;">
                                    👥 Agents responsables (<?= count($agents) ?>)
                                </summary>
                                <ul style="list-style: none; padding: 10px 0 0 0; margin: 0;">
                                    <?php foreach ($agents as $agent): ?>
                                        <li style="padding: 5px 0; color: #555;">
                                            ✓ <?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?>
                                            <br><small style="color: #999;"><?= htmlspecialchars($agent['email']) ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </div>
                    
                    <div class="service-actions">
                        <a href="./detail_service.php?id=<?= $service['idService'] ?>" class="btn btn-small btn-info">
                            👁️ Voir détails
                        </a>
                        <button onclick="openQRModal(<?= $service['idService'] ?>, '<?= htmlspecialchars($service['nomService']) ?>')" 
                                class="btn btn-small btn-qr">
                            🔲 QR Code
                        </button>
                        <a href="./demander_service.php?service=<?= $service['idService'] ?>" class="btn btn-small btn-primary">
                            ➕ Demander ce service
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- ===== PAGINATION ===== -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categorie > 0 ? '&categorie=' . $categorie : '' ?>" class="btn btn-page">⬅️ Première</a>
                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categorie > 0 ? '&categorie=' . $categorie : '' ?>" class="btn btn-page">← Précédent</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="btn btn-page active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categorie > 0 ? '&categorie=' . $categorie : '' ?>" class="btn btn-page"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categorie > 0 ? '&categorie=' . $categorie : '' ?>" class="btn btn-page">Suivant →</a>
                    <a href="?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categorie > 0 ? '&categorie=' . $categorie : '' ?>" class="btn btn-page">Dernière ➡️</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ===== MAP SECTION ===== -->
<div class="map-section">
    <h2>🗺️ Carte des Services Municipaux</h2>
    <p>Visualisez les services disponibles dans votre région</p>
    <div id="map" style="width: 100%; height: 500px; border-radius: 8px; margin-top: 20px;"></div>
</div>

<!-- ===== QR CODE MODAL ===== -->
<div id="qrModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeQRModal()">&times;</span>
        <h2>🔲 QR Code du Service</h2>
        <div id="qrContent"></div>
    </div>
</div>

<!-- ===== BACK LINK ===== -->
<a href="./citoyen.php" class="btn btn-secondary" style="margin-top: 20px;">
    ← Retour au tableau de bord
</a>

<!-- ===== STYLES ===== -->
<style>
.stats-banner {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0 30px 0;
}

.stat-item {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.stat-item h3 {
    margin: 0;
    font-size: 36px;
    font-weight: 700;
}

.stat-item p {
    margin: 8px 0 0 0;
    font-size: 14px;
    opacity: 0.9;
}

.search-filter-section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.search-filter-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.search-row {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 15px;
    align-items: flex-end;
}

.search-group, .filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.search-input, .filter-select {
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
}

.search-input:focus, .filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-group label {
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.filter-group {
    display: flex;
    gap: 8px;
}

.filter-group .btn {
    padding: 8px 16px;
    font-size: 13px;
}

.btn-search {
    background: #667eea;
    color: white;
}

.btn-search:hover {
    background: #5568d3;
}

.btn-reset {
    background: #6c757d;
    color: white;
}

.filter-info {
    margin-top: 15px;
    padding: 12px;
    background: #e7f3ff;
    border-left: 4px solid #667eea;
    color: #0066cc;
    font-size: 14px;
    border-radius: 4px;
}

.services-section {
    margin-top: 30px;
}

.services-section h2 {
    margin-bottom: 20px;
    color: #333;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.service-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.service-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    transform: translateY(-4px);
}

.service-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.service-header h3 {
    margin: 0;
    font-size: 18px;
    flex: 1;
}

.category-badge {
    background: rgba(255, 255, 255, 0.3);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    white-space: nowrap;
}

.service-body {
    padding: 15px;
    flex: 1;
    overflow: hidden;
}

.description {
    font-size: 13px;
    color: #666;
    line-height: 1.5;
    margin-bottom: 12px;
}

.service-details {
    font-size: 12px;
    color: #555;
    line-height: 1.6;
    margin-bottom: 12px;
}

.service-details p {
    margin: 6px 0;
}

.service-details a {
    color: #667eea;
    text-decoration: none;
}

.service-details a:hover {
    text-decoration: underline;
}

.agents-details {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.service-actions {
    padding: 12px 15px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-small {
    padding: 6px 12px;
    font-size: 12px;
    flex: 1;
    text-align: center;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-qr {
    background: #ffc107;
    color: #333;
}

.btn-primary {
    background: #28a745;
    color: white;
}

.btn-primary:hover {
    background: #218838;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
}

.no-results p {
    font-size: 18px;
    color: #999;
    margin-bottom: 20px;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn-page {
    padding: 8px 12px;
    background: white;
    border: 1px solid #ddd;
    color: #333;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-page:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.btn-page.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.map-section {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-top: 30px;
    margin-bottom: 30px;
}

.map-section h2 {
    margin-top: 0;
    margin-bottom: 10px;
}

.map-section p {
    color: #666;
    margin-bottom: 15px;
}

.alert {
    padding: 15px;
    margin: 20px 0;
    border-radius: 8px;
    animation: slideDown 0.3s ease;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 25px;
    border-radius: 8px;
    width: 90%;
    max-width: 400px;
    text-align: center;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

@media (max-width: 768px) {
    .search-row {
        grid-template-columns: 1fr;
    }
    
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .service-actions {
        flex-direction: column;
    }
    
    .btn-small {
        width: 100%;
    }
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
/* ===== HISTORIQUE DES DEMANDES ===== */
.history-button-container {
    display: flex;
    justify-content: flex-end;
    margin: 10px 0 25px 0;
}

.btn-history {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    padding: 12px 22px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    transition: all 0.3s ease;
}

.btn-history:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
}
</style>

<!-- ===== LEAFLET MAP LIBRARY ===== -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<script>
// Initialize map
// Initialize map
let map;
function initMap() {
    // Coordonnées Tunisie : Tunis
    const tunisLatLng = [36.8065, 10.1667];
    
    map = L.map('map').setView(tunisLatLng, 8); // zoom level 8 pour voir la Tunisie
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Add service markers
    const services = <?php echo json_encode($services); ?>;
    services.forEach(service => {
        if (service.adresse) {
            // Random coordinates around Tunisia (not France!)
            const lat = tunisLatLng[0] + (Math.random() - 0.5) * 1.5;
            const lng = tunisLatLng[1] + (Math.random() - 0.5) * 1.5;
            
            const marker = L.marker([lat, lng]).addTo(map);
            marker.bindPopup(`
                <strong>${service.nomService}</strong><br>
                ${service.nomCateg}<br>
                📍 ${service.adresse}<br>
                <a href="detail_service.php?id=${service.idService}">Voir détails →</a>
            `);
        }
    });
}
// QR Code Modal
function openQRModal(serviceId, serviceName) {
    const qrContent = document.getElementById('qrContent');
    qrContent.innerHTML = '<p>Génération du QR code...</p>';
    
    fetch(`generate_qr.php?service=${serviceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                qrContent.innerHTML = `
                    <p style="margin-bottom: 15px;"><strong>${serviceName}</strong></p>
                    <img src="${data.qr}" alt="QR Code" style="width: 250px; height: 250px; border: 2px solid #667eea; border-radius: 8px;">
                    <p style="margin-top: 15px; font-size: 12px; color: #666;">Scannez ce code pour voir les détails du service</p>
                    <a href="${data.qr}" download="service-${serviceId}.png" class="btn btn-primary" style="margin-top: 10px;">🖨️ Télécharger</a>
                `;
            }
        })
        .catch(error => {
            qrContent.innerHTML = '<p style="color: red;">Erreur lors de la génération du QR code</p>';
        });
    
    document.getElementById('qrModal').style.display = 'block';
}

function closeQRModal() {
    document.getElementById('qrModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('qrModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Initialize map on page load
document.addEventListener('DOMContentLoaded', initMap);
</script>