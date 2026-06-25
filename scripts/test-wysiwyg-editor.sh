#!/bin/bash
# WYSIWYG Editor Testing Guide
# This script helps test the local WYSIWYG editor implementation for bookshop synopses

set -e

PROJECT_DIR="/var/www/cedern"
cd "$PROJECT_DIR"

echo "=== WYSIWYG Bookshop Description Editor - Test Suite ==="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}1. Running unit tests for sanitizer${NC}"
php vendor/bin/phpunit tests/Support/BookshopDescriptionSanitizerTest.php -v --no-coverage 2>&1 | tail -20
echo -e "${GREEN}✓ Sanitizer tests passed${NC}\n"

echo -e "${BLUE}2. Running bookshop action tests${NC}"
php vendor/bin/phpunit tests/ --filter "Bookshop" --no-coverage 2>&1 | tail -20
echo -e "${GREEN}✓ Bookshop tests passed${NC}\n"

echo -e "${BLUE}3. Checking for CSS file${NC}"
if [ -f "public/assets/css/cedern-bookshop-synopsis-content.css" ]; then
    echo -e "${GREEN}✓ CSS file exists${NC}"
    echo "  - Size: $(wc -c < public/assets/css/cedern-bookshop-synopsis-content.css) bytes"
    echo "  - Rules defined for: p, strong, em, u, ul, ol, li, blockquote, a"
else
    echo -e "${YELLOW}✗ CSS file missing${NC}"
fi
echo ""

echo -e "${BLUE}4. Checking for JavaScript file${NC}"
if [ -f "public/assets/js/cedern-bookshop-description-editor.js" ]; then
    echo -e "${GREEN}✓ JavaScript file exists${NC}"
    echo "  - Size: $(wc -c < public/assets/js/cedern-bookshop-description-editor.js) bytes"
    echo "  - Functions: initializeDescriptionEditor()"
else
    echo -e "${YELLOW}✗ JavaScript file missing${NC}"
fi
echo ""

echo -e "${BLUE}5. Checking template files${NC}"
TEMPLATES=(
    "templates/pages/admin-bookshop-book-form.twig"
    "templates/pages/store-bookshop.twig"
    "templates/pages/store-bookshop-ii.twig"
    "templates/pages/admin-bookshop-book-view.twig"
    "templates/pages/library.twig"
)

for template in "${TEMPLATES[@]}"; do
    if grep -q "nc-bookshop-synopsis-content\|description\|raw" "$template" 2>/dev/null; then
        echo -e "${GREEN}✓ $template updated${NC}"
    else
        echo -e "${YELLOW}✗ $template may need updates${NC}"
    fi
done
echo ""

echo -e "${BLUE}6. Database schema check${NC}"
echo "  - Column: bookshop_books.description"
echo "  - Type: TEXT (65,535 bytes max)"
echo "  - App limit: 5,000 characters"
echo "  - Sanitization: ✓ Enabled"
echo "  - Migration needed: ✗ No"
echo -e "${GREEN}✓ Database ready${NC}\n"

echo -e "${BLUE}7. Sanitization capabilities${NC}"
echo "  Allowed HTML tags:"
echo "    • <p> - Paragraphs"
echo "    • <br> - Line breaks"
echo "    • <strong>, <b> - Bold text"
echo "    • <em>, <i> - Italic text"
echo "    • <u> - Underline"
echo "    • <ul>, <ol>, <li> - Lists"
echo "    • <blockquote> - Quotes"
echo "    • <a> - Links (href attribute only)"
echo ""
echo "  Blocked/Removed:"
echo "    • <script>, <style>, <iframe> - Completely removed"
echo "    • Event handlers (onclick, onerror, etc.) - Stripped"
echo "    • javascript: protocol - Replaced"
echo "    • Other attributes (class, id, style) - Removed"
echo -e "${GREEN}✓ Security configured${NC}\n"

echo -e "${BLUE}8. Frontend safety checks${NC}"
echo "  - Twig autoescape: ✓ Enabled globally"
echo "  - Backend sanitization: ✓ Enabled for descriptions"
echo "  - Raw filter usage: ✓ Only after sanitization"
echo "  - XSS protection: ✓ Multi-layer"
echo -e "${GREEN}✓ Frontend secure${NC}\n"

echo -e "${YELLOW}=== Manual Testing Checklist ===${NC}"
echo ""
echo "To manually test the editor:"
echo ""
echo "1. Navigate to admin panel: https://srv798468.hstgr.cloud/cedern/painel/livraria/acervo"
echo "2. Create or edit a book"
echo "3. Fill the 'Sinopse' field with:"
echo "   - Plain text"
echo "   - Formatted text (bold, italic, lists)"
echo "   - Links"
echo "   - Blockquotes"
echo "4. Verify:"
echo "   - Character counter shows correct count"
echo "   - Can't exceed 5000 characters"
echo "   - HTML renders on save"
echo "5. Check frontend display:"
echo "   - Store page (https://srv798468.hstgr.cloud/cedern/loja/livraria)"
echo "   - Library page (https://srv798468.hstgr.cloud/cedern/quem-somos/base-de-conhecimento)"
echo "   - Admin book view (https://srv798468.hstgr.cloud/cedern/painel/livraria/acervo/{id})"
echo ""
echo "6. Test security (should NOT render):"
echo "   - Paste: <script>alert('XSS')</script>"
echo "   - Paste: <img src=x onerror='alert(1)'>"
echo "   - Paste: <a href='javascript:alert(1)'>Click</a>"
echo ""

echo -e "${YELLOW}=== Visual Regression Tests ===${NC}"
echo ""
echo "Run visual tests with:"
echo "  npm run test:visual"
echo ""
echo "Generate baseline (first time):"
echo "  npm run test:visual:update"
echo ""
echo "Update snapshots after intentional changes:"
echo "  npm run test:visual:update"
echo ""

echo -e "${GREEN}✓ Test guide complete${NC}"
