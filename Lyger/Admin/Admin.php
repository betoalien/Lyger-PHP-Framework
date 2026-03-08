<?php

declare(strict_types=1);

namespace Lyger\Admin;

/**
 * AdminController - Base controller for admin panels
 */
abstract class AdminController
{
    protected string $model = '';
    protected array $columns = [];
    protected array $searchable = [];
    protected array $fillable = [];
    protected array $validationRules = [];

    /**
     * Display a listing of the resource.
     */
    public function index(): array
    {
        $modelClass = $this->model;
        $query = $modelClass::query();

        // Apply search
        $search = $_GET['search'] ?? '';
        if ($search && !empty($this->searchable)) {
            foreach ($this->searchable as $field) {
                $query->orWhere($field, 'like', "%{$search}%");
            }
        }

        // Apply sorting
        $sortBy = $_GET['sort'] ?? 'id';
        $sortDir = $_GET['dir'] ?? 'DESC';
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 15);

        $results = $query->paginate($perPage, $page);

        return [
            'data' => array_map(fn($item) => $item->toArray(), $results['data']),
            'meta' => [
                'current_page' => $results['current_page'],
                'per_page' => $results['per_page'],
                'total' => $results['total'],
                'last_page' => $results['last_page'],
            ],
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): array
    {
        $modelClass = $this->model;
        $item = $modelClass::find($id);

        if (!$item) {
            return ['error' => 'Not found', 'code' => 404];
        }

        return ['data' => $item->toArray()];
    }

    /**
     * Store a newly created resource.
     */
    public function store(array $data): array
    {
        $modelClass = $this->model;
        $item = $modelClass::create($data);

        return [
            'success' => true,
            'data' => $item->toArray(),
            'message' => 'Created successfully',
        ];
    }

