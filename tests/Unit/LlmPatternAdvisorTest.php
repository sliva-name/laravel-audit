<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Pattern\HeuristicPatternAdvisor;
use LaravelAudit\Pattern\JsonHttpClient;
use LaravelAudit\Pattern\LlmPatternAdvisor;
use LaravelAudit\Pattern\MethodFeatureExtractor;
use LaravelAudit\Pattern\MethodSnippetExtractor;
use LaravelAudit\Pattern\PatternInferenceEngine;
use LaravelAudit\Pattern\PatternModel;
use LaravelAudit\Tests\TestCase;
use ReflectionMethod;

final class LlmPatternAdvisorTest extends TestCase
{
    public function test_accepts_array_recommendation_after_normalization(): void
    {
        $advisor = $this->advisor();
        $normalize = new ReflectionMethod(LlmPatternAdvisor::class, 'normalizeLlmResult');
        $validate = new ReflectionMethod(LlmPatternAdvisor::class, 'isValidLlmResult');

        $result = $normalize->invoke($advisor, [
            'confirmed' => true,
            'pattern' => 'form_request',
            'title' => 'Form Request',
            'description' => 'Inline validation should move out.',
            'recommendation' => [
                'Create UpdatePasswordRequest.',
                'Move rules() there.',
            ],
            'confidence' => 0.9,
            'rationale' => 'Uses $request->validate().',
        ]);

        self::assertTrue($validate->invoke($advisor, $result));
        self::assertSame(
            "Create UpdatePasswordRequest.\nMove rules() there.",
            $result['recommendation'],
        );
    }

    private function advisor(): LlmPatternAdvisor
    {
        return new LlmPatternAdvisor(
            new HeuristicPatternAdvisor(
                new PatternInferenceEngine(
                    new MethodFeatureExtractor,
                    PatternModel::fromPath(__DIR__.'/../../resources/pattern-model.json'),
                ),
            ),
            new MethodSnippetExtractor,
            new JsonHttpClient,
            provider: 'openai',
            endpoint: 'http://localhost/v1/chat/completions',
            model: 'test-model',
        );
    }
}
