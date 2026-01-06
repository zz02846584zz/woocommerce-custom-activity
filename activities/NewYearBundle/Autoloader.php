<?php

namespace CustomActivity\NewYearBundle;

/**
 * PSR-4 自動載入器
 * 負責自動載入 NewYearBundle 命名空間下的所有類別
 */
final class Autoloader
{
    private string $baseDir;
    private string $namespace;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/') . '/';
        $this->namespace = 'CustomActivity\\NewYearBundle\\';
    }

    /**
     * 註冊自動載入器
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * 載入類別
     */
    private function loadClass(string $class): void
    {
        // 檢查是否為此命名空間
        if (strpos($class, $this->namespace) !== 0) {
            return;
        }

        // 移除命名空間前綴
        $relativeClass = substr($class, strlen($this->namespace));

        // 轉換為檔案路徑
        $file = $this->baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        // 載入檔案
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
