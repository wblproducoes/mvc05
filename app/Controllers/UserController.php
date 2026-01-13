<?php

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Security;
use App\Models\User;

/**
 * Controller de usuários
 * 
 * @package App\Controllers
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class UserController extends Controller
{
    /**
     * @var User Model de usuário
     */
    private User $userModel;

    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    /**
     * Lista usuários
     * 
     * @return void
     */
    public function index(): void
    {
        $page = (int) ($this->get('page') ?? 1);
        $perPage = 15;
        
        // Filtros
        $filters = [
            'name' => $this->get('name'),
            'email' => $this->get('email'),
            'role' => $this->get('role'),
            'active' => $this->get('active')
        ];
        
        // Remover filtros vazios
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        
        $users = $this->userModel->paginateWithFilters($page, $perPage, $filters);
        
        $this->render('users/index.twig', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->getRoles()
        ]);
    }

    /**
     * Exibe formulário de criação
     * 
     * @return void
     */
    public function create(): void
    {
        $this->render('users/create.twig', [
            'roles' => $this->getRoles()
        ]);
    }

    /**
     * Armazena novo usuário
     * 
     * @return void
     */
    public function store(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/users');
        }

        // Validar dados
        $validation = $this->validate($_POST, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'password_confirmation' => 'required|confirmed',
            'role' => 'required|in:admin,user',
            'phone' => 'max:20'
        ]);

        if (!$validation['valid']) {
            $this->render('users/create.twig', [
                'errors' => $validation['errors'],
                'old' => $_POST,
                'roles' => $this->getRoles()
            ]);
            return;
        }

        // Validar força da senha
        $passwordValidation = Security::validatePasswordStrength($this->post('password'));
        if (!$passwordValidation['valid']) {
            $this->render('users/create.twig', [
                'errors' => ['password' => $passwordValidation['errors']],
                'old' => $_POST,
                'roles' => $this->getRoles()
            ]);
            return;
        }

        try {
            $userId = $this->userModel->create([
                'name' => Security::sanitizeInput($this->post('name')),
                'email' => strtolower(trim($this->post('email'))),
                'password' => $this->post('password'),
                'role' => $this->post('role'),
                'phone' => Security::sanitizeInput($this->post('phone')),
                'active' => $this->post('active') === 'on'
            ]);

            $this->flash('success', 'Usuário criado com sucesso!');
            $this->redirect('/users');
            
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->flash('error', 'Erro ao criar usuário');
            
            $this->render('users/create.twig', [
                'old' => $_POST,
                'roles' => $this->getRoles()
            ]);
        }
    }

    /**
     * Exibe formulário de edição
     * 
     * @param int $id
     * @return void
     */
    public function edit(int $id): void
    {
        $user = $this->userModel->find($id);
        
        if (!$user) {
            $this->flash('error', 'Usuário não encontrado');
            $this->redirect('/users');
        }

        $this->render('users/edit.twig', [
            'user' => $user,
            'roles' => $this->getRoles()
        ]);
    }

    /**
     * Atualiza usuário
     * 
     * @param int $id
     * @return void
     */
    public function update(int $id): void
    {
        if (!$this->isPost()) {
            $this->redirect('/users');
        }

        $user = $this->userModel->find($id);
        
        if (!$user) {
            $this->flash('error', 'Usuário não encontrado');
            $this->redirect('/users');
        }

        // Validar dados
        $rules = [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'role' => 'required|in:admin,user',
            'phone' => 'max:20'
        ];

        // Validar senha apenas se fornecida
        if (!empty($this->post('password'))) {
            $rules['password'] = 'min:8';
            $rules['password_confirmation'] = 'confirmed';
        }

        $validation = $this->validate($_POST, $rules);

        // Verificar se email já existe (excluindo o usuário atual)
        if ($this->userModel->emailExists($this->post('email'), $id)) {
            $validation['valid'] = false;
            $validation['errors']['email'] = ['Este email já está em uso'];
        }

        if (!$validation['valid']) {
            $this->render('users/edit.twig', [
                'errors' => $validation['errors'],
                'user' => array_merge($user, $_POST),
                'roles' => $this->getRoles()
            ]);
            return;
        }

        // Validar força da senha se fornecida
        if (!empty($this->post('password'))) {
            $passwordValidation = Security::validatePasswordStrength($this->post('password'));
            if (!$passwordValidation['valid']) {
                $this->render('users/edit.twig', [
                    'errors' => ['password' => $passwordValidation['errors']],
                    'user' => array_merge($user, $_POST),
                    'roles' => $this->getRoles()
                ]);
                return;
            }
        }

        try {
            $updateData = [
                'name' => Security::sanitizeInput($this->post('name')),
                'email' => strtolower(trim($this->post('email'))),
                'role' => $this->post('role'),
                'phone' => Security::sanitizeInput($this->post('phone')),
                'active' => $this->post('active') === 'on'
            ];

            // Adicionar senha se fornecida
            if (!empty($this->post('password'))) {
                $updateData['password'] = $this->post('password');
            }

            $this->userModel->update($id, $updateData);

            $this->flash('success', 'Usuário atualizado com sucesso!');
            $this->redirect('/users');
            
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->flash('error', 'Erro ao atualizar usuário');
            
            $this->render('users/edit.twig', [
                'user' => array_merge($user, $_POST),
                'roles' => $this->getRoles()
            ]);
        }
    }

    /**
     * Exclui usuário
     * 
     * @param int $id
     * @return void
     */
    public function delete(int $id): void
    {
        if (!$this->isPost()) {
            $this->redirect('/users');
        }

        // Não permitir exclusão do próprio usuário
        if (Auth::id() === $id) {
            $this->flash('error', 'Você não pode excluir sua própria conta');
            $this->redirect('/users');
        }

        $user = $this->userModel->find($id);
        
        if (!$user) {
            $this->flash('error', 'Usuário não encontrado');
            $this->redirect('/users');
        }

        try {
            $this->userModel->delete($id);
            $this->flash('success', 'Usuário excluído com sucesso!');
            
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->flash('error', 'Erro ao excluir usuário');
        }

        $this->redirect('/users');
    }

    /**
     * Ativa/desativa usuário
     * 
     * @param int $id
     * @return void
     */
    public function toggleActive(int $id): void
    {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'message' => 'Método não permitido'], 405);
        }

        // Não permitir desativar o próprio usuário
        if (Auth::id() === $id) {
            $this->json(['success' => false, 'message' => 'Você não pode desativar sua própria conta']);
        }

        $user = $this->userModel->find($id);
        
        if (!$user) {
            $this->json(['success' => false, 'message' => 'Usuário não encontrado']);
        }

        try {
            $newStatus = !$user['active'];
            $this->userModel->toggleActive($id, $newStatus);
            
            $message = $newStatus ? 'Usuário ativado com sucesso' : 'Usuário desativado com sucesso';
            
            $this->json([
                'success' => true,
                'message' => $message,
                'active' => $newStatus
            ]);
            
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->json(['success' => false, 'message' => 'Erro ao alterar status do usuário']);
        }
    }

    /**
     * Exibe perfil do usuário
     * 
     * @param int $id
     * @return void
     */
    public function show(int $id): void
    {
        $user = $this->userModel->find($id);
        
        if (!$user) {
            $this->flash('error', 'Usuário não encontrado');
            $this->redirect('/users');
        }

        // Remover dados sensíveis
        $user = $this->userModel->sanitizeUser($user);

        $this->render('users/show.twig', [
            'user' => $user
        ]);
    }

    /**
     * Obtém lista de papéis disponíveis
     * 
     * @return array
     */
    private function getRoles(): array
    {
        return [
            'admin' => 'Administrador',
            'user' => 'Usuário'
        ];
    }

    /**
     * API para buscar usuários
     * 
     * @return void
     */
    public function apiSearch(): void
    {
        $query = $this->get('q', '');
        
        if (strlen($query) < 2) {
            $this->json(['success' => false, 'message' => 'Query muito curta']);
        }

        $sql = "SELECT id, name, email, role 
                FROM users 
                WHERE (name LIKE :query OR email LIKE :query) 
                AND active = 1 
                LIMIT 10";
        
        $users = $this->database->select($sql, ['query' => "%{$query}%"]);
        
        $this->json([
            'success' => true,
            'data' => $users
        ]);
    }
}