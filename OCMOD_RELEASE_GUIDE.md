# OCMOD Release Automation - Руководство

## Обзор

Автоматизированный CI/CD pipeline для создания OCMOD архивов с автоматическим извлечением версии и названия модуля из `install.xml`.

## Требования к структуре проекта

```
your-ocmod-module/
├── install.xml          # ОБЯЗАТЕЛЬНО: Файл манифеста OCMOD
├── upload/              # ОБЯЗАТЕЛЬНО: Папка с файлами модуля
│   ├── admin/
│   │   └── controller/
│   │       └── extension/
│   │           └── module/
│   │               └── your_module.php
│   └── catalog/
│       └── controller/
│           └── extension/
│               └── module/
│                   └── your_module.php
├── .github/
│   └── workflows/
│       └── create-ocmod-release.yml
└── README.md
```

## Формат install.xml

Workflow автоматически извлекает информацию из `install.xml`:

```xml
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>My Awesome Module</name>
    <code>my_awesome_module</code>
    <version>1.2.3</version>
    <author>Your Name</author>
    <link>https://yoursite.com</link>
    
    <file path="admin/controller/extension/module/my_module.php">
        <operation>
            <!-- ... -->
        </operation>
    </file>
</modification>
```

### Извлекаемые поля

1. **`<code>`** - используется для имени архива (приоритет 1)
2. **`<id>`** - альтернатива для `<code>` (приоритет 2)
3. **`<name>`** - конвертируется в snake_case если code/id отсутствуют (приоритет 3)
4. **`<version>`** - версия модуля (ОБЯЗАТЕЛЬНО)

## Naming Convention (Правило именования)

### Формат имени архива

```
{module_code}_v{version}.ocmod.zip
```

### Примеры

| install.xml | Имя архива |
|------------|-----------|
| `<code>payment_gateway</code><version>2.1.0</version>` | `payment_gateway_v2.1.0.ocmod.zip` |
| `<code>seo_pro</code><version>1.0.5</version>` | `seo_pro_v1.0.5.ocmod.zip` |
| `<name>My Module</name><version>3.2.1</version>` | `my_module_v3.2.1.ocmod.zip` |

## Как работает workflow

### Триггеры запуска

Workflow запускается автоматически при:

```yaml
on:
  push:
    branches:
      - main
      - master
      - develop
    paths:
      - 'install.xml'      # Изменения в манифесте
      - 'upload/**'        # Любые изменения в папке upload
```

### Этапы выполнения

1. **Проверка структуры**
   - Наличие `install.xml`
   - Наличие папки `upload/`

2. **Парсинг install.xml**
   - Извлечение версии
   - Извлечение кода модуля
   - Валидация данных

3. **Создание архива**
   - Архивируются ТОЛЬКО: `install.xml` + `upload/`
   - Используется формат: `{module_code}_v{version}.ocmod.zip`
   - Максимальное сжатие (zip -9)

4. **Публикация в ветку release**
   - Сохранение версионного архива
   - Создание `latest.ocmod.zip` (последняя версия)
   - Генерация контрольных сумм (SHA256, MD5)
   - Создание MANIFEST.json с метаданными

## Установка

### Шаг 1: Создание workflow файла

```bash
mkdir -p .github/workflows
cp create-ocmod-release.yml .github/workflows/
```

### Шаг 2: Создание ветки release

```bash
# Создание пустой ветки для архивов
git checkout --orphan release
git rm -rf .
echo "# OCMOD Release Archives" > README.md
git add README.md
git commit -m "Initialize release branch"
git push origin release

# Возврат к основной ветке
git checkout main
```

### Шаг 3: Настройка permissions в GitHub

1. Откройте: **Settings** → **Actions** → **General**
2. Найдите: **Workflow permissions**
3. Выберите: **Read and write permissions**
4. Сохраните изменения

### Шаг 4: Коммит и push

```bash
git add .github/workflows/create-ocmod-release.yml
git commit -m "Add OCMOD release automation"
git push origin main
```

