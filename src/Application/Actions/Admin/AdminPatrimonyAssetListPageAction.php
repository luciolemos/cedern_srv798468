<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPatrimonyAssetListPageAction extends AbstractAdminPatrimonyAction
{
    public const FLASH_KEY = 'admin_patrimony_asset_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private const ALL_PAGE_SIZE = 'all';

    private const SORT_FIELDS = [
        'asset_code',
        'name',
        'category_name',
        'location_name',
        'current_status',
        'conservation_state',
        'acquisition_value',
        'acquisition_date',
        'warranty_expires_at',
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $allAssets = [];
        $categories = [];
        $locations = [];
        $recentMovements = [];

        try {
            $allAssets = $this->patrimonyRepository->findAllAssetsForAdmin();
            $categories = $this->patrimonyRepository->findAllCategoriesForAdmin();
            $locations = $this->patrimonyRepository->findAllLocationsForAdmin();
            $recentMovements = $this->patrimonyRepository->findRecentMovements(8);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar módulo patrimonial.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $queryParams = $request->getQueryParams();

        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $selectedCategoryId = trim((string) ($queryParams['category_id'] ?? ''));
        $selectedLocationId = trim((string) ($queryParams['location_id'] ?? ''));
        $selectedStatus = trim((string) ($queryParams['status_filter'] ?? ''));
        $selectedConservation = trim((string) ($queryParams['conservation_filter'] ?? ''));
        $selectedAcquisitionType = trim((string) ($queryParams['acquisition_type'] ?? ''));
        $selectedWarrantyFilter = trim((string) ($queryParams['warranty_filter'] ?? ''));
        $responsibleTerm = trim((string) ($queryParams['responsible'] ?? ''));

        $assets = $allAssets;

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);
            $assets = array_values(array_filter(
                $assets,
                static function (array $asset) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($asset['asset_code'] ?? ''),
                        (string) ($asset['name'] ?? ''),
                        (string) ($asset['description'] ?? ''),
                        (string) ($asset['category_name'] ?? ''),
                        (string) ($asset['subcategory'] ?? ''),
                        (string) ($asset['brand'] ?? ''),
                        (string) ($asset['model'] ?? ''),
                        (string) ($asset['serial_number'] ?? ''),
                        (string) ($asset['supplier_name'] ?? ''),
                        (string) ($asset['invoice_number'] ?? ''),
                        (string) ($asset['current_location_display'] ?? ''),
                        (string) ($asset['current_responsible'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        if ($selectedCategoryId !== '') {
            $assets = array_values(array_filter(
                $assets,
                static fn (array $asset): bool => (string) ($asset['category_id'] ?? '') === $selectedCategoryId
            ));
        }

        if ($selectedLocationId !== '') {
            $assets = array_values(array_filter(
                $assets,
                static fn (array $asset): bool => (string) ($asset['current_location_id'] ?? '') === $selectedLocationId
            ));
        }

        if ($selectedStatus !== '' && array_key_exists($selectedStatus, $this->statusOptions())) {
            $assets = array_values(array_filter(
                $assets,
                static fn (array $asset): bool => (string) ($asset['current_status'] ?? '') === $selectedStatus
            ));
        } else {
            $selectedStatus = '';
        }

        if ($selectedConservation !== '' && array_key_exists($selectedConservation, $this->conservationOptions())) {
            $assets = array_values(array_filter(
                $assets,
                static fn (array $asset): bool => (string) ($asset['conservation_state'] ?? '') === $selectedConservation
            ));
        } else {
            $selectedConservation = '';
        }

        if ($selectedAcquisitionType !== '' && array_key_exists($selectedAcquisitionType, $this->acquisitionTypeOptions())) {
            $assets = array_values(array_filter(
                $assets,
                static fn (array $asset): bool => (string) ($asset['acquisition_type'] ?? '') === $selectedAcquisitionType
            ));
        } else {
            $selectedAcquisitionType = '';
        }

        if ($selectedWarrantyFilter !== '' && in_array($selectedWarrantyFilter, ['expiring', 'expired', 'none'], true)) {
            $assets = array_values(array_filter(
                $assets,
                static function (array $asset) use ($selectedWarrantyFilter): bool {
                    $days = $asset['warranty_days_remaining'] ?? null;

                    if ($selectedWarrantyFilter === 'expiring') {
                        return $days !== null && $days >= 0 && $days <= 45;
                    }

                    if ($selectedWarrantyFilter === 'expired') {
                        return $days !== null && $days < 0;
                    }

                    return $days === null;
                }
            ));
        } else {
            $selectedWarrantyFilter = '';
        }

        if ($responsibleTerm !== '') {
            $normalizedResponsible = strtolower($responsibleTerm);
            $assets = array_values(array_filter(
                $assets,
                static fn (array $asset): bool => stripos(
                    strtolower((string) ($asset['current_responsible'] ?? '')),
                    $normalizedResponsible
                ) !== false
            ));
        }

        $sortBy = (string) ($queryParams['sort'] ?? 'asset_code');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'asset_code';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($assets, static function (array $firstAsset, array $secondAsset) use ($sortBy, $sortMultiplier): int {
            $firstValue = $firstAsset[$sortBy] ?? '';
            $secondValue = $secondAsset[$sortBy] ?? '';

            if (in_array($sortBy, ['acquisition_value'], true)) {
                return (((float) $firstValue <=> (float) $secondValue) * $sortMultiplier);
            }

            if (in_array($sortBy, ['acquisition_date', 'warranty_expires_at'], true)) {
                return strnatcasecmp((string) $firstValue, (string) $secondValue) * $sortMultiplier;
            }

            return strnatcasecmp((string) $firstValue, (string) $secondValue) * $sortMultiplier;
        });

        $pagination = $this->buildPagination(
            $assets,
            $queryParams,
            $this->withBasePath($request, $this->assetListPath()),
            $sortBy,
            $sortDirection,
            [
                'q' => $searchTerm,
                'category_id' => $selectedCategoryId,
                'location_id' => $selectedLocationId,
                'status_filter' => $selectedStatus,
                'conservation_filter' => $selectedConservation,
                'acquisition_type' => $selectedAcquisitionType,
                'warranty_filter' => $selectedWarrantyFilter,
                'responsible' => $responsibleTerm,
            ]
        );

        $summary = $this->buildSummary($allAssets);

        return $this->renderPage($response, 'pages/admin-patrimony-assets.twig', [
            'patrimony_assets' => $pagination['items'],
            'patrimony_admin_status' => $status,
            'patrimony_assets_sort_links' => $pagination['sort_links'],
            'patrimony_assets_pagination' => $pagination['meta'],
            'patrimony_assets_search' => $searchTerm,
            'patrimony_assets_filters' => [
                'category_id' => $selectedCategoryId,
                'location_id' => $selectedLocationId,
                'status_filter' => $selectedStatus,
                'conservation_filter' => $selectedConservation,
                'acquisition_type' => $selectedAcquisitionType,
                'warranty_filter' => $selectedWarrantyFilter,
                'responsible' => $responsibleTerm,
            ],
            'patrimony_assets_filter_options' => [
                'categories' => $categories,
                'locations' => $locations,
                'status' => $this->statusOptions(),
                'conservation' => $this->conservationOptions(),
                'acquisition_types' => $this->acquisitionTypeOptions(),
            ],
            'patrimony_dashboard_summary' => $summary,
            'patrimony_recent_movements' => $recentMovements,
            'page_title' => 'Controle patrimonial | Dashboard',
            'page_url' => $this->absoluteUrl($request, $this->assetListPath()),
            'page_description' => 'Painel para controle patrimonial do CEDE.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $assets
     * @return array<string, mixed>
     */
    private function buildSummary(array $assets): array
    {
        $metrics = [
            'total_assets' => count($assets),
            'active_assets' => 0,
            'maintenance_assets' => 0,
            'disposed_assets' => 0,
            'total_active_value' => 0.0,
        ];
        $byCategory = [];
        $byLocation = [];
        $warrantyAlerts = [];

        foreach ($assets as $asset) {
            $status = (string) ($asset['current_status'] ?? '');
            $value = (float) ($asset['acquisition_value'] ?? 0);
            $categoryLabel = trim((string) ($asset['category_name'] ?? 'Sem categoria'));
            $locationLabel = trim((string) ($asset['current_location_display'] ?? 'Sem localização'));

            if ($status === 'baixado') {
                $metrics['disposed_assets']++;
            } else {
                $metrics['active_assets']++;
                $metrics['total_active_value'] += $value;
            }

            if ($status === 'em_manutencao') {
                $metrics['maintenance_assets']++;
            }

            $byCategory[$categoryLabel] = ($byCategory[$categoryLabel] ?? 0) + 1;
            $byLocation[$locationLabel] = ($byLocation[$locationLabel] ?? 0) + 1;

            if (($asset['warranty_expires_soon'] ?? false) === true || ($asset['warranty_expired'] ?? false) === true) {
                $warrantyAlerts[] = $asset;
            }
        }

        arsort($byCategory);
        arsort($byLocation);
        usort($warrantyAlerts, static function (array $firstAsset, array $secondAsset): int {
            return strnatcasecmp(
                (string) ($firstAsset['warranty_expires_at'] ?? ''),
                (string) ($secondAsset['warranty_expires_at'] ?? '')
            );
        });

        return [
            'metrics' => [
                'total_assets' => $metrics['total_assets'],
                'active_assets' => $metrics['active_assets'],
                'maintenance_assets' => $metrics['maintenance_assets'],
                'disposed_assets' => $metrics['disposed_assets'],
                'total_active_value' => $metrics['total_active_value'],
                'total_active_value_label' => 'R$ ' . number_format($metrics['total_active_value'], 2, ',', '.'),
            ],
            'category_rows' => array_map(
                static fn (string $label, int $count): array => ['label' => $label !== '' ? $label : 'Sem categoria', 'count' => $count],
                array_keys($byCategory),
                array_values($byCategory)
            ),
            'location_rows' => array_map(
                static fn (string $label, int $count): array => ['label' => $label !== '' ? $label : 'Sem localização', 'count' => $count],
                array_keys($byLocation),
                array_values($byLocation)
            ),
            'warranty_alerts' => array_slice($warrantyAlerts, 0, 8),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $queryParams
     * @param array<string, string> $extraFilters
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   sort_links: array<string, array<string, mixed>>,
     *   meta: array<string, mixed>
     * }
     */
    private function buildPagination(
        array $items,
        array $queryParams,
        string $basePath,
        string $sortBy,
        string $sortDirection,
        array $extraFilters
    ): array {
        $totalItems = count($items);
        $requestedPageSize = trim((string) ($queryParams['per_page'] ?? (string) self::DEFAULT_PAGE_SIZE));
        $showAllItems = $requestedPageSize === self::ALL_PAGE_SIZE;
        $pageSize = self::DEFAULT_PAGE_SIZE;

        if (!$showAllItems) {
            $requestedPageSizeNumber = (int) $requestedPageSize;
            $pageSize = in_array($requestedPageSizeNumber, self::PAGE_SIZE_OPTIONS, true)
                ? $requestedPageSizeNumber
                : self::DEFAULT_PAGE_SIZE;
        } else {
            $pageSize = max($totalItems, 1);
        }

        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $pagedItems = array_slice($items, $offset, $pageSize);
        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($pagedItems), $totalItems) : 0;
        $pageSizeQueryValue = $showAllItems ? self::ALL_PAGE_SIZE : (string) $pageSize;

        $baseQuery = array_merge([
            'per_page' => $pageSizeQueryValue,
            'sort' => $sortBy,
            'dir' => $sortDirection,
        ], array_filter($extraFilters, static fn (string $value): bool => $value !== ''));

        $sortLinks = [];
        foreach (self::SORT_FIELDS as $field) {
            $nextDirection = $sortBy === $field && $sortDirection === 'asc' ? 'desc' : 'asc';
            $indicator = '↕';

            if ($sortBy === $field) {
                $indicator = $sortDirection === 'asc' ? '↑' : '↓';
            }

            $sortLinks[$field] = [
                'url' => $basePath . '?' . http_build_query(array_merge($extraFilters, [
                    'page' => 1,
                    'per_page' => $pageSizeQueryValue,
                    'sort' => $field,
                    'dir' => $nextDirection,
                ])),
                'indicator' => $indicator,
                'active' => $sortBy === $field,
            ];
        }

        $paginationLinks = [];
        for ($page = 1; $page <= $totalPages; $page++) {
            $paginationLinks[] = [
                'number' => $page,
                'active' => $page === $currentPage,
                'url' => $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $page])),
            ];
        }

        $pageSizeOptions = array_map(static fn (int $option): array => [
            'value' => (string) $option,
            'label' => (string) $option,
            'selected' => !$showAllItems && $option === $pageSize,
        ], self::PAGE_SIZE_OPTIONS);
        $pageSizeOptions[] = [
            'value' => self::ALL_PAGE_SIZE,
            'label' => 'Todos',
            'selected' => $showAllItems,
        ];

        return [
            'items' => $pagedItems,
            'sort_links' => $sortLinks,
            'meta' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'page_size' => $pageSizeQueryValue,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'links' => $paginationLinks,
                'previous_url' => $currentPage > 1 ? $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $currentPage - 1])) : null,
                'next_url' => $currentPage < $totalPages ? $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $currentPage + 1])) : null,
                'page_size_options' => $pageSizeOptions,
            ],
        ];
    }
}
