# Data Migration Bundle for Symfony

A declarative, config-driven data migration tool for Symfony applications.  
Designed to migrate data from legacy sources (databases, JSON, CSV\*) into modern Doctrine entities with transformations, post-processing and ID mapping.

---

## Overview

This bundle provides a structured way to migrate data from an old system into a new Symfony project:

- maps legacy rows to Doctrine entities
- applies transformation pipelines to field values
- supports custom post-processors for complex logic
- keeps track of old → new ID mappings
- works with different data sources (database, JSON, CSV\*)

The entire migration process is described declaratively in a single YAML configuration file.

\*Under development in the current version

---

## Intallation

```bash
  composer require --dev fogswimmer/data-migration
```

## Commands

1. Initialize:

```bash
  bin/console data-migration:init
```

2. Launch data migration:

```bash
  bin/console data-migration:migrate
```

## How It Works

For each configured entity, the migration process follows these steps:

1. **Fetches data** from the configured data source (database, JSON, CSV).
2. **Creates a new entity instance** for each source row.
3. **Maps fields** from the source to entity properties.
4. **Applies transformation chains** (e.g. `strip_tags`, `truncate`, custom transformers).
5. **Persists the entity** and stores the old → new ID mapping.
6. **Executes post-processors**, allowing to:
   - create related entities
   - attach media
   - restore relations
   - perform any custom logic

Configuration lives in `config/packages/data_migration.yaml`.

---

## Data Sources

Currently supported data sources:

- **Database** (via Doctrine DBAL connection)
- **JSON files**

The data source is defined globally and reused across tables.

Example:

```yaml
data_source:
  type: database
  connection: old
```

or

```yaml
data_source:
  type: json
```

## Database Connection (Legacy DB)

This bundle does not manage connections itself — you are responsible for configuring access to the legacy database.

### Option 1: SSH Tunnel (Port Forwarding)

If the legacy database is accessible only via SSH:

```bash
ssh -L 0.0.0.0:3307:127.0.0.1:3306 user@remote-host
```

Then configure the connection using host.docker.internal so Docker containers can access the host:

```bash
OLD_DATABASE_URL=mysql://db_user:db_password@host.docker.internal:3307/db_name
```

> Important: host.docker.internal allows Docker containers to reach services running on the host machine.

### Option 2: Temporary Database Container

You may also spin up a temporary container with the legacy database:

```yaml
old-db:
  image: mariadb:10.11
  ports:
    - "3312:3306"
  environment:
    MYSQL_DATABASE: ${OLD_DB_NAME}
    MYSQL_USER: ${OLD_DB_USER}
    MYSQL_PASSWORD: ${OLD_DB_PASSWORD}
    MARIADB_ROOT_PASSWORD: root
```

Then connect normally:

```bash
OLD_DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3312/db_name

```

## Migration Configuration Example

```yaml
data_migration:
  data_source:
    type: database
    connection: old

  tables:
    App\Entity\Doctor:
      source: doctor

      map:
        fullName:
          - surname
          - name
          - patronymic
        description: desc

      transform:
        description:
          - strip_tags
          - truncate: 255

      post_process:
        - doctor_media
        - education_media
```

## Field Mapping

- A single column:

```yaml
description: desc
```

- Multiple columns (concatenated with space):

```yaml
fullName:
  - surname
  - name
  - patronymic
```

## Transformers

Transformers modify field values during migration.

### Transformer Interface

```php
interface DataMigrationTransformerInterface
{
    public function getName(): string;

    public function transform(mixed $value, mixed $params = null): mixed;
}

```

### Example Transformer

```php
final class TruncateTransformer implements DataMigrationTransformerInterface
{
    public function getName(): string
    {
        return 'truncate';
    }

    public function transform(mixed $value, mixed $params = null): mixed
    {
        return mb_substr((string) $value, 0, (int) $params);
    }
}

```

**Usage in YAML**

```yaml
transform:
  description:
    - strip_tags
    - truncate: 255
```

Transformers are automatically discovered via Symfony autowiring.

## Post Processors

Post-processors are executed after an entity is persisted.
They are intended for complex logic such as:

- creating related entities

- restoring relations

- attaching media

- working with legacy auxiliary tables

### Post Processor Interface

```php
interface DataMigrationPostProcessorInterface
{
    public function getName(): string;

    public function process(
        array $oldRow,
        object $entity,
        DataSourceInterface $dataSource,
        mixed $params = null
    ): void;
}
```

### Example Post Processor

1. Simple Data Source (e.h. json)

```php
final class DoctorMediaProcessor implements DataMigrationPostProcessorInterface
{
    public function getName(): string
    {
        return 'doctor_media';
    }

    public function process(
        array $oldRow,
        object $entity,
        DataSourceInterface $dataSource
    ): void {
        // custom logic here
    }
}
```

2.When usign database as source, please use RequiresAdvancedQuerySourceInterface to be able to use such methods as **fetchColumn**, **fetchOne**, **fetchAllByQuery** from **AdvancedQueryDataSourceInterface**

```php
final class DoctorMediaPostProcessor
    implements DataMigrationPostProcessorInterface, RequiresAdvancedQuerySourceInterface
{
    public function getName(): string
    {
        return 'doctor_media';
    }

    public function process(
        array $oldRow,
        object $entity,
        DataSourceInterface $source,
        ?array $params = null,
    ): void {

    }
}

interface AdvancedQueryDataSourceInterface extends DataSourceInterface
{
    public function fetchColumn(
        string $resource,
        string $column,
        array $criteria = [],
    ): array;

    public function fetchOne(
        string $resource,
        string $column,
        array $criteria = [],
    ): mixed;

    public function fetchAllByQuery(
        string $sql,
        array $params = [],
    ): iterable;
}

```

**Usage in YAML**

```yaml
post_process:
  - doctor_media
```

## ID Mapping

The migration engine automatically stores old → new ID mappings.

This allows post-processors to:

- resolve relations between migrated entities

- restore foreign keys correctly

## Extending the Bundle

You can freely extend the migration by adding:

- custom transformers

- custom post-processors

- custom data sources

All custom logic stays inside your project, the bundle only provides the infrastructure.

## Notes

- The bundle intentionally does not manage Doctrine ORM configuration.

- Database connectivity must be handled by the host project.

- Docker users must explicitly expose host services if required.
