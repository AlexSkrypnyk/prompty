<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Prompty;

/**
 * 🧙Prompty - Zero-dependency interactive CLI prompt library.
 *
 * Copy the contents of this file directly into your script, preserving
 * this header.
 *
 * @license MIT
 * @see LICENSE file for full license text.
 *
 * Copyright (c) 2026 Alex Skrypnyk (alex@drevops.com)
 * https://github.com/AlexSkrypnyk/prompty
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software to deal in it without restriction, including the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies, subject to the following condition:
 *
 * This notice must be included in all copies of this file, including when
 * used as a part of other files.
 */
class Prompty {

  /**
   * Singleton instance.
   *
   * @var static|null
   */
  protected static ?self $instance = NULL;

  /**
   * Stored TTY settings for standalone widget teardown.
   */
  protected ?string $prevTty = NULL;

  /**
   * Whether a flow is currently being defined/executed.
   */
  protected static bool $inFlow = FALSE;

  /**
   * Collected answers.
   *
   * @var array<string, string|bool|int|array<string,string>>
   */
  protected array $results = [];

  /**
   * Tree connector tracking.
   *
   * @var array<int, bool>
   */
  protected array $open = [];

  /**
   * Input stream for reading keystrokes (defaults to STDIN).
   *
   * @var resource|null
   */
  protected $input;

  /**
   * Unicode symbol set.
   *
   * @var array<string, string>
   */
  protected array $cfgSymbolsUnicode = [
    'bar' => '│',
    'completed' => '◆',
    'active' => '◇',
    'intro' => '┌',
    'outro' => '└',
    'pointer' => '❯',
    'radio_on' => '●',
    'radio_off' => '○',
    'check_on' => '◼',
    'check_off' => '◻',
    'hint_arrow' => '↳',
  ];

  /**
   * ASCII symbol set.
   *
   * @var array<string, string>
   */
  protected array $cfgSymbolsAscii = [
    'bar' => '|',
    'completed' => '+',
    'active' => 'o',
    'intro' => '#',
    'outro' => '#',
    'pointer' => '>',
    'radio_on' => '(*)',
    'radio_off' => '( )',
    'check_on' => '[x]',
    'check_off' => '[ ]',
    'hint_arrow' => '-->',
  ];

  /**
   * Active symbol set (resolved from unicode setting).
   *
   * @var array<string, string>
   */
  protected array $cfgSymbols;

  /**
   * ANSI color escape sequences.
   *
   * @var array<string, string>
   */
  protected array $cfgColors = [
    'reset' => "\033[0m",
    'dim' => "\033[2m",
    'dim_italic' => "\033[2;3m",
    'cyan' => "\033[36m",
    'green' => "\033[32m",
    'red' => "\033[31m",
    'gray' => "\033[90m",
    'bold' => "\033[1m",
    'white' => "\033[37m",
  ];

  /**
   * Default ANSI color escape sequences (used to restore after suppression).
   *
   * @var array<string, string>
   */
  protected array $cfgColorsDefault;

  /**
   * Spacing strings for indentation.
   *
   * @var array<string, string>
   */
  protected array $cfgSpacing = [
    'indent' => '  ',
    'hint_indent' => '    ',
    'hint_cont' => '      ',
  ];

  /**
   * UI labels.
   *
   * @var array<string, string>
   */
  protected array $cfgLabels = [
    'yes' => 'Yes',
    'no' => 'No',
    'cancelled' => '(cancelled)',
    'none' => 'None',
    'separator' => '/',
  ];

  /**
   * Whether to use unicode symbols (NULL = auto-detect).
   */
  protected ?bool $cfgUnicode = NULL;

  /**
   * Whether to emit ANSI color codes (NULL = auto-detect).
   */
  protected ?bool $cfgAnsi = NULL;

  /**
   * Environment variable prefix for auto-discovery.
   */
  protected string $cfgEnvPrefix = 'PROMPTY_';

  /**
   * Values treated as TRUE when coercing a discovered value.
   *
   * @var list<string>
   */
  protected array $cfgTruthy = ['1', 'true', 'yes'];

  /**
   * Values treated as FALSE when coercing a discovered value.
   *
   * @var list<string>
   */
  protected array $cfgFalsy = ['0', 'false', 'no'];

  /**
   * Constructs a Prompty instance.
   */
  protected function __construct() {
    if ($this->cfgUnicode === NULL) {
      $lang = getenv('LANG') ?: getenv('LC_ALL') ?: getenv('LC_CTYPE') ?: setlocale(LC_CTYPE, '0') ?: '';
      $this->cfgUnicode = stripos($lang, 'utf') !== FALSE;
    }

    $this->cfgSymbols = $this->cfgUnicode ? $this->cfgSymbolsUnicode : $this->cfgSymbolsAscii;

    $this->cfgColorsDefault = $this->cfgColors;
    if ($this->cfgAnsi === NULL) {
      $no_color = getenv('NO_COLOR');
      $this->cfgAnsi = ($no_color !== FALSE && $no_color !== '') || getenv('TERM') === 'dumb' ? FALSE : TRUE;
    }

    if ($this->cfgAnsi === FALSE) {
      $this->cfgColors = array_fill_keys(array_keys($this->cfgColors), '');
    }
  }

  /**
   * Returns the singleton instance, creating it on first call.
   */
  protected static function instance(): static {
    if (!static::$instance instanceof Prompty) {
      // @phpstan-ignore new.static
      static::$instance = new static();
    }

    return static::$instance;
  }

  /**
   * Get the resolved configuration array.
   *
   * @return array<string, mixed>
   *   The resolved configuration.
   */
  public static function config(): array {
    $p = static::instance();

    return [
      'symbols_unicode' => $p->cfgSymbolsUnicode,
      'symbols_ascii' => $p->cfgSymbolsAscii,
      'symbols' => $p->cfgSymbols,
      'colors' => $p->cfgColors,
      'spacing' => $p->cfgSpacing,
      'labels' => $p->cfgLabels,
      'unicode' => $p->cfgUnicode,
      'ansi' => $p->cfgAnsi,
      'env_prefix' => $p->cfgEnvPrefix,
      'truthy' => $p->cfgTruthy,
      'falsy' => $p->cfgFalsy,
    ];
  }

  /**
   * Get the collected results from the last flow.
   *
   * @return array<string, mixed>
   *   The collected results.
   */
  public static function results(): array {
    return static::instance()->results;
  }

