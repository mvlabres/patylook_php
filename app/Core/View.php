<?php

declare(strict_types=1);

namespace Patylook\Core;

use Throwable;

/** Renders PHP templates: a page inside the layout, plus reusable partials. */
final class View
{
    /** @var list<string> */
    private array $scripts = [];

    public function __construct(private readonly string $viewsPath, private readonly Formatter $formatter)
    {
    }

    /**
     * Renders a template (path relative to the views folder, without ".php") with the given variables.
     *
     * @param array<string, mixed> $vars
     */
    public function render(string $template, array $vars = []): string
    {
        $file = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('template "%s" não existe', $template));
        }
        $vars['view'] = $this;
        $vars['fmt'] = $this->formatter;

        // Isolated scope: the template sees only its own variables.
        $include = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
                return (string) ob_get_clean();
            } catch (Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };
        return $include($file, $vars);
    }

    /**
     * Renders a partial from views/partials.
     *
     * @param array<string, mixed> $vars
     */
    public function partial(string $name, array $vars = []): string
    {
        return $this->render('partials/' . $name, $vars);
    }

    /** Renders a page template inside the layout. Rendered in full first so it can register scripts. */
    public function page(string $name, PageData $page): string
    {
        $this->scripts = [];
        $content = $this->render('pages/' . $name, ['page' => $page]);
        return $this->render('layout', ['page' => $page, 'content' => $content]);
    }

    /** Registers a script to load after the page content (called from page templates). */
    public function script(string $src): void
    {
        $this->scripts[] = $src;
    }

    /** @return list<string> */
    public function scripts(): array
    {
        return $this->scripts;
    }
}
