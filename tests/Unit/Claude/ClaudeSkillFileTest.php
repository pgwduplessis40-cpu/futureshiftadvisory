<?php

declare(strict_types=1);

namespace Tests\Unit\Claude;

use Tests\TestCase;

final class ClaudeSkillFileTest extends TestCase
{
    public function test_fsa_project_skill_uses_anthropic_skill_markdown_format(): void
    {
        $path = base_path('.claude/skills/fsa-app/SKILL.md');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        $this->assertStringStartsWith("---\n", $contents);
        $this->assertMatchesRegularExpression('/\A---\n(?P<frontmatter>.*?)\n---\n(?P<body>.*)\z/s', $contents);

        preg_match('/\A---\n(?P<frontmatter>.*?)\n---\n(?P<body>.*)\z/s', $contents, $matches);

        $frontmatter = $matches['frontmatter'] ?? '';
        $body = $matches['body'] ?? '';

        $this->assertStringContainsString('description:', $frontmatter);
        $this->assertStringContainsString('when_to_use:', $frontmatter);
        $this->assertStringContainsString('paths:', $frontmatter);
        $this->assertStringContainsString('Standard Advisory analysis findings must flow', $body);
        $this->assertStringContainsString('AI Assistant Skill Routing', $body);
        $this->assertStringContainsString('Data', $body);
        $this->assertStringContainsString('Finance', $body);
        $this->assertStringContainsString('Productivity', $body);
        $this->assertStringContainsString('Operations', $body);
        $this->assertStringContainsString('Financial Planning and Analysis', $body);
        $this->assertStringContainsString('decision-toolkit', $body);
        $this->assertStringContainsString('fact-checker', $body);
        $this->assertStringContainsString('skill-creator', $body);
        $this->assertStringContainsString('forecasting-time-series-data', $body);
        $this->assertStringContainsString('AiClient', $body);
        $this->assertStringContainsString('Every uploaded file must be virus-scanned', $body);
        $this->assertStringContainsString('public holidays for the client region', $body);
    }

    public function test_claude_markdown_files_avoid_mojibake_and_expensive_memory_growth(): void
    {
        $paths = [
            base_path('CLAUDE.md'),
            base_path('.claude/skills/fsa-app/SKILL.md'),
        ];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertDoesNotMatchRegularExpression('/[Ââ�]/u', $contents, "{$path} contains mojibake.");
            $this->assertLessThanOrEqual(220, count(preg_split('/\R/', trim($contents)) ?: []), "{$path} is too long for always-loaded Claude context.");
        }
    }
}
