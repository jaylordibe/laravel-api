<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CRUDGenerator extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crud-generator {--model=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CRUD files';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $modelName = (string) $this->option('model');

        if (empty($modelName)) {
            $this->error('Model name is required.');

            return;
        }

        if (!File::exists(app_path("Models/{$modelName}.php"))) {
            $this->createModelFile($modelName);
            $this->createMigrationFile($modelName);
            $this->addRoute($modelName);
        }

        $this->createFactoryFile($modelName);
        $this->createRequestFile($modelName);
        $this->createResourceFile($modelName);
        $this->createDataFile($modelName);
        $this->createTestFile($modelName);
        $this->createControllerFile($modelName);
        $this->createServiceFile($modelName);
        $this->createRepositoryFile($modelName);

        $this->info(PHP_EOL . "Done generating CRUD files for {$modelName} model" . PHP_EOL . PHP_EOL);
    }

    /**
     * Create model file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createModelFile(string $modelName): void
    {
        $stubName = 'Model';
        $path = app_path("Models/{$modelName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Create migration file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createMigrationFile(string $modelName): void
    {
        $stubName = 'Migration';
        $migrationFileName = date('Y_m_d_His') . '_create_' . Str::lower(Str::snake(Str::plural($modelName)));
        $path = database_path("/migrations/{$migrationFileName}_table.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }

        $databaseTableConstantName = Str::upper(Str::snake(Str::plural($modelName)));
        $databaseTableConstantValue = Str::lower(Str::snake(Str::plural($modelName)));
        $databaseTableConstantTemplate = <<<DATABASETABLECONSTANTS
        \tconst {$databaseTableConstantName} = '{$databaseTableConstantValue}';
        DATABASETABLECONSTANTS;

        $databaseTableConstantFilePath = app_path('Constants/DatabaseTableConstant.php');
        $fileContents = file_get_contents($databaseTableConstantFilePath);

        // Find the position of the closing tag of the auth:api middleware group
        $closingMiddlewareTagPosition = strrpos($fileContents, "}");

        if ($closingMiddlewareTagPosition !== false) {
            // Insert the new routes before the closing tag
            $newFileContents = substr_replace($fileContents, $databaseTableConstantTemplate . "\n", $closingMiddlewareTagPosition - 1, 0);

            // Write the new content back into the file
            file_put_contents($databaseTableConstantFilePath, $newFileContents);
            $this->info("{$databaseTableConstantFilePath} successfully updated" . PHP_EOL);
        } else {
            echo "The position to insert the new database table constant value was not found.";
        }
    }

    /**
     * Create factory file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createFactoryFile(string $modelName): void
    {
        $stubName = 'Factory';
        $path = database_path("/factories/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Create request file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createRequestFile(string $modelName): void
    {
        $stubName = 'Request';
        $path = app_path("Http/Requests/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Create resource file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createResourceFile(string $modelName): void
    {
        $stubName = 'Resource';
        $path = app_path("Http/Resources/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Create data file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createDataFile(string $modelName): void
    {
        $stubName = 'Data';
        $path = app_path("Data/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }

        $stubName = 'FilterData';
        $path = app_path("Data/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Create test file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createTestFile(string $modelName): void
    {
        $stubName = 'UnitTest';
        $path = base_path("tests/Unit/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }

        $stubName = 'FeatureTest';
        $path = base_path("tests/Feature/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Create controller file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createControllerFile(string $modelName): void
    {
        $stubName = 'Controller';
        $path = app_path("Http/Controllers/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Create service file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createServiceFile(string $modelName): void
    {
        $stubName = 'Service';
        $path = app_path("Services/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Create repository file.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function createRepositoryFile(string $modelName): void
    {
        $stubName = 'Repository';
        $path = app_path("Repositories/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists. Skipping...");
        } else {
            $file = $this->getStubFile($modelName, $stubName);
            file_put_contents($path, $file);
            $this->info("{$path} successfully created" . PHP_EOL);
        }
    }

    /**
     * Add route.
     *
     * @param string $modelName
     *
     * @return void
     */
    private function addRoute(string $modelName): void
    {
        $controllerClass = "{$modelName}Controller";
        $useStatement = "use App\Http\Controllers\\{$controllerClass};\n";
        $resourceName = Str::lower(Str::kebab(Str::plural($modelName)));
        $modelIdName = lcfirst($modelName) . 'Id';
        $controllerClassName = "{$controllerClass}::class";

        $routeTemplate = <<<ROUTES
        \n\t// CRUD routes for {$modelName}
        \tRoute::prefix('{$resourceName}')->group(function () {
            \tRoute::post('/', [{$controllerClassName}, 'create']);
            \tRoute::get('/', [{$controllerClassName}, 'getPaginated']);
            \tRoute::get('/{{$modelIdName}}', [{$controllerClassName}, 'getById'])->where('{$modelIdName}', RoutePatternConstant::NUMERIC);
            \tRoute::put('/{{$modelIdName}}', [{$controllerClassName}, 'update'])->where('{$modelIdName}', RoutePatternConstant::NUMERIC);
            \tRoute::delete('/{{$modelIdName}}', [{$controllerClassName}, 'delete'])->where('{$modelIdName}', RoutePatternConstant::NUMERIC);
        \t});
        ROUTES;

        $routeFilePath = base_path('routes/api.php');
        $fileContents = file_get_contents($routeFilePath);

        // Check if the use statement for the controller is already there, if not, add it
        if (!str_contains($fileContents, $useStatement)) {
            $lastUsePosition = strrpos($fileContents, "use ");
            $insertPositionUseStatement = strpos($fileContents, "\n", $lastUsePosition) + 1;
            $fileContents = substr_replace($fileContents, $useStatement, $insertPositionUseStatement, 0);
        }

        // Find the position of the closing tag of the auth:api middleware group
        $closingMiddlewareTagPosition = strrpos($fileContents, "});");

        if ($closingMiddlewareTagPosition !== false) {
            // Insert the new routes before the closing tag
            $newFileContents = substr_replace($fileContents, $routeTemplate . "\n", $closingMiddlewareTagPosition, 0);

            // Write the new content back into the file
            file_put_contents($routeFilePath, $newFileContents);
            $this->info("{$routeFilePath} successfully updated" . PHP_EOL);
        } else {
            echo "The position to insert the new routes was not found.";
        }
    }

    /**
     * Get stub file.
     *
     * @param string $modelName
     * @param string $stubName
     *
     * @return string
     */
    private function getStubFile(string $modelName, string $stubName): string
    {
        $search = [
            '{{modelName}}',
            '{{modelNamePlural}}',
            '{{modelNameCamelCase}}',
            '{{modelNameCamelCasePlural}}',
            '{{modelNameKebabCase}}',
            '{{modelNameKebabCasePlural}}',
            '{{modelNameUpperKebabCasePlural}}',
            '{{modelNameSnakeCase}}',
            '{{modelNameSnakeCasePlural}}',
            '{{modelNameUpperSnakeCasePlural}}',
            '{{modelNameSpaceCase}}',
            '{{modelNameSpaceCasePlural}}',
            '{{modelNameUpperWordSpaceCase}}',
            '{{modelNameUpperFirstSpaceCase}}',
            '{{modelNameId}}'
        ];

        $modelNamePlural = Str::plural($modelName);
        $modelNameCamelCase = Str::camel($modelName);
        $modelNameCamelCasePlural = Str::camel($modelNamePlural);
        $modelNameKebabCase = Str::kebab($modelName);
        $modelNameKebabCasePlural = Str::kebab($modelNamePlural);
        $modelNameUpperKebabCasePlural = Str::upper($modelNameKebabCasePlural);
        $modelNameSnakeCase = Str::snake($modelName);
        $modelNameSnakeCasePlural = Str::snake($modelNamePlural);
        $modelNameUpperSnakeCasePlural = Str::upper($modelNameSnakeCasePlural);
        $modelNameSpaceCase = Str::replace('_', ' ', $modelNameSnakeCase);
        $modelNameSpaceCasePlural = Str::replace('_', ' ', $modelNameSnakeCasePlural);
        $modelNameUpperWordSpaceCase = ucwords($modelNameSpaceCase);
        $modelNameUpperFirstSpaceCase = ucfirst($modelNameSpaceCase);
        $modelNameId = "{$modelNameCamelCase}Id";

        $replace = [
            $modelName,
            $modelNamePlural,
            $modelNameCamelCase,
            $modelNameCamelCasePlural,
            $modelNameKebabCase,
            $modelNameKebabCasePlural,
            $modelNameUpperKebabCasePlural,
            $modelNameSnakeCase,
            $modelNameSnakeCasePlural,
            $modelNameUpperSnakeCasePlural,
            $modelNameSpaceCase,
            $modelNameSpaceCasePlural,
            $modelNameUpperWordSpaceCase,
            $modelNameUpperFirstSpaceCase,
            $modelNameId
        ];

        $subject = file_get_contents(resource_path("stubs/{$stubName}.stub"));

        return str_replace($search, $replace, $subject);
    }

}
