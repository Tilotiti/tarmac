# Cursor Rules Configuration

This directory contains optimized, scoped rules for the Tarmac project.

## Rule Files & Scoping

### Always Applied Rules (Loaded for all files)
- **`core.mdc`** - Core application context (~25 lines)
- **`local-env.mdc`** - Local dev environment setup (~38 lines)

**Total always-applied**: ~63 lines (down from ~280 lines!)

### Contextual Rules (Loaded only when relevant)

#### UI/Template Work
- **`mobile-first.mdc`** - Mobile-first design principles
  - Scope: `templates/**/*.twig`, `assets/**/*`
  - ~50 lines (down from ~95 lines)

- **`naming.mdc`** - Template naming conventions
  - Scope: `templates/**/*.twig`
  - ~55 lines

#### Translation Work
- **`translations.mdc`** - Translation workflow
  - Scope: `templates/**/*.twig`, `src/Controller/**/*.php`, `src/Form/**/*.php`
  - ~69 lines

#### Controller Development
- **`breadcrumbs.mdc`** - Breadcrumb navigation rules
  - Scope: `src/Controller/**/*.php`
  - ~156 lines

#### Filtering Features
- **`filters.mdc`** - Filter component usage
  - Scope: `templates/**/*.twig`, `src/Controller/**/*.php`, `src/Form/**/*FilterType.php`
  - ~70 lines

## Performance Impact

### Before Optimization
- Always loaded: ~280 lines of rules
- Context overhead: ~2,000 tokens per conversation turn
- All rules processed even when irrelevant

### After Optimization
- Always loaded: ~63 lines (77% reduction!)
- Context overhead: ~400 tokens per conversation turn (80% reduction!)
- Contextual rules load only when editing relevant files

## Expected Improvements

1. **Faster response times** on backend/entity work (no UI rules loaded)
2. **Reduced thinking time** (less context to process)
3. **More relevant suggestions** (only applicable rules loaded)
4. **Better token efficiency** (80% less overhead)

## Rule Priority System

Rules load in priority order (higher = more important):
- 200: Core application context
- 150: Translations
- 130: Template naming
- 120: Breadcrumbs
- 110: Filters
- 100: Mobile-first design

## Maintenance

When adding new rules:
1. Ask: "Is this needed for EVERY file?" → Use `alwaysApply: true`
2. Otherwise: Define specific `globs` for relevant files
3. Keep always-applied rules under 100 lines total
4. Use descriptive priorities (100-200 range)

## Testing the Configuration

After changes, verify rules load correctly:
1. Open a template file → mobile-first, translations, template-naming should load
2. Open a controller file → breadcrumbs, translations should load
3. Open an entity file → only core + local-environment should load

---

Last updated: 2025-10-27
Optimized by: AI Assistant (Cursor)

