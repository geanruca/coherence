<?php

namespace Geanruca\LaravelCoherence\Tests;

use Geanruca\LaravelCoherence\Tests\Models\Animal;
use Geanruca\LaravelCoherence\Providers\CoherenceServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Prism\Prism\Prism;
use Prism\Prism\Testing\TextResponseFake;

class AnimalTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CoherenceServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::shouldReceive('make')->andReturn('Mocked system prompt');

        $this->app['db']->connection()->getSchemaBuilder()->create('coherence_checks', function ($table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->boolean('passed')->default(false);
            $table->text('reason')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('animals', function ($table) {
            $table->id();
            $table->string('name');
            $table->float('mass_in_kilograms')->nullable();
            $table->timestamps();
        });
    }

    public function test_incoherent_model_is_stored_and_generates_a_log_without_strict_mode(): void
    {
        Config::set('coherence.strict_mode', false);

        Prism::fake([
            // saving → infer description
            TextResponseFake::make()->withText('A model representing an animal.'),
            // saving → validate
            TextResponseFake::make()->withText("| no | An elephant cannot weigh 1kg |\n"),
            // saved → infer again
            TextResponseFake::make()->withText('A model representing an animal.'),
            // saved → validate again
            TextResponseFake::make()->withText("| no | An elephant cannot weigh 1kg |\n"),
        ]);

        $animal = new Animal([
            'name' => 'Elephant',
            'mass_in_kilograms' => 1
        ]);

        $this->assertTrue($animal->save());

        $animal = $animal->fresh();
        $checks = $animal->coherenceChecks;
        $this->assertCount(1, $checks);

        $log = $checks->first();
        $this->assertEquals('An elephant cannot weigh 1kg', $log->reason);
    }

    public function test_incoherent_model_throws_exception_in_strict_mode(): void
    {
        Config::set('coherence.strict_mode', true);

        Prism::fake([
            TextResponseFake::make()->withText('A model representing an animal with a name and its mass.'),
            TextResponseFake::make()->withText("| no | An elephant cannot weigh 1kg |\n")
        ]);

        $animal = new Animal([
            'name' => 'Elephant',
            'mass_in_kilograms' => 1
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('📌 Reason: An elephant cannot weigh 1kg
🧠 Description: A model representing an animal with a name and its mass.
📦 Attributes: {
    "name": "Elephant",
    "mass_in_kilograms": 1
}');

        $animal->save();
    }

    public function test_coherent_model_passes_silently(): void
    {
        Config::set('coherence.strict_mode', false);

        Prism::fake([
            TextResponseFake::make()->withText('A model representing an animal with a name and its mass.'),
            TextResponseFake::make()->withText("| yes | The model is coherent |\n"),
        ]);

        $animal = new Animal([
            'name' => 'Elephant',
            'mass_in_kilograms' => 5000
        ]);

        $this->assertTrue($animal->save());

        $animal = $animal->fresh();
        $checks = $animal->coherenceChecks;
        $this->assertCount(0, $checks);
    }

    public function test_coherent_model_passes_silently_in_strict_mode(): void
    {
        Config::set('coherence.strict_mode', true);

        Prism::fake([
            TextResponseFake::make()->withText('A model representing an animal with a name and its mass.'),
            TextResponseFake::make()->withText("| yes | The model is coherent |\n"),
            TextResponseFake::make()->withText('A model representing an animal with a name and its mass.'),
            TextResponseFake::make()->withText("| yes | The model is coherent |\n"),
        ]);

        $animal = new Animal([
            'name' => 'Elephant',
            'mass_in_kilograms' => 5000
        ]);

        $this->assertTrue($animal->save());

        // Se genera un log de coherencia
        $animal = $animal->fresh();
        $checks = $animal->coherenceChecks;
        $this->assertCount(0, $checks);
    }

}
