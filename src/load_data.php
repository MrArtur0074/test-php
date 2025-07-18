<?php
$db_host = 'db';
$db_user = 'admin';
$db_pass = 'admin';
$db_name = 'test';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

/**
 * Получает данные с указанного API endpoint
 *
 * @param string $url URL API endpoint для получения данных
 * @return array Ассоциативный массив с полученными данными
 * @throws Exception Если произошла ошибка при запросе или декодировании JSON
 */
function fetchData($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FAILONERROR => true
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Ошибка CURL: $error");
    }
    
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
    }
    
    return $data;
}

/**
 * Вставляет данные в указанную таблицу с обработкой дубликатов и ошибок
 *
 * @param PDO $pdo Объект PDO для работы с базой данных
 * @param array $data Массив данных для вставки
 * @param string $table Название таблицы для вставки
 * @param array $fields Ассоциативный массив соответствия полей [поле_в_бд => поле_в_данных]
 * @param string $idField Название поля идентификатора (по умолчанию 'id')
 * @return array Массив с результатами:
 *               [
 *                 'count' => количество успешных вставок,
 *                 'errors' => количество ошибок,
 *                 'total' => всего записей обработано
 *               ]
 */
function insertData($pdo, $data, $table, $fields, $idField = 'id') {
    $count = 0;
    $errors = 0;
    
    $columns = implode(', ', array_keys($fields));
    $placeholders = ':' . implode(', :', array_keys($fields));
    $updates = [];
    
    foreach ($fields as $field => $value) {
        $updates[] = "$field = VALUES($field)";
    }
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
    
    $stmt = $pdo->prepare($sql);
    $checkStmt = $pdo->prepare("SELECT $idField FROM $table WHERE $idField = ?");
    
    foreach ($data as $item) {
        try {
            $checkStmt->execute([$item[$idField]]);
            if ($checkStmt->fetch()) {
                echo "Запись с ID {$item[$idField]} уже существует в $table, пропускаем...\n";
                continue;
            }
            
            $params = [];
            foreach ($fields as $field => $map) {
                $params[":$field"] = $item[$map];
            }
            
            $stmt->execute($params);
            $count++;
            
        } catch (PDOException $e) {
            $errors++;
            echo "Ошибка при обработке записи ID {$item[$idField]} в $table: " . $e->getMessage() . "\n";
            continue;
        }
    }
    
    return ['count' => $count, 'errors' => $errors, 'total' => count($data)];
}

try {
    $posts = fetchData('https://jsonplaceholder.typicode.com/posts');
    $comments = fetchData('https://jsonplaceholder.typicode.com/comments');
    
    $postsResult = insertData($pdo, $posts, 'posts', [
        'id' => 'id',
        'user_id' => 'userId',
        'title' => 'title',
        'body' => 'body'
    ]);
    
    $commentsResult = insertData($pdo, $comments, 'comments', [
        'id' => 'id',
        'post_id' => 'postId',
        'name' => 'name',
        'email' => 'email',
        'body' => 'body'
    ]);
    
    echo "----------------------------------------\n";
    echo "Итог загрузки:\n";
    echo "Посты:\n";
    echo "  Успешно: {$postsResult['count']}\n";
    echo "  Дубликатов: " . ($postsResult['total'] - $postsResult['count'] - $postsResult['errors']) . "\n";
    echo "  Ошибок: {$postsResult['errors']}\n";
    echo "  Всего: {$postsResult['total']}\n";
    
    echo "\nКомментарии:\n";
    echo "  Успешно: {$commentsResult['count']}\n";
    echo "  Дубликатов: " . ($commentsResult['total'] - $commentsResult['count'] - $commentsResult['errors']) . "\n";
    echo "  Ошибок: {$commentsResult['errors']}\n";
    echo "  Всего: {$commentsResult['total']}\n";
    
} catch (Exception $e) {
    die("Критическая ошибка: " . $e->getMessage());
}