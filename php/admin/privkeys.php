<?php
$pageTitle = 'Private Keys';
$currentPage = 'privkeys';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();

// Filters
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all | free | assigned | swept

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(address LIKE ? OR privkey LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter === 'free') {
    $where[] = 'assigned = 0';
} elseif ($filter === 'assigned') {
    $where[] = 'assigned = 1';
} elseif ($filter === 'swept') {
    $where[] = 'swept IS NOT NULL';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) FROM addresses $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$stmt = $db->prepare("SELECT * FROM addresses $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total' => (int)$db->query("SELECT COUNT(*) FROM addresses")->fetchColumn(),
    'free' => (int)$db->query("SELECT COUNT(*) FROM addresses WHERE assigned = 0")->fetchColumn(),
    'assigned' => (int)$db->query("SELECT COUNT(*) FROM addresses WHERE assigned = 1")->fetchColumn(),
    'swept' => (int)$db->query("SELECT COUNT(*) FROM addresses WHERE swept IS NOT NULL")->fetchColumn(),
];
?>

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    <div class="bg-white overflow-hidden shadow rounded-lg"><div class="p-5"><div class="flex items-center">
        <div class="p-3 rounded-full bg-indigo-100"><i class="fas fa-key text-indigo-600 text-xl"></i></div>
        <div class="ml-4"><h3 class="text-sm font-medium text-gray-500">Total Keys</h3>
        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total']; ?></p></div>
    </div></div></div>
    <div class="bg-white overflow-hidden shadow rounded-lg"><div class="p-5"><div class="flex items-center">
        <div class="p-3 rounded-full bg-green-100"><i class="fas fa-check-circle text-green-600 text-xl"></i></div>
        <div class="ml-4"><h3 class="text-sm font-medium text-gray-500">Free</h3>
        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['free']; ?></p></div>
    </div></div></div>
    <div class="bg-white overflow-hidden shadow rounded-lg"><div class="p-5"><div class="flex items-center">
        <div class="p-3 rounded-full bg-yellow-100"><i class="fas fa-clock text-yellow-600 text-xl"></i></div>
        <div class="ml-4"><h3 class="text-sm font-medium text-gray-500">Assigned</h3>
        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['assigned']; ?></p></div>
    </div></div></div>
    <div class="bg-white overflow-hidden shadow rounded-lg"><div class="p-5"><div class="flex items-center">
        <div class="p-3 rounded-full bg-gray-100"><i class="fas fa-lock text-gray-600 text-xl"></i></div>
        <div class="ml-4"><h3 class="text-sm font-medium text-gray-500">Swept</h3>
        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['swept']; ?></p></div>
    </div></div></div>
</div>

<div class="bg-white shadow rounded-lg mb-6">
    <form method="GET" class="p-4 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Address or private key..."
                   class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md font-mono">
        </div>
        <div>
            <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Filter</label>
            <select name="filter" id="filter"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="free" <?php echo $filter === 'free' ? 'selected' : ''; ?>>Free</option>
                <option value="assigned" <?php echo $filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                <option value="swept" <?php echo $filter === 'swept' ? 'selected' : ''; ?>>Swept</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-search mr-2"></i> Filter
            </button>
        </div>
    </form>
</div>

<div class="bg-white shadow rounded-lg overflow-x-auto">
    <div class="px-4 py-5 sm:px-6 flex items-center justify-between">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Private Keys</h3>
        <button type="button" id="toggleKeys" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <i class="fas fa-eye mr-2"></i> Show keys
        </button>
    </div>
    <div class="border-t border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Private Key</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Swept</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($keys)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">No keys found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($keys as $key): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars((string)($key['id'] ?? '')); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900"><?php echo htmlspecialchars($key['address'] ?? ''); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 privkey-cell">
                        <span class="privkey-hidden">••••••••••••••••••••••••••••••••</span>
                        <span class="privkey-value hidden" title="Keep this key secret"><?php echo htmlspecialchars($key['privkey'] ?? ''); ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <?php if (!empty($key['assigned'])): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">assigned</span>
                        <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">free</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo !empty($key['swept']) ? htmlspecialchars($key['swept']) : '—'; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($key['created_at'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
        <div class="text-sm text-gray-700">
            Page <?php echo $page; ?> of <?php echo $pages; ?> (<?php echo $total; ?> keys)
        </div>
        <div class="flex-1 flex justify-end">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 mr-2">Previous</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('toggleKeys').addEventListener('click', function () {
    const hidden = document.querySelectorAll('.privkey-hidden');
    const values = document.querySelectorAll('.privkey-value');
    const show = this.innerText.includes('Show');
    hidden.forEach(el => el.classList.toggle('hidden', show));
    values.forEach(el => el.classList.toggle('hidden', !show));
    this.innerHTML = show
        ? '<i class="fas fa-eye-slash mr-2"></i> Hide keys'
        : '<i class="fas fa-eye mr-2"></i> Show keys';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
