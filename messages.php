<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes/MessageManager.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();
$pdo = getConnexion();
$messageManager = new MessageManager($pdo);

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// ⚠️ À adapter : méthode pour obtenir toutes les conversations de l’utilisateur
$conversations = []; // Remplace par ta méthode réelle
$unreadCounts = $messageManager->compterNonLusParConversation($user['idUtilisateur']);
$totalUnread = $messageManager->compterNonLus($user['idUtilisateur']);

$unreadByConv = [];
foreach ($unreadCounts as $count) {
    $key = ($count['idRec'] ?? 'null') . '_' . ($count['idService'] ?? 'null') . '_' . ($count['idRequest'] ?? 'null');
    $unreadByConv[$key] = (int) $count['nb_non_lu'];
}

$headerConfig = [
    'title' => 'Messagerie',
    'subtitle' => 'Échanges entre citoyens et agents municipaux',
    'icon' => '💬',
    'role' => ucfirst($user['role']),
    'profileLink' => './profil.php',
    'bgGradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
];
require_once 'includes/dashboard_header.php';
?>

<div class="messages-container">
    <div class="messages-topbar">
        <h2>Mes conversations</h2>
        <?php if ($user['role'] === 'citoyen'): ?>
            <a href="./citoyen_services.php" class="btn btn-primary">Mes demandes</a>
        <?php else: ?>
            <a href="./agent_services.php" class="btn btn-primary">Demandes à traiter</a>
        <?php endif; ?>
    </div>

    <?php if ($totalUnread > 0): ?>
        <div class="alert-info">Vous avez <strong><?= $totalUnread ?></strong> message(s) non lu(s).</div>
    <?php endif; ?>

    <!-- Liste des conversations -->
    <div class="conversations-list">
        <?php foreach ($conversations as $conv): ?>
            <div class="conversation-item" data-conv-id="<?= (int) $conv['idConversation'] ?>">
                <h3><?= htmlspecialchars($conv['autrePrenom'].' '.$conv['autreNom']) ?></h3>
                <p><?= htmlspecialchars($conv['dernier_message'] ?? '') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Bulle de chat -->
<div id="chat-bubble">💬</div>

<!-- Fenêtre de chat -->
<div id="chat-box" class="hidden">
  <div id="chat-header">
    <h3>Conversation</h3>
    <button id="close-chat">✖</button>
  </div>
  <div id="chat-messages"></div>
  <form id="chat-form">
    <input type="text" name="contenu" id="contenu" placeholder="Votre message..." maxlength="2000" required>
    <button type="submit">Envoyer</button>
  </form>
</div>

<style>
/* Bulle de chat */
#chat-bubble {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background: #667eea;
  color: white;
  border-radius: 50%;
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 24px;
  box-shadow: 0 4px 10px rgba(0,0,0,.2);
}

/* Fenêtre de chat */
#chat-box {
  position: fixed;
  bottom: 90px;
  right: 20px;
  width: 320px;
  height: 420px;
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
}
#chat-box.hidden { display: none; }

#chat-header {
  background: #667eea;
  color: white;
  padding: 10px;
  display: flex;
  justify-content: space-between;
}

#chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
}

.message {
  margin: 5px 0;
  padding: 8px;
  border-radius: 6px;
  max-width: 80%;
}
.message.sent {
  background: #667eea;
  color: white;
  margin-left: auto;
}
.message.received {
  background: #f1f1f1;
  color: #333;
  margin-right: auto;
}

#chat-form {
  display: flex;
  padding: 10px;
  gap: 5px;
}
#chat-form input {
  flex: 1;
  padding: 8px;
}
</style>

<script>
let currentConvId = null;

// Ouvrir/fermer le chat
document.getElementById('chat-bubble').addEventListener('click', () => {
  document.getElementById('chat-box').classList.remove('hidden');
});
document.getElementById('close-chat').addEventListener('click', () => {
  document.getElementById('chat-box').classList.add('hidden');
});

// Quand on clique sur une conversation
document.querySelectorAll('.conversation-item').forEach(item => {
  item.addEventListener('click', () => {
    currentConvId = item.dataset.convId;
    document.getElementById('chat-box').classList.remove('hidden');
    loadMessages();
  });
});

// Charger les messages
function loadMessages() {
  if (!currentConvId) return;
  fetch('get_messages.php?id_conv=' + currentConvId)
    .then(res => res.json())
    .then(data => {
      const container = document.getElementById('chat-messages');
      container.innerHTML = '';
      data.forEach(msg => {
        const div = document.createElement('div');
        div.classList.add('message');
        div.classList.add(msg.idExpediteaur == <?= $user['idUtilisateur'] ?> ? 'sent' : 'received');
        div.textContent = msg.prenom + ' : ' + msg.contenu;
        container.appendChild(div);
      });
      container.scrollTop = container.scrollHeight;
    });
}
setInterval(loadMessages, 2000);

// Envoi AJAX
document.getElementById('chat-form').addEventListener('submit', e => {
  e.preventDefault();
  if (!currentConvId) return;
  fetch('send_message.php?id_conv=' + currentConvId, {
    method: 'POST',
    body: new FormData(e.target)
  }).then(() => {
    document.getElementById('contenu').value = '';
    loadMessages();
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>
