<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeService extends Command
{
    protected $signature = 'make:crudService
                            {--namespace= : The namespace for the service}
                            {--class= : The name of the service class}
                            {--model= : The associated model}
                            {--method= : The validation method}
                            {--rule= : The validation rules}';

    protected $description = 'Generate a service class file from a stub template with dynamic subdirectories in the namespace';

    public function handle()
    {
        $filesystem = new Filesystem();

        $stubPath = app_path('Console/Commands/stubs/crudService.stub'); // Path to the stub file

        if (!$filesystem->exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return 1;
        }

        $class = $this->option('class');
        if (!$class) {
            $this->error('The --class option is required.');
            return 1;
        }

        // Set default namespace if not provided
        $namespace = $this->option('namespace')
            ? 'App\\Services\\' . trim($this->option('namespace'), '\\/')
            : 'App\\Services';

        // Convert namespace to directory path
        $directory = app_path(str_replace('\\', '/', str_replace('App\\', '', $namespace)));

        // Ensure the directory exists
        $filesystem->ensureDirectoryExists($directory);

        $model = $this->option('model') ?? '';
        $method = $this->option('method') ?? '';
        $rule = $this->processRuleOption($this->option('rule'));

        // Read stub content
        $stub = $filesystem->get($stubPath);

        // Replace placeholders
        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ model }}', '{{ method }}', '{{ rule }}'],
            [$namespace, $class, $model, $method, $rule],
            $stub
        );

        $outputPath = "{$directory}/{$class}.php";

        if ($filesystem->exists($outputPath)) {
            $this->error("Service class already exists at: {$outputPath}");
            return 1;
        }


        // Write the class file
        $filesystem->put($outputPath, $stub);

        $this->info("Service class {$class} created at: {$outputPath}");

        return 0;
    }

    /**
     * Process the rule option and convert it into an array string.
     *
     * @param string|null $rule
     * @return string
     */
    protected function processRuleOption($rule)
    {
        if (!$rule) {
            return '[]';
        }

        $rule = trim($rule, "[] \n\t");
        $rules = explode(',', $rule);
        $ruleArray = [];

        foreach ($rules as $item) {
            $parts = explode('=>', $item);
            if (count($parts) === 2) {
                $field = trim($parts[0], " \t\n\r\"'");
                $validation = trim($parts[1], " \t\n\r\"'");
                $ruleArray[] = "'{$field}' => '{$validation}'";
            }
        }

        return "[\n\t\t\t" . implode(",\n\t\t\t", $ruleArray) . "\n\t\t]";
    }
}
