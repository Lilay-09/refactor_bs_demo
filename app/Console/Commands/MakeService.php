<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MakeService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $path = app_path("Services/{$name}.php");

        if (\File::exists($path)) {
            $this->error("Service {$name} already exists!");
            return 1;
        }

        // Ensure the directory exists
       \File::ensureDirectoryExists(dirname($path));

        // Determine the namespace
        $namespace = $this->getNamespace($name);

        // Load the stub and replace placeholders
        $stub = file_get_contents(__DIR__ . '/stubs/service.stub');
        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, class_basename($name)],
            $stub
        );

        \File::put($path, $stub);

        $this->info("Service {$name} created successfully.");

        // Run Composer dump-autoload
        $this->dumpAutoload();

        return 0;
    }

     /**
     * Determine the namespace for the given class name.
     *
     * @param  string  $name
     * @return string
     */
    protected function getNamespace($name)
    {
        $segments = explode('/', $name);
        array_pop($segments);

        return 'App\\Services' . (count($segments) ? '\\' . implode('\\', $segments) : '');
    }

    /**
     * Run Composer dump-autoload.
     */
    protected function dumpAutoload()
    {
        $process = new Process(['composer', 'dump-autoload']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->info($process->getOutput());
    }
}
