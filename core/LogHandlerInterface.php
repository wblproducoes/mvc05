<?php

namespace Core;

/**
 * Interface para handlers de log
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.5.0
 */
interface LogHandlerInterface
{
    /**
     * Processa entrada de log
     */
    public function handle(array $logEntry): void;
}