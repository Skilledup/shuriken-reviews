# Shuriken Reviews Documentation

Welcome to the comprehensive documentation for **Shuriken Reviews** — a powerful and flexible WordPress plugin for rating systems and enhanced comment functionality.

## Quick Start

- [Getting Started](#getting-started)
- [Roadmap & Status](#roadmap--status)
- [Architecture Overview](#architecture-overview)

---

## Documentation Index

### 📋 Reference Guides

#### [REST API Reference](guides/rest-api.md)
Complete reference for all REST API endpoints with authentication, parameters, and usage examples.
- Ratings CRUD operations
- Search and filtering endpoints
- Public endpoints (stats, nonce)
- Authentication methods
- Error handling

#### [Helper Functions](guides/helper-functions.md)
Reference for all global helper functions providing access to plugin services and components.
- Core helper functions
- Service accessors (database, analytics)
- Component accessors
- Usage patterns and examples

#### [Hooks Reference](guides/hooks-reference.md)
Complete API reference for all 20 WordPress hooks (12 filters + 8 actions) with examples and use cases.
- Rating display filters
- Vote submission filters  
- Database operation filters
- Frontend asset filters
- Action hooks

#### [Exception System](guides/exception-handling.md)
Learn about the plugin's exception hierarchy and error handling system for robust, maintainable code.
- Exception types and hierarchy
- Factory methods
- Usage examples
- Error handling patterns
- HTTP status code mapping

#### [Dependency Injection](guides/dependency-injection.md)
Guide to the lightweight DI container for flexible service management and testability.
- Container usage
- Registering services
- Constructor injection pattern
- Testing with mocks
- DI adoption status (87.5% coverage)
- Best practices

#### [Testing & Testing Utilities](guides/testing.md)
Learn how to test your code with mock implementations without requiring a database.
- Interface-based testing
- Mock implementations
- PHPUnit examples
- WordPress test suite integration

#### [Error Handling in FSE Blocks](guides/error-handling-blocks.md)
Comprehensive guide to error handling in Gutenberg blocks with retry functionality.
- Error flow from backend to frontend
- User-friendly error messages
- Retry functionality
- Error types and codes
- Best practices and testing

### 🏗️ Architecture & Design

#### [Architecture Overview](ARCHITECTURE.md)
High-level overview of the plugin's structure, design decisions, module organization, and DI container adoption status.

### 🗺️ Roadmap

#### [Development Roadmap](ROADMAP.md)
Current status of features, implementation progress, and planned work.
- Implemented features
- Rate limiting roadmap
- Integration features
- Future extensions

---

## Getting Started

### For End Users

1. **Installation** - See main [README.md](../README.md) for installation instructions
2. **Configuration** - Visit **Shuriken Reviews > Settings** in your WordPress admin
3. **Creating Ratings** - Navigate to **Shuriken Reviews > Ratings**
4. **Analytics** - Check **Shuriken Reviews > Analytics** for statistics

### For Developers

1. **Understand the Architecture** - Read [Architecture Overview](ARCHITECTURE.md)
2. **Learn the Hooks System** - Check [Hooks Reference](guides/hooks-reference.md)
3. **Explore Dependency Injection** - See [Dependency Injection Guide](guides/dependency-injection.md)
4. **Set Up Testing** - Follow [Testing Guide](guides/testing.md)

---

## Roadmap & Status

### Current Version: 1.11.1

**Major Features** ✅
- Rating system with parent-child relationships
- Mirror ratings with vote synchronization
- Display-only aggregate ratings
- Guest voting support
- FSE Block integration with style presets
- Mirror management in block editor (CRUD + inline rename)
- Shared block helpers and unified rating search
- Shortcode support
- REST API endpoints
- Analytics dashboard with CSV export
- Voter Activity page (member & guest tracking)
- Vote rate limiting with modern settings UI
- 25+ WordPress hooks for extensibility
- Dependency injection container (87.5% coverage)
- Comprehensive exception system
- Interface-based testing support

**Planned Features** 🚧
- Server-side render pre-fetch (batch query for frontend pages)
- Statistics caching
- Rate limit performance caching
- Email notifications
- Webhook integration

See [ROADMAP.md](ROADMAP.md) for detailed implementation status.

---

## Architecture Overview

The plugin uses a **modular architecture** with clear separation of concerns:

```
shuriken-reviews/
├── shuriken-reviews.php          # Main plugin file (orchestration)
├── blocks/
│   ├── shared/
│   │   ├── ratings-store.js            # Shared @wordpress/data store
│   │   └── block-helpers.js            # Shared utilities
│   ├── shuriken-rating/
│   │   ├── index.js                    # Single rating block
│   │   ├── block.json                  # Block metadata
│   │   └── editor.css                  # Editor-only styles
│   └── shuriken-grouped-rating/
│       ├── index.js                    # Grouped rating block
│       ├── block.json                  # Block metadata
│       └── editor.css                  # Editor-only styles
├── includes/
│   ├── class-shuriken-rest-api.php      # REST API endpoints
│   ├── class-shuriken-shortcodes.php    # Shortcode rendering
│   ├── class-shuriken-block.php         # Gutenberg block
│   ├── class-shuriken-ajax.php          # AJAX handlers
│   ├── class-shuriken-frontend.php      # Frontend assets
│   ├── class-shuriken-admin.php         # Admin pages
│   ├── class-shuriken-analytics.php     # Analytics logic
│   ├── class-shuriken-database.php      # Database operations
│   ├── class-shuriken-container.php     # DI container
│   ├── class-shuriken-exception-handler.php  # Error handling
│   ├── interfaces/
│   │   ├── interface-shuriken-database.php
│   │   ├── interface-shuriken-analytics.php
│   │   └── interface-shuriken-rate-limiter.php
│   └── exceptions/
│       ├── class-shuriken-exception.php
│       ├── class-shuriken-database-exception.php
│       ├── class-shuriken-validation-exception.php
│       ├── class-shuriken-not-found-exception.php
│       ├── class-shuriken-permission-exception.php
│       ├── class-shuriken-logic-exception.php
│       ├── class-shuriken-configuration-exception.php
│       ├── class-shuriken-rate-limit-exception.php
│       └── class-shuriken-integration-exception.php
└── docs/
    ├── INDEX.md                 # This file
    ├── ARCHITECTURE.md
    ├── ROADMAP.md
    └── guides/
        ├── hooks-reference.md
        ├── dependency-injection.md
        ├── exception-handling.md
        ├── rest-api.md
        ├── error-handling-blocks.md
        ├── helper-functions.md
        └── testing.md
```

**Key Design Principles:**
- **Single Responsibility Principle** - Each class has one clear purpose
- **Loose Coupling** - Depend on interfaces, not concrete classes
- **High Cohesion** - Related functionality grouped together
- **Extensibility** - 20+ hooks for customization without modifying core
- **Testability** - Interfaces and dependency injection for unit testing

---

## Common Tasks

### I want to customize rating display

👉 See [Hooks Reference - Rating Display Filters](guides/hooks-reference.md#rating-display-filters)

### I want to control voting permissions

👉 See [Hooks Reference - Vote Submission Filters](guides/hooks-reference.md#vote-submission-filters)

### I want to create a custom rating service

👉 Read [Dependency Injection Guide](guides/dependency-injection.md) and [Architecture Overview](ARCHITECTURE.md)

### I want to add a feature that requires database changes

👉 Check [Exception System Guide](guides/exception-handling.md) and [Testing Guide](guides/testing.md)

### I want to extend the plugin

👉 Review [Hooks Reference](guides/hooks-reference.md) for integration points

### I want to write unit tests

👉 Follow [Testing Guide](guides/testing.md) with mock implementations

---

## Key Concepts

### Hooks System
The plugin exposes 20 WordPress hooks (filters and actions) for complete customization:
- **Filters** modify data before processing or display
- **Actions** run custom code at specific points in execution

All hooks work consistently for both shortcodes and Gutenberg blocks.

### Dependency Injection
Services are injected into classes rather than created internally:
- Makes code testable (can inject mocks)
- Makes code flexible (can swap implementations)
- Makes dependencies explicit (visible in constructor)

### Exception System
Comprehensive error handling with specific exception types:
- Type-safe exception catching
- Consistent error messages
- Automatic HTTP status code mapping
- Built-in logging

### Interfaces
Contracts that classes implement to ensure testability:
- `Shuriken_Database_Interface` - Database operations
- `Shuriken_Analytics_Interface` - Analytics calculations

---

## Support & Contribution

For support, issues, and contributions:

📌 **GitHub Repository:** [github.com/Skilledup/shuriken-reviews](https://github.com/Skilledup/shuriken-reviews)

💬 **Report Issues:** [GitHub Issues](https://github.com/Skilledup/shuriken-reviews/issues)

---

## Version History

### v1.11.x (Current)
Mirror management in block editor:
- Full mirror CRUD (create, rename, delete) in grouped block modals
- Unified rating/mirror search dropdown
- Shared block helpers module
- New `/ratings/{id}/mirrors` REST endpoint
- `parents_and_mirrors` search type
- Polished modal UI with CSS classes

### v1.10.x
FSE block v2 with style presets, vote rate limiting, voter activity page.

### v1.9.x
Data retrieval efficiency, shared store, AJAX search, batch queries.

### v1.7.x
Initial hooks system, DI container, and REST API implementation.

### v1.6.0 & Earlier
See main [README.md](../README.md) for complete changelog.

---

## License

Licensed under [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html)

Developed with ❤️ by [Skilledup](https://skilledup.ir)
