<?php

namespace Geanruca\LaravelCoherence\Traits;

use Geanruca\LaravelCoherence\Models\CoherenceCheck;
use Illuminate\Support\Facades\View;
use Prism\Prism\Prism;

trait HasCoherence
{
    public static function bootHasCoherence()
    {
        static::saved(function ($model) {
            [$isCoherent, $reason] = $model->checkCoherence();

            if (! $isCoherent) {
                $model->coherenceChecks()->create([
                    'reason' => $reason,
                    'passed' => false,
                ]);
            }
        });

        if (config('coherence.strict_mode')) {
            static::saving(function ($model) {
                [$isCoherent, $reason, $description] = $model->checkCoherence();

                if (! $isCoherent) {
                    // Siempre registrar el intento fallido
                    $model->coherenceChecks()->create([
                        'reason' => $reason,
                        'description' => $description,
                        'passed' => false,
                    ]);

                    // En modo estricto, lanzar excepción ANTES de guardar
                    $summary = json_encode($model->getAttributes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $class = get_class($model);

                    throw new \Exception(
                        "🚫 Coherence check failed for model [{$class}]\n" .
                        "📌 Reason: {$reason}\n" .
                        "🧠 Description: {$description}\n" .
                        "📦 Attributes: {$summary}"
                    );
                }
            });
        }


    }

    public function checkCoherence(): array
    {
        $className = class_basename($this);
        $attributes = $this->attributesToArray();

        if (method_exists($this, 'describeForCoherence')) {
            $description = $this->describeForCoherence();
        } else {
            $description = $this->inferDescriptionFromAttributeNames($className, array_keys($attributes));
        }

        $json = $this->jsonEncodeAttributes($attributes);

        $prompt = 'You are validating a model called "' . $className . "\".\n"
            . "This model represents: {$description}\n\n"
            . "Attributes:\n```json\n{$json}\n```\n\n"
            . "Is this information coherent? Answer in a table. In the first field tell me yes or not, and in the second field tell me why.";

        $response = Prism::text()
            ->using(config('coherence.provider'), config('coherence.model'))
            ->withSystemPrompt(View::make('coherence::prompts.system'))
            ->withPrompt($prompt)
            ->asText();

        [$isCoherent, $reason] = $this->parseMarkdownTable($response->text);

        return [$isCoherent, $reason, $description];
    }

    protected function jsonEncodeAttributes(array $attributes): string
    {
        return json_encode($attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    protected function parseMarkdownTable(string $text): array
    {
        preg_match('/\|\s*(yes|no)\s*\|\s*(.*?)\s*\|/i', $text, $matches);
        $isCoherent = strtolower(trim($matches[1] ?? '')) === 'yes';
        $reason = trim($matches[2] ?? 'Unknown reason');

        return [$isCoherent, $reason];
    }

    public function coherenceChecks()
    {
        return $this->morphMany(\Geanruca\LaravelCoherence\Models\CoherenceCheck::class, 'model');
    }

    protected function inferDescriptionFromAttributeNames(string $className, array $attributeNames): string
    {
        $attributeList = implode(', ', $attributeNames);

        $descriptionPrompt = 'Based only on the model name "' . $className . '" '
            . 'and the following attribute names: ' . $attributeList . ",\n"
            . 'describe briefly what this model likely represents. Reply with a single sentence.';

        $response = Prism::text()
            ->using(config('coherence.provider'), config('coherence.model'))
            ->withSystemPrompt(View::make('coherence::prompts.system'))
            ->withPrompt($descriptionPrompt)
            ->asText();

        return trim($response->text);
    }
}