<?php
$db_host = 'db';
$db_user = 'admin';
$db_pass = 'admin';
$db_name = 'test';

$searchResults = [];
$executionTime = 0;
$error = '';
$searchPerformed = false;

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", 
        $db_user, 
        $db_pass, 
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]
    );
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $searchPerformed = true;

        if (!isset($_POST['search']) || trim($_POST['search']) === '') {
            throw new Exception('Поле поиска не может быть пустым.');
        }
        
        $searchTerm = trim($_POST['search']);
        
        if (strlen($searchTerm) < 3) {
            throw new Exception('Введите минимум 3 символа для поиска.');
        }
        
        if (preg_match('/[<>\'"]/', $searchTerm)) {
            throw new Exception('Недопустимые символы в поисковом запросе.');
        }
        
        $startTime = microtime(true);
        
        try {
            $stmt = $pdo->prepare("
                SELECT p.title, c.body, p.created_at, c.created_at as comment_date, u.name as author_name
                FROM posts p
                JOIN comments c ON p.id = c.post_id
                JOIN users u ON u.id = p.user_id
                WHERE MATCH(c.body) AGAINST(:search IN BOOLEAN MODE)
                LIMIT 100
            ");
            
            if (!$stmt) {
                throw new Exception('Ошибка подготовки SQL запроса.');
            }
            
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
            
            if (!$stmt->execute()) {
                throw new Exception('Ошибка выполнения SQL запроса.');
            }
            
            $searchResults = $stmt->fetchAll();
            
            if ($searchResults === false) {
                throw new Exception('Ошибка получения результатов.');
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            echo $e->getMessage();
            throw new Exception("Произошла ошибка при выполнении поиска. Пожалуйста, попробуйте позже. ");
        }
    }
    
} catch (PDOException $e) {
    $error = 'Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.';
    error_log('DB Connection error: ' . $e->getMessage());
} catch (Exception $e) {
    $error = $e->getMessage();
}

function pluralize($number, $one, $two, $five) {
    $n = abs($number) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $five;
    if ($n1 > 1 && $n1 < 5) return $two;
    if ($n1 == 1) return $one;
    return $five;
}

function formatDate($date) {
    if (empty($date)) return 'Дата неизвестна';
    return date('d.m.Y H:i', strtotime($date));
}

function highlightSearchTerm($text, $term) {
    if (empty($term)) return $text;
    return preg_replace('/(' . preg_quote($term, '/') . ')/iu', '<span class="highlight">$1</span>', $text);
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <title>Поиск записей по комментариям</title>
</head>
<body>
    <h1>Поиск записей по комментариям</h1>
    
    <?php if (!empty($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form class="search-form" method="POST">
        <input type="text" name="search" class="search-input" placeholder="Введите минимум 3 символа..." 
               value="<?= isset($_POST['search']) ? htmlspecialchars($_POST['search']) : '' ?>" 
               required minlength="3" maxlength="100">
        <button type="submit" class="search-button">Найти</button>
    </form>
    
    <?php if ($searchPerformed): ?>
        <?php if (!empty($searchResults)): ?>
            <div class="search-results">
                <h2 class="results-header">Найдено <?= count($searchResults) ?> <?= pluralize(count($searchResults), 'совпадение', 'совпадения', 'совпадений') ?></h2>
                <div class="results-stats">
                    <span class="badge">Время поиска: <?= $executionTime ?> мс</span>
                    <?php if (strlen($searchTerm) > 0): ?>
                        <span class="badge">Запрос: "<?= htmlspecialchars($searchTerm) ?>"</span>
                    <?php endif; ?>
                </div>

                <div class="results-list">
                    <?php foreach ($searchResults as $result): ?>
                        <article class="result-card">
                            <header class="post-header">
                                <h3 class="post-title">
                                    <?= htmlspecialchars($result['title'] ?? 'Без названия') ?>
                                </h3>
                                <div class="post-meta">
                                    <span class="meta-item">
                                        <i class="icon icon-user"></i>
                                        <?= htmlspecialchars($result['author_name'] ?? 'Автор не указан') ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="icon icon-calendar"></i>
                                        <?= formatDate($result['created_at'] ?? '') ?>
                                    </span>
                                </div>
                            </header>

                            <div class="comment-content">
                                <div class="comment-meta">
                                    <span class="comment-author">
                                        <i class="icon icon-comment"></i>
                                    </span>
                                    <span class="comment-date">
                                        <?= formatDate($result['comment_date'] ?? '') ?>
                                    </span>
                                </div>
                                <div class="comment-text">
                                    <?= highlightSearchTerm(nl2br(htmlspecialchars($result['body'] ?? '')), $searchTerm) ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-results">
                <div class="empty-icon">
                    <i class="icon icon-search"></i>
                </div>
                <h3>Ничего не найдено</h3>
                <p>По запросу "<?= htmlspecialchars($searchTerm) ?>" совпадений не обнаружено.</p>
                <div class="search-tips">
                    <p>Попробуйте:</p>
                    <ul>
                        <li>Использовать другие ключевые слова</li>
                        <li>Проверить орфографию</li>
                        <li>Упростить запрос</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>