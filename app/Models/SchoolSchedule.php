<?php

namespace App\Models;

use Core\Model;

/**
 * Model de horários escolares
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.4.0
 */
class SchoolSchedule extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'school_schedules';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'team_id',
        'day_of_week',
        'class_number',
        'teacher_id',
        'subject_id',
        'start_time',
        'end_time'
    ];

    /**
     * @var array Campos de data que devem ser convertidos
     */
    protected array $dates = ['dh', 'dh_update', 'deleted_at'];

    /**
     * @var array Dias da semana
     */
    public const DAYS_OF_WEEK = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo'
    ];

    /**
     * Busca horários ativos
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT sch.*, 
                       u.name as teacher_name,
                       ss.name as subject_name, ss.translate as subject_translate
                FROM {$this->table} sch
                LEFT JOIN users u ON sch.teacher_id = u.id
                LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                WHERE sch.deleted_at IS NULL
                ORDER BY sch.day_of_week, sch.class_number";
        
        return $this->database->select($sql);
    }

    /**
     * Busca horário com relacionamentos
     * 
     * @param int $id
     * @return array|null
     */
    public function findWithRelations(int $id): ?array
    {
        $sql = "SELECT sch.*, 
                       u.name as teacher_name, u.email as teacher_email,
                       ss.name as subject_name, ss.translate as subject_translate
                FROM {$this->table} sch
                LEFT JOIN users u ON sch.teacher_id = u.id
                LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                WHERE sch.id = :id AND sch.deleted_at IS NULL";
        
        return $this->database->selectOne($sql, ['id' => $id]);
    }

    /**
     * Busca horários por turma
     * 
     * @param int $teamId
     * @return array
     */
    public function getByTeam(int $teamId): array
    {
        $sql = "SELECT sch.*, 
                       u.name as teacher_name,
                       ss.name as subject_name, ss.translate as subject_translate
                FROM {$this->table} sch
                LEFT JOIN users u ON sch.teacher_id = u.id
                LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                WHERE sch.team_id = :team_id AND sch.deleted_at IS NULL
                ORDER BY sch.day_of_week, sch.class_number";
        
        return $this->database->select($sql, ['team_id' => $teamId]);
    }

    /**
     * Busca horários por professor
     * 
     * @param int $teacherId
     * @return array
     */
    public function getByTeacher(int $teacherId): array
    {
        $sql = "SELECT sch.*, 
                       ss.name as subject_name, ss.translate as subject_translate
                FROM {$this->table} sch
                LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                WHERE sch.teacher_id = :teacher_id AND sch.deleted_at IS NULL
                ORDER BY sch.day_of_week, sch.class_number";
        
        return $this->database->select($sql, ['teacher_id' => $teacherId]);
    }

    /**
     * Busca horários por matéria
     * 
     * @param int $subjectId
     * @return array
     */
    public function getBySubject(int $subjectId): array
    {
        $sql = "SELECT sch.*, 
                       u.name as teacher_name
                FROM {$this->table} sch
                LEFT JOIN users u ON sch.teacher_id = u.id
                WHERE sch.subject_id = :subject_id AND sch.deleted_at IS NULL
                ORDER BY sch.day_of_week, sch.class_number";
        
        return $this->database->select($sql, ['subject_id' => $subjectId]);
    }

    /**
     * Busca horários por dia da semana
     * 
     * @param int $dayOfWeek
     * @return array
     */
    public function getByDayOfWeek(int $dayOfWeek): array
    {
        $sql = "SELECT sch.*, 
                       u.name as teacher_name,
                       ss.name as subject_name, ss.translate as subject_translate
                FROM {$this->table} sch
                LEFT JOIN users u ON sch.teacher_id = u.id
                LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                WHERE sch.day_of_week = :day_of_week AND sch.deleted_at IS NULL
                ORDER BY sch.class_number";
        
        return $this->database->select($sql, ['day_of_week' => $dayOfWeek]);
    }

    /**
     * Obtém grade de horários formatada por turma
     * 
     * @param int $teamId
     * @return array
     */
    public function getScheduleGrid(int $teamId): array
    {
        $schedules = $this->getByTeam($teamId);
        $grid = [];
        
        // Inicializar grid
        for ($day = 1; $day <= 7; $day++) {
            $grid[$day] = [];
        }
        
        // Preencher grid
        foreach ($schedules as $schedule) {
            $grid[$schedule['day_of_week']][$schedule['class_number']] = $schedule;
        }
        
        return $grid;
    }

    /**
     * Verifica conflito de horário para professor
     * 
     * @param int $teacherId
     * @param int $dayOfWeek
     * @param int $classNumber
     * @param int|null $excludeId
     * @return bool
     */
    public function hasTeacherConflict(int $teacherId, int $dayOfWeek, int $classNumber, ?int $excludeId = null): bool
    {
        $conditions = [
            'teacher_id = :teacher_id',
            'day_of_week = :day_of_week',
            'class_number = :class_number',
            'deleted_at IS NULL'
        ];
        
        $params = [
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'class_number' => $classNumber
        ];
        
        if ($excludeId) {
            $conditions[] = 'id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE " . implode(' AND ', $conditions);
        
        $result = $this->database->selectOne($sql, $params);
        return $result['count'] > 0;
    }

    /**
     * Verifica conflito de horário para turma
     * 
     * @param int $teamId
     * @param int $dayOfWeek
     * @param int $classNumber
     * @param int|null $excludeId
     * @return bool
     */
    public function hasTeamConflict(int $teamId, int $dayOfWeek, int $classNumber, ?int $excludeId = null): bool
    {
        $conditions = [
            'team_id = :team_id',
            'day_of_week = :day_of_week',
            'class_number = :class_number',
            'deleted_at IS NULL'
        ];
        
        $params = [
            'team_id' => $teamId,
            'day_of_week' => $dayOfWeek,
            'class_number' => $classNumber
        ];
        
        if ($excludeId) {
            $conditions[] = 'id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE " . implode(' AND ', $conditions);
        
        $result = $this->database->selectOne($sql, $params);
        return $result['count'] > 0;
    }

    /**
     * Obtém estatísticas de horários
     * 
     * @return array
     */
    public function getStats(): array
    {
        // Total de horários
        $total = $this->count(['deleted_at' => null]);
        
        // Por dia da semana
        $dayStats = [];
        for ($day = 1; $day <= 7; $day++) {
            $count = $this->count(['day_of_week' => $day, 'deleted_at' => null]);
            $dayStats[] = [
                'day' => $day,
                'day_name' => self::DAYS_OF_WEEK[$day],
                'count' => $count
            ];
        }
        
        // Por professor
        $teacherStats = $this->database->select("
            SELECT u.name as teacher_name, COUNT(sch.id) as count
            FROM users u
            LEFT JOIN school_schedules sch ON u.id = sch.teacher_id AND sch.deleted_at IS NULL
            WHERE u.deleted_at IS NULL
            GROUP BY u.id, u.name
            HAVING count > 0
            ORDER BY count DESC
            LIMIT 10
        ");
        
        // Por matéria
        $subjectStats = $this->database->select("
            SELECT ss.translate as subject_name, COUNT(sch.id) as count
            FROM school_subjects ss
            LEFT JOIN school_schedules sch ON ss.id = sch.subject_id AND sch.deleted_at IS NULL
            WHERE ss.deleted_at IS NULL
            GROUP BY ss.id, ss.translate
            HAVING count > 0
            ORDER BY count DESC
            LIMIT 10
        ");

        return [
            'total' => $total,
            'by_day' => $dayStats,
            'by_teacher' => $teacherStats,
            'by_subject' => $subjectStats
        ];
    }

    /**
     * Busca horários com paginação e filtros
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function paginateWithFilters(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $conditions = ['sch.deleted_at IS NULL'];
        $params = [];
        
        // Filtro por turma
        if (!empty($filters['team_id'])) {
            $conditions[] = "sch.team_id = :team_id";
            $params['team_id'] = $filters['team_id'];
        }
        
        // Filtro por professor
        if (!empty($filters['teacher_id'])) {
            $conditions[] = "sch.teacher_id = :teacher_id";
            $params['teacher_id'] = $filters['teacher_id'];
        }
        
        // Filtro por matéria
        if (!empty($filters['subject_id'])) {
            $conditions[] = "sch.subject_id = :subject_id";
            $params['subject_id'] = $filters['subject_id'];
        }
        
        // Filtro por dia da semana
        if (!empty($filters['day_of_week'])) {
            $conditions[] = "sch.day_of_week = :day_of_week";
            $params['day_of_week'] = $filters['day_of_week'];
        }

        $offset = ($page - 1) * $perPage;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        
        // Buscar dados
        $sql = "SELECT sch.id, sch.team_id, sch.day_of_week, sch.class_number, 
                       sch.start_time, sch.end_time, sch.dh,
                       u.name as teacher_name,
                       ss.name as subject_name, ss.translate as subject_translate
                FROM {$this->table} sch
                LEFT JOIN users u ON sch.teacher_id = u.id
                LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                {$whereClause} 
                ORDER BY sch.day_of_week, sch.class_number 
                LIMIT {$perPage} OFFSET {$offset}";
        
        $data = $this->database->select($sql, $params);
        
        // Adicionar nome do dia da semana
        foreach ($data as &$item) {
            $item['day_name'] = self::DAYS_OF_WEEK[$item['day_of_week']] ?? 'Desconhecido';
        }
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} sch
                     LEFT JOIN users u ON sch.teacher_id = u.id
                     LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                     {$whereClause}";
        
        $totalResult = $this->database->selectOne($countSql, $params);
        $total = $totalResult['total'];
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Soft delete
     * 
     * @param int $id
     * @return int
     */
    public function softDelete(int $id): int
    {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Restaurar horário
     * 
     * @param int $id
     * @return int
     */
    public function restore(int $id): int
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Busca horários deletados
     * 
     * @return array
     */
    public function getDeleted(): array
    {
        $sql = "SELECT sch.*, 
                       u.name as teacher_name,
                       ss.translate as subject_name
                FROM {$this->table} sch
                LEFT JOIN users u ON sch.teacher_id = u.id
                LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                WHERE sch.deleted_at IS NOT NULL
                ORDER BY sch.deleted_at DESC";
        
        return $this->database->select($sql);
    }

    /**
     * Obtém nome do dia da semana
     * 
     * @param int $dayOfWeek
     * @return string
     */
    public static function getDayName(int $dayOfWeek): string
    {
        return self::DAYS_OF_WEEK[$dayOfWeek] ?? 'Desconhecido';
    }

    /**
     * Obtém todos os dias da semana
     * 
     * @return array
     */
    public static function getAllDays(): array
    {
        return self::DAYS_OF_WEEK;
    }

    /**
     * Formata horário
     * 
     * @param string|null $time
     * @return string
     */
    public static function formatTime(?string $time): string
    {
        if (!$time) {
            return '';
        }
        
        return date('H:i', strtotime($time));
    }

    /**
     * Cria um novo horário
     * 
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        // Validar conflitos antes de criar
        if (isset($data['teacher_id'], $data['day_of_week'], $data['class_number'])) {
            if ($this->hasTeacherConflict($data['teacher_id'], $data['day_of_week'], $data['class_number'])) {
                throw new \Exception('Professor já possui aula neste horário');
            }
        }
        
        if (isset($data['team_id'], $data['day_of_week'], $data['class_number'])) {
            if ($this->hasTeamConflict($data['team_id'], $data['day_of_week'], $data['class_number'])) {
                throw new \Exception('Turma já possui aula neste horário');
            }
        }

        return parent::create($data);
    }

    /**
     * Atualiza um horário
     * 
     * @param int $id
     * @param array $data
     * @return int
     */
    public function update(int $id, array $data): int
    {
        // Validar conflitos antes de atualizar
        if (isset($data['teacher_id'], $data['day_of_week'], $data['class_number'])) {
            if ($this->hasTeacherConflict($data['teacher_id'], $data['day_of_week'], $data['class_number'], $id)) {
                throw new \Exception('Professor já possui aula neste horário');
            }
        }
        
        if (isset($data['team_id'], $data['day_of_week'], $data['class_number'])) {
            if ($this->hasTeamConflict($data['team_id'], $data['day_of_week'], $data['class_number'], $id)) {
                throw new \Exception('Turma já possui aula neste horário');
            }
        }

        return parent::update($id, $data);
    }
}