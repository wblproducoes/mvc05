#!/usr/bin/env php
<?php
/**
 * Script para gerenciamento de versões
 * 
 * Uso:
 * php scripts/version.php current          # Mostra versão atual
 * php scripts/version.php bump patch       # Incrementa patch (1.0.0 -> 1.0.1)
 * php scripts/version.php bump minor       # Incrementa minor (1.0.0 -> 1.1.0)
 * php scripts/version.php bump major       # Incrementa major (1.0.0 -> 2.0.0)
 * php scripts/version.php set 1.2.3        # Define versão específica
 * 
 * @author Sistema Administrativo
 * @version 1.0.0
 */

class VersionManager
{
    private string $versionFile;
    private string $composerFile;
    private string $changelogFile;
    
    public function __construct()
    {
        $this->versionFile = __DIR__ . '/../VERSION';
        $this->composerFile = __DIR__ . '/../composer.json';
        $this->changelogFile = __DIR__ . '/../CHANGELOG.md';
    }
    
    /**
     * Obtém a versão atual
     */
    public function getCurrentVersion(): string
    {
        if (!file_exists($this->versionFile)) {
            return '0.0.0';
        }
        
        return trim(file_get_contents($this->versionFile));
    }
    
    /**
     * Define uma nova versão
     */
    public function setVersion(string $version): void
    {
        if (!$this->isValidVersion($version)) {
            throw new InvalidArgumentException("Versão inválida: {$version}");
        }
        
        // Atualizar arquivo VERSION
        file_put_contents($this->versionFile, $version);
        
        // Atualizar composer.json
        $this->updateComposerVersion($version);
        
        echo "✅ Versão atualizada para: {$version}\n";
        echo "📝 Não esqueça de atualizar o CHANGELOG.md\n";
        echo "🏷️  Para criar uma tag: git tag v{$version}\n";
    }
    
    /**
     * Incrementa a versão
     */
    public function bumpVersion(string $type): void
    {
        $currentVersion = $this->getCurrentVersion();
        $newVersion = $this->calculateNewVersion($currentVersion, $type);
        
        $this->setVersion($newVersion);
    }
    
    /**
     * Calcula nova versão baseada no tipo de incremento
     */
    private function calculateNewVersion(string $current, string $type): string
    {
        $parts = explode('.', $current);
        $major = (int) ($parts[0] ?? 0);
        $minor = (int) ($parts[1] ?? 0);
        $patch = (int) ($parts[2] ?? 0);
        
        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
                
            case 'minor':
                $minor++;
                $patch = 0;
                break;
                
            case 'patch':
                $patch++;
                break;
                
            default:
                throw new InvalidArgumentException("Tipo inválido: {$type}. Use: major, minor, patch");
        }
        
        return "{$major}.{$minor}.{$patch}";
    }
    
    /**
     * Valida formato da versão (semver)
     */
    private function isValidVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    }
    
    /**
     * Atualiza versão no composer.json
     */
    private function updateComposerVersion(string $version): void
    {
        if (!file_exists($this->composerFile)) {
            return;
        }
        
        $composer = json_decode(file_get_contents($this->composerFile), true);
        $composer['version'] = $version;
        
        file_put_contents(
            $this->composerFile,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }
    
    /**
     * Mostra informações da versão atual
     */
    public function showInfo(): void
    {
        $version = $this->getCurrentVersion();
        $date = date('Y-m-d H:i:s');
        
        echo "📦 Sistema Administrativo MVC\n";
        echo "🏷️  Versão atual: {$version}\n";
        echo "📅 Data: {$date}\n";
        echo "📁 Arquivo: {$this->versionFile}\n";
        
        // Verificar se há mudanças não commitadas
        if ($this->hasUncommittedChanges()) {
            echo "⚠️  Há mudanças não commitadas no repositório\n";
        }
        
        // Mostrar últimas tags
        $this->showRecentTags();
    }
    
    /**
     * Verifica se há mudanças não commitadas
     */
    private function hasUncommittedChanges(): bool
    {
        $output = shell_exec('git status --porcelain 2>/dev/null');
        return !empty(trim($output ?? ''));
    }
    
    /**
     * Mostra tags recentes
     */
    private function showRecentTags(): void
    {
        $output = shell_exec('git tag --sort=-version:refname -l "v*" 2>/dev/null | head -5');
        
        if (!empty(trim($output ?? ''))) {
            echo "\n🏷️  Tags recentes:\n";
            $tags = explode("\n", trim($output));
            foreach ($tags as $tag) {
                if (!empty($tag)) {
                    echo "   {$tag}\n";
                }
            }
        }
    }
    
    /**
     * Cria entrada no changelog
     */
    public function addChangelogEntry(string $version): void
    {
        if (!file_exists($this->changelogFile)) {
            echo "⚠️  Arquivo CHANGELOG.md não encontrado\n";
            return;
        }
        
        $date = date('Y-m-d');
        $entry = "\n## [{$version}] - {$date}\n\n### Adicionado\n- \n\n### Alterado\n- \n\n### Corrigido\n- \n\n";
        
        $changelog = file_get_contents($this->changelogFile);
        
        // Inserir após a seção "Não Lançado"
        $pattern = '/(## \[Não Lançado\].*?)(---)/s';
        $replacement = "$1{$entry}---";
        
        $newChangelog = preg_replace($pattern, $replacement, $changelog);
        
        if ($newChangelog !== $changelog) {
            file_put_contents($this->changelogFile, $newChangelog);
            echo "📝 Entrada adicionada ao CHANGELOG.md\n";
        }
    }
}

// Processar argumentos da linha de comando
function main(): void
{
    global $argv;
    
    $manager = new VersionManager();
    
    if (count($argv) < 2) {
        echo "Uso: php version.php <comando> [argumentos]\n\n";
        echo "Comandos:\n";
        echo "  current              Mostra versão atual\n";
        echo "  bump <type>          Incrementa versão (major|minor|patch)\n";
        echo "  set <version>        Define versão específica\n";
        echo "  changelog <version>  Adiciona entrada no changelog\n";
        echo "\nExemplos:\n";
        echo "  php version.php current\n";
        echo "  php version.php bump patch\n";
        echo "  php version.php set 1.2.3\n";
        exit(1);
    }
    
    $command = $argv[1];
    
    try {
        switch ($command) {
            case 'current':
                $manager->showInfo();
                break;
                
            case 'bump':
                if (!isset($argv[2])) {
                    throw new InvalidArgumentException("Tipo de incremento requerido: major, minor, patch");
                }
                $manager->bumpVersion($argv[2]);
                break;
                
            case 'set':
                if (!isset($argv[2])) {
                    throw new InvalidArgumentException("Versão requerida");
                }
                $manager->setVersion($argv[2]);
                break;
                
            case 'changelog':
                if (!isset($argv[2])) {
                    throw new InvalidArgumentException("Versão requerida");
                }
                $manager->addChangelogEntry($argv[2]);
                break;
                
            default:
                throw new InvalidArgumentException("Comando inválido: {$command}");
        }
        
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Executar apenas se chamado diretamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    main();
}