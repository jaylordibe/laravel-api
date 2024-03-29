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

        echo PHP_EOL . "Done generating CRUD files for {$modelName}" . PHP_EOL . PHP_EOL;
    }

    private function createModelFile(string $modelName): void
    {
        $stubName = 'Model';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(app_path("Models/{$modelName}.php"), $file);
    }

    private function createMigrationFile(string $modelName): void
    {
        $stubName = 'Migration';
        $file = $this->getStubFile($modelName, $stubName);
        $migrationFileName = $this->getDatePrefix() . '_' . 'create_' . Str::plural(strtolower($this->decamelize($modelName)));
        file_put_contents(database_path("/migrations/{$migrationFileName}_table.php"), $file);
    }

    private function createRequestFile(string $modelName): void
    {
        $stubName = 'Request';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(app_path("Http/Requests/{$modelName}{$stubName}.php"), $file);
    }

    private function createResourceFile(string $modelName): void
    {
        $stubName = 'Resource';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(app_path("Http/Resources/{$modelName}{$stubName}.php"), $file);
    }

    private function createDataFile(string $modelName): void
    {
        $stubName = 'Data';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(app_path("Data/{$modelName}{$stubName}.php"), $file);

        $stubName = 'FilterData';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(app_path("Data/{$modelName}{$stubName}.php"), $file);
    }

    private function createTestFile(string $modelName): void
    {
        // Uncomment the following lines if you want to generate unit tests
//        $stubName = 'UnitTest';
//        $file = $this->getStubFile($modelName, $stubName);
//        file_put_contents(base_path("tests/Feature/{$modelName}{$stubName}.php"), $file);

        $stubName = 'FeatureTest';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(base_path("tests/Unit/{$modelName}{$stubName}.php"), $file);
    }

    private function createControllerFile(string $modelName): void
    {
        $stubName = 'Controller';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(app_path("Http/Controllers/{$modelName}{$stubName}.php"), $file);
    }

    private function createServiceFile(string $modelName): void
    {
        $stubName = 'Service';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(app_path("Services/{$modelName}{$stubName}.php"), $file);
    }

    private function createRepositoryFile(string $modelName): void
    {
        $stubName = 'Repository';
        $file = $this->getStubFile($modelName, $stubName);
        file_put_contents(app_path("Repositories/{$modelName}{$stubName}.php"), $file);
    }

    private function addRoute(string $modelName): void
    {
        $controllerClass = "{$modelName}Controller";
        $useStatement = "use App\Http\Controllers\\{$controllerClass};\n";
        $controllerClassWithNamespace = "{$controllerClass}::class";

        $routeTemplate = <<<ROUTES
        \n\t// CRUD routes for $modelName
        \tRoute::post('{$modelName}s', [{$controllerClassWithNamespace}, 'create']);
        \tRoute::get('{$modelName}s', [{$controllerClassWithNamespace}, 'getPaginated']);
        \tRoute::get('{$modelName}s/{addressId}', [{$controllerClassWithNamespace}, 'getById'])->where('addressId', RoutePatternConstant::NUMERIC);
        \tRoute::put('{$modelName}s/{addressId}', [{$controllerClassWithNamespace}, 'update'])->where('addressId', RoutePatternConstant::NUMERIC);
        \tRoute::delete('{$modelName}s/{addressId}', [{$controllerClassWithNamespace}, 'delete'])->where('addressId', RoutePatternConstant::NUMERIC);
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

            echo "CRUD routes for {$modelName} have been added successfully.";
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
