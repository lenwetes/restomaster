#!/bin/bash
# Auditoría de código RestoMaster
# Uso desde Windows: Git Bash -> bash scripts/audit.sh

echo "🔍 INICIANDO AUDITORÍA DE CÓDIGO RestoMaster..."
echo ""

mkdir -p reports

# 1. PHPStan
echo "1️⃣ Analizando tipos (PHPStan)..."
./vendor/bin/phpstan analyse --memory-limit=1G > reports/phpstan.txt 2>&1
PHPSTAN_ERRORS=$(grep -c "Line\|ERROR" reports/phpstan.txt || echo "0")
echo "   ✓ Errores encontrados: $PHPSTAN_ERRORS"

# 2. Pint
echo "2️⃣ Verificando formato (Pint)..."
./vendor/bin/pint --test > reports/pint.txt 2>&1
PINT_ERRORS=$(grep -c "FAIL\|would have been fixed" reports/pint.txt || echo "0")
echo "   ✓ Archivos a arreglar: $PINT_ERRORS"

# 3. PHP-CS-Fixer
echo "3️⃣ Verificando formato de código (PHP-CS-Fixer)..."
./vendor/bin/php-cs-fixer fix --dry-run > reports/php-cs-fixer.txt 2>&1
FCS_ERRORS=$(wc -l < reports/php-cs-fixer.txt)
echo "   ✓ Líneas a arreglar: $FCS_ERRORS"

# 4. PHPCodeSniffer
echo "4️⃣ Analizando standards PSR-12 (PHPCodeSniffer)..."
./vendor/bin/phpcs app --standard=PSR12 > reports/phpcs.txt 2>&1
PHPCS_ERRORS=$(grep -c "ERROR" reports/phpcs.txt || echo "0")
echo "   ✓ Errores encontrados: $PHPCS_ERRORS"

# 5. Rector (dry-run)
echo "5️⃣ Buscando oportunidades de modernización (Rector)..."
./vendor/bin/rector process --dry-run > reports/rector.txt 2>&1
RECTOR_CHANGES=$(grep -c "would have been changed\|Skip file" reports/rector.txt || echo "0")
echo "   ✓ Cambios potenciales: $RECTOR_CHANGES"

# 6. Escaneo rápido de secretos
echo "6️⃣ Escaneando secretos hardcodeados..."
SECRETS=$(grep -rEn "(password|secret|token|api[_-]?key|client[_-]?secret)\s*[=:]\s*['\"][^'\"]{6,}" app/ config/ routes/ database/ 2>/dev/null | grep -v "env(" | wc -l)
echo "   ✓ Posibles secretos: $SECRETS"

# Resumen
echo ""
echo "════════════════════════════════════════"
echo "📊 RESUMEN AUDITORÍA"
echo "════════════════════════════════════════"
echo "PHPStan (tipos):        $PHPSTAN_ERRORS errores"
echo "Pint (formato):         $PINT_ERRORS archivos"
echo "PHP-CS-Fixer (formato): $FCS_ERRORS líneas"
echo "PHPCodeSniffer (PSR12): $PHPCS_ERRORS errores"
echo "Rector (modernización): $RECTOR_CHANGES cambios"
echo "Secretos:               $SECRETS hallazgos"
echo "════════════════════════════════════════"
echo "📁 Reportes en: reports/"
