# Lyger Framework v0.1

A high-performance PHP framework with Rust FFI integration.

## Installation

```bash
# Clone the repository
git clone https://github.com/betoalien/Lyger-PHP-Framework.git
cd Lyger-framework

# Install PHP dependencies
composer install

# Start development server
php rawr serve
```

## Demo

```bash
# Clone the demo project
git clone https://github.com/betoalien/Lyger-PHP-v0.1-Dental-Clinic-Demo.git
```

## Requirements

- PHP 8.0 or higher
- FFI extension enabled

Create a `php.ini` file in the root directory:
```ini
[PHP]
ffi.enable = 1
```

## Configuration

### Database

Edit `.env` file:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Supported databases: SQLite, PostgreSQL, MySQL, MariaDB

## CLI Commands

```bash
php rawr serve                    # Start server
php rawr serve --port=8080        # Custom port
php rawr make:controller Name     # Create controller
php rawr make:model Name          # Create model
php rawr make:migration Name      # Create migration
php rawr migrate                  # Run migrations
php rawr migrate:rollback         # Rollback migrations
```

## Documentation

Full documentation coming soon.

## License

MIT
