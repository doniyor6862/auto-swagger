<?php

namespace Laravel\AutoSwagger\Console\Commands;

use Illuminate\Console\Command;
use Laravel\AutoSwagger\Services\SwaggerGenerator;

class GenerateSwaggerCommand extends Command
{
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
    public function handle(SwaggerGenerator $generator): int
    {
        $this->info('Generating OpenAPI documentation...');

        $output = $this->option('output') ?? config('auto-swagger.output_file');
        
        $openApiDoc = $generator->generate();
        
        if ($generator->saveToFile($output)) {
            $this->info('OpenAPI documentation generated successfully at: ' . $output);
            return Command::SUCCESS;
        } else {
            $this->error('Failed to save OpenAPI documentation to file: ' . $output);
            return Command::FAILURE;
        }
    }
}
