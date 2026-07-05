# Slot Booking Service

Демо сервис бронирования слотов на Laravel 12, MySQL 8, Redis.

## 1. Установка

**Требования:** Docker и Docker Compose.

### Клонирование репозитория

```bash
git clone https://github.com/Supermasterhuaster/demo.git
```

### Запуск

```bash
cd demo
docker compose up --build -d
docker compose exec app php artisan migrate --seed
```

Приложение будет доступно по адресу: http://localhost

### Остановка

```bash
docker compose down
```

Сброс данных БД:

```bash
docker compose down -v
```

---

## 2. Тестовые запросы (curl)

Базовый URL: `http://localhost/api`

> **Важно:** команды с `$SLOT_ID`, `$KEY`, `$HOLD_ID` нужно выполнять **в одной сессии терминала**.
> Если скопировали только одну команду - сначала выполните блок "Подготовка переменных".
> Ошибка `jq: parse error` означает, что пришёл HTML (404), а не JSON - переменные пустые или слот не найден.

### Подготовка переменных (выполнить один раз)

```bash
SLOT_ID=$(curl -s http://localhost/api/slots/availability | jq -r '.[0].slot_id')
SLOT_ID_2=$(curl -s http://localhost/api/slots/availability | jq -r '.[1].slot_id')
KEY="550e8400-e29b-41d4-a716-446655440000"
echo "SLOT_ID=$SLOT_ID SLOT_ID_2=$SLOT_ID_2 KEY=$KEY"
```

Чистая БД (слоты с id=1,2):

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Доступность слотов

```bash
# Список слотов с capacity и remaining
curl -s http://localhost/api/slots/availability | jq
```

### Создание холда

```bash
# Создать временную бронь (самодостаточная команда, без переменных)
curl -s -X POST "http://localhost/api/slots/$(curl -s http://localhost/api/slots/availability | jq -r '.[0].slot_id')/hold" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" | jq
```

```bash
# То же через переменные (после блока «Подготовка переменных»)
curl -s -X POST "http://localhost/api/slots/${SLOT_ID}/hold" \
  -H "Idempotency-Key: $KEY" | jq
```

```bash
# Повтор с тем же ключом — вернёт тот же холд (идемпотентность)
curl -s -X POST "http://localhost/api/slots/${SLOT_ID}/hold" \
  -H "Idempotency-Key: $KEY" | jq
```

```bash
# Без Idempotency-Key — ошибка 422
curl -s -X POST "http://localhost/api/slots/${SLOT_ID}/hold" | jq
```

```bash
# Слот переполнен — ошибка 409 (6-й холд на слот с capacity=5)
for i in 1 2 3 4 5; do
  curl -s -X POST "http://localhost/api/slots/${SLOT_ID_2}/hold" \
    -H "Idempotency-Key: $(uuidgen)" > /dev/null
done
curl -s -X POST "http://localhost/api/slots/${SLOT_ID_2}/hold" \
  -H "Idempotency-Key: $(uuidgen)" -w "\nHTTP %{http_code}\n"
```

### Подтверждение холда

```bash
# Создать холд и подтвердить (выполнять целиком одним блоком)
SLOT_ID=$(curl -s http://localhost/api/slots/availability | jq -r '.[0].slot_id')
HOLD_ID=$(curl -s -X POST "http://localhost/api/slots/${SLOT_ID}/hold" \
  -H "Idempotency-Key: $(uuidgen)" | jq -r '.id')
curl -s -X POST "http://localhost/api/holds/${HOLD_ID}/confirm" | jq
```

```bash
# Повторное подтверждение — ошибка 409
curl -s -X POST "http://localhost/api/holds/${HOLD_ID}/confirm" -w "\nHTTP %{http_code}\n"
```

```bash
# Подтверждение несуществующего холда — ошибка 404
curl -s -X POST http://localhost/api/holds/99999/confirm -w "\nHTTP %{http_code}\n"
```

### Отмена холда

```bash
# Отменить held-холд — HTTP 204 (выполнять целиком одним блоком)
SLOT_ID=$(curl -s http://localhost/api/slots/availability | jq -r '.[0].slot_id')
HOLD_ID=$(curl -s -X POST "http://localhost/api/slots/${SLOT_ID}/hold" \
  -H "Idempotency-Key: $(uuidgen)" | jq -r '.id')
curl -s -X DELETE "http://localhost/api/holds/${HOLD_ID}" -w "\nHTTP %{http_code}\n"
```

```bash
# Повторная отмена — HTTP 204 (идемпотентно)
curl -s -X DELETE "http://localhost/api/holds/${HOLD_ID}" -w "\nHTTP %{http_code}\n"
```

```bash
# Отменить confirmed-холд — место возвращается в слот (выполнять целиком)
SLOT_ID=$(curl -s http://localhost/api/slots/availability | jq -r '.[0].slot_id')
HOLD_ID=$(curl -s -X POST "http://localhost/api/slots/${SLOT_ID}/hold" \
  -H "Idempotency-Key: $(uuidgen)" | jq -r '.id')
curl -s -X POST "http://localhost/api/holds/${HOLD_ID}/confirm" | jq
curl -s -X DELETE "http://localhost/api/holds/${HOLD_ID}" -w "\nHTTP %{http_code}\n"
curl -s http://localhost/api/slots/availability | jq
```

### Автотесты

```bash
docker compose exec app php artisan test
```
