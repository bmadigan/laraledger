<?php

use App\Models\Release;
use App\Services\Analysis\HeuristicAnalyzer;

beforeEach(function () {
    $this->analyzer = new HeuristicAnalyzer;
});

describe('conventional commit parsing', function () {
    test('detects feat prefix as MINOR', function () {
        $commits = [
            ['sha' => 'abc123', 'commit' => ['message' => 'feat: add new user registration']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MINOR);
        expect($result['changes']['features'])->toHaveCount(1);
    });

    test('detects fix prefix as PATCH', function () {
        $commits = [
            ['sha' => 'abc123', 'commit' => ['message' => 'fix: correct validation error']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_PATCH);
        expect($result['changes']['fixes'])->toHaveCount(1);
    });

    test('detects feat! as MAJOR breaking change', function () {
        $commits = [
            ['sha' => 'abc123', 'commit' => ['message' => 'feat!: remove deprecated API endpoints']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MAJOR);
        expect($result['changes']['breaking'])->toHaveCount(1);
    });

    test('detects scope in conventional commits', function () {
        $commits = [
            ['sha' => 'abc123', 'commit' => ['message' => 'feat(auth): add OAuth support']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MINOR);
    });

    test('detects breaking indicator with scope', function () {
        $commits = [
            ['sha' => 'abc123', 'commit' => ['message' => 'refactor(api)!: change response format']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MAJOR);
        expect($result['changes']['breaking'])->toHaveCount(1);
    });
});

describe('breaking change indicators', function () {
    test('detects BREAKING CHANGE in message body', function () {
        $commits = [
            [
                'sha' => 'abc123',
                'commit' => [
                    'message' => "feat: update user model\n\nBREAKING CHANGE: User ID is now a UUID",
                ],
            ],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MAJOR);
        expect($result['changes']['breaking'])->not->toBeEmpty();
    });

    test('detects lowercase breaking change indicator', function () {
        $commits = [
            [
                'sha' => 'abc123',
                'commit' => [
                    'message' => "fix: update config\n\nbreaking change: config key renamed",
                ],
            ],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MAJOR);
    });
});

describe('PR label analysis', function () {
    test('detects breaking label as MAJOR', function () {
        $commits = [
            [
                'sha' => 'abc123',
                'commit' => ['message' => 'update API'],
                'labels' => [['name' => 'breaking-change']],
            ],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MAJOR);
    });

    test('detects feature label as MINOR', function () {
        $commits = [
            [
                'sha' => 'abc123',
                'commit' => ['message' => 'add feature'],
                'labels' => [['name' => 'enhancement']],
            ],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MINOR);
    });

    test('detects bug label as PATCH', function () {
        $commits = [
            [
                'sha' => 'abc123',
                'commit' => ['message' => 'fix issue'],
                'labels' => [['name' => 'bug']],
            ],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_PATCH);
    });

    test('handles string labels', function () {
        $commits = [
            [
                'sha' => 'abc123',
                'commit' => ['message' => 'update'],
                'labels' => ['breaking'],
            ],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MAJOR);
    });
});

describe('version type precedence', function () {
    test('MAJOR takes precedence over MINOR and PATCH', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'feat!: breaking feature']],
            ['sha' => 'abc2', 'commit' => ['message' => 'feat: new feature']],
            ['sha' => 'abc3', 'commit' => ['message' => 'fix: bug fix']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MAJOR);
    });

    test('MINOR takes precedence over PATCH when no breaking', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'feat: new feature']],
            ['sha' => 'abc2', 'commit' => ['message' => 'fix: bug fix']],
            ['sha' => 'abc3', 'commit' => ['message' => 'fix: another fix']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_MINOR);
    });
});

describe('confidence calculation', function () {
    test('high confidence with multiple strong signals', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'feat!: breaking change']],
            ['sha' => 'abc2', 'commit' => ['message' => 'feat!: another breaking change']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['confidence'])->toBeGreaterThanOrEqual(80);
    });

    test('lower confidence with mixed signals', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'update something']],
            ['sha' => 'abc2', 'commit' => ['message' => 'change files']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['confidence'])->toBeLessThanOrEqual(70);
    });

    test('low confidence with no clear signals', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'update']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['confidence'])->toBeLessThanOrEqual(60);
    });
});

describe('change categorization', function () {
    test('categorizes breaking changes correctly', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'feat!: remove old API']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['changes']['breaking'])->toHaveCount(1);
        expect($result['changes']['breaking'][0]['sha'])->toBe('abc1');
    });

    test('categorizes features correctly', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'feat: add export feature']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['changes']['features'])->toHaveCount(1);
    });

    test('categorizes fixes correctly', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'fix: resolve crash on startup']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['changes']['fixes'])->toHaveCount(1);
    });

    test('handles multiple changes in single commit', function () {
        $commits = [
            [
                'sha' => 'abc1',
                'commit' => ['message' => "feat!: breaking feature\n\nBREAKING CHANGE: removes old method"],
            ],
        ];

        $result = $this->analyzer->analyze($commits);

        // Should have at least one breaking change
        expect($result['changes']['breaking'])->not->toBeEmpty();
    });
});

describe('ignored paths', function () {
    test('respects ignored paths for file analysis', function () {
        $commits = [
            [
                'sha' => 'abc1',
                'commit' => ['message' => 'update tests'],
                'files' => [
                    ['filename' => 'tests/Unit/UserTest.php'],
                    ['filename' => 'tests/Feature/ApiTest.php'],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($commits, ['tests/']);

        // File signals should be minimal for ignored paths
        expect($result['recommended_type'])->toBe(Release::TYPE_PATCH);
    });
});

describe('reasoning generation', function () {
    test('includes reasoning in result', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'feat: add feature']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['reasoning'])->toBeArray();
        expect($result['reasoning'])->not->toBeEmpty();
    });

    test('includes signal count in reasoning', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'feat: add feature']],
        ];

        $result = $this->analyzer->analyze($commits);

        $reasoningText = implode(' ', $result['reasoning']);
        expect($reasoningText)->toContain('signal');
    });
});

describe('source indication', function () {
    test('marks analysis as heuristic source', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => 'feat: add feature']],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['source'])->toBe(Release::SOURCE_HEURISTIC);
    });
});

describe('edge cases', function () {
    test('handles empty commits array', function () {
        $result = $this->analyzer->analyze([]);

        expect($result['recommended_type'])->toBe(Release::TYPE_PATCH);
        expect($result['confidence'])->toBeLessThanOrEqual(60);
    });

    test('handles commits without message', function () {
        $commits = [
            ['sha' => 'abc1', 'commit' => []],
        ];

        $result = $this->analyzer->analyze($commits);

        expect($result['recommended_type'])->toBe(Release::TYPE_PATCH);
    });

    test('truncates long commit messages in summary', function () {
        $longMessage = str_repeat('a', 200);
        $commits = [
            ['sha' => 'abc1', 'commit' => ['message' => "feat: {$longMessage}"]],
        ];

        $result = $this->analyzer->analyze($commits);

        expect(strlen($result['changes']['features'][0]['message']))->toBeLessThanOrEqual(103);
    });
});
