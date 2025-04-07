<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class simpleCrud extends Command
{
    protected $signature = 'make:simpleController
                            {--namespace= : The namespace for the controller}
                            {--class= : The name of the controller class}
                            {--model= : The associated model}
                            {--method= : The validation method}
                            {--rule= : The validation rules}';

    protected $description = 'Generate a controller file from a stub template with dynamic subdirectories in the namespace';

    public function handle()
    {
        $filesystem = new Filesystem();
        $stubPath = base_path('app/Console/Commands/stubs/simple-controller.stub'); // Ensure you have the correct stub path

        if (!$filesystem->exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return;
        }

        // Get the namespace option or default to App\Http\Controllers
        $namespace = $this->option('namespace')
            ? 'App\Http\Controllers\\' . ltrim($this->option('namespace'), '\\')
            : 'App\Http\Controllers';

        // Convert namespace to directory path (e.g., App\Http\Controllers -> app/Http/Controllers)
        $directory = app_path(str_replace('\\', '/', str_replace('App\\', '', $namespace)));

        // Ensure the directory exists
        $filesystem->ensureDirectoryExists($directory);

        // Get the remaining options
        $class = $this->option('class');
        $model = $this->option('model');
        $method = $this->option('method');
        $rule = $this->processRuleOption($this->option('rule'));

        // Define the output file path
        $outputPath = "{$directory}/{$class}.php";

        // Read the stub file content
        $stub = $filesystem->get($stubPath);

        // Replace placeholders in the stub
        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $class,
            '{{ model }}' => $model,
            '{{ method }}' => $method,
            '{{ rule }}' => $rule,
        ];

        foreach ($replacements as $placeholder => $value) {
            $stub = str_replace($placeholder, $value, $stub);
        }

        // Write the generated class file
        $filesystem->put($outputPath, $stub);

        $this->info("Controller {$class} generated successfully at {$outputPath}");
    }

    /**
     * Process the rule option and convert it into an array format.
     *
     * @param string $rule
     * @return string
     */
    protected function processRuleOption($rule)
    {
        // If no rule provided, return an empty array
        if (!$rule) {
            return '[]';
        }

        // Remove any unwanted spaces or characters before and after the rule
        $rule = trim($rule, "[] \n\t");

        // If the rule is in the form of a PHP array-like string, we'll process it
        // This will handle the syntax like [name=>'required|string|max:150',email=>'required|email']
        $rules = explode(',', $rule);
        $ruleArray = [];

        foreach ($rules as $item) {
            // Split each rule by the first `=>` found, to separate the field and its validation rules
            // We also trim any surrounding spaces or unwanted characters
            $item = trim($item);
            $parts = explode('=>', $item);

            if (count($parts) == 2) {
                $field = trim($parts[0], " \t'"); // Remove extra spaces or quotes
                $validation = trim($parts[1], " \t'"); // Remove extra spaces or quotes

                // Add the field and rule to the array
                $ruleArray[] = "'{$field}' => '{$validation}'";
            }
        }

        // Join the array elements with newlines and commas after each line
        return "[\n\t\t\t" . implode(",\n\t\t\t", $ruleArray) . "\n\t\t]";
    }

}
