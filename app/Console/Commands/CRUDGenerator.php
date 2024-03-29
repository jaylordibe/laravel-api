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

        $this->createModelFile($modelName);
        $this->createMigrationFile($modelName);
        $this->createRequestFile($modelName);
        $this->createResourceFile($modelName);
        $this->createDataFile($modelName);
        $this->createTestFile($modelName);
        $this->createControllerFile($modelName);
        $this->createServiceFile($modelName);
        $this->createRepositoryFile($modelName);
        $this->addRoute($modelName);

        echo PHP_EOL . "Done generating CRUD files for {$modelName} model" . PHP_EOL . PHP_EOL;
    }

    private function createModelFile(string $modelName): void
    {
        $stubName = 'Model';
        $path = app_path("Models/{$modelName}.php");

        if (File::exists($path)) {
            $this->error("Model {$modelName} already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function createMigrationFile(string $modelName): void
    {
        $stubName = 'Migration';
        $migrationFileName = $this->getDatePrefix() . '_' . 'create_' . Str::plural(strtolower($this->decamelize($modelName)));
        $path = database_path("/migrations/{$migrationFileName}_table.php");

        if (File::exists($path)) {
            $this->error("Migration file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function createRequestFile(string $modelName): void
    {
        $stubName = 'Request';
        $path = app_path("Http/Requests/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("Request file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function createResourceFile(string $modelName): void
    {
        $stubName = 'Resource';
        $path = app_path("Http/Resources/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("Resource file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function createDataFile(string $modelName): void
    {
        $stubName = 'Data';
        $path = app_path("Data/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("Data file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;

        $stubName = 'FilterData';
        $path = app_path("Data/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("FilterData file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function createTestFile(string $modelName): void
    {
        // Uncomment the following lines if you want to generate unit tests
//        $stubName = 'UnitTest';
//        $path = base_path("tests/Unit/{$modelName}{$stubName}.php");
//
//        if (File::exists($path)) {
//            $this->error("Test file for {$modelName} model already exists. Skipping...");
//
//            return;
//        }
//
//        $file = $this->getStubFile($modelName, $stubName);
//        file_put_contents($path, $file);
//        echo "{$path} successfully created" . PHP_EOL;

        $stubName = 'FeatureTest';
        $path = base_path("tests/Feature/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("Feature test file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function createControllerFile(string $modelName): void
    {
        $stubName = 'Controller';
        $path = app_path("Http/Controllers/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("Controller file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function createServiceFile(string $modelName): void
    {
        $stubName = 'Service';
        $path = app_path("Services/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("Service file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function createRepositoryFile(string $modelName): void
    {
        $stubName = 'Repository';
        $path = app_path("Repositories/{$modelName}{$stubName}.php");

        if (File::exists($path)) {
            $this->error("Repository file for {$modelName} model already exists. Skipping...");

            return;
        }

        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents($path, $file);
        echo "{$path} successfully created" . PHP_EOL;
    }

    private function addRoute(string $modelName): void
    {
        $controllerClass = "{$modelName}Controller";
        $useStatement = "use App\Http\Controllers\\{$controllerClass};\n";
        $resource = Str::plural(strtolower($this->camelToDashed($modelName)));
        $controllerClassWithNamespace = "{$controllerClass}::class";

        $routeTemplate = <<<ROUTES
        \n\t// CRUD routes for $modelName
        \tRoute::post('{$resource}', [{$controllerClassWithNamespace}, 'create']);
        \tRoute::get('{$resource}', [{$controllerClassWithNamespace}, 'getPaginated']);
        \tRoute::get('{$resource}/{id}', [{$controllerClassWithNamespace}, 'getById'])->where('id', RoutePatternConstant::NUMERIC);
        \tRoute::put('{$resource}/{id}', [{$controllerClassWithNamespace}, 'update'])->where('id', RoutePatternConstant::NUMERIC);
        \tRoute::delete('{$resource}/{id}', [{$controllerClassWithNamespace}, 'delete'])->where('id', RoutePatternConstant::NUMERIC);
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
            echo "{$routeFilePath} successfully updated" . PHP_EOL;
        } else {
            echo "The position to insert the new routes was not found.";
        }
    }

    private function getStubFile(string $modelName, string $stubName): string
    {
        $search = [
            '{{modelName}}',
            '{{modelNameLowerCaseFirstLetter}}',
            '{{modelNameLowerCaseDash}}',
            '{{modelNameLowerCaseDashPlural}}',
            '{{modelNameLowerCaseFirstLetterPlural}}',
            '{{modelNameDecamelizeLowerCaseSingularToPlural}}',
            '{{modelNameDecamelizeUpperCaseSingularToPlural}}',
            '{{modelNameSingularToPlural}}',
            '{{modelNameSpacesLowerCase}}',
            '{{modelNameSpacesUpperCaseWord}}',
            '{{modelNameSpacesUpperCaseFirstLetter}}'
        ];

        $modelNameLowerCaseFirstLetter = lcfirst($modelName);
        $modelNameLowerCaseDash = strtolower($this->camelToDashed($modelName));
        $modelNameLowerCaseDashPlural = Str::plural(strtolower($this->camelToDashed($modelName)));
        $modelNameLowerCaseFirstLetterPlural = Str::plural(lcfirst($modelName));
        $modelNameDecamelizeLowerCaseSingularToPlural = Str::plural(strtolower($this->decamelize($modelName)));
        $modelNameDecamelizeUpperCaseSingularToPlural = Str::plural(strtoupper($this->decamelize($modelName)));
        $modelNameSingularToPlural = Str::plural($modelName);
        $modelNameSpacesLowerCase = trim(strtolower($this->camelToSpace($modelName)));
        $modelNameSpacesUpperCaseWord = trim(ucwords($this->camelToSpace($modelName)));
        $modelNameSpacesUpperCaseFirstLetter = ucfirst(trim(strtolower($this->camelToSpace($modelName))));

        $replace = [
            $modelName,
            $modelNameLowerCaseFirstLetter,
            $modelNameLowerCaseDash,
            $modelNameLowerCaseDashPlural,
            $modelNameLowerCaseFirstLetterPlural,
            $modelNameDecamelizeLowerCaseSingularToPlural,
            $modelNameDecamelizeUpperCaseSingularToPlural,
            $modelNameSingularToPlural,
            $modelNameSpacesLowerCase,
            $modelNameSpacesUpperCaseWord,
            $modelNameSpacesUpperCaseFirstLetter
        ];

        $subject = file_get_contents(resource_path("stubs/{$stubName}.stub"));

        return str_replace($search, $replace, $subject);
    }

    private function camelToSpace($string): string
    {
        $pieces = preg_split('/(?=[A-Z])/', $string);

        return implode(" ", $pieces);
    }

    private function camelToDashed($string): string
    {
        return strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $string));
    }

    private function decamelize($string): string
    {
        return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
    }

    private function getDatePrefix(): string
    {
        return date('Y_m_d_His');
    }

}
