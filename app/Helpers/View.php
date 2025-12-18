<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Res;
use Random\RandomException;
use Slim\Psr7\Stream;

/**
 * Minimalistic view renderer for templates.
 */
final class View
{
    /**
     * Renders template with optional layout.
     *
     * @param Res         $res      HTTP response to write into
     * @param string      $template Template path relative to templates/
     * @param array       $params   Variables for template
     * @param string|null $layout   Layout path relative to templates/
     */
    public static function render(Res $res, string $template, array $params = [], ?string $layout = null): Res
    {
        try {
            $basePath = dirname(__DIR__, 2) . '/templates/';

            $csrfName = $_ENV['CSRF_TOKEN_NAME'] ?? '_csrf_token';
            // Получаем CSRF-токен из сессии, если она активна
            $csrfToken = '';
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[$csrfName])) {
                $csrfToken = $_SESSION[$csrfName];
            }

            $currentPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
            $menu = require $basePath . 'menu.php';
            self::markActive($menu, $currentPath);
            $submenu = [];
            foreach ($menu as $menuItem) {
                if (($menuItem['class'] ?? '') === 'active' && isset($menuItem['children'])) {
                    $submenu = $menuItem['children'];
                    break;
                }
            }

            $vars = array_merge([
                'csrfToken' => $csrfToken,
                'currentPath' => $currentPath,
                'menu' => $menu,
                'submenu' => $submenu,
            ], $params);

            $templatePath = $basePath . ltrim($template, '/');
            ob_start();
            // Allow page-specific params to override any local names
            extract($vars, EXTR_OVERWRITE);
            require $templatePath;
            $content = ob_get_clean();

            if ($layout !== null) {
                $layoutPath = $basePath . ltrim($layout, '/');
                ob_start();
                $title = $vars['title'] ?? '';
                require $layoutPath;
                $content = ob_get_clean();
            }

            $res->getBody()->write($content);
            return $res->withHeader('Content-Type', 'text/html');
        } catch (RandomException $e) {
            Logger::critical("Ошибка при рендеринге шаблона: {$e->getMessage()}. Шаблон: {$template}, макет {$layout}");

            // Очищаем буфер вывода на случай, если что-то уже было выведено
            if (ob_get_level() > 0) {
                ob_clean();
            }

            // Формируем страницу с ошибкой
            $errorContent = '<h1>Ошибка сервера</h1><p>Произошла ошибка при обработке запроса. Попробуйте позже.</p>';

            $res->getBody()->write($errorContent);
            return $res
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html');
        }
    }

    /**
     * Recursively marks active menu items based on current path.
     *
     * @param array  $items       Menu items
     * @param string $currentPath Current request path
     *
     * @return void
     */
    private static function markActive(array &$items, string $currentPath): void
    {
        [$path] = self::findActivePath($items, $currentPath);
        self::applyActive($items, $path);
    }

    /**
     * Finds path to the menu item with the longest URL matching current path.
     *
     * @param array  $items       Menu items
     * @param string $currentPath Current request path
     *
     * @return array{0:array<int,int>,1:int} Path indexes and match length
     */
    private static function findActivePath(array $items, string $currentPath): array
    {
        $bestPath = [];
        $bestLength = 0;

        foreach ($items as $index => $item) {
            $matchLength = 0;
            $url = isset($item['url']) ? rtrim($item['url'], '/') : null;
            if ($url !== null) {
                if ($url === '/dashboard') {
                    $matchLength = $currentPath === '/dashboard' ? strlen($url) : 0;
                } elseif ($currentPath === $url || str_starts_with($currentPath, $url . '/')) {
                    $matchLength = strlen($url);
                }
            }

            $childPath = [];
            $childLength = 0;
            if (!empty($item['children']) && is_array($item['children'])) {
                [$childPath, $childLength] = self::findActivePath($item['children'], $currentPath);
            }

            // Prefer deeper match when lengths are equal and > 0,
            // so that when parent URL equals first child URL, child becomes active.
            if ($childLength > $matchLength || ($childLength === $matchLength && $childLength > 0)) {
                $candidatePath = array_merge([$index], $childPath);
                $candidateLength = $childLength;
            } elseif ($matchLength > 0) {
                $candidatePath = [$index];
                $candidateLength = $matchLength;
            } else {
                $candidatePath = [];
                $candidateLength = 0;
            }

            if ($candidateLength > $bestLength) {
                $bestPath = $candidatePath;
                $bestLength = $candidateLength;
            }
        }

        return [$bestPath, $bestLength];
    }

    /**
     * Applies `active` class to menu items along the given path.
     *
     * @param array $items Menu items passed by reference
     * @param array $path  Path indexes to active item
     *
     * @return void
     */
    private static function applyActive(array &$items, array $path): void
    {
        foreach ($items as $index => &$item) {
            if ($path !== [] && $index === $path[0]) {
                $item['class'] = 'active';
                if (!empty($item['children']) && is_array($item['children'])) {
                    self::applyActive($item['children'], array_slice($path, 1));
                }
            } else {
                $item['class'] = '';
                if (!empty($item['children']) && is_array($item['children'])) {
                    self::applyActive($item['children'], []);
                }
            }
        }
        unset($item);
    }
}
