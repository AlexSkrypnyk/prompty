<?php

/**
 * @file
 * Playground — nested flow with conditionals, 3 levels deep, 3 items per level.
 *
 * Same as flow-nested.php but specifically demonstrates the difference
 * in rendering when unicode is disabled.
 */

declare(strict_types=1);

require __DIR__ . '/../Prompty.php';

use AlexSkrypnyk\Prompty\Prompty;

$results = Prompty::flow(fn(): array => [
  // --- Level 0, item 1: Project type ---
  'type' => Prompty::select('Project type',
    options: ['app' => 'Application', 'lib' => 'Library', 'site' => 'Static site'],
    description: 'What are you building?',
    hints: [
      'app' => 'Full-stack or single-page application.',
      'lib' => 'Reusable package published to npm.',
      'site' => 'Content-driven site with static generation.',
    ],
    children: [
      // --- Level 1, item 1: App framework (conditional on type=app) ---
      'app_framework' => Prompty::select('App framework',
        options: ['next' => 'Next.js', 'nuxt' => 'Nuxt', 'sveltekit' => 'SvelteKit'],
        description: 'Server-side rendering framework.',
        condition: fn($r): bool => ($r['type'] ?? '') === 'app',
        children: [
          // --- Level 2: SSR settings (conditional on framework) ---
          'ssr_mode' => Prompty::select('SSR mode',
            options: ['ssr' => 'Server-side', 'ssg' => 'Static generation', 'hybrid' => 'Hybrid'],
            description: 'How pages are rendered.',
            condition: fn($r): bool => in_array($r['app_framework'] ?? '', ['next', 'nuxt']),
          ),
          'edge_runtime' => Prompty::confirm('Use edge runtime?',
            description: 'Deploy to edge functions for lower latency.',
            condition: fn($r): bool => ($r['app_framework'] ?? '') === 'next',
          ),
          'api_routes' => Prompty::confirm('Include API routes?',
            description: 'Add server-side API endpoints.',
          ),
        ],
      ),
      // --- Level 1, item 2: Lib settings (conditional on type=lib) ---
      'lib_format' => Prompty::multiselect('Output formats',
        options: ['esm' => 'ESM', 'cjs' => 'CommonJS', 'umd' => 'UMD'],
        description: 'Which module formats to build.',
        condition: fn($r): bool => ($r['type'] ?? '') === 'lib',
        children: [
          // --- Level 2: Build tool ---
          'bundler' => Prompty::select('Bundler',
            options: ['tsup' => 'tsup', 'rollup' => 'Rollup', 'unbuild' => 'unbuild'],
            description: 'Build tool for the library.',
          ),
          'declarations' => Prompty::confirm('Generate .d.ts files?',
            description: 'TypeScript declaration files for consumers.',
          ),
          'sourcemaps' => Prompty::confirm('Include sourcemaps?',
            description: 'Helps consumers debug into your library.',
          ),
        ],
      ),
      // --- Level 1, item 3: Site generator (conditional on type=site) ---
      'site_generator' => Prompty::select('Generator',
        options: ['astro' => 'Astro', 'eleventy' => 'Eleventy', 'hugo' => 'Hugo'],
        description: 'Static site generator.',
        condition: fn($r): bool => ($r['type'] ?? '') === 'site',
        children: [
          // --- Level 2: Content source ---
          'content_source' => Prompty::select('Content source',
            options: ['markdown' => 'Markdown files', 'cms' => 'Headless CMS', 'both' => 'Both'],
            description: 'Where content lives.',
          ),
          'image_optimization' => Prompty::confirm('Enable image optimization?',
            description: 'Automatic resizing, format conversion, and lazy loading.',
          ),
          'deploy_target' => Prompty::select('Deploy target',
            options: ['vercel' => 'Vercel', 'netlify' => 'Netlify', 'cloudflare' => 'Cloudflare Pages'],
            description: 'Hosting platform.',
          ),
        ],
      ),
    ],
  ),

  // --- Level 0, item 2: Code quality ---
  'quality' => Prompty::multiselect('Code quality',
    options: ['typescript' => 'TypeScript', 'eslint' => 'ESLint', 'prettier' => 'Prettier', 'husky' => 'Husky'],
    description: "Select code quality tools.\nSpace to toggle, enter to confirm.",
    hints: [
      'typescript' => 'Adds type safety with static type checking.',
      'eslint' => 'Catches common code issues and enforces style rules.',
      'prettier' => "Automatic code formatting on save.\nKeeps your codebase consistent.",
      'husky' => 'Git hooks for pre-commit checks.',
    ],
    children: [
      'ts_strict' => Prompty::confirm('Strict TypeScript?',
        description: 'Enables strict mode in tsconfig.json.',
        condition: fn($r): bool => in_array('typescript', $r['quality'] ?? []),
      ),
      'eslint_config' => Prompty::select('ESLint config',
        options: ['recommended' => 'Recommended', 'strict' => 'Strict', 'custom' => 'Custom'],
        description: 'Base ESLint configuration.',
        condition: fn($r): bool => in_array('eslint', $r['quality'] ?? []),
      ),
      'lint_staged' => Prompty::confirm('Lint staged files only?',
        description: 'Only lint files that are being committed.',
        condition: fn($r): bool => in_array('husky', $r['quality'] ?? []),
      ),
    ],
  ),

  // --- Level 0, item 3: Testing ---
  'testing' => Prompty::multiselect('Testing',
    options: ['unit' => 'Unit tests', 'e2e' => 'E2E tests', 'coverage' => 'Coverage reporting'],
    description: 'Select testing tools.',
    hints: [
      'unit' => 'Fast isolated tests with Vitest.',
      'e2e' => "End-to-end browser tests with Playwright.\nSlower but tests real user flows.",
      'coverage' => 'Track how much code is tested.',
    ],
    children: [
      'test_runner' => Prompty::select('Unit test runner',
        options: ['vitest' => 'Vitest', 'jest' => 'Jest'],
        description: 'Unit testing framework.',
        condition: fn($r): bool => in_array('unit', $r['testing'] ?? []),
      ),
      'e2e_runner' => Prompty::select('E2E framework',
        options: ['playwright' => 'Playwright', 'cypress' => 'Cypress'],
        description: 'Browser testing framework.',
        condition: fn($r): bool => in_array('e2e', $r['testing'] ?? []),
      ),
      'coverage_threshold' => Prompty::select('Coverage threshold',
        options: ['none' => 'No minimum', '70' => '70%', '80' => '80%', '90' => '90%'],
        description: 'Fail CI if coverage drops below this.',
        condition: fn($r): bool => in_array('coverage', $r['testing'] ?? []),
      ),
    ],
  ),
],
  intro: 'Project scaffolder',
  outro: function (array $results): void {
    Prompty::outro('Project configured!');
    echo "\nAll answers:\n";
    foreach ($results as $key => $value) {
      $display = is_array($value) ? (count($value) > 0 ? implode(', ', $value) : 'none') : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
      echo sprintf('  %s: %s%s', $key, $display, PHP_EOL);
    }
  },
  cancelled: 'Cancelled.',
  numbering: TRUE,
  unicode: FALSE,
  env_prefix: 'SCAFFOLD_',
);
