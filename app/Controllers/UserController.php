<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\UserServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\Pager;
use Patylook\Http\Responder;
use Patylook\Models\FlashKind;
use Patylook\Models\User;
use Throwable;

/** User administration (administrators only). */
final class UserController extends Controller
{
    public function __construct(Responder $responder, private readonly UserServiceInterface $users)
    {
        parent::__construct($responder);
    }

    public function index(Request $request): Response
    {
        $q = $request->query('q');
        $result = $this->users->list($this->user($request), $this->pageNumber($request), $q);
        $page = $this->responder->newPage($request, 'Usuários', 'users');
        $page->data['users'] = $result->items;
        $page->data['query'] = $q;
        $page->data['pager'] = Pager::for($request, $result);
        return $this->responder->view('user_list', $page);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, 200, new User(isActive: true));
    }

    public function edit(Request $request): Response
    {
        return $this->form($request, 200, $this->users->get($this->user($request), $this->routeId($request)));
    }

    public function store(Request $request): Response
    {
        $form = new Form($request);
        $user = $this->fromForm($form, 0);
        $password = $request->post('password');
        if ($password !== $request->post('confirm_password')) {
            $form->errors->add('confirm_password', 'A confirmação não confere com a senha');
        }
        try {
            $form->throwIfInvalid();
            $this->users->create($this->user($request), $user, $password);
        } catch (ValidationException $e) {
            return $this->form($request, 422, $user, $e->fields());
        }
        return $this->responder->success($request, '/users', 'Usuário cadastrado.');
    }

    public function update(Request $request): Response
    {
        $id = $this->routeId($request);
        $user = $this->fromForm(new Form($request), $id);
        try {
            $this->users->update($this->user($request), $user);
        } catch (ValidationException $e) {
            return $this->form($request, 422, $user, $e->fields());
        }
        return $this->responder->success($request, '/users', 'Usuário atualizado.');
    }

    public function setActive(Request $request): Response
    {
        $id = $this->routeId($request);
        $active = $request->post('active') === '1';
        try {
            $this->users->setActive($this->user($request), $id, $active);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, '/users', $e);
        }
        return $this->responder->success($request, '/users', $active ? 'Usuário ativado.' : 'Usuário desativado.');
    }

    public function resetPassword(Request $request): Response
    {
        $id = $this->routeId($request);
        $destination = '/users/' . $id . '/edit';
        $password = $request->post('password');
        if ($password !== $request->post('confirm_password')) {
            return $this->responder->redirect($request, $destination, FlashKind::Error, 'A confirmação não confere com a nova senha.');
        }
        try {
            $this->users->resetPassword($this->user($request), $id, $password);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, $destination, $e);
        }
        return $this->responder->success($request, '/users', 'Senha redefinida. O usuário precisará entrar novamente.');
    }

    private function fromForm(Form $form, int $id): User
    {
        return new User(
            id: $id,
            username: $form->str('username'),
            email: $form->str('email'),
            name: $form->str('name'),
            categoryId: $form->integer('user_category_id'),
        );
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, int $status, User $edited, array $errors = []): Response
    {
        $page = $this->responder->newPage($request, $edited->id !== 0 ? 'Editar usuário' : 'Novo usuário', 'users');
        $page->errors = $errors;
        $page->data['edited'] = $edited;
        $page->data['categories'] = $this->users->categories();
        return $this->responder->view('user_form', $page, $status);
    }
}
