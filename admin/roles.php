<?php
// SPARK Platform - Admin Role Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle role operations
$action = $_GET['action'] ?? 'list';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $role_name = sanitize($_POST['role_name']);
        $description = sanitize($_POST['description']);
        $permissions = $_POST['permissions'] ?? [];
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $hierarchy_level = (int)($_POST['hierarchy_level'] ?? 1);

        // Validation
        if (empty($role_name)) {
            throw new Exception("Role name is required");
        }

        // Prevent multiple default roles
        if ($is_default) {
            $existing_default = fetchColumn("SELECT COUNT(*) FROM roles WHERE is_default = 1", []);
            if ($existing_default > 0) {
                throw new Exception("Only one role can be set as default");
            }
        }

        // Convert permissions array to JSON
        $permissions_json = json_encode($permissions);

        // Insert role
        $sql = "INSERT INTO roles (
            role_name, description, permissions, is_default, is_active,
            hierarchy_level, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $role_name, $description, $permissions_json, $is_default, $is_active,
            $hierarchy_level, $_SESSION['user_id']
        ];

        $role_id = executeInsert($sql, $params);

        // Update users with this role if it's the new default
        if ($is_default) {
            executeUpdate("UPDATE roles SET is_default = 0 WHERE id != ?", [$role_id]);
        }

        // Log activity
        logActivity('role_created', "Role '{$role_name}' created", $_SESSION['user_id'], $role_id);

        $_SESSION['success'][] = "Role created successfully!";
        header('Location: roles.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: roles.php?action=create');
        exit();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $role_id = (int)$_POST['role_id'];
        $role_name = sanitize($_POST['role_name']);
        $description = sanitize($_POST['description']);
        $permissions = $_POST['permissions'] ?? [];
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $hierarchy_level = (int)($_POST['hierarchy_level'] ?? 1);

        // Validation
        if (empty($role_name)) {
            throw new Exception("Role name is required");
        }

        // Prevent multiple default roles
        if ($is_default) {
            $existing_default = fetchColumn("SELECT COUNT(*) FROM roles WHERE is_default = 1 AND id != ?", [$role_id]);
            if ($existing_default > 0) {
                throw new Exception("Only one role can be set as default");
            }
        }

        // Convert permissions array to JSON
        $permissions_json = json_encode($permissions);

        // Update role
        $sql = "UPDATE roles SET
            role_name = ?, description = ?, permissions = ?, is_default = ?, is_active = ?,
            hierarchy_level = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $role_name, $description, $permissions_json, $is_default, $is_active,
            $hierarchy_level, $role_id
        ];

        executeUpdate($sql, $params);

        // Update users with this role if it's the new default
        if ($is_default) {
            executeUpdate("UPDATE roles SET is_default = 0 WHERE id != ?", [$role_id]);
        }

        // Log activity
        logActivity('role_updated', "Role '{$role_name}' updated", $_SESSION['user_id'], $role_id);

        $_SESSION['success'][] = "Role updated successfully!";
        header('Location: roles.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: roles.php?action=edit&id=' . $_POST['role_id']);
        exit();
    }
}

