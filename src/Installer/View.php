<?php declare(strict_types = 1);

namespace Medusa\App\ApiResolver\Installer;

use function array_merge;
use function dd;
use function extract;
use function ob_get_clean;
use function ob_start;

/**
 * Class View
 * @package Medusa\App\ApiResolver\InternalAdminInterface
 * @author  Pascale Schnell <pascale.schnell@check24.de>
 */
class View {

    private static array $navEntries = [];

    private static array $prevAssigns = [];

    public function __construct(private string $viewFile, private array $assigns = []) {

    }

    public static function navEntries(array $entries, int $group = 0) {
        self::$navEntries[$group] = $entries;
    }

    /**
     * @return array
     */
    public static function getNavEntries(int $group = 0): array {
        return self::$navEntries[$group] ?? [];
    }

    public function assign(string $k, mixed $v): static {
        $this->assigns[$k] = $v;
        return $this;
    }

    public function render(): string {
        $this->assigns = array_merge(self::$prevAssigns, $this->assigns);
        $this->assigns['title'] ??= 'Admin | ' . $this->assigns['headline'];
        $this->assigns['error'] ??= null;
        $this->assigns['headline'] ??= null;
        self::$prevAssigns = $this->assigns;
        ob_start();
        extract($this->assigns);
        require __DIR__ . '/Views/' . $this->viewFile . '.phtml';
        $content = ob_get_clean();
        return $content;
    }

}