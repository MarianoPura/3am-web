<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;

/**
 * Base controller.
 *
 * Provides the two things every controller needs — a view renderer and a
 * database handle — and nothing else. Controllers stay thin: they read input,
 * call a model, and return a Response. Business logic belongs in app/Services,
 * queries belong in app/Models.
 */
abstract class Controller
{
    public function __construct(
        protected readonly Container $container,
    ) {
    }

    protected function view(): View
    {
        return $this->container->get(View::class);
    }

    protected function db(): Database
    {
        return $this->container->get(Database::class);
    }

    /**
     * Render a template into an HTML response.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return $this->view()->response($template, $data, $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(url($path), $status);
    }

    /**
     * Redirect back to the form, preserving what was typed.
     *
     * Losing five minutes of typing to a validation error is the fastest way to
     * lose a lead on the contact form, so this is not a nicety.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    protected function back(string $path, array $errors = [], array $old = []): Response
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old']    = $old;

        return $this->redirect($path);
    }
}
