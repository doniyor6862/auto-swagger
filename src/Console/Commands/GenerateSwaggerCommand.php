<?php

namespace Laravel\AutoSwagger\Console\Commands;

use Illuminate\Console\Command;
use Laravel\AutoSwagger\Services\SwaggerGenerator;

class GenerateSwaggerCommand extends Command
{
    /**
     * Callbacks to run after the OpenAPI document is generated
     *
     * @var array
     */
    protected array $afterGenerateCallbacks = [];
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:generate {--output= : Path to save the OpenAPI specification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OpenAPI/Swagger documentation from attributes';

    /**
     * Execute the console command.
     */
    /**
     * Register a callback to run after the OpenAPI document is generated
     *
     * @param callable $callback Function that receives the OpenAPI document and returns the modified document
     * @return $this
     */
    public function onAfterGenerate(callable $callback): self
    {
        $this->afterGenerateCallbacks[] = $callback;
        return $this;
    }
    
    public function handle(SwaggerGenerator $generator): int
    {
        $this->info('Generating OpenAPI documentation...');

        $output = $this->option('output') ?? config('auto-swagger.output_file');
        
        $openApiDoc = $generator->generate();
        
        // Run all after-generate callbacks
        foreach ($this->afterGenerateCallbacks as $callback) {
            $openApiDoc = $callback($openApiDoc);
        }
        
        if ($generator->saveToFile($output, $openApiDoc)) {
            $this->info('OpenAPI documentation generated successfully at: ' . $output);
            return Command::SUCCESS;
        } else {
            $this->error('Failed to save OpenAPI documentation to file: ' . $output);
            return Command::FAILURE;
        }
    }
}
