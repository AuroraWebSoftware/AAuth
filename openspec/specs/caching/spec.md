# Caching

## Purpose

Optimize authorization performance by caching role data and permission computations, reducing database queries for repeated permission checks within and across requests.

## Requirements

### Requirement: Role caching SHALL be configurable

When `aauth-advanced.cache.enabled` is true, role lookups SHALL use cached data instead of direct database queries.

#### Scenario: Cache enabled
- **WHEN** `cache.enabled` is true in aauth-advanced config
- **THEN** role data is loaded from cache with configured store, TTL, and prefix

#### Scenario: Cache disabled
- **WHEN** `cache.enabled` is false
- **THEN** role data is loaded directly from the database on every request

### Requirement: Cache SHALL support configurable store, TTL, and prefix

The cache configuration SHALL allow specifying cache store, time-to-live, and key prefix via config and environment variables.

#### Scenario: Custom cache configuration
- **WHEN** config specifies `store: redis`, `ttl: 3600`, `prefix: aauth`
- **THEN** role data is cached in Redis with 1-hour TTL and `aauth:` key prefix

### Requirement: Request-level context SHALL prevent redundant computations

The AAuth class SHALL use Laravel's Context facade for request-scoped caching to avoid redundant permission computations within a single request.

#### Scenario: Multiple permission checks in one request
- **WHEN** `can('edit-post')` is called multiple times in the same request
- **THEN** the authorization context is computed once and reused

### Requirement: Cache SHALL be clearable

The system SHALL provide mechanisms to clear cached authorization data.

#### Scenario: Clear context
- **WHEN** `AAuth::clearContext()` is called
- **THEN** the request-level context is cleared

#### Scenario: Clear role cache
- **WHEN** `Cache::forget('aauth:role:' . $roleId)` is called
- **THEN** the cached role data is invalidated
