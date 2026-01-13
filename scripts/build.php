#!/usr/bin/env php
<?php
/**
 * Script de build para preparar releases
 * 
 * @author Sistema Administrativo
 * @version 1.0.0
 */

class BuildManager
{
    private string $rootDir;
    private string $buildDir;
    private string $version;
    
    public function __construct()
    {
        $this->rootDir = dirname(__DIR__);
        $this->buildDir = $this->rootDir . '/build';
        $this->version = $this->getVersion();
    }
    
    /**
     * Executa o processo de build completo
     */
    public function build(): void
    {
        echo "🚀 Iniciando build do Sistema Administrativo MVC v{$this->version}\n\n";
        
        $this->cleanBuildDir();
        $this->copyFiles();
        $this->installDependencies();
        $this->optimizeAutoloader();
        $this->createArchive();
        $this->generateChecksums();
        
        echo "\n✅ Build concluído com sucesso!\n";
        echo "📦 Arquivo: sistema-administrativo-mvc-v{$this->version}.zip\n";
        echo "📁 Diretório: {$this->buildDir}\n";
    }
    
    /**
     * Limpa diretório de build
     */
    private function cleanBuildDir(): void
    {
        echo "🧹 Limpando diretório de build...\n";
        
        if (is_dir($this->buildDir)) {
            $this->removeDirectory($this->buildDir);
        }
        
        mkdir($this->buildDir, 0755, true);
    }
    
    /**
     * Copia arquivos necessários
     */
    private function copyFiles(): void
    {
        echo "📂 Copiando arquivos...\n";
        
        $projectDir = $this->buildDir . '/sistema-administrativo-mvc';
        mkdir($projectDir, 0755, true);
        
        // Arquivos e diretórios para incluir
        $includes = [
            'app',
            'core',
            'database',
            'public',
            'storage',
            'composer.json',
            'composer.lock',
            '.env.example',
            '.htaccess',
            'README.md',
            'CHANGELOG.md',
            'VERSION',
            '.release.json'
        ];
        
        foreach ($includes as $item) {
            $source = $this->rootDir . '/' . $item;
            $destination = $projectDir . '/' . $item;
            
            if (is_file($source)) {
                copy($source, $destination);
            } elseif (is_dir($source)) {
                $this->copyDirectory($source, $destination);
            }
        }
        
        // Criar diretórios vazios necessários
        $emptyDirs = [
            'tmp',
            'vendor'
        ];
        
        foreach ($emptyDirs as $dir) {
            $dirPath = $projectDir . '/' . $dir;
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
                touch($dirPath . '/.gitkeep');
            }
        }
    }
    
    /**
     * Instala dependências de produção
     */
    private function installDependencies(): void
    {
        echo "📦 Instalando dependências...\n";
        
        $projectDir = $this->buildDir . '/sistema-administrativo-mvc';
        
        $command = "cd {$projectDir} && composer install --no-dev --optimize-autoloader --no-interaction";
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new RuntimeException("Erro ao instalar dependências: " . implode("\n", $output));
        }
    }
    
    /**
     * Otimiza autoloader
     */
    private function optimizeAutoloader(): void
    {
        echo "⚡ Otimizando autoloader...\n";
        
        $projectDir = $this->buildDir . '/sistema-administrativo-mvc';
        
        $command = "cd {$projectDir} && composer dump-autoload --optimize --no-dev";
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new RuntimeException("Erro ao otimizar autoloader");
        }
    }
    
    /**
     * Cria arquivo compactado
     */
    private function createArchive(): void
    {
        echo "📦 Criando arquivo compactado...\n";
        
        $archiveName = "sistema-administrativo-mvc-v{$this->version}.zip";
        $archivePath = $this->buildDir . '/' . $archiveName;
        
        $zip = new ZipArchive();
        
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new RuntimeException("Não foi possível criar o arquivo ZIP");
        }
        
        $projectDir = $this->buildDir . '/sistema-administrativo-mvc';
        $this->addDirectoryToZip($zip, $projectDir, 'sistema-administrativo-mvc');
        
        $zip->close();
        
        echo "✅ Arquivo criado: {$archiveName}\n";
    }
    
    /**
     * Gera checksums
     */
    private function generateChecksums(): void
    {
        echo "🔐 Gerando checksums...\n";
        
        $archiveName = "sistema-administrativo-mvc-v{$this->version}.zip";
        $archivePath = $this->buildDir . '/' . $archiveName;
        
        $checksums = [
            'md5' => md5_file($archivePath),
            'sha1' => sha1_file($archivePath),
            'sha256' => hash_file('sha256', $archivePath)
        ];
        
        $checksumFile = $this->buildDir . '/' . "sistema-administrativo-mvc-v{$this->version}.checksums.txt";
        
        $content = "Sistema Administrativo MVC v{$this->version}\n";
        $content .= "Arquivo: {$archiveName}\n";
        $content .= "Data: " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "Checksums:\n";
        $content .= "MD5:    {$checksums['md5']}\n";
        $content .= "SHA1:   {$checksums['sha1']}\n";
        $content .= "SHA256: {$checksums['sha256']}\n";
        
        file_put_contents($checksumFile, $content);
        
        echo "✅ Checksums gerados\n";
    }
    
    /**
     * Obtém versão atual
     */
    private function getVersion(): string
    {
        $versionFile = $this->rootDir . '/VERSION';
        
        if (!file_exists($versionFile)) {
            return '1.0.0';
        }
        
        return trim(file_get_contents($versionFile));
    }
    
    /**
     * Remove diretório recursivamente
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Copia diretório recursivamente
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $files = array_diff(scandir($source), ['.', '..']);
        
        foreach ($files as $file) {
            $sourcePath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;
            
            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }
    
    /**
     * Adiciona diretório ao ZIP
     */
    private function addDirectoryToZip(ZipArchive $zip, string $source, string $prefix = ''): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $prefix . '/' . substr($filePath, strlen($source) + 1);
                
                // Normalizar separadores de diretório
                $relativePath = str_replace('\\', '/', $relativePath);
                
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
}

// Executar build
try {
    $builder = new BuildManager();
    $builder->build();
} catch (Exception $e) {
    echo "❌ Erro no build: " . $e->getMessage() . "\n";
    exit(1);
}