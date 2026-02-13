# OCMOD CI/CD - Быстрый старт

## 📦 Что это дает?

При каждом push в GitHub автоматически создается OCMOD архив с именем:
```
{module_code}_v{version}.ocmod.zip
```

Версия и имя извлекаются автоматически из `install.xml`.

## 🚀 Установка за 5 минут

### 1. Скопируйте workflow в проект

```bash
mkdir -p .github/workflows
cp create-ocmod-release.yml .github/workflows/
```

### 2. Создайте ветку release

```bash
git checkout --orphan release
git rm -rf .
echo "# OCMOD Releases" > README.md
git add README.md
git commit -m "Initialize release branch"
git push origin release
git checkout main
```

### 3. Настройте права в GitHub

**Settings** → **Actions** → **General** → **Workflow permissions** → **Read and write permissions** → Save

### 4. Готово! Сделайте push

```bash
git add .github/workflows/
git commit -m "Add OCMOD CI/CD"
git push origin main
```

## 📝 Требования к проекту

Ваш проект должен содержать:

```
your-module/
├── install.xml          # ← ОБЯЗАТЕЛЬНО
└── upload/              # ← ОБЯЗАТЕЛЬНО
    ├── admin/
    │   └── controller/...
    └── catalog/
        └── controller/...
```

### install.xml минимум:

```xml
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <code>my_module</code>        <!-- Имя архива -->
    <version>1.0.0</version>      <!-- ОБЯЗАТЕЛЬНО -->
    
    <!-- Ваши модификации -->
</modification>
```

## 📤 Использование

### Новый релиз

1. Измените версию в `install.xml`:
   ```xml
   <version>1.1.0</version>
   ```

2. Сделайте push:
   ```bash
   git add install.xml upload/
   git commit -m "Release v1.1.0"
   git push
   ```

3. **Готово!** Архив `my_module_v1.1.0.ocmod.zip` появится в ветке `release`

### Скачивание

**Последняя версия:**
```
https://github.com/USERNAME/REPO/raw/release/latest.ocmod.zip
```

**Конкретная версия:**
```
https://github.com/USERNAME/REPO/raw/release/my_module_v1.1.0.ocmod.zip
```

## 🔧 Локальная сборка

Для сборки без push используйте скрипт:

```bash
chmod +x build-ocmod.sh
./build-ocmod.sh
```

Результат:
```
✓ my_module_v1.0.0.ocmod.zip
✓ my_module_v1.0.0.ocmod.zip.sha256
✓ my_module_v1.0.0.ocmod.zip.md5
✓ RELEASE_INFO.txt
```

## 📋 Naming Convention

| install.xml | Имя архива |
|------------|-----------|
| `<code>payment_gateway</code><version>2.0.0</version>` | `payment_gateway_v2.0.0.ocmod.zip` |
| `<code>seo_module</code><version>1.5.3</version>` | `seo_module_v1.5.3.ocmod.zip` |

## ❓ Troubleshooting

**Workflow не запускается:**
- Проверьте, что изменили `install.xml` или файлы в `upload/`
- Push должен быть в ветку main/master/develop

**Ошибка "Version not found":**
- Добавьте `<version>1.0.0</version>` в install.xml

**Ошибка "Cannot determine module code":**
- Добавьте `<code>my_module</code>` в install.xml

**Ошибка при push в release:**
- Settings → Actions → General → Workflow permissions → Read and write

## 📚 Дополнительно

- **OCMOD_RELEASE_GUIDE.md** - полная документация
- **install.xml.example** - пример файла манифеста
- **build-ocmod.sh** - скрипт локальной сборки

## 🎯 Пример полного цикла

```bash
# 1. Разработка
vim upload/admin/controller/extension/module/my_module.php

# 2. Обновление версии
vim install.xml  # Измените <version>1.0.0</version> на <version>1.1.0</version>

# 3. Коммит
git add .
git commit -m "v1.1.0: Add new feature"

# 4. Push (запускает автосборку)
git push origin main

# 5. Через минуту архив готов
# https://github.com/USERNAME/REPO/raw/release/my_module_v1.1.0.ocmod.zip
```

## ✅ Проверка работы

После push:

1. Откройте **Actions** в GitHub
2. Увидите запущенный workflow "Create OCMOD Release"
3. После завершения проверьте ветку `release`
4. Скачайте и протестируйте архив

Готово! 🎉
