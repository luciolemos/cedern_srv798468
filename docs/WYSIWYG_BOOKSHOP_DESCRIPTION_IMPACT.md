/**
 * WYSIWYG Editor Implementation - Database Impact Analysis
 * 
 * Changes to bookshop_books.description field
 * Date: 2026-06-24
 * 
 * SUMMARY:
 * - No schema changes needed (TEXT column already supports HTML)
 * - All existing data remains backward compatible
 * - Sanitization applied on write and again before formatted display
 * 
 * STORAGE CAPACITY:
 * - MySQL TEXT: up to 65,535 bytes (≈ 64KB)
 * - Application limit: 5,000 characters (unicode-aware)
 * - Typical usage: 500-2,000 characters
 * 
 * DATA MIGRATION:
 * - Existing descriptions: No changes required
 * - They are sanitized before formatted display
 * - New editor generates HTML, properly escaped on display
 * 
 * BACKWARD COMPATIBILITY:
 * ✓ Old plain text descriptions display correctly
 * ✓ New HTML descriptions render with formatting
 * ✓ Query performance: No impact (no new indexes needed)
 * 
 * VALIDATION & SANITIZATION:
 * - New entries sanitized via BookshopDescriptionSanitizer before persistence
 * - Existing entries sanitized via BookshopDescriptionSanitizer before |raw rendering
 * - Allowed tags: <p>, <br>, <strong>, <b>, <em>, <i>, <u>, <ul>, <ol>, <li>, <blockquote>, <a>
 * - Dangerous content blocked: scripts, event handlers, javascript: protocol
 * - Max length enforced: 5,000 characters
 * 
 * FRONTEND SECURITY:
 * - Display uses |raw filter only after backend output sanitization
 * - Links include .nc-bookshop-synopsis-content class for styling
 * - No additional escaping needed (sanitizer handles it)
 * 
 * PERFORMANCE IMPACT:
 * - Minimal: Sanitization runs on write and on paginated rendered rows
 * - Database queries unchanged
 * - No migration script required
 * 
 * TESTING:
 * - Unit tests: BookshopDescriptionSanitizerTest
 * - Visual tests: Not affected (only home page tested)
 * - Manual testing: Edit books and verify HTML rendering
 * 
 * ROLLBACK PLAN:
 * If needed, simply:
 * 1. Revert template changes to use |escape instead of |raw
 * 2. Keep sanitizer (won't hurt existing data)
 * 3. HTML stored in DB won't render, but no data loss
 * 
 * NOTES:
 * - No database backup required before deployment
 * - Existing content queries: SELECT * FROM bookshop_books WHERE description != '';
 * - Monitor: Check for validation errors in logs during first week
 */
