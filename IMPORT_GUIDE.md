# Руководство по импорту данных в J2i WMS

Вы можете массово загрузить устройства в систему, используя CSV формат (Excel -> Сохранить как -> CSV).

## Формат данных
Файл должен быть в формате CSV (разделитель запятая), в кодировке UTF-8. 
Первая строка заголовка игнорируется, данные начинаются со 2-й строки.

### Структура колонок (по порядку)
1. **Brand**: Бренд (например, Apple, Samsung)
2. **Model**: Модель (например, iPhone 15 Pro, Galaxy S24)
3. **IMEI**: IMEI код устройства (уникальный)
4. **Memory**: Память (128GB, 256GB, 1TB)
5. **Color**: Цвет (Black, Blue, Natural Titanium)
6. **Purchase Price**: Цена закупки (число)
7. **Currency**: Валюта закупки (CZK, EUR, USD)
8. **Status**: Статус (in_stock, sold, reserved)
9. **Purchase Date**: Дата закупки (YYYY-MM-DD)

### Пример (import_example.csv):
```csv
Brand,Model,IMEI,Memory,Color,Purchase Price,Currency,Status,Purchase Date
Apple,iPhone 16 Pro,359812345678901,256GB,Black Titanium,28500,CZK,in_stock,2024-02-01
Samsung,Galaxy S24 Ultra,351234567890123,512GB,Phantom Black,22000,CZK,in_stock,2024-01-15
```

## Как импортировать

1. Подготовьте файл в Excel и сохраните как `import.csv` в корне проекта или в папке `tools`.
2. Откройте терминал.
3. Перейдите в папку проекта.
4. Запустите скрипт импорта:

```bash
php tools/import_devices.php import.csv
```

Скрипт автоматически:
- Найдет или создаст необходимые бренды, модели, цвета и объемы памяти.
- Добавит устройства в базу данных.
- Сообщит о результатах или ошибках.