## Использование

### Автоматический релиз

Просто обновите версию в `install.xml` и сделайте push:

```bash
# Отредактируйте install.xml, измените <version>1.0.0</version> на <version>1.1.0</version>

git add install.xml upload/
git commit -m "Release v1.1.0: Add new features"
git push origin main
```

Workflow автоматически:
1. Создаст `your_module_v1.1.0.ocmod.zip`
2. Обновит `latest.ocmod.zip`
3. Сохранит в ветку `release`

### Скачивание архива

**Последняя версия:**
```
https://github.com/USERNAME/REPO/raw/release/latest.ocmod.zip
```

**Конкретная версия:**
```
https://github.com/USERNAME/REPO/raw/release/your_module_v1.1.0.ocmod.zip
```

**Через git:**
```bash
git clone --single-branch --branch release https://github.com/USERNAME/REPO.git releases
cd releases
# Все версии архивов здесь
```

## Структура ветки release

После выполнения workflow ветка `release` будет содержать:

```
release/
├── README.md
├── latest.ocmod.zip                    # Последняя версия (симлинк)
├── latest.ocmod.zip.sha256            # Контрольная сумма
├── latest.ocmod.zip.md5
├── your_module_v1.0.0.ocmod.zip       # Версия 1.0.0
├── your_module_v1.0.0.ocmod.zip.sha256
├── your_module_v1.0.0.ocmod.zip.md5
├── your_module_v1.1.0.ocmod.zip       # Версия 1.1.0
├── your_module_v1.1.0.ocmod.zip.sha256
├── your_module_v1.1.0.ocmod.zip.md5
├── MANIFEST.json                      # Метаданные последнего релиза
└── RELEASE_INFO.txt                   # Информация о сборке
```

## MANIFEST.json

Пример содержимого:

```json
{
  "latest": {
    "filename": "payment_gateway_v2.1.0.ocmod.zip",
    "module_code": "payment_gateway",
    "version": "2.1.0",
    "branch": "main",
    "commit": "a1b2c3d4e5f6789...",
    "short_sha": "a1b2c3d",
    "timestamp": "20260213_143022",
    "date": "2026-02-13 14:30:22",
    "author": "developer",
    "message": "Release v2.1.0: Add PayPal integration"
  }
}
```

## Проверка целостности

После скачивания архива проверьте его целостность:

### SHA256
```bash
sha256sum your_module_v1.1.0.ocmod.zip
# Сравните с содержимым your_module_v1.1.0.ocmod.zip.sha256
```

### MD5
```bash
md5sum your_module_v1.1.0.ocmod.zip
# Сравните с содержимым your_module_v1.1.0.ocmod.zip.md5
```

### Автоматическая проверка
```bash
sha256sum -c your_module_v1.1.0.ocmod.zip.sha256
# Output: your_module_v1.1.0.ocmod.zip: OK
```

## Установка OCMOD в OcStore

1. Скачайте `.ocmod.zip` файл
2. Войдите в админ-панель OcStore
3. Перейдите: **Extensions** → **Installer**
4. Нажмите **Upload** и выберите архив
5. После загрузки перейдите: **Extensions** → **Modifications**
6. Нажмите кнопку **Refresh** (синяя кнопка с иконкой обновления)

## Локальная разработка

### Создание архива локально

```bash
# Используйте тот же формат, что и workflow
zip -r -9 your_module_v1.0.0.ocmod.zip upload install.xml
```

### Проверка содержимого
```bash
unzip -l your_module_v1.0.0.ocmod.zip
```

Должны быть только:
```
Archive:  your_module_v1.0.0.ocmod.zip
  Length      Date    Time    Name
---------  ---------- -----   ----
     1234  2026-02-13 14:30   install.xml
        0  2026-02-13 14:30   upload/
     5678  2026-02-13 14:30   upload/admin/controller/...
---------                     -------
```

### Тестирование парсинга install.xml

