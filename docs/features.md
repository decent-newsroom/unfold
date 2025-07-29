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

## Core Features to Preserve
- Article management (CRUD operations)
- Article display and listing
- Author pages
- Basic article filtering (should use database queries instead of Elasticsearch)