    /**
     * Update the specified resource.
     */
    public function update(int $id, array $data): array
    {
        $modelClass = $this->model;
        $item = $modelClass::find($id);

        if (!$item) {
            return ['error' => 'Not found', 'code' => 404];
        }

        foreach ($data as $key => $value) {
            $item->$key = $value;
        }
        $item->save();

        return [
            'success' => true,
            'data' => $item->toArray(),
            'message' => 'Updated successfully',
        ];
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(int $id): array
    {
        $modelClass = $this->model;
        $item = $modelClass::find($id);

        if (!$item) {
            return ['error' => 'Not found', 'code' => 404];
        }

        $item->delete();

        return [
            'success' => true,
            'message' => 'Deleted successfully',
        ];
    }

    /**
     * Get columns for table display
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get validation rules
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }
}

/**
 * AdminPanel - Admin panel builder
 */
class AdminPanel
{
    private string $title = 'Admin Panel';
    private string $model = '';
    private array $columns = [];
    private array $actions = ['create', 'edit', 'delete'];
    private array $sidebar = [];
    private string $theme = 'default';

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function setActions(array $actions): self
    {
        $this->actions = $actions;
        return $this;
    }

    public function setSidebar(array $sidebar): self
    {
        $this->sidebar = $sidebar;
        return $this;
    }

    public function setTheme(string $theme): self
    {
        $this->theme = $theme;
        return $this;
    }

    public function getConfig(): array
    {
        return [
            'title' => $this->title,
            'model' => $this->model,
            'columns' => $this->columns,
            'actions' => $this->actions,
            'sidebar' => $this->sidebar,
            'theme' => $this->theme,
        ];
    }

    public function render(): string
    {
        return AdminTheme::render($this->getConfig());
    }

    public static function for(string $model): self
    {
        $instance = new self();
        return $instance->setModel($model);
    }
}

/**
 * AdminTheme - Default admin panel theme
 */
class AdminTheme
{
    public static function render(array $config): string
    {
        $columns = json_encode($config['columns']);
        $title = htmlspecialchars($config['title']);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        admin: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-admin-900 text-white flex flex-col">
            <div class="p-6 border-b border-gray-700">
                <h1 class="text-xl font-bold">{$title}</h1>
            </div>
            <nav class="flex-1 p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="#" onclick="loadPage('dashboard')" class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadPage('table')" class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                            <i class="fas fa-table"></i> {$config['title']}
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b p-4 flex justify-between items-center">
                <h2 class="text-lg font-semibold" id="pageTitle">Dashboard</h2>
                <div class="flex items-center gap-4">
                    <button class="p-2 hover:bg-gray-100 rounded">
                        <i class="fas fa-bell"></i>
                    </button>
                    <div class="flex items-center gap-2">
                        <i class="fas fa-user-circle text-xl"></i>
                        <span>Admin</span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="flex-1 overflow-auto p-6" id="content">
                <div id="dashboard-view">
                    <!-- KPIs -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Total Records</p>
                                    <p class="text-2xl font-bold" id="totalRecords">0</p>
                                </div>
                                <div class="text-3xl text-blue-500"><i class="fas fa-database"></i></div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Active</p>
                                    <p class="text-2xl font-bold" id="activeRecords">0</p>
                                </div>
                                <div class="text-3xl text-green-500"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">This Month</p>
                                    <p class="text-2xl font-bold" id="monthRecords">0</p>
                                </div>
                                <div class="text-3xl text-purple-500"><i class="fas fa-calendar"></i></div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Growth</p>
                                    <p class="text-2xl font-bold text-green-500" id="growthPercent">+0%</p>
                                </div>
                                <div class="text-3xl text-orange-500"><i class="fas fa-chart-line"></i></div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold mb-4">Monthly Trend</h3>
                            <div class="h-64 bg-gray-50 rounded flex items-center justify-center">
                                <p class="text-gray-400">Chart placeholder</p>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold mb-4">Distribution</h3>
                            <div class="h-64 bg-gray-50 rounded flex items-center justify-center">
                                <p class="text-gray-400">Chart placeholder</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table View (hidden by default) -->
                <div id="table-view" class="hidden">
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-4 border-b flex justify-between items-center">
                            <div class="flex items-center gap-4">
                                <input type="text" id="searchInput" placeholder="Search..." class="border rounded-lg px-4 py-2">
                                <button onclick="search()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <button onclick="showCreateModal()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                                <i class="fas fa-plus"></i> Add New
                            </button>
                        </div>
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr id="tableHeaders"></tr>
                            </thead>
                            <tbody id="tableBody"></tbody>
                        </table>
                        <div class="p-4 border-t" id="pagination"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const config = {$columns};
        let currentPage = 1;
        let currentData = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initTable();
            loadData();
        });

        function initTable() {
            const headerRow = document.getElementById('tableHeaders');
            headerRow.innerHTML = '';

            config.forEach(col => {
                const th = document.createElement('th');
                th.className = 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
                th.textContent = col.label;
                headerRow.appendChild(th);
            });

            const actionTh = document.createElement('th');
            actionTh.className = 'px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase';
            actionTh.textContent = 'Actions';
            headerRow.appendChild(actionTh);
        }

        async function loadData() {
            // Simulated data - in production, fetch from API
            currentData = [
                {id: 1, name: 'Item 1', status: 'active', created_at: '2026-01-15'},
                {id: 2, name: 'Item 2', status: 'inactive', created_at: '2026-02-01'},
                {id: 3, name: 'Item 3', status: 'active', created_at: '2026-02-15'},
            ];

            renderTable();
            updateKPIs();
        }

        function renderTable() {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';

            currentData.forEach(row => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';

                config.forEach(col => {
                    const td = document.createElement('td');
                    td.className = 'px-6 py-4 whitespace-nowrap';
                    td.textContent = row[col.key] || '';
                    tr.appendChild(td);
                });

                const actionTd = document.createElement('td');
                actionTd.className = 'px-6 py-4 whitespace-nowrap text-right';
                actionTd.innerHTML = `
                    <button onclick="editRow(${row.id})" class="text-blue-500 hover:text-blue-700 mr-3">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteRow(${row.id})" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                tr.appendChild(actionTd);

                tbody.appendChild(tr);
            });
        }

        function updateKPIs() {
            document.getElementById('totalRecords').textContent = currentData.length;
            document.getElementById('activeRecords').textContent = currentData.filter(r => r.status === 'active').length;
            document.getElementById('monthRecords').textContent = currentData.length;
            document.getElementById('growthPercent').textContent = '+' + Math.floor(Math.random() * 20) + '%';
        }

        function loadPage(page) {
            const dashboardView = document.getElementById('dashboard-view');
            const tableView = document.getElementById('table-view');
            const pageTitle = document.getElementById('pageTitle');

            if (page === 'dashboard') {
                dashboardView.classList.remove('hidden');
                tableView.classList.add('hidden');
                pageTitle.textContent = 'Dashboard';
            } else {
                dashboardView.classList.add('hidden');
                tableView.classList.remove('hidden');
                pageTitle.textContent = '{$config['title']}';
            }
        }

        function search() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const filtered = currentData.filter(row =>
                Object.values(row).some(val => String(val).toLowerCase().includes(query))
            );
            currentData = filtered;
            renderTable();
        }

        function showCreateModal() {
            alert('Create modal - customize this');
        }

        function editRow(id) {
            alert('Edit row ' + id);
        }

        function deleteRow(id) {
            if (confirm('Are you sure?')) {
                currentData = currentData.filter(r => r.id !== id);
                renderTable();
                updateKPIs();
            }
        }
    </script>
</body>
</html>
HTML;
    }
}
