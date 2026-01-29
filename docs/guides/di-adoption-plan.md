# DI Container Adoption Plan

This document outlines the plan to fully adopt dependency injection across the Shuriken Reviews plugin.

## Current State Assessment

### ✅ DI-Ready Services (Phase 1 & 2 Complete)
| Component | Status | Dependencies |
|-----------|--------|--------------|
| `Shuriken_Container` | ✅ Complete | Lightweight DI container |
| `Shuriken_Database_Interface` | ✅ Complete | 20+ method contract |
| `Shuriken_Analytics_Interface` | ✅ Complete | 15+ method contract |
| `Shuriken_Analytics` | ✅ Complete | `$db` via constructor |
| `Shuriken_REST_API` | ✅ Complete | `$db` via constructor |
| `Shuriken_Admin` | ✅ Complete | `$db`, `$analytics` via constructor |
| `Shuriken_AJAX` | ✅ Complete | `$db` via constructor |
| `Shuriken_Block` | ✅ Complete | `$db` via constructor |
| `Shuriken_Shortcodes` | ✅ Complete | `$db` via constructor |

### ⏭️ No Changes Needed
| Class | Reason |
|-------|--------|
| `Shuriken_Database` | Foundation service, singleton is appropriate |
| `Shuriken_Frontend` | No database dependencies (assets only) |

### Coverage Metrics
- **DI Adoption:** 87.5% (7 of 8 services)
- **All services with database dependencies now use DI**

---

## Phase 1: Core Services ✅ COMPLETED

**Status:** Implemented on January 29, 2026

### 1.1 Shuriken_REST_API ✅

- Added `$db` property with `Shuriken_Database_Interface` type
- Added optional `$db` parameter to constructor
- Made constructor public
- Replaced all 15+ `shuriken_db()` calls with `$this->db`
- Added `get_db()` method for testing access

### 1.2 Shuriken_Admin ✅

- Added `$db` and `$analytics` properties
- Added optional `$db` and `$analytics` parameters to constructor
- Made constructor public
- Replaced all direct helper calls with `$this->db` and `$this->analytics`
- Added `get_db()` and `get_analytics()` methods

### 1.3 Shuriken_AJAX ✅

- Added `$db` property
- Added optional `$db` parameter to constructor
- Made constructor public
- Replaced all `shuriken_db()` calls with `$this->db`
- Added `get_db()` method

---

## Phase 2: Block & Shortcode Services ✅ COMPLETED

**Status:** Implemented on January 29, 2026

### 2.1 Shuriken_Block ✅

- Added `$db` property with `Shuriken_Database_Interface` type
- Added optional `$db` parameter to constructor
- Made constructor public
- Replaced all `shuriken_db()` calls with `$this->db`
- Updated container registration to inject database
- Added `get_db()` method

### 2.2 Shuriken_Shortcodes ✅

- Added `$db` property with `Shuriken_Database_Interface` type
- Added optional `$db` parameter to constructor
- Made constructor public
- Replaced `shuriken_db()` calls with `$this->db`
- Updated container registration to inject database
- Added `get_db()` method

---

## Container Registration (Final State)

All services are now registered with proper dependency injection:

```php
// Foundation services
$this->singleton('database', function() {
    return Shuriken_Database::get_instance();
});

$this->singleton('analytics', function($container) {
    return new Shuriken_Analytics($container->get('database'));
});

// Core services (Phase 1)
$this->singleton('rest_api', function($container) {
    return new Shuriken_REST_API($container->get('database'));
});

$this->singleton('admin', function($container) {
    return new Shuriken_Admin(
        $container->get('database'),
        $container->get('analytics')
    );
});

$this->singleton('ajax', function($container) {
    return new Shuriken_AJAX($container->get('database'));
});

// Block & Shortcode services (Phase 2)
$this->singleton('shortcodes', function($container) {
    return new Shuriken_Shortcodes($container->get('database'));
});

$this->singleton('block', function($container) {
    return new Shuriken_Block($container->get('database'));
});

// No-dependency service
$this->singleton('frontend', function() {
    return Shuriken_Frontend::get_instance();
});
```

---

## Dependency Graph (Final State)

```
┌─────────────────────────────────────────────────────┐
│                  Shuriken_Container                  │
│                   (Service Registry)                 │
└─────────────────────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
    ┌──────────┐   ┌───────────┐   ┌───────────┐
    │ database │   │ analytics │   │ frontend  │
    │ (base)   │   │ (db) ✅   │   │ (none)    │
    └──────────┘   └───────────┘   └───────────┘
          │               │
          ▼               ▼
    ┌──────────────────────────────────────────┐
    │   All Database-Dependent Services        │
    │                                          │
    │   ┌──────────┐  ┌───────────┐  ┌──────┐ │
    │   │ rest_api │  │   admin   │  │ ajax │ │
    │   │ (db) ✅  │  │(db+ana)✅ │  │(db)✅│ │
    │   └──────────┘  └───────────┘  └──────┘ │
    │                                          │
    │   ┌──────────┐  ┌────────────┐          │
    │   │  block   │  │ shortcodes │          │
    │   │  (db) ✅ │  │   (db) ✅  │          │
    │   └──────────┘  └────────────┘          │
    │                                          │
    └──────────────────────────────────────────┘

✅ = DI-ready with proper constructor injection
```

---

## Testing Strategy

### For Each DI-Ready Class:

1. **Verify DI works with mock:**
```php
public function test_dependency_injection() {
    $mock_db = new Mock_Shuriken_Database();
    $service = new Shuriken_REST_API($mock_db);
    
    $this->assertSame($mock_db, $service->get_db());
}
```

2. **Verify backward compatibility:**
```php
public function test_backward_compatibility() {
    // No arguments = uses default database
    $service = new Shuriken_REST_API();
    $this->assertInstanceOf(Shuriken_Database::class, $service->get_db());
}
```

3. **Verify container integration:**
```php
public function test_container_integration() {
    $service = shuriken_container()->get('rest_api');
    $this->assertInstanceOf(Shuriken_REST_API::class, $service);
    $this->assertInstanceOf(Shuriken_Database::class, $service->get_db());
}
```

---

## Backward Compatibility

### ✅ Maintained
- `shuriken_db()` helper function (uses container internally)
- `shuriken_analytics()` helper function (uses container internally)
- `ClassName::get_instance()` static methods (still work)
- `ClassName::init()` static methods (still work)

### 🔄 Changed (Internal Only)
- Services now created via container with injected dependencies
- Constructors are now public and accept optional dependencies
- Direct helper calls inside classes replaced with `$this->db`

---

## Success Metrics (Final)

| Metric | Before | After Phase 1 | After Phase 2 |
|--------|--------|---------------|---------------|
| Classes with DI support | 1 | 5 | 7 |
| DI adoption percentage | 12.5% | 62.5% | **87.5%** |
| Direct `shuriken_db()` calls in services | 20+ | ~8 | **0** |
| Services using container DI | 1 | 5 | **7** |

---

## Resources

- [Dependency Injection Guide](dependency-injection.md) - DI concepts and usage
- [Testing Guide](testing.md) - Testing with mocks
- [Architecture Overview](../ARCHITECTURE.md) - Overall architecture