  /**
   * Get the library version.
   *
   * Returns 'development' when the version token has not been replaced
   * (i.e. running from source). During release, the __PROMPTY_VERSION__
   * token is replaced with the actual tag.
   *
   * @return string
   *   The version string.
   */
  public static function version(): string {
    return str_starts_with('__PROMPTY_VERSION__', '__') ? 'development' : '__PROMPTY_VERSION__';
  }

  /**
   * Configure the singleton instance.
   *
   * Creates the singleton if it does not exist yet. Call before any widgets
   * or flows to customise symbols, colors, env prefix, truthy/falsy values.
   *
   * @param array<string, string>|null $symbols_unicode
   *   Partial unicode symbol overrides.
   * @param array<string, string>|null $symbols_ascii
   *   Partial ASCII symbol overrides.
   * @param array<string, string>|null $colors
   *   Partial ANSI color overrides.
   * @param array<string, string>|null $spacing
   *   Partial spacing overrides.
   * @param array<string, string>|null $labels
   *   Partial label overrides.
   * @param bool|null $unicode
   *   Force unicode (TRUE), ASCII (FALSE), or auto-detect (NULL).
   * @param bool|null $ansi
   *   Force ANSI colors (TRUE), suppress (FALSE), or auto-detect (NULL).
   * @param string|null $env_prefix
   *   Environment variable prefix.
   * @param list<string>|null $truthy
   *   Values treated as TRUE.
   * @param list<string>|null $falsy
   *   Values treated as FALSE.
   */
  public static function configure(
    ?array $symbols_unicode = NULL,
    ?array $symbols_ascii = NULL,
    ?array $colors = NULL,
    ?array $spacing = NULL,
    ?array $labels = NULL,
    ?bool $unicode = NULL,
    ?bool $ansi = NULL,
    ?string $env_prefix = NULL,
    ?array $truthy = NULL,
    ?array $falsy = NULL,
  ): void {
    $p = static::instance();
    if ($symbols_unicode !== NULL) {
      $p->cfgSymbolsUnicode = array_replace($p->cfgSymbolsUnicode, $symbols_unicode);
    }
    if ($symbols_ascii !== NULL) {
      $p->cfgSymbolsAscii = array_replace($p->cfgSymbolsAscii, $symbols_ascii);
    }
    if ($colors !== NULL) {
      $p->cfgColorsDefault = array_replace($p->cfgColorsDefault, $colors);
      $p->cfgColors = array_replace($p->cfgColors, $colors);
    }
    if ($spacing !== NULL) {
      $p->cfgSpacing = array_replace($p->cfgSpacing, $spacing);
    }
    if ($labels !== NULL) {
      $p->cfgLabels = array_replace($p->cfgLabels, $labels);
    }
    if ($unicode !== NULL) {
      $p->cfgUnicode = $unicode;
    }
    if ($ansi !== NULL) {
      $p->cfgAnsi = $ansi;
    }
    if ($env_prefix !== NULL) {
      $p->cfgEnvPrefix = $env_prefix;
    }
    if ($truthy !== NULL) {
      $p->cfgTruthy = $truthy;
    }
    if ($falsy !== NULL) {
      $p->cfgFalsy = $falsy;
    }
    $p->cfgSymbols = $p->cfgUnicode ? $p->cfgSymbolsUnicode : $p->cfgSymbolsAscii;
    $p->cfgColors = $p->cfgAnsi ? $p->cfgColorsDefault : array_fill_keys(array_keys($p->cfgColorsDefault), '');
  }

  /**
   * Run a flow.
   *
   * Accepts a callable that returns the steps array. The callable is invoked
   * after $inFlow is set, so widgets inside it return closures for deferred
   * execution instead of running immediately.
   *
   * Flow state and terminal state are restored on every exit path, including
   * when a step, condition or callback throws.
   *
   * @param callable $steps
   *   Callable that returns the associative steps array.
   * @param string|callable|null $intro
   *   Intro message or callable to render before the first step.
   * @param string|callable|null $outro
   *   Outro message or callable to render after the last step.
   * @param string|callable|null $cancelled
   *   Message or callable to render when the flow is cancelled.
   * @param bool $numbering
   *   Whether to number steps.
   * @param array<string, string>|null $symbols_unicode
   *   Partial unicode symbol overrides.
   * @param array<string, string>|null $symbols_ascii
   *   Partial ASCII symbol overrides.
   * @param array<string, string>|null $colors
   *   Partial ANSI color overrides.
   * @param array<string, string>|null $spacing
   *   Partial spacing overrides.
   * @param array<string, string>|null $labels
   *   Partial label overrides.
   * @param bool|null $unicode
   *   Force unicode (TRUE), ASCII (FALSE), or auto-detect (NULL).
   * @param bool|null $ansi
   *   Force ANSI colors (TRUE), suppress (FALSE), or auto-detect (NULL).
   * @param string|null $env_prefix
   *   Environment variable prefix.
   * @param list<string>|null $truthy
   *   Values treated as TRUE.
   * @param list<string>|null $falsy
   *   Values treated as FALSE.
   *
   * @return array<string, string|bool|int|array<string,string>>|null
   *   Collected results or NULL if cancelled.
   */
  public static function flow(
    callable $steps,
    string|callable|null $intro = NULL,
    string|callable|null $outro = NULL,
    string|callable|null $cancelled = NULL,
    bool $numbering = FALSE,
    ?array $symbols_unicode = NULL,
    ?array $symbols_ascii = NULL,
    ?array $colors = NULL,
    ?array $spacing = NULL,
    ?array $labels = NULL,
    ?bool $unicode = NULL,
    ?bool $ansi = NULL,
    ?string $env_prefix = NULL,
    ?array $truthy = NULL,
    ?array $falsy = NULL,
  ): ?array {
    if ($symbols_unicode !== NULL
      || $symbols_ascii !== NULL
      || $colors !== NULL
      || $spacing !== NULL
      || $labels !== NULL
      || $unicode !== NULL
      || $ansi !== NULL
      || $env_prefix !== NULL
      || $truthy !== NULL
      || $falsy !== NULL) {
      static::configure($symbols_unicode, $symbols_ascii, $colors, $spacing, $labels, $unicode, $ansi, $env_prefix, $truthy, $falsy);
    }
    $p = static::instance();
    $p->results = [];
    static::$inFlow = TRUE;

    try {
      // Evaluate the steps callable now that $inFlow is TRUE.
      $steps = $steps();

      $options = [
        'numbering' => $numbering,
      ];

      // No TTY exists when input is piped, and the flow then runs with
      // env/discovered values without terminal control. An injected input
      // stream (test mode) also skips TTY setup.
      $p->setupTty();

      if ($p->prevTty !== NULL) {
        // @codeCoverageIgnoreStart
        // Restore the terminal on a fatal error, which skips the finally
        // below. teardownTty() clears prevTty, so this is a no-op once the
        // finally has run.
        register_shutdown_function(function () use ($p): void {
          $p->teardownTty();
        });
        // @codeCoverageIgnoreEnd
      }

      if ($intro !== NULL) {
        is_callable($intro) ? $intro($p->results) : $p->printLines($p->renderIntro($intro));
      }

      $outcome = $p->walkFlow($steps, 0, $options, '');

      if ($outcome === FALSE) {
        if ($cancelled !== NULL) {
          is_callable($cancelled) ? $cancelled($p->results) : $p->printLines($p->renderOutro($cancelled));
        }

        return NULL;
      }

      if ($outro !== NULL) {
        is_callable($outro) ? $outro($p->results) : $p->printLines($p->renderOutro($outro));
      }

      return $p->results;
    }
    finally {
      $p->teardownTty();
      static::$inFlow = FALSE;
    }
  }

