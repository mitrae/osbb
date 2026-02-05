<!--
SYNC IMPACT REPORT
==================
Version change: 0.0.0 → 1.0.0
Bump rationale: Initial constitution creation (MAJOR)

Added sections:
- Core Principles (5 principles)
- Technology Stack
- Development Workflow
- Governance

Modified principles: N/A (initial version)
Removed sections: N/A (initial version)

Templates status:
- .specify/templates/plan-template.md: ✅ Compatible (Constitution Check section exists)
- .specify/templates/spec-template.md: ✅ Compatible
- .specify/templates/tasks-template.md: ✅ Compatible

Follow-up TODOs: None
-->

# OpenBizBox Constitution

## Core Principles

### I. Bundle-Based Architecture

All features MUST be organized within Symfony bundles following the established structure:

- **FrontendBundle**: Customer-facing functionality (catalog, cart, checkout, user accounts)
- **BackendBundle**: Administration panel and management interfaces
- **ApiBundle**: REST API endpoints (FOSRest, NelmioApiDoc, HATEOAS)
- New features MUST extend existing bundles or justify creation of new bundles
- Cross-bundle dependencies MUST flow from specific to general (Frontend → Backend → Core)

**Rationale**: Maintains separation of concerns and enables independent deployment of customer-facing vs administrative functionality.

### II. Code Style & Quality

All code MUST adhere to the established style guidelines:

- **PHP**: PSR-2 based with tabs for indentation, camelCase for methods/variables, UPPERCASE for constants, PascalCase for class names
- **Frontend**: 2-space indentation, camelCase variables, PascalCase components
- **Documentation**: Docblocks MUST be added to all classes and public methods
- **Error Handling**: Exceptions MUST be caught and logged through Sentry; user-facing errors MUST be translated
- **Quality Gate**: `./core/symfony/vendor/bin/phpcs --standard=PSR2` MUST pass before merge

**Rationale**: Consistent code style reduces cognitive load during reviews and maintenance.

### III. Testing Requirements

All changes MUST be validated through appropriate testing:

- **Unit Tests**: PHPUnit for business logic; groups include 'databaseAware', 'economics', 'cron'
- **Functional Tests**: Behat for user journeys; tags include '@passive', '@semiactive', '@active', '@javascript'
- **Test Execution**: Run `phing test` (all), `phing test-model` (unit), or `phing test-behat-feature featureTag=X` (single feature)
- External dependencies MUST be mocked in unit tests
- Database-dependent tests MUST be tagged with 'databaseAware'

**Rationale**: Automated testing prevents regressions in a large ecommerce codebase where manual testing is impractical.

### IV. Backward Compatibility

Changes MUST maintain backward compatibility unless explicitly approved:

- Database schema changes MUST use migration scripts in `core/changes/` with pattern `[priority]-[issue_number]-[description].[php|sql]`
- ORM changes auto-generate at priority 50; scripts needing to run after ORM MUST use priority > 50
- API changes MUST be versioned; breaking changes require deprecation period
- Theme/template changes MUST respect LiipThemeBundle override hierarchy
- User customization files (`/web/user-*.less`, `/user-scripts.js`) MUST NOT be overwritten

**Rationale**: Production shops depend on stable APIs and data structures; breaking changes cause merchant downtime.

### V. Frontend Design System

Frontend changes MUST follow the established design system:

- **Framework**: Bootstrap 3.4.1 with custom 24-column grid (non-negotiable; deeply integrated)
- **Styling**: LESS compilation via Webpack Encore; user overrides in `/web/user-variables.less` and `/web/user-styles.less`
- **Templates**: LiipThemeBundle with resolution order: user theme-specific → user fallback → core theme → core fallback
- **JavaScript**: jQuery 3.3.1, Bootstrap JS, Lodash available globally; custom scripts in `/user-scripts.js`
- Theme changes MUST include all required files: `theme.less`, `tt-variables.less`, `info.yml`

**Rationale**: The 24-column Bootstrap grid and theming system are foundational; alternatives require 2-3+ months refactoring.

## Technology Stack

The following technology choices are fixed and MUST NOT be replaced without constitution amendment:

| Layer | Technology | Version |
|-------|------------|---------|
| Framework | Symfony | 4.x |
| Database | MariaDB/MySQL | 10.5+ |
| Search | Elasticsearch | 7.17.x |
| Cache | Redis | 3.2+ |
| Queue | RabbitMQ (AMQP) | - |
| PHP | PHP-FPM | 7.4+ |
| CSS | Bootstrap + LESS | 3.4.1 |
| JS Build | Webpack Encore | - |

**Dependencies requiring explicit approval to add/upgrade**:
- Any new Symfony bundle
- Any new JavaScript library loaded globally
- Any new external service integration

## Development Workflow

### Branch Strategy

- All work MUST branch from `develop`
- Feature branches MUST follow pattern: `[issue-number]-[brief-description]`
- Merges to `develop` MUST go through pull requests
- Direct pushes to `develop` or `main` are PROHIBITED

### Pull Request Requirements

1. Code MUST pass PSR-2 linting
2. All existing tests MUST pass
3. New functionality MUST include appropriate tests
4. Database changes MUST include migration scripts
5. Reviewers MUST verify backward compatibility implications

### Release Process

1. Run `phing release` to prepare SQL changes
2. Create whatsnew files for user-facing changes: `whatsnew.[STORY_ID].[description].php`
3. Translation updates via `phing i18n`

## Governance

This constitution supersedes all other development practices for the OpenBizBox project.

### Amendment Process

1. Propose amendment via pull request to `.specify/memory/constitution.md`
2. Document rationale and migration plan for breaking changes
3. Require approval from project maintainers
4. Update version following semver: MAJOR (breaking), MINOR (additions), PATCH (clarifications)

### Compliance

- All PRs MUST be reviewed against constitution principles
- Violations MUST be documented in PR with justification if approved as exception
- Repeated violations MAY result in reverted changes
- Runtime development guidance lives in `CLAUDE.md`

### Exception Handling

Exceptions to constitution rules require:
1. Written justification in PR description
2. Explicit approval from at least one maintainer
3. Documentation of technical debt if applicable

**Version**: 1.0.0 | **Ratified**: 2025-11-30 | **Last Amended**: 2025-11-30
