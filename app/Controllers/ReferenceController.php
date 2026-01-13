<?php

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\Gender;
use App\Models\Level;
use App\Models\Status;

/**
 * Controller para gerenciamento de tabelas de referência
 * 
 * @package App\Controllers
 * @author Sistema Administrativo
 * @version 1.1.0
 */
class ReferenceController extends Controller
{
    /**
     * Lista todas as tabelas de referência
     * 
     * @return void
     */
    public function index(): void
    {
        // Verificar permissão
        if (!Auth::can('references.view')) {
            $this->flash('error', 'Acesso negado');
            $this->redirect('/dashboard');
        }

        $genderModel = new Gender();
        $levelModel = new Level();
        $statusModel = new Status();

        $data = [
            'genders' => $genderModel->getActive(),
            'levels' => $levelModel->getActive(),
            'statuses' => $statusModel->getActive()
        ];

        $this->render('reference/index.twig', $data);
    }

    /**
     * Gerenciamento de gêneros
     * 
     * @return void
     */
    public function genders(): void
    {
        if (!Auth::can('references.manage')) {
            $this->flash('error', 'Acesso negado');
            $this->redirect('/dashboard');
        }

        $genderModel = new Gender();
        $genders = $genderModel->all();

        $this->render('reference/genders.twig', [
            'genders' => $genders
        ]);
    }

    /**
     * Criar/Editar gênero
     * 
     * @param int|null $id
     * @return void
     */
    public function genderForm(?int $id = null): void
    {
        if (!Auth::can('references.manage')) {
            $this->flash('error', 'Acesso negado');
            $this->redirect('/dashboard');
        }

        $genderModel = new Gender();
        $gender = $id ? $genderModel->find($id) : null;

        if ($this->isPost()) {
            $validation = $this->validate($_POST, [
                'name' => 'required|min:2|max:100',
                'translate' => 'required|min:2|max:100',
                'description' => 'max:255'
            ]);

            if (!$validation['valid']) {
                $this->render('reference/gender-form.twig', [
                    'errors' => $validation['errors'],
                    'gender' => array_merge($gender ?? [], $_POST)
                ]);
                return;
            }

            // Verificar se nome já existe
            if ($genderModel->nameExists($this->post('name'), $id)) {
                $this->flash('error', 'Nome já existe');
                $this->render('reference/gender-form.twig', [
                    'gender' => array_merge($gender ?? [], $_POST)
                ]);
                return;
            }

            try {
                $data = [
                    'name' => $this->post('name'),
                    'translate' => $this->post('translate'),
                    'description' => $this->post('description')
                ];

                if ($id) {
                    $genderModel->update($id, $data);
                    $this->flash('success', 'Gênero atualizado com sucesso!');
                } else {
                    $genderModel->create($data);
                    $this->flash('success', 'Gênero criado com sucesso!');
                }

                $this->redirect('/reference/genders');

            } catch (\Exception $e) {
                error_log($e->getMessage());
                $this->flash('error', 'Erro ao salvar gênero');
            }
        }

        $this->render('reference/gender-form.twig', [
            'gender' => $gender
        ]);
    }

    /**
     * Gerenciamento de níveis
     * 
     * @return void
     */
    public function levels(): void
    {
        if (!Auth::can('references.manage')) {
            $this->flash('error', 'Acesso negado');
            $this->redirect('/dashboard');
        }

        $levelModel = new Level();
        $levels = $levelModel->all();

        $this->render('reference/levels.twig', [
            'levels' => $levels
        ]);
    }

    /**
     * Criar/Editar nível
     * 
     * @param int|null $id
     * @return void
     */
    public function levelForm(?int $id = null): void
    {
        if (!Auth::can('references.manage')) {
            $this->flash('error', 'Acesso negado');
            $this->redirect('/dashboard');
        }

        $levelModel = new Level();
        $level = $id ? $levelModel->find($id) : null;

        if ($this->isPost()) {
            $validation = $this->validate($_POST, [
                'name' => 'required|min:2|max:100',
                'translate' => 'required|min:2|max:100',
                'description' => 'max:255'
            ]);

            if (!$validation['valid']) {
                $this->render('reference/level-form.twig', [
                    'errors' => $validation['errors'],
                    'level' => array_merge($level ?? [], $_POST)
                ]);
                return;
            }

            // Verificar se nome já existe
            if ($levelModel->nameExists($this->post('name'), $id)) {
                $this->flash('error', 'Nome já existe');
                $this->render('reference/level-form.twig', [
                    'level' => array_merge($level ?? [], $_POST)
                ]);
                return;
            }

            try {
                $data = [
                    'name' => $this->post('name'),
                    'translate' => $this->post('translate'),
                    'description' => $this->post('description')
                ];

                if ($id) {
                    $levelModel->update($id, $data);
                    $this->flash('success', 'Nível atualizado com sucesso!');
                } else {
                    $levelModel->create($data);
                    $this->flash('success', 'Nível criado com sucesso!');
                }

                $this->redirect('/reference/levels');

            } catch (\Exception $e) {
                error_log($e->getMessage());
                $this->flash('error', 'Erro ao salvar nível');
            }
        }

        $this->render('reference/level-form.twig', [
            'level' => $level
        ]);
    }

