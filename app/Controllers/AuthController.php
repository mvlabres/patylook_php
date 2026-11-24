<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\AuthServiceInterface;
use Patylook\Contracts\DashboardServiceInterface;
use Patylook\Contracts\UserServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\InvalidCredentialsException;
use Patylook\Exceptions\TooManyAttemptsException;
use Patylook\Exceptions\UnauthenticatedException;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\Responder;
use Patylook\Http\SessionCookie;

/** Login, logout, the dashboard and the user's own profile. */
final class AuthController extends Controller
{
    public function __construct(
        Responder $responder,
        private readonly AuthServiceInterface $auth,
        private readonly UserServiceInterface $users,
        private readonly DashboardServiceInterface $dashboard,
        private readonly SessionCookie $cookie,
    ) {
        parent::__construct($responder);
    }

    public function loginForm(Request $request): Response
    {
        try {
            $this->auth->authenticate($request->cookie(SessionCookie::NAME));
            return Response::redirect('/');
        } catch (UnauthenticatedException) {
            // Not signed in: show the form.
        }
        $page = $this->responder->newPage($request, 'Entrar');
        $page->data['username'] = '';
        return $this->responder->view('login', $page);
    }

    public function loginSubmit(Request $request): Response
    {
        $username = (new Form($request))->str('username');
        try {
            $result = $this->auth->login($request->clientAddress(), $username, $request->post('password'));
        } catch (InvalidCredentialsException|TooManyAttemptsException $e) {
            $page = $this->responder->newPage($request, 'Entrar');
            $page->errors[''] = $e->getMessage();
            $page->data['username'] = $username;
            return $this->responder->view('login', $page, $e instanceof TooManyAttemptsException ? 429 : 401);
        }
        return $this->cookie->set(Response::redirect('/'), $result->token);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($this->principal($request)->token);
        return $this->cookie->clear(Response::redirect('/login'));
    }

    public function dashboard(Request $request): Response
    {
        $page = $this->responder->newPage($request, 'Painel', 'dashboard');
        $page->data['dashboard'] = $this->dashboard->load();
        return $this->responder->view('dashboard', $page);
    }

    public function profile(Request $request): Response
    {
        return $this->responder->view('profile', $this->responder->newPage($request, 'Meu perfil', 'profile'));
    }

    public function changePassword(Request $request): Response
    {
        $form = new Form($request);
        if ($request->post('new_password') !== $request->post('confirm_password')) {
            $form->errors->add('confirm_password', 'A confirmação não confere com a nova senha');
        }
        try {
            $form->throwIfInvalid();
            $this->users->changeOwnPassword(
                $this->user($request),
                $request->post('current_password'),
                $request->post('new_password'),
            );
        } catch (ValidationException $e) {
            $page = $this->responder->newPage($request, 'Meu perfil', 'profile');
            $page->errors = $e->fields();
            return $this->responder->view('profile', $page, 422);
        }
        // All sessions were revoked by the password change, including this one.
        return $this->cookie->clear(Response::redirect('/login'));
    }
}
