# Changelog

## [2.2.0] - 2024-07-31

### Added
- New `_product_class` metadata field
- Enhanced manufacturer detection with HTML entity handling
- JavaScript price structure fixes for accessibility
- Comprehensive debug interface with field mapping reference
- Better slug sanitization for special characters

### Changed
- Improved manufacturer slug processing with fallback sources
- Enhanced taxonomy path generation for hierarchical structures
- Updated admin interface with cleaner documentation

### Fixed
- HTML entities in manufacturer names
- Multiple consecutive hyphens in slugs
- Price display structure for screen readers
- Empty taxonomy terms handling

## [2.1.0] - 2024-06-15

### Added
- PEWC (Product Extra Fields) integration
- Enhanced discount calculation logic
- Better error handling

### Changed
- Improved discount price calculation
- Enhanced REST API response structure

### Fixed
- Discount detection for products without sale prices
- Taxonomy hierarchy processing for nested categories

## [2.0.0] - 2024-05-20

### Added
- Complete plugin rewrite with modular architecture
- REST API integration for WooCommerce endpoints
- Admin debug interface
- Hierarchical taxonomy support
- Dynamic metadata injection without database storage
- Multi-source manufacturer detection
- Discount price calculation with plugin integration

### Changed
- Moved from database storage to dynamic computation
- Improved performance with on-demand processing

## [1.0.0] - 2024-01-15

### Added
- Initial plugin release
- Basic category and tag slug generation
- Simple metadata injection
- WooCommerce integration