```bash
# Установите xmlstarlet
sudo apt-get install xmlstarlet

# Извлечение версии
xmlstarlet sel -t -v "//version" install.xml

# Извлечение кода
xmlstarlet sel -t -v "//code" install.xml
```

## Troubleshooting

### Проблема: "Version not found in install.xml"

**Причина:** Отсутствует или некорректный тег `<version>`

**Решение:**
```xml
<!-- Убедитесь, что тег присутствует -->
<version>1.0.0</version>

<!-- Не должно быть лишних пробелов -->
<version>  1.0.0  </version>  <!-- НЕПРАВИЛЬНО -->
```

### Проблема: "Cannot determine module code"

**Причина:** Отсутствуют теги `<code>`, `<id>` и `<name>`

**Решение:** Добавьте хотя бы один из тегов:
```xml
<code>my_module</code>
<!-- или -->
<id>my_module</id>
<!-- или -->
<name>My Module</name>
```

### Проблема: "upload directory not found"

**Причина:** Папка `upload/` отсутствует в корне проекта

**Решение:**
```bash
mkdir -p upload
# Создайте структуру модуля
```

### Проблема: Workflow не запускается

**Проверьте:**
1. Файл находится в `.github/workflows/`
2. Расширение файла `.yml` или `.yaml`
3. Push был в ветку main/master/develop
4. Изменялись файлы `install.xml` или `upload/**`

### Проблема: Ошибка при push в release

**Причина:** Недостаточно прав

**Решение:**
Settings → Actions → General → Workflow permissions → **Read and write permissions**

## Best Practices

### 1. Семантическое версионирование

Используйте SemVer формат: `MAJOR.MINOR.PATCH`

```xml
<version>2.1.3</version>
```

- **MAJOR** (2) - несовместимые изменения API
- **MINOR** (1) - новая функциональность, обратно совместимая
- **PATCH** (3) - исправления ошибок

### 2. Changelog

Создайте `CHANGELOG.md` в корне проекта:

```markdown
# Changelog

## [1.1.0] - 2026-02-13
### Added
- New payment method integration
- Customer notification system

### Fixed
- Bug in order processing
- Security vulnerability in admin panel
```

### 3. Тестирование перед релизом

```bash
# Проверьте структуру
ls -R upload/

# Проверьте install.xml
xmlstarlet val install.xml

# Создайте тестовый архив
zip -r test.zip upload install.xml
unzip -l test.zip
```

### 4. Документирование изменений

При коммите используйте осмысленные сообщения:

```bash
git commit -m "Release v1.2.0: Add PayPal integration"
# Не делайте так:
git commit -m "update"
```

## Расширенные сценарии

### Автоматическое создание GitHub Release

Добавьте в конец workflow:

```yaml
      - name: Create GitHub Release
        if: github.ref == 'refs/heads/main'
        uses: softprops/action-gh-release@v1
        with:
          tag_name: v${{ steps.ocmod.outputs.version }}
          name: Release v${{ steps.ocmod.outputs.version }}
          files: ${{ env.ARCHIVE_NAME }}
          body: |
            ## OCMOD Module Release
            
            **Module:** ${{ steps.ocmod.outputs.module_code }}
            **Version:** ${{ steps.ocmod.outputs.version }}
            
            Download and install via OcStore Extension Installer.
```

### Уведомления в Telegram

```yaml
      - name: Send Telegram notification
        if: success()
        run: |
          curl -X POST "https://api.telegram.org/bot${{ secrets.TELEGRAM_BOT_TOKEN }}/sendMessage" \
            -d "chat_id=${{ secrets.TELEGRAM_CHAT_ID }}" \
            -d "text=🚀 OCMOD Released: ${{ steps.ocmod.outputs.module_code }} v${{ steps.ocmod.outputs.version }}"
```

## Заключение

Этот workflow обеспечивает полностью автоматизированный процесс создания OCMOD релизов, соответствующий стандартам OcStore/OpenCart и современным практикам DevOps 2026 года.

При возникновении проблем проверьте логи в разделе **Actions** вашего GitHub репозитория.
