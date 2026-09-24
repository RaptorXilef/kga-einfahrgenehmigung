<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\View;

use App\Contracts\System\AssetHelperInterface;

/**
 * Wandelt die verschachtelte Berechtigungs-Struktur in reines HTML um.
 * Befreit das PHTML-Template von der Inline-Rekursions-Logik.
 */
final class PermissionTreePresenter
{
    public static function renderTree(array $nodes, array $rolePerms, int $depth, AssetHelperInterface $asset): string
    {
        $html = '';
        foreach ($nodes as $id => $node) {
            $key = $node['key'] ?? null;
            $isAllowed = $key && \in_array($key, $rolePerms, true);
            $hasChildren = !empty($node['children']);
            $permissionLabel = \htmlspecialchars((string) ($node['label'] ?? $id));
            $dataKey = \htmlspecialchars((string) ($key ?? $id));

            $rootClass = $depth === 0 ? 'c-tree-item--root' : '';
            $catClass = !$key ? 'c-tree-item--category' : '';
            $labelRootClass = $depth === 0 ? 'c-tree-item__label--root' : '';

            $iconHtml = '';
            if (isset($node['icon'])) {
                $iconUrl = $asset->url('assets/img/icons/' . $node['icon']);
                $iconHtml = '<img src="' . $iconUrl . '" class="c-icon c-icon--inline u-margin-inline-end-xs" loading="lazy" alt="">';
            }

            $subLabelHtml = $key
                ? '<span class="c-tree-item__sublabel">' . \htmlspecialchars((string) $key) . '</span>'
                : '<span class="c-tree-item__sublabel c-tree-item__sublabel--category">Kategorie</span>';

            $controlsHtml = '<div class="p-controls">';
            if ($key) {
                $checkedAttr = $isAllowed ? 'checked' : '';
                $keyEscaped = \htmlspecialchars((string) $key);
                $controlsHtml .= <<<HTML
                        <label class="c-switch">
                            <input type="checkbox" name="perms[]" value="{$keyEscaped}" data-perm-check="true" class="c-switch__input" aria-label="Berechtigung: {$permissionLabel}" {$checkedAttr}>
                            <span class="c-switch__label" aria-hidden="true" data-on="ALLOW" data-off="NONE"></span>
                        </label>
                    HTML;
            }
            $controlsHtml .= '</div>';

            $childrenHtml = $hasChildren ? self::renderTree($node['children'], $rolePerms, $depth + 1, $asset) : '';

            $html .= <<<HTML
                <div class="c-tree-node" style="--depth: {$depth};" data-key="{$dataKey}">
                    <div class="c-tree-item {$rootClass} {$catClass}">
                        <div class="c-tree-item__label-wrapper u-flex u-flex-column">
                            <span class="c-tree-item__label {$labelRootClass}">
                                {$iconHtml}
                                {$permissionLabel}
                            </span>
                            {$subLabelHtml}
                        </div>
                        {$controlsHtml}
                    </div>
                    {$childrenHtml}
                </div>
                HTML;
        }

        return $html;
    }
}
