# Organization Hierarchy

## Purpose

Provide a tree-based organization hierarchy with scopes (levels) and nodes (entities) for multi-tenant and departmental authorization. Users are assigned roles at specific nodes, and access cascades through the hierarchy via materialized path queries.

## Requirements

### Requirement: Organization scopes SHALL define hierarchy levels

OrganizationScope model SHALL define named hierarchy levels with integer ordering (e.g., Company=1, Department=2, Team=3).

#### Scenario: Creating hierarchy levels
- **WHEN** scopes are created with levels 1, 2, 3
- **THEN** they represent a 3-level organization hierarchy

### Requirement: Organization nodes SHALL form a tree structure using materialized paths

OrganizationNode model SHALL store a `path` field in format `1/2/4` (ancestor IDs separated by `/`) and a `parent_id` for tree traversal.

#### Scenario: Creating child nodes
- **WHEN** a node is created with parent_id pointing to an existing node
- **THEN** its path is set to `{parent_path}/{node_id}`

#### Scenario: Root node
- **WHEN** the root node exists
- **THEN** its path is its own ID (e.g., `1`)

### Requirement: Users SHALL access nodes based on role assignments

The system SHALL return accessible organization nodes for the current user+role combination via `organizationNodes()` and `organizationNodesQuery()`.

#### Scenario: User assigned to department node
- **WHEN** user has role assigned at Department A (path `1/2`)
- **THEN** `organizationNodes()` returns Department A and all descendant nodes (Teams under it)

#### Scenario: Accessing specific node
- **WHEN** `organizationNode($nodeId)` is called for an accessible node
- **THEN** the OrganizationNode model is returned

#### Scenario: Accessing inaccessible node
- **WHEN** `organizationNode($nodeId)` is called for a node not in user's scope
- **THEN** an InvalidOrganizationNodeException is thrown

### Requirement: Ancestor-descendant checks SHALL use path-based queries

The `descendant($parentId, $childId)` method SHALL verify hierarchical relationships using LIKE queries on the materialized path.

#### Scenario: Valid descendant
- **WHEN** parent has path `1/2` and child has path `1/2/4`
- **THEN** `descendant(2, 4)` returns true

#### Scenario: Not a descendant
- **WHEN** nodes are in different branches
- **THEN** `descendant()` returns false

### Requirement: Organization nodes SHALL support polymorphic model binding

Nodes SHALL have optional `model_type` and `model_id` fields for binding to application-specific Eloquent models.

#### Scenario: Node bound to a model
- **WHEN** a node has `model_type` and `model_id` set
- **THEN** the associated model can be retrieved via the polymorphic relationship
