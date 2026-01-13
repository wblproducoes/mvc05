<?php

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Security;

/**
 * Controller de autenticação
 * 
 * @package App\Controllers
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class AuthController extends Controller
{
    /**
     * Exibe página de login
     * 
     * @return void
     */
    public function showLogin(): void
    {
        // Redirecionar se já estiver logado
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/login.twig');
    }

    /**
     * Processa login
     * 
     * @return void
     */
    public function login(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/login');
        }

        // Validar dados
        $validation = $this->validate($_POST, [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        if (!$validation['valid']) {
            $this->flash('error', 'Dados inválidos');
            $this->render('auth/login.twig', [
                'errors' => $validation['errors'],
                'old' => $_POST
            ]);
            return;
        }

        $email = $this->post('email');
        $password = $this->post('password');
        $remember = $this->post('remember') === 'on';

        // Verificar rate limiting
        if (!Security::rateLimitCheck($email)) {
            $this->flash('error', 'Muitas tentativas de login. Tente novamente em 15 minutos.');
            $this->render('auth/login.twig');
            return;
        }

        // Tentar fazer login
        if (Auth::attempt($email, $password, $remember)) {
            // Limpar tentativas de login
            Security::clearLoginAttempts($email);
            
            $this->flash('success', 'Login realizado com sucesso!');
            $this->redirect('/dashboard');
        } else {
            // Registrar tentativa de login
            Security::recordLoginAttempt($email);
            
            $this->flash('error', 'Email ou senha incorretos');
            $this->render('auth/login.twig', [
                'old' => ['email' => $email]
            ]);
        }
    }

    /**
     * Processa logout
     * 
     * @return void
     */
    public function logout(): void
    {
        Auth::logout();
        $this->flash('success', 'Logout realizado com sucesso!');
        $this->redirect('/login');
    }

    /**
     * Exibe página de recuperação de senha
     * 
     * @return void
     */
    public function showForgotPassword(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/forgot-password.twig');
    }

    /**
     * Processa recuperação de senha
     * 
     * @return void
     */
    public function forgotPassword(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/forgot-password');
        }

        // Validar email
        $validation = $this->validate($_POST, [
            'email' => 'required|email'
        ]);

        if (!$validation['valid']) {
            $this->render('auth/forgot-password.twig', [
                'errors' => $validation['errors'],
                'old' => $_POST
            ]);
            return;
        }

        $email = $this->post('email');

        // Verificar se usuário existe
        $userModel = new \App\Models\User();
        $user = $userModel->findByEmail($email);

        if (!$user) {
            $this->flash('error', 'Email não encontrado');
            $this->render('auth/forgot-password.twig', [
                'old' => $_POST
            ]);
            return;
        }

        // Gerar token de recuperação
        $token = Security::generateRandomString(64);
        $hashedToken = hash('sha256', $token);
        
        // Salvar token no banco (implementar tabela password_resets)
        // Por enquanto, apenas simular
        
        // Enviar email (implementar)
        // $this->sendPasswordResetEmail($user, $token);

        $this->flash('success', 'Instruções de recuperação enviadas para seu email');
        $this->redirect('/login');
    }

    /**
     * Exibe página de redefinição de senha
     * 
     * @param string $token
     * @return void
     */
    public function showResetPassword(string $token): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }

        // Verificar se token é válido (implementar)
        
        $this->render('auth/reset-password.twig', [
            'token' => $token
        ]);
    }

    /**
     * Processa redefinição de senha
     * 
     * @return void
     */
    public function resetPassword(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/login');
        }

        // Validar dados
        $validation = $this->validate($_POST, [
            'token' => 'required',
            'password' => 'required|min:8',
            'password_confirmation' => 'required|confirmed'
        ]);

        if (!$validation['valid']) {
            $this->render('auth/reset-password.twig', [
                'errors' => $validation['errors'],
                'token' => $this->post('token')
            ]);
            return;
        }

        // Verificar token e atualizar senha (implementar)
        
        $this->flash('success', 'Senha redefinida com sucesso!');
        $this->redirect('/login');
    }

    /**
     * Verifica status de autenticação (API)
     * 
     * @return void
     */
    public function checkAuth(): void
    {
        $this->json([
            'authenticated' => Auth::check(),
            'user' => Auth::user()
        ]);
    }
}