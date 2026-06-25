<?php
// admin/dashboard.php
require_once '../config.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: index.php');
    exit;
}

// Подключаемся к почте
$imap = connectIMAP();

// Получаем письма
$search = isset($_GET['search']) ? imap_utf7_encode($_GET['search']) : '';
$sectorFilter = $_GET['sector'] ?? '';

if ($search) {
    $emails = imap_search($imap, "SUBJECT \"{$search}\"");
} else {
    $emails = imap_search($imap, 'ALL');
}

// Сортируем по убыванию (новые сверху)
if ($emails) {
    rsort($emails);
}

$consultations = [];
if ($emails) {
    foreach ($emails as $msgNumber) {
        $email = parseEmail($imap, $msgNumber);
        
        // Фильтр по сектору
        if ($sectorFilter && stripos($email['sector'], $sectorFilter) === false) {
            continue;
        }
        
        $consultations[] = $email;
    }
}

imap_close($imap);

$sectorNames = [
    'international' => 'Международный сектор',
    'family' => 'Семейно-правовой сектор',
    'social' => 'Социально-правовой сектор',
    'judicial' => 'Судебный блок'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет юриста - deti.gov.ru</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Сайдбар -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">⚖️</div>
                <h2>Консультации</h2>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <span class="nav-icon">📥</span>
                    <span>Все обращения</span>
                    <span class="badge"><?= count($consultations) ?></span>
                </a>
                
                <div class="nav-section">По секторам</div>
                
                <?php foreach ($sectorNames as $key => $name): ?>
                <a href="dashboard.php?sector=<?= $key ?>" class="nav-item">
                    <span class="nav-icon">📁</span>
                    <span><?= $name ?></span>
                </a>
                <?php endforeach; ?>
                
                <div class="nav-section">Система</div>
                
                <a href="logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span>
                    <span>Выйти</span>
                </a>
            </nav>
        </aside>

        <!-- Основной контент -->
        <main class="main-content">
            <header class="topbar">
                <h1>Обращения</h1>
                <div class="topbar-actions">
                    <form method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Поиск по номеру или теме..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit" class="btn btn-icon">🔍</button>
                    </form>
                    <button class="btn btn-refresh" onclick="location.reload()">🔄</button>
                </div>
            </header>

            <div class="content">
                <?php if (empty($consultations)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3>Нет обращений</h3>
                    <p>Новые консультации появятся здесь автоматически</p>
                </div>
                <?php else: ?>
                
                <div class="consultations-list">
                    <?php foreach ($consultations as $consult): ?>
                    <div class="consultation-card <?= $consult['seen'] ? 'seen' : 'new' ?>" 
                         data-msg="<?= $consult['msgNumber'] ?>">
                        
                        <div class="consultation-header">
                            <div class="consultation-number">
                                <?= htmlspecialchars($consult['consultationNumber']) ?>
                            </div>
                            <div class="consultation-date"><?= $consult['date'] ?></div>
                        </div>
                        
                        <div class="consultation-body">
                            <div class="consultation-meta">
                                <span class="sector-badge sector-<?= array_search($consult['sector'], $sectorNames) ?: 'default' ?>">
                                    <?= htmlspecialchars($consult['sector']) ?>
                                </span>
                                <span class="category"><?= htmlspecialchars($consult['category']) ?></span>
                            </div>
                            
                            <div class="consultation-info">
                                <div class="info-row">
                                    <strong>ФИО:</strong> <?= htmlspecialchars($consult['fullName']) ?>
                                </div>
                                <div class="info-row">
                                    <strong>Телефон:</strong> 
                                    <a href="tel:<?= preg_replace('/\D/', '', $consult['phone']) ?>">
                                        <?= htmlspecialchars($consult['phone']) ?>
                                    </a>
                                </div>
                                <div class="info-row">
                                    <strong>Email:</strong> 
                                    <a href="mailto:<?= htmlspecialchars($consult['email']) ?>">
                                        <?= htmlspecialchars($consult['email']) ?>
                                    </a>
                                </div>
                                <div class="info-row">
                                    <strong>Регион:</strong> <?= htmlspecialchars($consult['region']) ?>
                                </div>
                            </div>
                            
                            <div class="consultation-description">
                                <?= nl2br(htmlspecialchars(mb_substr($consult['description'], 0, 200))) ?>
                                <?= mb_strlen($consult['description']) > 200 ? '...' : '' ?>
                            </div>
                            
                            <?php if (!empty($consult['attachments'])): ?>
                            <div class="consultation-attachments">
                                <strong>Вложения (<?= count($consult['attachments']) ?>):</strong>
                                <?php foreach ($consult['attachments'] as $att): ?>
                                <a href="#" class="attachment-link" 
                                   onclick="downloadAttachment(<?= $consult['msgNumber'] ?>, '<?= $att['filename'] ?>')">
                                    📎 <?= htmlspecialchars($att['filename']) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="consultation-actions">
                            <button class="btn btn-primary" 
                                    onclick="openReplyModal(<?= $consult['msgNumber'] ?>, '<?= addslashes($consult['email']) ?>', '<?= addslashes($consult['consultationNumber']) ?>')">
                                ✉️ Ответить
                            </button>
                            <button class="btn btn-secondary" 
                                    onclick="viewFullEmail(<?= $consult['msgNumber'] ?>)">
                                👁 Полностью
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Модальное окно ответа -->
    <div id="replyModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Ответ на консультацию</h2>
                <button class="modal-close" onclick="closeReplyModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="replyForm">
                    <input type="hidden" id="replyMsgNumber">
                    <input type="hidden" id="replyConsultNumber">
                    
                    <div class="form-group">
                        <label>Кому</label>
                        <input type="email" id="replyTo" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Тема</label>
                        <input type="text" id="replySubject" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Текст ответа</label>
                        <textarea id="replyText" rows="10" class="form-control" 
                                  placeholder="Введите текст ответа..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Способ ответа</label>
                        <select id="replyMethod" class="form-control">
                            <option value="email">Email</option>
                            <option value="phone">Телефон</option>
                            <option value="both">Email + Телефон</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeReplyModal()">
                            Отмена
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Отправить ответ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно просмотра -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Полное письмо</h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Загружается через AJAX -->
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
</body>
</html>