  /**
   * Renders a text input widget, returning the user's input string.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param string $default
   *   Pre-filled, editable initial value seeded into the input buffer. It is
   *   also the answer when nothing is typed.
   * @param string $placeholder
   *   Placeholder text shown when the input is empty. It is the answer when
   *   nothing is typed and no default was seeded.
   * @param string $description
   *   Optional description rendered below the label.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. It is trimmed, and an
   *   empty result is an empty answer: it takes $default, or $placeholder.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<string, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|string|null
   *   A closure in flow mode, or the entered string in standalone mode.
   */
  public static function text(
    string $label,
    string $default = '',
    string $placeholder = '',
    string $description = '',
    mixed $discovered = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|string|null {
    if (static::$inFlow && $ctx === NULL) {
      $call = fn(array $ctx): array|\Closure|string|null => static::text(
        $label,
        default: $default,
        placeholder: $placeholder,
        description: $description,
        discovered: $discovered,
        ctx: $ctx,
      );
      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $standalone = !static::$inFlow;

    if ($standalone) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    $resolved = $discovered ?? $ctx['discovered'] ?? NULL;

    if ($resolved !== NULL) {
      /** @var int|float|string|bool $resolved */
      $display = trim((string) $resolved);

      // An empty answer resolves as it does interactively: the seeded default,
      // or the placeholder when nothing was seeded.
      if ($display === '') {
        $display = $default !== '' ? $default : $placeholder;
      }

      $p->printLines($p->renderCompleted($label, $display, $depth, $open));
      if ($standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $display;
    }

    $render_active = function (string $value) use ($p, $label, $placeholder, $description, $depth, $open): array {
      $cursor = $p->color('█', 'cyan');
      $display = $value === '' ? $p->color($placeholder, 'gray') . $cursor : $p->color($value, 'white') . $cursor;

      if ($depth === 0) {
        $lines = [$p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);
        $lines[] = $p->bar() . $p->cfgSpacing['indent'] . $display;
        $lines[] = $p->bar();

        return $lines;
      }

      $label_prefix = $p->bar() . $p->labelPrefix($depth, $open);
      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = [$label_prefix . $p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);
      $lines[] = $p->bar() . $body_prefix . $display;
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $value = $default;
    $line_count = $p->printLines($render_active($value));

    while (TRUE) {
      $key = $p->readKey();

      if ($key === 'ctrl-c' || $key === 'escape') {
        $p->redraw($line_count, $p->renderCancelled($label, $value, $depth, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $display = $value !== '' ? $value : $placeholder;
        $p->redraw($line_count, $p->renderCompleted($label, $display, $depth, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return $display;
      }

      if ($key === 'backspace') {
        if ($value !== '') {
          $value = mb_substr($value, 0, -1);
        }
      }
      elseif ($key === 'space') {
        $value .= ' ';
      }
      // Reject control bytes (ord < 32); accept printable ASCII and bytes
      // 0x80-0xFF, so UTF-8 characters delivered one byte per readKey()
      // call accumulate in the buffer.
      elseif (mb_strlen($key) === 1 && ord($key) >= 32) {
        $value .= $key;
      }

      $line_count = $p->redraw($line_count, $render_active($value));
    }
  }

  /**
   * Renders a single-select widget, returning the chosen option key.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param array<int|string, string> $options
   *   Map of option key to display label. Must not be empty.
   * @param string $default
   *   Option key to focus initially. An empty string focuses the first option;
   *   anything else must be a key of $options.
   * @param string $description
   *   Optional description rendered below the label.
   * @param array<int|string, string> $hints
   *   Map of option key to hint text.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. Must be a key of
   *   $options.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<string, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|string|null
   *   A closure in flow mode, or the selected option key in standalone mode.
   *
   * @throws \InvalidArgumentException
   *   When $options is empty, or a default or discovered value is not a key of
   *   $options.
   */
  public static function select(
    string $label,
    array $options = [],
    string $default = '',
    string $description = '',
    array $hints = [],
    mixed $discovered = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|string|null {
    if (static::$inFlow && $ctx === NULL) {
      $call = fn(array $ctx): array|\Closure|string|null => static::select(
        $label,
        options: $options,
        default: $default,
        description: $description,
        hints: $hints,
        discovered: $discovered,
        ctx: $ctx,
      );
      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $standalone = !static::$inFlow;

    // Validate before raw mode is entered, so a rejected call cannot leave
    // the terminal unrestored.
    $p->assertDeclaredOptions($label, $options);
    $default_key = $default === '' ? NULL : $p->assertOptionKey('Default', $label, $default, $options);
    $resolved = $discovered ?? $ctx['discovered'] ?? NULL;
    $resolved_key = $resolved === NULL ? NULL : $p->assertOptionKey('Discovered', $label, $resolved, $options);

    if ($standalone) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    $option_keys = $p->optionKeys($options);
    $option_labels = array_values($options);
    $ordered_hints = array_map(fn(string $key) => $hints[$key] ?? '', $option_keys);

    if ($resolved_key !== NULL) {
      $p->printLines($p->renderCompleted($label, $options[$resolved_key], $depth, $open));
      if ($standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $resolved_key;
    }

    $render_active = function (int $focused) use ($p, $label, $option_labels, $description, $ordered_hints, $depth, $open): array {
      if ($depth === 0) {
        $lines = [$p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);

        foreach ($option_labels as $index => $option) {
          $is_focused = $index === $focused;
          $radio = $p->color($p->cfgSymbols[$is_focused ? 'radio_on' : 'radio_off'], $is_focused ? 'green' : 'dim');
          $text = $is_focused ? $option : $p->color($option, 'dim');

          $lines[] = $p->bar() . $p->pointer($is_focused) . $radio . ' ' . $text;

          if ($is_focused && ($ordered_hints[$index] ?? '') !== '') {
            $lines = array_merge($lines, $p->renderHint($ordered_hints[$index]));
          }
        }

        $lines[] = $p->bar();

        return $lines;
      }

      $label_prefix = $p->bar() . $p->labelPrefix($depth, $open);
      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = [$label_prefix . $p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);

      foreach ($option_labels as $index => $option) {
        $is_focused = $index === $focused;
        $radio = $p->color($p->cfgSymbols[$is_focused ? 'radio_on' : 'radio_off'], $is_focused ? 'green' : 'dim');
        $text = $is_focused ? $option : $p->color($option, 'dim');

        $lines[] = $p->bar() . $body_prefix . $p->pointer($is_focused) . $radio . ' ' . $text;

        if ($is_focused && ($ordered_hints[$index] ?? '') !== '') {
          $lines = array_merge($lines, $p->renderHint($ordered_hints[$index], $depth, $open));
        }
      }

      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $focused = 0;

    if ($default_key !== NULL) {
      // The default was checked against the same keys, so it is always found.
      $focused = (int) array_search($default_key, $option_keys, TRUE);
    }

    $line_count = $p->printLines($render_active($focused));

    while (TRUE) {
      $key = $p->readKey();

      if ($key === 'ctrl-c' || $key === 'escape') {
        $p->redraw($line_count, $p->renderCancelled($label, $option_labels[$focused], $depth, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $p->redraw($line_count, $p->renderCompleted($label, $option_labels[$focused], $depth, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return $option_keys[$focused];
      }

      if ($key === 'up' || $key === 'left') {
        $focused = ($focused - 1 + count($option_labels)) % count($option_labels);
      }
      elseif ($key === 'down' || $key === 'right') {
        $focused = ($focused + 1) % count($option_labels);
      }

      $line_count = $p->redraw($line_count, $render_active($focused));
    }
  }

  /**
   * Renders a multi-select widget, returning an array of chosen option keys.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param array<int|string, string> $options
   *   Map of option key to display label. Must not be empty.
   * @param list<int|string> $default
   *   Option keys to pre-check when the widget is rendered interactively. Every
   *   entry must be a key of $options; an empty list pre-checks nothing.
   * @param string $description
   *   Optional description rendered below the label.
   * @param array<int|string, string> $hints
   *   Map of option key to hint text.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. Every entry must be a
   *   key of $options.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<string, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|list<string>|null
   *   A closure in flow mode, or the array of selected keys in standalone mode,
   *   deduplicated and in option order.
   *
   * @throws \InvalidArgumentException
   *   When $options is empty, or a default or discovered entry is not a key of
   *   $options.
   */
  public static function multiselect(
    string $label,
    array $options = [],
    array $default = [],
    string $description = '',
    array $hints = [],
    mixed $discovered = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|null {
    if (static::$inFlow && $ctx === NULL) {
      $call = fn(array $ctx): array|\Closure|null => static::multiselect(
        $label,
        options: $options,
        default: $default,
        description: $description,
        hints: $hints,
        discovered: $discovered,
        ctx: $ctx,
      );
      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $standalone = !static::$inFlow;

    // Env values for multiselect arrive comma-separated (e.g., "a,b,c").
    $ctx_discovered = $ctx['discovered'] ?? NULL;
    /** @var int|float|string|bool|null $ctx_discovered */
    $resolved = $discovered ?? ($ctx_discovered !== NULL ? explode(',', (string) $ctx_discovered) : NULL);

    // Validate before raw mode is entered, so a rejected call cannot leave
    // the terminal unrestored.
    $p->assertDeclaredOptions($label, $options);
    $default_keys = $p->assertOptionKeys('Default', $label, $default, $options);
    $resolved_keys = $resolved === NULL ? NULL : $p->assertOptionKeys('Discovered', $label, $resolved, $options);

    if ($standalone) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    $option_keys = $p->optionKeys($options);
    $option_labels = array_values($options);
    $ordered_hints = array_map(fn(string $key) => $hints[$key] ?? '', $option_keys);

    if ($resolved_keys !== NULL) {
      $display = $resolved_keys !== [] ? implode(', ', array_map(fn(string $key): string => $options[$key], $resolved_keys)) : $p->cfgLabels['none'];
      $p->printLines($p->renderCompleted($label, $display, $depth, $open));
      if ($standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $resolved_keys;
    }

    $render_active = function (int $focused, array $checked) use ($p, $label, $option_labels, $description, $ordered_hints, $depth, $open): array {
      if ($depth === 0) {
        $lines = [$p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);

        foreach ($option_labels as $index => $option) {
          $is_checked = $checked[$index] ?? FALSE;
          $is_focused = $index === $focused;
          $check = $p->color($p->cfgSymbols[$is_checked ? 'check_on' : 'check_off'], $is_focused || $is_checked ? 'green' : 'dim');
          $text = $is_focused || $is_checked ? $option : $p->color($option, 'dim');

          $lines[] = $p->bar() . $p->pointer($is_focused) . $check . ' ' . $text;

          if ($is_focused && ($ordered_hints[$index] ?? '') !== '') {
            $lines = array_merge($lines, $p->renderHint($ordered_hints[$index]));
          }
        }

        $lines[] = $p->bar();

        return $lines;
      }

      $label_prefix = $p->bar() . $p->labelPrefix($depth, $open);
      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = [$label_prefix . $p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);

      foreach ($option_labels as $index => $option) {
        $is_checked = $checked[$index] ?? FALSE;
        $is_focused = $index === $focused;
        $check = $p->color($p->cfgSymbols[$is_checked ? 'check_on' : 'check_off'], $is_focused || $is_checked ? 'green' : 'dim');
        $text = $is_focused || $is_checked ? $option : $p->color($option, 'dim');

        $lines[] = $p->bar() . $body_prefix . $p->pointer($is_focused) . $check . ' ' . $text;

        if ($is_focused && ($ordered_hints[$index] ?? '') !== '') {
          $lines = array_merge($lines, $p->renderHint($ordered_hints[$index], $depth, $open));
        }
      }

      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $focused = 0;
    $checked = array_map(fn(string $key): bool => in_array($key, $default_keys, TRUE), $option_keys);
    $line_count = $p->printLines($render_active($focused, $checked));

    while (TRUE) {
      $key = $p->readKey();

      if ($key === 'ctrl-c' || $key === 'escape') {
        $p->redraw($line_count, $p->renderCancelled($label, '', $depth, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $selected_keys = [];
        $selected_labels = [];

        foreach ($option_labels as $index => $option_label) {
          if ($checked[$index]) {
            $selected_keys[] = $option_keys[$index];
            $selected_labels[] = $option_label;
          }
        }

        $p->redraw($line_count, $p->renderCompleted($label, $selected_labels !== [] ? implode(', ', $selected_labels) : $p->cfgLabels['none'], $depth, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return $selected_keys;
      }

      if ($key === 'space') {
        $checked[$focused] = !$checked[$focused];
      }
      elseif ($key === 'up' || $key === 'left') {
        $focused = ($focused - 1 + count($option_labels)) % count($option_labels);
      }
      elseif ($key === 'down' || $key === 'right') {
        $focused = ($focused + 1) % count($option_labels);
      }

      $line_count = $p->redraw($line_count, $render_active($focused, $checked));
    }
  }

  /**
   * Renders a yes/no confirm widget, returning TRUE for yes and FALSE for no.
   *
   * @param string $label
   *   The widget label shown to the user.
   * @param bool $default
   *   The default selection (TRUE for yes, FALSE for no).
   * @param string $description
   *   Optional description rendered below the label.
   * @param mixed $discovered
   *   Pre-filled value that bypasses interactive input. A boolean is taken as
   *   is; anything else must appear in the truthy or falsy list.
   * @param callable|null $condition
   *   Optional condition callback; skips the step when it returns FALSE.
   * @param array<string, mixed> $children
   *   Child steps to execute after this widget.
   * @param array<string, mixed>|null $ctx
   *   Flow context passed by the flow walker.
   *
   * @return \Closure|array<string, mixed>|bool|null
   *   A closure in flow mode, or the boolean result in standalone mode.
   *
   * @throws \InvalidArgumentException
   *   When a discovered value appears in neither the truthy nor falsy list.
   */
  public static function confirm(
    string $label,
    bool $default = TRUE,
    string $description = '',
    mixed $discovered = NULL,
    ?callable $condition = NULL,
    array $children = [],
    ?array $ctx = NULL,
  ): \Closure|array|bool|null {
    if (static::$inFlow && $ctx === NULL) {
      $call = fn(array $ctx): array|bool|\Closure|null => static::confirm(
        $label,
        default: $default,
        description: $description,
        discovered: $discovered,
        ctx: $ctx,
      );
      if ($condition !== NULL || $children !== []) {
        return ['__call' => $call, '__children' => $children, '__condition' => $condition];
      }

      return $call;
    }

    $p = static::instance();
    $ctx ??= [
      'depth' => 0,
      'is_last' => FALSE,
      'open' => [],
    ];
    $standalone = !static::$inFlow;

    /** @var list<string> $truthy */
    $truthy = $ctx['truthy'] ?? $p->cfgTruthy;
    /** @var list<string> $falsy */
    $falsy = $ctx['falsy'] ?? $p->cfgFalsy;

    // Validate before raw mode is entered, so a rejected value cannot leave
    // the terminal unrestored.
    $resolved = $discovered ?? $ctx['discovered'] ?? NULL;
    $resolved_bool = $resolved === NULL ? NULL : $p->assertDiscoveredBool($label, $resolved, $truthy, $falsy);

    if ($standalone) {
      $p->setupTty();
    }

    /** @var int $depth */
    $depth = $ctx['depth'] ?? 0;
    /** @var array<int, bool> $open */
    $open = $ctx['open'] ?? [];
    $label = $p->numberLabel($label, $ctx);

    if ($resolved_bool !== NULL) {
      $p->printLines($p->renderCompleted($label, $resolved_bool ? $p->cfgLabels['yes'] : $p->cfgLabels['no'], $depth, $open));
      if ($standalone) {
        // @codeCoverageIgnoreStart
        $p->teardownTty();
        // @codeCoverageIgnoreEnd
      }

      return $resolved_bool;
    }

    $render_active = function (bool $focused_yes) use ($p, $label, $description, $depth, $open): array {
      $options_display = $focused_yes
        ? $p->color($p->cfgSymbols['radio_on'], 'green') . ' ' . $p->cfgLabels['yes'] . ' ' . $p->color($p->cfgLabels['separator'], 'dim')
          . ' ' . $p->color($p->cfgSymbols['radio_off'], 'dim') . ' ' . $p->color($p->cfgLabels['no'], 'dim')
        : $p->color($p->cfgSymbols['radio_off'], 'dim') . ' ' . $p->color($p->cfgLabels['yes'], 'dim') . ' ' . $p->color($p->cfgLabels['separator'], 'dim')
          . ' ' . $p->color($p->cfgSymbols['radio_on'], 'green') . ' ' . $p->cfgLabels['no'];

      if ($depth === 0) {
        $lines = [$p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
        $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description) : [$p->bar()]);
        $lines[] = $p->bar() . $p->cfgSpacing['indent'] . $options_display;
        $lines[] = $p->bar();

        return $lines;
      }

      $label_prefix = $p->bar() . $p->labelPrefix($depth, $open);
      $body_prefix = $p->bodyPrefix($depth, $open);

      $lines = [$label_prefix . $p->color($p->cfgSymbols['active'], 'cyan') . $p->cfgSpacing['indent'] . $label];
      $lines = array_merge($lines, $description !== '' ? $p->renderDescription($description, $depth, $open) : [$p->bar() . $body_prefix]);
      $lines[] = $p->bar() . $body_prefix . $options_display;
      $lines[] = $p->bar() . $body_prefix;

      return $lines;
    };

    $yes = $default;
    $line_count = $p->printLines($render_active($yes));

    while (TRUE) {
      $key = $p->readKey();

      if ($key === 'ctrl-c' || $key === 'escape') {
        $p->redraw($line_count, $p->renderCancelled($label, $yes ? $p->cfgLabels['yes'] : $p->cfgLabels['no'], $depth, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return NULL;
      }

      if ($key === 'enter') {
        $p->redraw($line_count, $p->renderCompleted($label, $yes ? $p->cfgLabels['yes'] : $p->cfgLabels['no'], $depth, $open));
        if ($standalone) {
          // @codeCoverageIgnoreStart
          $p->teardownTty();
          // @codeCoverageIgnoreEnd
        }

        return $yes;
      }

      if (in_array($key, ['left', 'right', 'up', 'down', 'tab'], TRUE)) {
        $yes = !$yes;
      }
      elseif ($key === 'y' || $key === 'Y') {
        $yes = TRUE;
      }
      elseif ($key === 'n' || $key === 'N') {
        $yes = FALSE;
      }

      $line_count = $p->redraw($line_count, $render_active($yes));
    }
  }

  /**
   * Prints an intro banner at the start of a flow.
   */
  public static function intro(string $message): void {
    $p = static::instance();
    $p->printLines($p->renderIntro($message));
  }

  /**
   * Prints an outro banner at the end of a flow.
   */
  public static function outro(string $message): void {
    $p = static::instance();
    $p->printLines($p->renderOutro($message));
  }

  /**
   * Prints an array of lines and returns the line count.
   *
   * @param array<int, string> $lines
   *   Lines to print.
   *
   * @return int
   *   The number of lines printed.
   */
  public static function output(array $lines): int {
    return static::instance()->printLines($lines);
  }

  /**
   * Wraps text in an ANSI color sequence.
   */
  protected function color(string $text, string $color): string {
    return isset($this->cfgColors[$color]) ? $this->cfgColors[$color] . $text . $this->cfgColors['reset'] : $text;
  }

  /**
   * Returns the styled vertical bar character for the tree connector.
   */
  protected function bar(): string {
    return $this->color($this->cfgSymbols['bar'], 'gray');
  }

  /**
   * Returns the pointer gutter shown before an option's indicator.
   *
   * Marks the focused row independently of the selection state, so it stays
   * distinguishable even when it is already checked.
   *
   * @param bool $focused
   *   Whether this option is the focused one.
   *
   * @return string
   *   The styled pointer when focused, or equal-width padding when not,
   *   followed by a separating space.
   */
  protected function pointer(bool $focused): string {
    return ($focused ? $this->color($this->cfgSymbols['pointer'], 'cyan') : ' ') . ' ';
  }

  /**
   * Restores TTY settings to the previously saved state.
   */
  protected function restoreTty(string $prev): void {
    shell_exec('stty ' . $prev . ' 2>/dev/null');
  }

  /**
   * Reads a single keypress from the input stream and returns its name.
   */
  protected function readKey(): string {
    $stream = $this->input ?? STDIN;
    $char = fread($stream, 1);

    if ($char === FALSE || $char === '') {
      return '';
    }

    return match ($char) {
      "\x03" => 'ctrl-c',
      "\n", "\r" => 'enter',
      "\x7f", "\x08" => 'backspace',
      "\t" => 'tab',
      ' ' => 'space',
      "\x1b" => match (fread($stream, 2)) {
        '[A' => 'up',
        '[B' => 'down',
        '[C' => 'right',
        '[D' => 'left',
        default => 'escape',
      },
      default => $char,
    };
  }

  /**
   * Shows the terminal cursor.
   */
  protected function showCursor(): void {
    echo "\033[?25h";
  }

  /**
   * Hides the terminal cursor.
   */
  protected function hideCursor(): void {
    echo "\033[?25l";
  }

  /**
   * Set up TTY for standalone widget execution.
   */
  protected function setupTty(): void {
    $this->prevTty = $this->input === NULL ? (shell_exec('stty -g 2>/dev/null') ?: NULL) : NULL;

    if ($this->prevTty !== NULL) {
      $this->prevTty = trim($this->prevTty);
      shell_exec('stty -echo -icanon min 1 time 0 2>/dev/null');
      $this->hideCursor();
    }
  }

  /**
   * Tear down TTY after standalone widget execution.
   */
  protected function teardownTty(): void {
    if ($this->prevTty !== NULL) {
      // @codeCoverageIgnoreStart
      $this->restoreTty($this->prevTty);
      $this->showCursor();
      $this->prevTty = NULL;
      // @codeCoverageIgnoreEnd
    }
  }

  /**
   * Prints an array of lines to stdout and returns the line count.
   *
   * @param array<int, string> $lines
   *   Lines to print.
   *
   * @return int
   *   The number of lines printed.
   */
  protected function printLines(array $lines): int {
    echo implode(PHP_EOL, $lines) . PHP_EOL;

    return count($lines);
  }

  /**
   * Clears previously printed lines and redraws with new content.
   *
   * @param int $prev_line_count
   *   The number of lines previously printed to clear.
   * @param array<int, string> $lines
   *   Lines to redraw.
   *
   * @return int
   *   The number of lines printed.
   */
  protected function redraw(int $prev_line_count, array $lines): int {
    if ($prev_line_count > 0) {
      echo "\033[{$prev_line_count}A\r\033[J";
    }

    return $this->printLines($lines);
  }

  /**
   * Returns the indentation prefix for a widget label at the given depth.
   *
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return string
   *   The label prefix string.
   */
  protected function labelPrefix(int $depth, array $open): string {
    $result = '  ';

    for ($level = 1; $level < $depth; $level++) {
      $result .= ($open[$level] ?? FALSE) ? $this->color($this->cfgSymbols['bar'], 'gray') . '  ' : '   ';
    }

    return $result;
  }

  /**
   * Returns the indentation prefix for widget body lines at the given depth.
   *
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return string
   *   The body prefix string.
   */
  protected function bodyPrefix(int $depth, array $open): string {
    $result = '  ';

    for ($level = 1; $level <= $depth; $level++) {
      $result .= ($open[$level] ?? FALSE) ? $this->color($this->cfgSymbols['bar'], 'gray') . '  ' : '   ';
    }

    return $result;
  }

  /**
   * Appends a step number suffix to a label when numbering is enabled.
   *
   * @param string $label
   *   The original label text.
   * @param array<string, mixed> $ctx
   *   Flow context.
   *
   * @return string
   *   The label with an optional step number suffix.
   */
  protected function numberLabel(string $label, array $ctx): string {
    if (isset($ctx['number'])) {
      /** @var string $number */
      $number = $ctx['number'];

      return $label . ' ' . $this->color('(' . $number . ')', 'dim');
    }

    return $label;
  }

  /**
   * Lists a widget's option keys as strings.
   *
   * PHP casts a canonical decimal-integer string array key to an int, so
   * array_keys() over options such as ['10' => 'Table 10'] yields ints. Every
   * option key in the class comes from here, so the widgets return and match
   * the strings their contracts declare.
   *
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return list<string>
   *   The option keys in declaration order.
   */
  protected function optionKeys(array $options): array {
    return array_map(strval(...), array_keys($options));
  }

  /**
   * Rejects a widget declared without options.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @throws \InvalidArgumentException
   *   When $options is empty.
   */
  protected function assertDeclaredOptions(string $label, array $options): void {
    if ($options === []) {
      throw new \InvalidArgumentException(sprintf('No options declared for "%s". Provide at least one option.', $label));
    }
  }

  /**
   * Validates a supplied value against a widget's declared options.
   *
   * @param string $parameter
   *   The parameter that supplied the value, capitalised, opening the error
   *   message: 'Discovered' or 'Default'.
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param mixed $value
   *   The supplied value.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return string
   *   The supplied value as a trimmed option key.
   *
   * @throws \InvalidArgumentException
   *   When the value is not a key of $options.
   */
  protected function assertOptionKey(string $parameter, string $label, mixed $value, array $options): string {
    $key = is_scalar($value) ? trim((string) $value) : get_debug_type($value);

    if (!array_key_exists($key, $options)) {
      $available = $options === [] ? 'none' : implode(', ', $this->optionKeys($options));

      throw new \InvalidArgumentException(sprintf('%s value "%s" for "%s" is not a valid option. Available options: %s.', $parameter, $key, $label, $available));
    }

    return $key;
  }

  /**
   * Validates supplied multiselect values against the declared options.
   *
   * Entries that are empty after trimming are dropped, so an empty value and a
   * trailing comma both mean "nothing selected". Surviving entries are checked
   * for membership and returned in option order without duplicates, matching
   * what the interactive path returns.
   *
   * @param string $parameter
   *   The parameter that supplied the value, capitalised, opening the error
   *   message: 'Discovered' or 'Default'.
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param mixed $value
   *   The supplied value: a list of option keys, or a single option key.
   * @param array<int|string, string> $options
   *   Map of option key to display label.
   *
   * @return list<string>
   *   The selected option keys, deduplicated and in option order.
   *
   * @throws \InvalidArgumentException
   *   When any entry is not a key of $options.
   */
  protected function assertOptionKeys(string $parameter, string $label, mixed $value, array $options): array {
    $entries = is_array($value) ? $value : [$value];
    $selected = [];

    foreach ($entries as $entry) {
      if (is_scalar($entry) && trim((string) $entry) === '') {
        continue;
      }

      $selected[] = $this->assertOptionKey($parameter, $label, $entry, $options);
    }

    return array_values(array_filter($this->optionKeys($options), fn(string $key): bool => in_array($key, $selected, TRUE)));
  }

  /**
   * Coerces a discovered value to a boolean using the truthy/falsy lists.
   *
   * @param string $label
   *   The widget label, used to identify the widget in the error message.
   * @param mixed $value
   *   The discovered value.
   * @param list<string> $truthy
   *   Values treated as TRUE.
   * @param list<string> $falsy
   *   Values treated as FALSE.
   *
   * @return bool
   *   The coerced boolean.
   *
   * @throws \InvalidArgumentException
   *   When the value appears in neither list.
   */
  protected function assertDiscoveredBool(string $label, mixed $value, array $truthy, array $falsy): bool {
    if (is_bool($value)) {
      return $value;
    }

    $display = is_scalar($value) ? (string) $value : get_debug_type($value);
    $normalise = fn(string $token): string => strtolower(trim($token));
    $normalised = $normalise($display);

    if (in_array($normalised, array_map($normalise, $truthy), TRUE)) {
      return TRUE;
    }

    if (in_array($normalised, array_map($normalise, $falsy), TRUE)) {
      return FALSE;
    }

    $accepted = implode(', ', array_merge($truthy, $falsy));

    throw new \InvalidArgumentException(sprintf('Discovered value "%s" for "%s" is not a valid answer. Accepted values: %s.', $display, $label, $accepted));
  }

  /**
   * Renders the intro banner lines.
   *
   * @param string $message
   *   The intro message to display.
   *
   * @return array<int, string>
   *   The rendered intro lines.
   */
  protected function renderIntro(string $message): array {
    return [
      '',
      $this->color($this->cfgSymbols['intro'], 'gray') . $this->cfgSpacing['indent'] . $this->color($message, 'bold'),
      $this->bar(),
    ];
  }

  /**
   * Renders the outro banner lines.
   *
   * @param string $message
   *   The outro message to display.
   *
   * @return array<int, string>
   *   The rendered outro lines.
   */
  protected function renderOutro(string $message): array {
    return [
      $this->bar(),
      $this->color($this->cfgSymbols['outro'], 'gray') . $this->cfgSpacing['indent'] . $this->color($message, 'green'),
      '',
    ];
  }

  /**
   * Renders description text lines with appropriate depth indentation.
   *
   * @param string $description
   *   The description text to render.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered description lines.
   */
  protected function renderDescription(string $description, int $depth = 0, array $open = []): array {
    $body_prefix = $depth > 0 ? $this->bodyPrefix($depth, $open) : $this->cfgSpacing['indent'];

    $lines = array_map(
      fn(string $text_line): string => $this->bar() . $body_prefix . $this->color($text_line, 'dim_italic'),
      explode("\n", $description),
    );

    $lines[] = $this->bar() . ($depth > 0 ? $this->bodyPrefix($depth, $open) : '');

    return $lines;
  }

  /**
   * Renders hint text lines with arrow prefix and depth indentation.
   *
   * @param string $hint
   *   The hint text to render.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered hint lines.
   */
  protected function renderHint(string $hint, int $depth = 0, array $open = []): array {
    $body_prefix = $depth > 0 ? $this->bodyPrefix($depth, $open) : '';
    $hint_lines = explode("\n", $hint);

    return array_map(
      fn(string $text_line, int $index): string => $this->bar() . $body_prefix . ($index === 0
          ? $this->cfgSpacing['hint_indent'] . $this->color($this->cfgSymbols['hint_arrow'], 'dim') . ' ' . $this->color($text_line, 'dim_italic')
          : $this->cfgSpacing['hint_cont'] . $this->color($text_line, 'dim_italic')
        ),
      $hint_lines,
      array_keys($hint_lines),
    );
  }

  /**
   * Renders the completed state lines for a widget.
   *
   * @param string $label
   *   The widget label.
   * @param string $value
   *   The submitted value to display.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered completed-state lines.
   */
  protected function renderCompleted(string $label, string $value, int $depth = 0, array $open = []): array {
    if ($depth === 0) {
      return [
        $this->color($this->cfgSymbols['completed'], 'cyan') . $this->cfgSpacing['indent'] . $label,
        $this->bar() . $this->cfgSpacing['indent'] . $this->color($value, 'dim'),
        $this->bar(),
      ];
    }

    $label_prefix = $this->bar() . $this->labelPrefix($depth, $open);
    $body_prefix = $this->bodyPrefix($depth, $open);

    return [
      $label_prefix . $this->color($this->cfgSymbols['completed'], 'cyan') . $this->cfgSpacing['indent'] . $label,
      $this->bar() . $body_prefix . $this->color($value, 'dim'),
      $this->bar() . $body_prefix,
    ];
  }

  /**
   * Renders the cancelled state lines for a widget.
   *
   * @param string $label
   *   The widget label.
   * @param string $value
   *   The current value at the time of cancellation.
   * @param int $depth
   *   The current nesting depth.
   * @param array<int, bool> $open
   *   Tracks which depth levels have continuing siblings.
   *
   * @return array<int, string>
   *   The rendered cancelled-state lines.
   */
  protected function renderCancelled(string $label, string $value, int $depth = 0, array $open = []): array {
    if ($depth === 0) {
      return [
        $this->color($this->cfgSymbols['active'], 'red') . $this->cfgSpacing['indent'] . $label,
        $this->bar() . $this->cfgSpacing['indent'] . $this->color($value, 'dim') . $this->color(' ' . $this->cfgLabels['cancelled'], 'red'),
        $this->bar(),
      ];
    }

    $label_prefix = $this->bar() . $this->labelPrefix($depth, $open);
    $body_prefix = $this->bodyPrefix($depth, $open);

    return [
      $label_prefix . $this->color($this->cfgSymbols['active'], 'red') . $this->cfgSpacing['indent'] . $label,
      $this->bar() . $body_prefix . $this->color($value, 'dim') . $this->color(' ' . $this->cfgLabels['cancelled'], 'red'),
      $this->bar() . $body_prefix,
    ];
  }

  /**
   * Extracts the condition callback a flow step was declared with.
   *
   * @param mixed $step
   *   A flow step: a closure, or a step array carrying '__condition'.
   *
   * @return callable|null
   *   The condition callback, or NULL when the step is unconditional.
   */
  protected function stepCondition(mixed $step): ?callable {
    if (is_callable($step) || !is_array($step)) {
      return NULL;
    }

    $condition = $step['__condition'] ?? NULL;

    return is_callable($condition) ? $condition : NULL;
  }

  /**
   * Checks whether any of the given steps would render against the results.
   *
   * @param array<array-key, mixed> $steps
   *   Flow steps to test.
   *
   * @return bool
   *   TRUE when at least one step is unconditional or its condition passes.
   */
  protected function hasVisibleStep(array $steps): bool {
    foreach ($steps as $step) {
      $condition = $this->stepCondition($step);

      if ($condition === NULL || $condition($this->results)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Recursively walks flow steps, executing each widget and collecting results.
   *
   * @param array<string, mixed> $steps
   *   Flow steps (closures or step arrays).
   * @param int $depth
   *   The current nesting depth.
   * @param array<string, mixed> $options
   *   Flow options; only the 'numbering' key is read. Env prefix, truthy
   *   and falsy values come from the cfg* properties.
   * @param string $number_prefix
   *   Dot-separated prefix for hierarchical step numbering.
   *
   * @return bool
   *   TRUE if all steps completed, FALSE if the user cancelled.
   */
  protected function walkFlow(array $steps, int $depth, array $options, string $number_prefix): bool {
    $step_number = 0;
    $index = 0;

    foreach ($steps as $key => $step) {
      $index++;

      if (is_callable($step)) {
        $call = $step;
        $children = [];
      }
      else {
        /** @var array<string, mixed> $step */
        $call = $step['__call'];
        /** @var array<string, mixed> $children */
        $children = is_array($step['__children'] ?? NULL) ? $step['__children'] : [];
      }

      $condition = $this->stepCondition($step);

      if ($condition !== NULL && !$condition($this->results)) {
        continue;
      }

      $step_number++;

      $is_last = $depth > 0 && !$this->hasVisibleStep(array_slice($steps, $index));

      // Update tree connector state before creating ctx so the widget sees
      // the correct open/closed state for its depth level.
      if ($depth > 0) {
        if ($is_last) {
          unset($this->open[$depth]);
        }
        else {
          $this->open[$depth] = TRUE;
        }
      }

      $number = $number_prefix !== '' ? $number_prefix . '.' . $step_number : (string) $step_number;
      $env_value = getenv($this->cfgEnvPrefix . strtoupper((string) $key));

      $ctx = [
        'depth' => $depth,
        'is_last' => $is_last,
        'open' => $this->open,
        'results' => $this->results,
        'number' => ($options['numbering'] ?? FALSE) ? $number : NULL,
        'discovered' => $env_value !== FALSE ? $env_value : NULL,
        'truthy' => $this->cfgTruthy,
        'falsy' => $this->cfgFalsy,
      ];

      /** @var callable $call */
      $value = $call($ctx);

      if ($value === NULL) {
        return FALSE;
      }

      $this->results[$key] = $value;

      if ($this->hasVisibleStep($children)) {
        $child_depth = $depth + 1;
        $sep = $this->bar() . $this->labelPrefix($child_depth, $this->open) . $this->bar();
        $this->printLines([$sep]);

        if (!$this->walkFlow($children, $child_depth, $options, $number)) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

}