if ($action === 'delete') {
    try {
        $role_id = (int)$_GET['id'];

        // Get role info for logging
        $role = fetchRow("SELECT role_name FROM roles WHERE id = ?", [$role_id]);
        if (!$role) {
            throw new Exception("Role not found");
        }

        // Check if role is assigned to any user
        $users_count = fetchColumn("SELECT COUNT(*) FROM users WHERE role_id = ?", [$role_id]);
        if ($users_count > 0) {
            throw new Exception("Cannot delete role assigned to users. Reassign users first.");
        }

        // Delete role
        executeUpdate("DELETE FROM roles WHERE id = ?", [$role_id]);

        // Log activity
        logActivity('role_deleted', "Role '{$role['role_name']}' deleted", $_SESSION['user_id'], $role_id);

        $_SESSION['success'][] = "Role deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: roles.php');
    exit();
}

if ($action === 'toggle_status') {
    try {
        $role_id = (int)$_GET['id'];
        $is_active = (int)$_GET['is_active'];

        executeUpdate("UPDATE roles SET is_active = ? WHERE id = ?", [$is_active, $role_id]);

        $action_text = $is_active ? 'activated' : 'deactivated';
        $_SESSION['success'][] = "Role {$action_text} successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: roles.php');
    exit();
}

if ($action === 'set_default' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $role_id = (int)$_POST['role_id'];

        // Update all roles to not default
        executeUpdate("UPDATE roles SET is_default = 0", []);

        // Set this role as default
        executeUpdate("UPDATE roles SET is_default = 1 WHERE id = ?", [$role_id]);

        // Log activity
        logActivity('role_default_set', "Default role updated", $_SESSION['user_id'], $role_id);

        $_SESSION['success'][] = "Default role set successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: roles.php');
    exit();
}

$page_title = 'Role Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Roles', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Role Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-user-shield me-2"></i> Role Management
                        </h2>
                        <p class="text-muted">Manage user roles and permissions</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-info" onclick="showPermissionMatrix()">
                            <i class="fas fa-th me-2"></i> Permission Matrix
                        </button>
                        <button class="btn btn-outline-success" onclick="exportRoles()">
                            <i class="fas fa-download me-2"></i> Export Roles
                        </button>
                        <a href="roles.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Create Role
                        </a>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Role Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'create' ? 'Create Role' : 'Edit Role'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $role_data = $_SESSION['form_data'] ?? [];
                            if ($action === 'edit') {
                                $role_id = (int)$_GET['id'];
                                $role_data = fetchRow("SELECT * FROM roles WHERE id = ?", [$role_id]);
                                if (!$role_data) {
                                    $_SESSION['errors'][] = "Role not found";
                                    header('Location: roles.php');
                                    exit();
                                }
                                // Decode permissions
                                $role_data['permissions'] = json_decode($role_data['permissions'], true) ?: [];
                            }
                            ?>

                            <form method="POST" class="needs-validation" novalidate>
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="role_id" value="<?php echo $role_data['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <!-- Basic Information -->
                                    <div class="col-md-8">
                                        <h6 class="text-primary mb-3">Basic Information</h6>

                                        <div class="mb-3">
                                            <label for="role_name" class="form-label">Role Name *</label>
                                            <input type="text" class="form-control" id="role_name" name="role_name"
                                                   value="<?php echo htmlspecialchars($role_data['role_name'] ?? ''); ?>"
                                                   required maxlength="255" placeholder="e.g., Student, Admin, Faculty">
                                            <div class="invalid-feedback">Please provide a role name</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description"
                                                      rows="4" placeholder="Describe the role and its responsibilities..."><?php echo htmlspecialchars($role_data['description'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="hierarchy_level" class="form-label">Hierarchy Level</label>
                                                    <input type="number" class="form-control" id="hierarchy_level" name="hierarchy_level"
                                                           value="<?php echo htmlspecialchars($role_data['hierarchy_level'] ?? 1); ?>"
                                                           min="1" max="10">
                                                    <div class="form-text">Lower numbers have higher permissions</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                                               <?php echo (isset($role_data['is_active']) && $role_data['is_active']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="is_active">
                                                            Active Role
                                                        </label>
                                                        <div class="form-text">Role can be assigned to users</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="is_default" name="is_default"
                                                               <?php echo (isset($role_data['is_default']) && $role_data['is_default']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="is_default">
                                                            Default Role
                                                        </label>
                                                        <div class="form-text">New users get this role by default</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Permissions -->
                                    <div class="col-md-4">
                                        <h6 class="text-primary mb-3">Permissions</h6>

                                        <div class="permission-groups">
                                            <?php
                                            $permission_categories = [
                                                'Dashboard' => [
                                                    'view_dashboard' => 'View Dashboard',
                                                    'view_analytics' => 'View Analytics'
                                                ],
                                                'User Management' => [
                                                    'view_users' => 'View Users',
                                                    'create_users' => 'Create Users',
                                                    'edit_users' => 'Edit Users',
                                                    'delete_users' => 'Delete Users',
                                                    'manage_roles' => 'Manage Roles'
                                                ],
                                                'Content Management' => [
                                                    'manage_events' => 'Manage Events',
                                                    'manage_payments' => 'Manage Payments',
                                                    'manage_gallery' => 'Manage Gallery',
                                                    'manage_news' => 'Manage News',
                                                    'manage_pages' => 'Manage Pages'
                                                ],
                                                'Reports & Analytics' => [
                                                    'view_reports' => 'View Reports',
                                                    'export_data' => 'Export Data',
                                                    'view_logs' => 'View Activity Logs'
                                                ],
                                                'System Settings' => [
                                                    'view_settings' => 'View Settings',
                                                    'edit_settings' => 'Edit Settings',
                                                    'manage_backups' => 'Manage Backups'
                                                ]
                                            ];

                                            foreach ($permission_categories as $category => $permissions):
                                            ?>
                                            <div class="permission-group">
                                                <h6 class="permission-category"><?php echo $category; ?></h6>
                                                <div class="permission-list">
                                                    <?php foreach ($permissions as $key => $label): ?>
                                                        <div class="permission-item">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox"
                                                                       name="permissions[]"
                                                                       value="<?php echo $key; ?>"
                                                                       <?php echo (isset($role_data['permissions']) && in_array($key, $role_data['permissions'])) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="<?php echo $key; ?>">
                                                                    <?php echo $label; ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="alert alert-info mt-3">
                                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Permission Guidelines</h6>
                                            <ul class="mb-0">
                                                <li><strong>Dashboard:</strong> Access to main dashboard and analytics</li>
                                                <li><strong>User Management:</strong> Full user account management</li>
                                                <li><strong>Content:</strong> Add, edit, and delete platform content</li>
                                                <li><strong>Reports:</strong> Generate reports and view system logs</li>
                                                <li><strong>Settings:</strong> Configure system-wide settings</li>
                                            </ul>
                                        </div>

                                        <div class="mb-3">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllPermissions()">
                                                <i class="fas fa-check-square me-2"></i> Select All
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllPermissions()">
                                                <i class="fas fa-square me-2"></i> Clear All
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="roles.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'create' ? 'Create Role' : 'Update Role'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Roles List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> All Roles
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchRoles" placeholder="Search roles...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Filter by Status -->
                                        <select class="form-select" id="filterStatus" style="width: 150px;">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>

                                        <!-- Filter by Default -->
                                        <select class="form-select" id="filterDefault" style="width: 150px;">
                                            <option value="">All</option>
                                            <option value="default">Default Role</option>
                                            <option value="not_default">Not Default</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-primary">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM roles", []); ?></h3>
                                            <p>Total Roles</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM roles WHERE is_active = 1", []); ?></h3>
                                            <p>Active</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM roles WHERE is_default = 1", []); ?></h3>
                                            <p>Default</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM users WHERE role_id IN (SELECT id FROM roles WHERE is_active = 1)", []); ?></h3>
                                            <p>Users</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Roles Table -->
                            <div class="table-responsive">
                                <table class="table table-hover" id="rolesTable">
                                    <thead>
                                        <tr>
                                            <th>Role Name</th>
                                            <th>Description</th>
                                            <th>Hierarchy</th>
                                            <th>Users</th>
                                            <th>Status</th>
                                            <th>Default</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "
                                            SELECT r.*, COUNT(u.id) as user_count
                                            FROM roles r
                                            LEFT JOIN users u ON r.id = u.role_id
                                            GROUP BY r.id
                                            ORDER BY r.hierarchy_level ASC, r.role_name ASC
                                        ";
                                        $roles = fetchAll($sql);

                                        foreach ($roles as $role):
                                        ?>
                                        <tr data-role-id="<?php echo $role['id']; ?>">
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($role['role_name']); ?></strong>
                                                    <?php if ($role['is_default']): ?>
                                                        <br><span class="badge bg-warning">Default</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-muted" style="max-width: 200px;">
                                                    <?php echo htmlspecialchars(substr($role['description'] ?? '', 0, 100)) . (strlen($role['description']) > 100 ? '...' : ''); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">Level <?php echo $role['hierarchy_level']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $role['user_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $role['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <?php if ($role['is_default']): ?>
                                                        <i class="fas fa-star text-warning"></i>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="setDefaultRole(<?php echo $role['id']; ?>)" title="Set as Default">
                                                            <i class="fas fa-star"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo formatDate($role['created_at']); ?>
                                                    <br><small class="text-muted">By Admin</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewRole(<?php echo $role['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editRole(<?php echo $role['id']; ?>)" title="Edit Role">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-<?php echo $role['is_active'] ? 'danger' : 'success'; ?>" onclick="toggleRoleStatus(<?php echo $role['id']; ?>, <?php echo $role['is_active'] ? 0 : 1; ?>)" title="<?php echo $role['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $role['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteRole(<?php echo $role['id']; ?>)" title="Delete Role">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Role Details Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Role Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="roleModalContent">
                <!-- Role details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Permission Matrix Modal -->
<div class="modal fade" id="permissionMatrix" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Permission Matrix</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Permission</th>
                                <?php
                                $all_roles = fetchAll("SELECT id, role_name FROM roles ORDER BY role_name");
                                foreach ($all_roles as $role):
                                ?>
                                <th class="text-center"><?php echo htmlspecialchars($role['role_name']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $all_permissions = [
                                'view_dashboard' => 'Dashboard',
                                'view_analytics' => 'Analytics',
                                'view_users' => 'Users - View',
                                'create_users' => 'Users - Create',
                                'edit_users' => 'Users - Edit',
                                'delete_users' => 'Users - Delete',
                                'manage_roles' => 'Roles',
                                'manage_events' => 'Events',
                                'manage_payments' => 'Payments',
                                'manage_gallery' => 'Gallery',
                                'manage_news' => 'News',
                                'manage_pages' => 'Pages',
                                'view_reports' => 'Reports - View',
                                'export_data' => 'Reports - Export',
                                'view_logs' => 'Logs',
                                'view_settings' => 'Settings - View',
                                'edit_settings' => 'Settings - Edit',
                                'manage_backups' => 'Backups'
                            ];

                            foreach ($all_permissions as $perm_key => $perm_name):
                            ?>
                            <tr>
                                <td class="permission-name"><?php echo $perm_name; ?></td>
                                <?php
                                foreach ($all_roles as $role):
                                    $role_permissions = json_decode(fetchColumn("SELECT permissions FROM roles WHERE id = ?", [$role['id']]), true) ?: [];
                                    $has_permission = in_array($perm_key, $role_permissions);
                                ?>
                                    <td class="text-center">
                                        <i class="fas fa-<?php echo $has_permission ? 'check' : 'times'; ?> <?php echo $has_permission ? 'text-success' : 'text-danger'; ?>"></i>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Role Management Styles */
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 255, 136, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
}

.stat-content h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
    color: var(--accent-color);
}

.stat-content p {
    margin: 0;
    color: var(--text-muted);
}

.permission-group {
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.permission-category {
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.permission-item {
    margin-bottom: 0.5rem;
}

.permission-item:last-child {
    margin-bottom: 0;
}

.form-check {
    margin-bottom: 0.5rem;
}

.table-responsive {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: var(--secondary-color);
    border-bottom: 2px solid var(--border-color);
    font-weight: 600;
    color: var(--text-primary);
}

.table tbody tr:hover {
    background: var(--glass-bg);
}

/* Badge colors */
.badge.bg-primary { background: var(--primary-color) !important; }
.badge.bg-success { background: var(--success-color) !important; }
.badge.bg-warning { background: var(--warning-color) !important; }
.badge.bg-info { background: var(--info-color) !important; }
.badge.bg-danger { background: var(--error-color) !important; }
.badge.bg-secondary { background: var(--text-muted) !important; }

/* Permission Matrix Styles */
.permission-name {
    font-weight: bold;
    min-width: 150px;
}

.text-center {
    text-align: center;
}

/* Form enhancements */
.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
}

/* Loading state */
.loading {
    pointer-events: none;
    opacity: 0.6;
}

.loading::after {
    content: '';
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Form validation
document.querySelector('.needs-validation')?.addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});

// Permission selection functions
function selectAllPermissions() {
    const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = true);
}

function clearAllPermissions() {
    const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = false);
}

// Search functionality
document.getElementById('searchRoles')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#rolesTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('filterStatus')?.addEventListener('change', filterRoles);
document.getElementById('filterDefault')?.addEventListener('change', filterRoles);

function filterRoles() {
    const statusFilter = document.getElementById('filterStatus').value;
    const defaultFilter = document.getElementById('filterDefault').value;
    const rows = document.querySelectorAll('#rolesTable tbody tr');

    rows.forEach(row => {
        const statusCell = row.querySelector('td:nth-child(6) .badge');
        const status = statusCell.textContent.toLowerCase();
        const defaultStar = row.querySelector('td:nth-child(7) .fa-star');

        const statusMatch = !statusFilter || status === statusFilter;
        const defaultMatch = !defaultFilter || (defaultFilter === 'default' && defaultStar.classList.contains('text-warning')) || (defaultFilter === 'not_default' && !defaultStar.classList.contains('text-warning'));

        row.style.display = statusMatch && defaultMatch ? '' : 'none';
    });
}

// CRUD operations
function viewRole(roleId) {
    const modal = new bootstrap.Modal(document.getElementById('roleModal'));
    const content = document.getElementById('roleModalContent');

    // Show loading
    content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    modal.show();

    // Fetch role details
    fetch(`../api/roles.php?action=get&id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const role = data.data;
                const permissions = json_decode(role.permissions, true) || [];

                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <table class="table table-sm">
                                <tr><th>Role Name:</th><td>${role.role_name}</td></tr>
                                <tr><th>Description:</th><td>${role.description || 'No description'}</td></tr>
                                <tr><th>Hierarchy Level:</th><td>Level ${role.hierarchy_level}</td></tr>
                                <tr><th>Status:</th><td><span class="badge bg-${role.is_active ? 'success' : 'secondary'}">${role.is_active ? 'Active' : 'Inactive'}</span></td></tr>
                                <tr><th>Default:</th><td>${role.is_default ? 'Yes' : 'No'}</td></tr>
                                <tr><th>Created:</th><td>${formatDate(role.created_at)}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Users Assigned</h6>
                            <div class="user-list">
                                ${role.user_count > 0 ? `
                                    <p><strong>${role.user_count}</strong> users assigned to this role</p>
                                ` : '<p>No users assigned to this role</p>'}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading role details</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading role details</div>';
        });
}

function editRole(roleId) {
    window.location.href = `roles.php?action=edit&id=${roleId}`;
}

function deleteRole(roleId) {
    if (confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
        window.location.href = `roles.php?action=delete&id=${roleId}`;
    }
}

function toggleRoleStatus(roleId, isActive) {
    window.location.href = `roles.php?action=toggle_status&id=${roleId}&is_active=${isActive}`;
}

function setDefaultRole(roleId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'roles.php?action=set_default';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'role_id';
    input.value = roleId;

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function showPermissionMatrix() {
    const modal = new bootstrap.Modal(document.getElementById('permissionMatrix'));
    modal.show();
}

function exportRoles() {
    window.location.href = '../api/export.php?type=roles';
}

// Helper functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

// Clear form data
<?php if (isset($_SESSION['form_data'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php unset($_SESSION['form_data']); ?>
});
<?php endif; ?>
</script>

<?php
include __DIR__ . '/../templates/admin_footer.php';
?>