# Migrator

**Migrator** is a global CLI tool that analyses the complexity of upgrading or migrating a PHP project. Run it against any codebase to get a scored report across framework coupling, database coupling, dependency compatibility, architecture quality, test coverage, and codebase size.

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

The tool will prompt you for migration or upgrade mode, then source and target framework or PHP version.

On the first run, create a `migrator.php` config file in the project root. You can commit this file to persist your configuration.

## Configuration

```php
<?php

declare(strict_types=1);

use KerrialNewham\Migrator\Config\Config;

return new Config(
    path: getcwd(),
    exclude: [
        'vendor',
        'tests',
        'node_modules',
        'var',
    ],
);
```

### Options

| Option | Type | Description |
|---|---|---|
| `path` | `string` | Root path of the project to analyse. Defaults to `getcwd()`. |
| `exclude` | `string[]` | Directories to skip entirely (vendor, cache, assets, etc.). |
| `legacyDirs` | `string[]` | Directories containing legacy framework code being migrated away from (see below). |

### Mid-migration analysis with `legacyDirs`

When a project is mid-migration, legacy and new code coexist. Including the legacy directories inflates coupling scores and hides the quality of the new code layer. Use `legacyDirs` to separate them:

```php
return new Config(
    path: getcwd(),
    exclude: [
        'vendor',
        'tests',
        'node_modules',
        'var',
    ],
    legacyDirs: [
        'addons/main',   // legacy CI3 modules being migrated away
        'system/cms',    // legacy CI3 application layer
    ],
);
```

The tool will analyse the new code layer independently and append a **Legacy Code Remaining** section to the report showing:

- Legacy file count vs new code file count
- Migration progress as a percentage of files migrated
- Legacy coupling score with a plain-English label (e.g. *heavily coupled — significant rewrite work remains*)

Use the pre-migration score (no `legacyDirs`) as your baseline difficulty rating. Use `legacyDirs` during migration to track progress.

## What it analyses

### Upgrade

Scores how difficult it would be to upgrade the project to a newer PHP version:

| Metric | Weight |
|---|---|
| Framework Version Upgradability | 35% |
| Dependencies Upgradability | 30% |
| PHP Version Upgradability | 20% |
| Codebase Size | 15% |

Scores are rated:

| Range | Label |
|---|---|
| 0–49 | Difficult Upgrade |
| 50–79 | Medium Upgrade |
| 80–100 | Easy Upgrade |

### Migration

Scores how difficult it would be to migrate the project to a different framework:

| Metric | Weight | What it measures |
|---|---|---|
| Framework Coupling | 30% | How many files reference framework-specific patterns (namespaces, helpers, base classes, facades) |
| Database Coupling | 20% | Database abstraction layer quality — Doctrine scores highest, CI3 Active Record and raw SQL lowest |
| Dependency Compatibility | 10% | Composer packages that conflict with or don't exist for the target framework |
| Architecture Quality | 25% | Presence of service layer, repositories, interfaces, constructor DI, and Data Mapper ORM entities |
| Test Coverage | 5% | Ratio of test files; penalises framework-coupled tests |
| Codebase Size | 10% | Logarithmic penalty for large codebases |

Scores are rated:

| Range | Label |
|---|---|
| 0–35 | Extremely Difficult |
| 35–55 | Very Difficult |
| 55–70 | Difficult |
| 70–85 | Moderate |
| 85–100 | Straightforward |

## Supported frameworks

Detection and coupling analysis is supported for:

- Symfony
- Laravel
- CodeIgniter (CI3 and CI4)
- Yii
- Zend / Laminas
- CakePHP
- Phalcon
- Tempest
