# Migrator

**Migrator** is a global CLI tool that analyses the complexity of upgrading or migrating a PHP project. Run it against any codebase to get a scored report across framework coupling, database coupling, dependency compatibility, architecture quality, and test coverage.

> **This is a global tool — do not install it as a project dependency.** It runs on your development machine and analyses projects by path.

## Requirements

- PHP 8.2+

## Installation

Install globally with Composer:

```bash
composer global require kerrialn/migrator
```

Ensure the global Composer bin directory is in your `PATH`. Add this to your `~/.zshrc` or `~/.bashrc`:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

Then reload your shell:

```bash
source ~/.zshrc
```

## Usage

Navigate to the project you want to analyse and run:

```bash
cd /path/to/your/project
migrator analyse
```

On the first run, a `migrator.php` config file is generated in the current directory. You can commit this file to the project to persist custom configuration.

## Configuration

The generated `migrator.php` looks like this:

```php
return new Config(
    path: getcwd(),
    exclude: [
        'vendor',
        'tests',
    ],
);
```

Adjust `exclude` to skip directories that should not be analysed.

## What it analyses

### Upgrade
Scores how difficult it would be to upgrade the project to a newer PHP version:

| Metric | Weight |
|---|---|
| Framework Version Upgradability | 35% |
| Dependencies Upgradability | 30% |
| PHP Version Upgradability | 20% |
| Codebase Size | 15% |

### Migration
Scores how difficult it would be to migrate the project to a different framework:

| Metric | Weight |
|---|---|
| Framework Coupling | 35% |
| Database Coupling | 25% |
| Dependency Compatibility | 20% |
| Architecture Quality | 15% |
| Test Coverage | 5% |

Scores are rated:

- **0–49** — Very Difficult
- **50–79** — Moderate
- **80–100** — Straightforward
