<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Service extends Command
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
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $path = app_path('Services/' . str_replace('\\', '/', $name) . '.php');

        if (File::exists($path)) {
            $this->error("Service {$name} already exists!");
            return Command::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));

        $namespace = $this->getNamespace($name);
        $class = class_basename($name);

        $stubPath = app_path('Console/Commands/stubs/service.stub');
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);
        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $class],
            $stub
        );


        File::put($path, $stub);

        $this->info("Service {$name} created successfully.");


        return Command::SUCCESS;
    }

    /**
     * Determine the namespace for the given class name.
     */
    protected function getNamespace(string $name): string
    {
        $segments = explode('/', str_replace('\\', '/', $name));
        array_pop($segments); // remove the class name

        return 'App\\Services' . (count($segments) ? '\\' . implode('\\', $segments) : '');
    }

    /**
     * Run Composer dump-autoload.
     */
    // protected function dumpAutoload(): void
    // {
    //     $process = Process::fromShellCommandline('composer dump-autoload');
    //     $process->run();

    //     if (!$process->isSuccessful()) {
    //         throw new ProcessFailedException($process);
    //     }

    //     $this->info(trim($process->getOutput()));
    // }
}

