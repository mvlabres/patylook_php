<?php

declare(strict_types=1);

namespace Patylook\Http;

use Patylook\Contracts\AuthServiceInterface;
use Patylook\Contracts\LoggerInterface;
use Patylook\Core\PageData;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Core\View;
use Patylook\Dto\Principal;
use Patylook\Exceptions\ConflictException;
use Patylook\Exceptions\InUseException;
use Patylook\Exceptions\InvalidTransitionException;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\FlashKind;
use Throwable;

/**
 * Builds the responses controllers return: pages, redirects with a flash message and error pages.
 */
final class Responder
{
    public function __construct(
        private readonly View $view,
        private readonly AuthServiceInterface $auth,
        private readonly LoggerInterface $log,
    ) {
    }

    /** Creates the template root for the current request, consuming any pending flash message. */
    public function newPage(Request $request, string $title, string $active = ''): PageData
    {
        $page = new PageData($title, $active);
        $principal = $request->attribute('principal');
        if ($principal instanceof Principal) {
            $page->user = $principal->user;
            $page->csrf = $principal->session->csrfToken;
            $page->flash = $this->auth->popFlash($principal->session);
        }
        return $page;
    }

    public function view(string $template, PageData $page, int $status = 200): Response
    {
        return Response::html($this->view->page($template, $page), $status);
    }

    /** Redirects to $destination, leaving a flash message to be shown there. */
    public function redirect(Request $request, string $destination, FlashKind $kind, string $message): Response
    {
        $principal = $request->attribute('principal');
        if ($principal instanceof Principal && $message !== '') {
            try {
                $this->auth->setFlash($principal->session, $kind, $message);
            } catch (Throwable $e) {
                $this->log->warning('salvar mensagem flash', ['err' => $e->getMessage()]);
            }
        }
        return Response::redirect($destination);
    }

    public function success(Request $request, string $destination, string $message): Response
    {
        return $this->redirect($request, $destination, FlashKind::Success, $message);
    }

    /**
     * Reports a failed action (toggle, delete...) back on the page the user came from. Failures
     * without a friendly message are rethrown for the global error handler.
     */
    public function actionError(Request $request, string $destination, Throwable $error): Response
    {
        $message = match (true) {
            $error instanceof ValidationException => $error->getMessage(),
            $error instanceof InUseException => 'Não é possível excluir: o registro está sendo usado por outros dados.',
            $error instanceof ConflictException => 'O registro foi alterado por outra pessoa. Atualize a página e tente novamente.',
            $error instanceof InvalidTransitionException => 'Esta mudança de status não é permitida.',
            default => throw $error,
        };
        return $this->redirect($request, $destination, FlashKind::Error, $message);
    }

    public function errorPage(Request $request, int $status, string $title, string $message): Response
    {
        $page = $this->newPage($request, $title);
        $page->data['Status'] = $status;
        $page->data['Message'] = $message;
        try {
            return $this->view('error', $page, $status);
        } catch (Throwable $e) {
            $this->log->error('renderizar página de erro', ['err' => $e->getMessage()]);
            return Response::text($title, $status);
        }
    }
}
