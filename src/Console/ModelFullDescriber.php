<?php

declare(strict_types=1);

namespace PMieleszkiewicz\ModelDescriber\Console;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModelFullDescriber extends AbstractModelDescriber
{

    const DEFAULT_MODEL_NAMESPACE = 'App\\Models';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model:describe {class : Described model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Eloquent model details (basic info, properties)';

    /**
     * @var Repository
     */
    private $config;

    /**
     * Create a new command instance.
     *
     * @param Repository $config
     */
    public function __construct(Repository $config)
    {
        parent::__construct($config);

        $this->config = $config;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $class = $this->parseClassName($this->argument('class'));
        if (!class_exists($class)) {
            $this->error("Class $class not found!");
            return 1;
        }
        $model = new $class;

        $this->info("Model: $class");

        $basicInfo = $this->getBasicInfo($model);
        $this->printBasicInfo($basicInfo);

        $propertiesInfo = $this->getPropertiesInfo($model);
        $columns = $this->getColumnNames($model->getTable());
        $this->printPropertiesInfo($columns, $propertiesInfo);

        return 0;
    }

    /**
     * Returns model basic info such as table name, primary key and pagination per page default
     *
     * @param Model $model
     * @return array
     */
    protected function getBasicInfo(Model $model): array
    {
        $tableName = $model->getTable();
        $primaryKeyName = $model->getKeyName();
        $primaryKeyType = $model->getKeyType();
        $primaryKeyIncrementing = $model->incrementing;
        $perPage = $model->getPerPage();

        return [
            'table_name' => $tableName,
            'primary_key' => [
                'name' => $primaryKeyName,
                'type' => $primaryKeyType,
                'incrementing' => $primaryKeyIncrementing,
            ],
            'default_per_page' => $perPage,
        ];
    }

    /**
     * Prints basic info such as table name, primary key and pagination per page default value in seperate lines
     *
     * @param array $data
     */
    protected function printBasicInfo(array $data): void
    {
        foreach ($data as $key => $value) {
            $label = Str::of($key)->snake()->replace('_', ' ')->ucfirst();
            if (is_array($value)) {
                ['name' => $name, 'type' => $type, 'incrementing' => $incrementing] = $value;
                $incrementingText = $incrementing ? 'incrementing ' : '';
                $value = "$name ({$incrementingText}{$type})";
            }
            $this->line("{$label}: {$value}");
        }
    }

    /**
     * Returns list of model table columns with their types
     *
     * @param Model $model
     * @return array
     */
    protected function getPropertiesInfo(Model $model): array
    {
        $types = $this->getColumnsWithTypes($model->getTable());
        $dbTypes = $this->getColumnsWithDatabaseTypes($model->getTable());
        $fillable = $model->getFillable();
        $guarded = $model->getGuarded();
        $visible = $model->getVisible();
        $hidden = $model->getHidden();
        $casts = $model->getCasts();

        return [
            'types' => $types,
            'db_types' => $dbTypes,
            'fillable' => $fillable,
            'guarded' => $guarded,
            'visible' => $visible,
            'hidden' => $hidden,
            'casts' => $casts,
        ];
    }

    /**
     * Returns all columns with their PHP types
     *
     * @param string $tableName
     * @return array
     */
    private function getColumnsWithTypes(string $tableName): array
    {
        $columns = $this->getColumnTypes($tableName, $this->getColumnNames($tableName));
        $convertedColumns = [];

        foreach ($columns as $column => $type) {
            $convertedColumns[$column] = $this->convertDatabaseTypeToPHPType($type);
        }

        return $convertedColumns;
    }

    /**
     * Converts database type to PHP scalar type.
     * Based on barryvdh/laravel-ide-helper (https://github.com/barryvdh/laravel-ide-helper/blob/master/src/Console/ModelsCommand.php)
     *
     * @param $type
     * @return string
     */
    private function convertDatabaseTypeToPHPType($type): string
    {
        switch ($type) {
            case 'string':
            case 'text':
            case 'date':
            case 'time':
            case 'guid':
            case 'datetimetz':
            case 'datetime':
            case 'decimal':
                return 'string';
            case 'integer':
            case 'bigint':
            case 'smallint':
                return 'int';
            case 'boolean':
                switch ($this->config->get('database.default')) {
                    case 'sqlite':
                    case 'mysql':
                        return 'integer';
                    default:
                        return 'boolean';
                }
            case 'float':
                return 'float';
            default:
                return 'mixed';
        }
    }

    /**
     * Returns all columns with their database types
     *
     * @param string $tableName
     * @return array
     */
    private function getColumnsWithDatabaseTypes(string $tableName): array
    {
        return $this->getColumnTypes($tableName, $this->getColumnNames($tableName));
    }

    /**
     * Returns table column names
     *
     * @param string $table
     * @return array
     */
    private function getColumnNames(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    /**
     * Returns table columns with their types
     *
     * @param string $table
     * @param array $columns
     * @return array
     */
    private function getColumnTypes(string $table, array $columns): array
    {
        $columnTypes = [];
        foreach ($columns as $column) {
            $columnTypes[$column] = $this->getColumnType($table, $column);
        }

        return $columnTypes;
    }

    /**
     * Returns column type based on table and column name
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    private function getColumnType(string $table, string $column): string
    {
        return Schema::getColumnType($table, $column);
    }

    /**
     * Prints model properties in form of a table
     *
     * @param array $columns
     * @param array $data
     */
    private function printPropertiesInfo(array $columns, array $data)
    {
        $headers = ['Name', 'PHP type', 'DB type', 'Casts', 'Fillable', 'Guarded', 'Visible', 'Hidden'];
        [
            'types' => $types,
            'db_types' => $dbTypes,
            'fillable' => $fillable,
            'guarded' => $guarded,
            'visible' => $visible,
            'hidden' => $hidden,
            'casts' => $casts
        ] = $data;

        $formattedData = [];
        foreach ($columns as $column) {
            $col = [];
            $col['name'] = $column;
            $col['type'] = $types[$column] ?? '';
            $col['db_type'] = $dbTypes[$column] ?? '';
            $col['casts'] = $casts[$column] ?? '';
            $col['fillable'] = in_array($column, $fillable) ? "Yes" : 'No';
            $col['guarded'] = in_array($column, $guarded) ? "Yes" : 'No';
            $col['visible'] = in_array($column, $visible) ? "Yes" : 'No';
            $col['hidden'] = in_array($column, $hidden) ? "Yes" : 'No';

            $formattedData[] = $col;
        }

        $this->table($headers, $formattedData);
    }
}
