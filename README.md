# Поиск по комментариям (Docker)

Проект предоставляет веб-интерфейс для полнотекстового поиска по комментариям с возможностью просмотра связанных постов.

## 🧩 Требования

- **Docker**
- **Docker Compose**
- **2 ГБ свободного места на диске** (для базы данных)

---

## 🚀 Установка и запуск

Клонируйте репозиторий:

```bash
git clone https://github.com/MrArtur0074/test-php.git[ваш-репозиторий]
cd test-php
```

Соберите и запустите контейнеры:

```bash
docker compose up -d --build
```

⏳ Дождитесь инициализации (1–2 минуты), пока MySQL полностью запустится.

---

## 🧪 Загрузка тестовых данных

Перед использованием необходимо загрузить тестовые данные:

1. Откройте в браузере:

    ```
    http://localhost:8080/load_data.php
    ```

2. Дождитесь сообщения об успешной загрузке данных.

---

## 🔍 Использование

Основной интерфейс поиска доступен по адресу:

```
http://localhost:8080
```

### Особенности поиска:

- Минимальная длина запроса — **3 символа**
- Поддержка **сложных запросов** (через `FULLTEXT`)
- **Подсветка** найденных слов в результатах
- Отображение **времени выполнения запроса**

---

## 🗂 Структура проекта

```
├── docker-compose.yml      # Конфигурация Docker
├── migrations         # SQL-скрипты для инициализации БД
├── docker         # конфиг
├── src
│   ├── index.php           # Главная страница поиска
│   └── load_data.php       # Скрипт загрузки тестовых данных
│   
```

---

## 🛑 Остановка и очистка

Остановить контейнеры:

```bash
docker compose down
```

Для полной очистки (с удалением данных БД):

```bash
docker compose down -v
```

---

## ⚠️ Возможные проблемы

**Ошибка подключения к БД:**

- Убедитесь, что загрузили данные через `/load_data.php`
- Проверьте, что контейнер MySQL запущен (`docker ps`)

**Медленный поиск:**

- Убедитесь, что создан `FULLTEXT` индекс (создаётся автоматически)

**Проблемы с загрузкой данных:**

- Попробуйте перезапустить контейнеры:

```bash
docker-compose restart
```