    /**
     * Gerenciamento de status
     * 
     * @return void
     */
    public function statuses(): void
    {
        if (!Auth::can('references.manage')) {
            $this->flash('error', 'Acesso negado');
            $this->redirect('/dashboard');
        }

        $statusModel = new Status();
        $statuses = $statusModel->all();

        $this->render('reference/statuses.twig', [
            'statuses' => $statuses
        ]);
    }

    /**
     * Criar/Editar status
     * 
     * @param int|null $id
     * @return void
     */
    public function statusForm(?int $id = null): void
    {
        if (!Auth::can('references.manage')) {
            $this->flash('error', 'Acesso negado');
            $this->redirect('/dashboard');
        }

        $statusModel = new Status();
        $status = $id ? $statusModel->find($id) : null;

        if ($this->isPost()) {
            $validation = $this->validate($_POST, [
                'name' => 'required|min:2|max:100',
                'translate' => 'required|min:2|max:100',
                'color' => 'required|in:primary,secondary,success,danger,warning,info,light,dark',
                'description' => 'max:255'
            ]);

            if (!$validation['valid']) {
                $this->render('reference/status-form.twig', [
                    'errors' => $validation['errors'],
                    'status' => array_merge($status ?? [], $_POST),
                    'colors' => $this->getBootstrapColors()
                ]);
                return;
            }

            // Verificar se nome já existe
            if ($statusModel->nameExists($this->post('name'), $id)) {
                $this->flash('error', 'Nome já existe');
                $this->render('reference/status-form.twig', [
                    'status' => array_merge($status ?? [], $_POST),
                    'colors' => $this->getBootstrapColors()
                ]);
                return;
            }

            try {
                $data = [
                    'name' => $this->post('name'),
                    'translate' => $this->post('translate'),
                    'color' => $this->post('color'),
                    'description' => $this->post('description')
                ];

                if ($id) {
                    $statusModel->update($id, $data);
                    $this->flash('success', 'Status atualizado com sucesso!');
                } else {
                    $statusModel->create($data);
                    $this->flash('success', 'Status criado com sucesso!');
                }

                $this->redirect('/reference/statuses');

            } catch (\Exception $e) {
                error_log($e->getMessage());
                $this->flash('error', 'Erro ao salvar status');
            }
        }

        $this->render('reference/status-form.twig', [
            'status' => $status,
            'colors' => $this->getBootstrapColors()
        ]);
    }

    /**
     * Soft delete de registro
     * 
     * @param string $type
     * @param int $id
     * @return void
     */
    public function delete(string $type, int $id): void
    {
        if (!$this->isPost() || !Auth::can('references.manage')) {
            $this->redirect('/reference');
        }

        try {
            switch ($type) {
                case 'gender':
                    $model = new Gender();
                    break;
                case 'level':
                    $model = new Level();
                    break;
                case 'status':
                    $model = new Status();
                    break;
                default:
                    throw new \Exception('Tipo inválido');
            }

            $model->softDelete($id);
            $this->flash('success', ucfirst($type) . ' excluído com sucesso!');

        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->flash('error', 'Erro ao excluir registro');
        }

        $this->redirect("/reference/{$type}s");
    }

    /**
     * Restaurar registro
     * 
     * @param string $type
     * @param int $id
     * @return void
     */
    public function restore(string $type, int $id): void
    {
        if (!$this->isPost() || !Auth::can('references.manage')) {
            $this->redirect('/reference');
        }

        try {
            switch ($type) {
                case 'gender':
                    $model = new Gender();
                    break;
                case 'level':
                    $model = new Level();
                    break;
                case 'status':
                    $model = new Status();
                    break;
                default:
                    throw new \Exception('Tipo inválido');
            }

            $model->restore($id);
            $this->flash('success', ucfirst($type) . ' restaurado com sucesso!');

        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->flash('error', 'Erro ao restaurar registro');
        }

        $this->redirect("/reference/{$type}s");
    }

    /**
     * Obtém cores do Bootstrap
     * 
     * @return array
     */
    private function getBootstrapColors(): array
    {
        return [
            'primary' => 'Primário (Azul)',
            'secondary' => 'Secundário (Cinza)',
            'success' => 'Sucesso (Verde)',
            'danger' => 'Perigo (Vermelho)',
            'warning' => 'Aviso (Amarelo)',
            'info' => 'Informação (Ciano)',
            'light' => 'Claro',
            'dark' => 'Escuro'
        ];
    }
}