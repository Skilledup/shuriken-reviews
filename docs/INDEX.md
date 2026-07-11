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
Complete API reference for all WordPress hooks (50+ filters and actions) with examples and use cases.
- Rating display filters
- Vote submission filters  
- Database operation filters
- Frontend asset filters
- Post meta filters (content injection, JSON-LD) — added in 1.12
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
- DI adoption status (100% coverage)
- Best practices

#### [Testing & Testing Utilities](guides/testing.md)
Learn how to test your code with mock implementations without requiring a database.
- Interface-based testing
- Mock implementations
- PHPUnit examples
- WordPress test suite integration

#### [Add-on Development](guides/add-on-development.md)
Build third-party plugins that extend Shuriken without forking core.
- Add-on plugin skeleton
- PHP, REST, admin, block editor, and frontend integration surfaces
- Block view data pipeline (`shuriken_block_view_data` → `shurikenBlockViewData`)
- Caching considerations for full-page cache compatibility
- Service access and container overrides

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

### 📜 Changelog

#### [Changelog](CHANGELOG.md)
Full history of changes, features, and fixes organised by version.

---

## Getting Started

### For End Users

1. **Installation** - See main [README.md](../README.md) for installation instructions
2. **Configuration** - Visit **Shuriken Reviews > Settings** in your WordPress admin
3. **Creating Ratings** - Navigate to **Shuriken Reviews > Ratings**
4. **Analytics** - Check **Shuriken Reviews > Analytics** for statistics

### For Developers

1. **Understand the Architecture** - Read [Architecture Overview](ARCHITECTURE.md)
2. **Build an add-on** - Follow [Add-on Development Guide](guides/add-on-development.md)
3. **Learn the Hooks System** - Check [Hooks Reference](guides/hooks-reference.md)
4. **Explore Dependency Injection** - See [Dependency Injection Guide](guides/dependency-injection.md)
5. **Set Up Testing** - Follow [Testing Guide](guides/testing.md)

---

## Roadmap & Status

See [ROADMAP.md](ROADMAP.md) for current feature status and planned work.

---

## Architecture Overview

The plugin uses a **modular architecture** with clear separation of concerns. See [ARCHITECTURE.md](ARCHITECTURE.md) for the full component breakdown, data flow diagrams, and design patterns.

**Key Design Principles:**
- **Single Responsibility Principle** - Each class has one clear purpose
- **Loose Coupling** - Depend on interfaces, not concrete classes
- **High Cohesion** - Related functionality grouped together
- **Extensibility** - 50+ hooks for customization without modifying core
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

👉 Start with [Add-on Development Guide](guides/add-on-development.md), then [Hooks Reference](guides/hooks-reference.md) for the full catalog

### I want to write unit tests

👉 Follow [Testing Guide](guides/testing.md) with mock implementations

---

## Key Concepts

### Hooks System
The plugin exposes 50+ WordPress hooks (filters and actions) for complete customization:
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
- `Shuriken_Analytics_Interface` - Analytics calculations (extends five focused sub-interfaces: `Formatter`, `Ranking`, `Dashboard`, `Rating_Stats`, `Context`)

---

## Support & Contribution

For support, issues, and contributions:

📌 **GitHub Repository:** [github.com/Skilledup/shuriken-reviews](https://github.com/Skilledup/shuriken-reviews)

💬 **Report Issues:** [GitHub Issues](https://github.com/Skilledup/shuriken-reviews/issues)

---

## License

Licensed under [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html)

Developed with ❤️ by [Skilledup](https://skilledup.ir)
