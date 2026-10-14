<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\StartRoadRunnerCommand;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

class StartRoadRunnerCommandTest extends TestCase
{
    public function test_default_worker_command_is_resolved_relative_to_the_base_path()
    {
        $app = $this->createApplication();

        $command = $this->command();

        $this->assertEquals(
            $app->basePath('vendor/bin/roadrunner-worker'),
            $command->workerCommand()
        );
    }

    public function test_configured_relative_worker_command_is_resolved_relative_to_the_base_path()
    {
        $app = $this->createApplication();

        $app['config']->set('octane.roadrunner.command', 'vendor/bin/custom-worker');

        $command = $this->command();

        $this->assertEquals(
            $app->basePath('vendor/bin/custom-worker'),
            $command->workerCommand()
        );
    }

    public function test_configured_absolute_worker_command_is_not_prefixed_with_the_base_path()
    {
        $app = $this->createApplication();

        $app['config']->set('octane.roadrunner.command', '/var/www/current/vendor/bin/roadrunner-worker');

        $command = $this->command();

        $this->assertEquals(
            '/var/www/current/vendor/bin/roadrunner-worker',
            $command->workerCommand()
        );
    }

    /** @dataProvider roadRunnerVersions */
    #[DataProvider('roadRunnerVersions')]
    public function test_roadrunner_version_requirements_handle_calendar_and_semantic_versions($version, $meetsRequirements)
    {
        $this->assertSame($meetsRequirements, $this->command()->roadRunnerVersionMeetsRequirements($version));
    }

    public static function roadRunnerVersions()
    {
        return [
            'calendar version below minimum' => ['2023.2.2', false],
            'calendar version at minimum' => ['2023.3.0', true],
            'latest calendar version' => ['2025.1.15', true],
            'legacy semantic version' => ['2.12.3', false],
            'first semantic version after calendar versions' => ['3.0.0', true],
            'later semantic version' => ['3.4.1', true],
        ];
    }

    public function test_http_log_with_uppercase_level_is_written_as_request_info()
    {
        $this->createApplication();

        $server = Mockery::mock(Process::class);
        $server->shouldReceive('getIncrementalOutput')->once()->andReturn(json_encode([
            'time' => '2026-10-14T12:00:00.123Z',
            'level' => 'INFO',
            'msg' => 'http log',
            'logger' => 'http',
            'status' => 200,
            'method' => 'GET',
            'URI' => '/up',
            'remote_address' => '127.0.0.1:50000',
            'elapsed' => 12,
        ]));
        $server->shouldReceive('getIncrementalErrorOutput')->once()->andReturn('');
        $server->shouldReceive('clearOutput')->once()->andReturnSelf();
        $server->shouldReceive('clearErrorOutput')->once()->andReturnSelf();

        $command = $this->command();
        $command->writeServerOutput($server);

        $this->assertSame([[
            'method' => 'GET',
            'url' => '/up',
            'statusCode' => 200,
            'duration' => 12.0,
        ]], $command->requests);
    }

    protected function command()
    {
        return new class extends StartRoadRunnerCommand
        {
            public function __construct()
            {
                //
            }

            public $requests = [];

            public function workerCommand()
            {
                return parent::workerCommand();
            }

            public function roadRunnerVersionMeetsRequirements($version)
            {
                return parent::roadRunnerVersionMeetsRequirements($version);
            }

            public function writeServerOutput($server)
            {
                parent::writeServerOutput($server);
            }

            public function requestInfo($request, $verbosity = null)
            {
                $this->requests[] = $request;
            }
        };
    }
}
