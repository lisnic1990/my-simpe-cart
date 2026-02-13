#!/bin/bash

###############################################################################
# OCMOD Archive Builder
# Локальная сборка OCMOD архивов с той же логикой, что и GitHub Actions
###############################################################################

set -e

# Цвета
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }
print_info() { echo -e "${BLUE}ℹ $1${NC}"; }

print_info "=== OCMOD Archive Builder ==="
echo ""

# Проверка наличия необходимых утилит
if ! command -v xmlstarlet &> /dev/null; then
    print_error "xmlstarlet не установлен"
    print_info "Установите: sudo apt-get install xmlstarlet (Ubuntu/Debian)"
    print_info "или: brew install xmlstarlet (macOS)"
    exit 1
fi

if ! command -v zip &> /dev/null; then
    print_error "zip не установлен"
    exit 1
fi

# Шаг 1: Проверка структуры проекта
print_info "Шаг 1: Проверка структуры проекта..."

if [ ! -f "install.xml" ]; then
    print_error "Файл install.xml не найден в текущей директории"
    print_info "Убедитесь, что вы находитесь в корне OCMOD проекта"
    exit 1
fi

if [ ! -d "upload" ]; then
    print_error "Папка upload/ не найдена"
    print_info "Создайте папку upload/ с файлами модуля"
    exit 1
fi

print_success "Структура проекта корректна"

# Шаг 2: Парсинг install.xml
print_info "Шаг 2: Извлечение информации из install.xml..."

# Проверка валидности XML
if ! xmlstarlet val install.xml > /dev/null 2>&1; then
    print_error "install.xml содержит ошибки синтаксиса XML"
    xmlstarlet val install.xml
    exit 1
fi

# Извлечение версии
VERSION=$(xmlstarlet sel -t -v "//version" install.xml 2>/dev/null | xargs)
if [ -z "$VERSION" ]; then
    print_error "Версия не найдена в install.xml"
    print_info "Добавьте тег: <version>1.0.0</version>"
    exit 1
fi

# Извлечение кода модуля
MODULE_CODE=$(xmlstarlet sel -t -v "//code" install.xml 2>/dev/null | xargs)
if [ -z "$MODULE_CODE" ]; then
    MODULE_CODE=$(xmlstarlet sel -t -v "//id" install.xml 2>/dev/null | xargs)
fi

if [ -z "$MODULE_CODE" ]; then
    MODULE_NAME=$(xmlstarlet sel -t -v "//n" install.xml 2>/dev/null | xargs)
    if [ -z "$MODULE_NAME" ]; then
        MODULE_NAME=$(xmlstarlet sel -t -v "//name" install.xml 2>/dev/null | xargs)
    fi
    MODULE_CODE=$(echo "$MODULE_NAME" | tr '[:upper:]' '[:lower:]' | tr ' ' '_' | tr -cd '[:alnum:]_')
fi

if [ -z "$MODULE_CODE" ]; then
    print_error "Не удалось определить код модуля из install.xml"
    print_info "Добавьте один из тегов: <code>, <id>, или <n>"
    exit 1
fi

print_success "Код модуля: $MODULE_CODE"
print_success "Версия: $VERSION"

# Извлечение дополнительной информации (опционально)
MODULE_NAME=$(xmlstarlet sel -t -v "//n" install.xml 2>/dev/null | xargs)
if [ -z "$MODULE_NAME" ]; then
    MODULE_NAME=$(xmlstarlet sel -t -v "//name" install.xml 2>/dev/null | xargs)
fi
AUTHOR=$(xmlstarlet sel -t -v "//author" install.xml 2>/dev/null | xargs)

if [ -n "$MODULE_NAME" ]; then
    echo "Название: $MODULE_NAME"
fi
if [ -n "$AUTHOR" ]; then
    echo "Автор: $AUTHOR"
fi
echo ""

# Шаг 3: Формирование имени архива
ARCHIVE_NAME="${MODULE_CODE}_v${VERSION}.ocmod.zip"
print_info "Шаг 3: Создание архива: $ARCHIVE_NAME"

# Удаление старого архива если существует
if [ -f "$ARCHIVE_NAME" ]; then
    print_warning "Архив $ARCHIVE_NAME уже существует и будет перезаписан"
    rm -f "$ARCHIVE_NAME"
fi

# Шаг 4: Создание архива
print_info "Шаг 4: Архивирование файлов..."

# Создаем архив с максимальным сжатием
zip -r -9 "$ARCHIVE_NAME" upload install.xml

if [ ! -f "$ARCHIVE_NAME" ]; then
    print_error "Не удалось создать архив"
    exit 1
fi

print_success "Архив создан успешно"

# Шаг 5: Проверка архива
print_info "Шаг 5: Проверка содержимого архива..."

echo ""
echo "Содержимое архива:"
unzip -l "$ARCHIVE_NAME"
echo ""

# Информация о размере
FILE_SIZE=$(ls -lh "$ARCHIVE_NAME" | awk '{print $5}')
print_success "Размер архива: $FILE_SIZE"

# Шаг 6: Создание контрольных сумм
print_info "Шаг 6: Создание контрольных сумм..."

sha256sum "$ARCHIVE_NAME" > "$ARCHIVE_NAME.sha256"
md5sum "$ARCHIVE_NAME" > "$ARCHIVE_NAME.md5"

print_success "SHA256: $(cat $ARCHIVE_NAME.sha256 | cut -d' ' -f1)"
print_success "MD5: $(cat $ARCHIVE_NAME.md5 | cut -d' ' -f1)"

# Шаг 7: Создание README для релиза
print_info "Шаг 7: Создание RELEASE_INFO.txt..."

cat > RELEASE_INFO.txt << EOF
OCMOD Release Package
=====================

Module Information:
  Code: $MODULE_CODE
  $([ -n "$MODULE_NAME" ] && echo "Name: $MODULE_NAME")
  Version: $VERSION
  $([ -n "$AUTHOR" ] && echo "Author: $AUTHOR")

Archive Information:
  Filename: $ARCHIVE_NAME
  Size: $FILE_SIZE
  Created: $(date +'%Y-%m-%d %H:%M:%S')

Checksums:
  SHA256: $(cat $ARCHIVE_NAME.sha256 | cut -d' ' -f1)
  MD5: $(cat $ARCHIVE_NAME.md5 | cut -d' ' -f1)

Installation:
  1. Login to OcStore admin panel
  2. Go to: Extensions → Installer
  3. Upload: $ARCHIVE_NAME
  4. Go to: Extensions → Modifications
  5. Click: Refresh button

Build Date: $(date +'%Y-%m-%d %H:%M:%S')
EOF

print_success "RELEASE_INFO.txt создан"

# Итоговая информация
echo ""
print_success "=== Сборка завершена успешно! ==="
echo ""
print_info "Созданные файлы:"
echo "  ✓ $ARCHIVE_NAME"
echo "  ✓ $ARCHIVE_NAME.sha256"
echo "  ✓ $ARCHIVE_NAME.md5"
echo "  ✓ RELEASE_INFO.txt"
echo ""
print_info "Для установки в OcStore:"
echo "  1. Загрузите файл $ARCHIVE_NAME через Extensions → Installer"
echo "  2. Обновите модификации (Extensions → Modifications → Refresh)"
echo ""

# Опциональная проверка целостности
read -p "Проверить целостность архива? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_info "Проверка целостности..."
    if sha256sum -c "$ARCHIVE_NAME.sha256"; then
        print_success "Архив прошел проверку целостности"
    else
        print_error "Ошибка проверки целостности"
        exit 1
    fi
fi

echo ""
print_success "Готово! 🚀"
