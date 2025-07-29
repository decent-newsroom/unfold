# Features

## Search Functionality (REMOVED)
- **Status**: Being removed as part of scaling down
- **Previous implementation**: Used Elasticsearch via FOSElasticaBundle
- **Replacement**: Will need basic database-based search for articles by title/content if search is still needed
- **Components affected**: 
  - SearchComponent (Twig component)
  - FeaturedList component
  - Article indexing commands
  - Controllers using Elasticsearch queries

## Credit System (REMOVED)
- **Status**: Completely removed as part of scaling down
- **Previous implementation**: Credit-based search system with Redis storage
- **Components removed**:
  - Entire `src/Credits/` directory (CreditsManager, RedisCreditStore, CreditTransaction entity)
  - GetCreditsComponent (Twig component for adding credits)
  - CreditTransactionController (admin interface)
  - Credit accounting in SearchComponent
  - Credit balance display in search interface
  - Credits cache configuration
  - Credit translation keys

## Core Features to Preserve
- Article management (CRUD operations)
- Article display and listing
- Author pages
- Basic article filtering (should use database queries instead of Elasticsearch)
